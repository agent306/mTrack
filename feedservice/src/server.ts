import { randomUUID } from 'node:crypto';
import http from 'node:http';
import net from 'node:net';

type Config = {
  port: number;
  publicUrl: string;
  ingestUrl: string;
  maxPayloadBytes: number;
  forwardTimeoutMs: number;
  forwardMethod: string;
  healthPath: string;
  logBody: boolean;
  socketDetectionTimeoutMs: number;
  tcpIdleTimeoutMs: number;
  tcpPort: number | null;
};

type UpstreamResponse = {
  statusCode: number;
  statusText: string;
  body: string;
  contentType: string;
};

type ForwardBufferOptions = {
  body: Buffer;
  requestId: string;
  headers?: Record<string, string>;
  search?: string;
};

type HttpStartStatus = 'http' | 'maybe' | 'raw';

const HTTP_METHOD_PREFIXES = [
  'GET ',
  'POST ',
  'PUT ',
  'PATCH ',
  'DELETE ',
  'HEAD ',
  'OPTIONS ',
  'TRACE ',
  'CONNECT ',
];

const config: Config = {
  port: envInt('PORT', 5023),
  publicUrl: process.env.FEEDSERVICE_PUBLIC_URL ?? 'http://mtrackerfeed.nashath.dev:5023',
  ingestUrl: process.env.MTRACK_INGEST_URL ?? 'https://mtrack.nashath.dev/api/ingest',
  maxPayloadBytes: envInt('FEEDSERVICE_MAX_PAYLOAD_BYTES', 1048576),
  forwardTimeoutMs: envInt('FEEDSERVICE_FORWARD_TIMEOUT_MS', 15000),
  forwardMethod: (process.env.FEEDSERVICE_FORWARD_METHOD ?? 'POST').toUpperCase(),
  healthPath: process.env.FEEDSERVICE_HEALTH_PATH ?? '/health',
  logBody: (process.env.FEEDSERVICE_LOG_BODY ?? 'false').toLowerCase() === 'true',
  socketDetectionTimeoutMs: envInt('FEEDSERVICE_SOCKET_DETECTION_TIMEOUT_MS', 1000),
  tcpIdleTimeoutMs: envInt('FEEDSERVICE_TCP_IDLE_TIMEOUT_MS', 1500),
  tcpPort: optionalEnvInt('FEEDSERVICE_TCP_PORT'),
};

function envInt(name: string, fallback: number): number {
  const rawValue = process.env[name];

  if (!rawValue) {
    return fallback;
  }

  const parsed = Number.parseInt(rawValue, 10);

  return Number.isFinite(parsed) ? parsed : fallback;
}

function optionalEnvInt(name: string): number | null {
  const rawValue = process.env[name];

  if (!rawValue) {
    return null;
  }

  const parsed = Number.parseInt(rawValue, 10);

  return Number.isFinite(parsed) ? parsed : null;
}

function jsonResponse(response: http.ServerResponse, statusCode: number, payload: Record<string, unknown>): void {
  response.writeHead(statusCode, {
    'Content-Type': 'application/json',
    'Cache-Control': 'no-store',
  });
  response.end(JSON.stringify(payload));
}

function selectedHeaders(request: http.IncomingMessage, requestId: string): Record<string, string> {
  const headers: Record<string, string> = {
    'X-Feedservice-Request-Id': requestId,
    'X-Feedservice-Method': request.method ?? 'UNKNOWN',
    'X-Feedservice-Path': request.url ?? '/',
    'X-Feedservice-Transport': 'http',
  };

  for (const header of [
    'content-type',
    'user-agent',
    'x-device-id',
    'x-tracker-id',
    'x-forwarded-for',
    'x-real-ip',
  ]) {
    const value = request.headers[header];

    if (typeof value === 'string') {
      headers[header] = value;
    }
  }

  return headers;
}

async function readBody(request: http.IncomingMessage, maxBytes: number): Promise<Buffer> {
  const chunks: Buffer[] = [];
  let total = 0;

  for await (const chunk of request) {
    const buffer = Buffer.isBuffer(chunk) ? chunk : Buffer.from(chunk);

    total += buffer.length;

    if (total > maxBytes) {
      throw new Error('payload_too_large');
    }

    chunks.push(buffer);
  }

  return Buffer.concat(chunks);
}

function targetUrl(incomingUrl: string | undefined): URL {
  const target = new URL(config.ingestUrl);
  const incoming = new URL(incomingUrl ?? '/', 'http://feedservice.local');

  if (incoming.search) {
    target.search = incoming.search;
  }

  return target;
}

function bodyToArrayBuffer(body: Buffer): ArrayBuffer {
  const copy = new Uint8Array(body.length);

  copy.set(body);

  return copy.buffer;
}

async function forwardBuffer({ body, requestId, headers = {}, search = '' }: ForwardBufferOptions): Promise<UpstreamResponse> {
  const target = new URL(config.ingestUrl);

  if (search) {
    target.search = search;
  }

  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), config.forwardTimeoutMs);

  try {
    const response = await fetch(target, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/octet-stream',
        'X-Feedservice-Request-Id': requestId,
        ...headers,
      },
      body: bodyToArrayBuffer(body),
      signal: controller.signal,
    });

    const responseText = await response.text();

    return {
      statusCode: response.status,
      statusText: response.statusText,
      body: responseText,
      contentType: response.headers.get('content-type') ?? 'application/json',
    };
  } finally {
    clearTimeout(timer);
  }
}

async function forwardPayload({
  request,
  body,
  requestId,
}: {
  request: http.IncomingMessage;
  body: Buffer;
  requestId: string;
}): Promise<UpstreamResponse> {
  const target = targetUrl(request.url);
  const method = config.forwardMethod === 'PRESERVE' ? request.method ?? 'POST' : config.forwardMethod;
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), config.forwardTimeoutMs);

  try {
    const response = await fetch(target, {
      method,
      headers: selectedHeaders(request, requestId),
      body: method === 'GET' || method === 'HEAD' ? undefined : bodyToArrayBuffer(body),
      signal: controller.signal,
    });

    const responseText = await response.text();

    return {
      statusCode: response.status,
      statusText: response.statusText,
      body: responseText,
      contentType: response.headers.get('content-type') ?? 'application/json',
    };
  } finally {
    clearTimeout(timer);
  }
}

function logEvent(event: string, payload: Record<string, unknown>): void {
  console.log(JSON.stringify({
    event,
    at: new Date().toISOString(),
    ...payload,
  }));
}

async function handleRequest(request: http.IncomingMessage, response: http.ServerResponse): Promise<void> {
  const requestId = randomUUID();
  const startedAt = Date.now();

  if (request.url?.split('?')[0] === config.healthPath) {
    jsonResponse(response, 200, {
      status: 'ok',
      publicUrl: config.publicUrl,
      ingestUrl: config.ingestUrl,
    });
    return;
  }

  let body: Buffer;

  try {
    body = await readBody(request, config.maxPayloadBytes);
  } catch (error) {
    if (error instanceof Error && error.message === 'payload_too_large') {
      logEvent('feed.rejected', {
        requestId,
        method: request.method,
        path: request.url,
        reason: 'payload_too_large',
        maxPayloadBytes: config.maxPayloadBytes,
      });
      jsonResponse(response, 413, {
        status: 'rejected',
        reason: `Payload exceeds ${config.maxPayloadBytes} byte limit.`,
        requestId,
      });
      return;
    }

    throw error;
  }

  logEvent('feed.received', {
    requestId,
    method: request.method,
    path: request.url,
    bytes: body.length,
    contentType: request.headers['content-type'] ?? null,
    body: config.logBody ? body.toString('utf8') : undefined,
  });

  try {
    const upstream = await forwardPayload({ request, body, requestId });

    response.writeHead(upstream.statusCode, {
      'Content-Type': upstream.contentType,
      'X-Feedservice-Request-Id': requestId,
    });
    response.end(upstream.body);

    logEvent('feed.forwarded', {
      requestId,
      upstreamStatus: upstream.statusCode,
      durationMs: Date.now() - startedAt,
    });
  } catch (error) {
    const reason = error instanceof Error ? error.message : 'unknown_error';

    logEvent('feed.forward_failed', {
      requestId,
      reason,
      durationMs: Date.now() - startedAt,
    });
    jsonResponse(response, 502, {
      status: 'failed',
      reason: 'Unable to forward payload to mTrack ingest.',
      requestId,
    });
  }
}

function classifyHttpStart(buffer: Buffer): HttpStartStatus {
  const probe = buffer.subarray(0, 8).toString('latin1').toUpperCase();

  if (!probe) {
    return 'maybe';
  }

  if (HTTP_METHOD_PREFIXES.some((prefix) => probe.startsWith(prefix))) {
    return 'http';
  }

  if (HTTP_METHOD_PREFIXES.some((prefix) => prefix.startsWith(probe))) {
    return 'maybe';
  }

  return 'raw';
}

function startRawTcpSession(
  socket: net.Socket,
  initialChunks: Buffer[] = [],
  initialTotal = 0,
  ended = false,
): void {
  const requestId = randomUUID();
  const startedAt = Date.now();
  const chunks = [...initialChunks];
  let total = initialTotal;
  let finalized = false;

  const failTooLarge = (): void => {
    logEvent('feed.tcp_rejected', {
      requestId,
      reason: 'payload_too_large',
      maxPayloadBytes: config.maxPayloadBytes,
    });
    socket.write('ERR');
    socket.destroy();
    finalized = true;
  };

  const finalize = async (): Promise<void> => {
    if (finalized) {
      return;
    }

    finalized = true;
    socket.setTimeout(0);

    const body = Buffer.concat(chunks);

    logEvent('feed.tcp_received', {
      requestId,
      remoteAddress: socket.remoteAddress,
      bytes: body.length,
      body: config.logBody ? body.toString('hex') : undefined,
    });

    try {
      const upstream = await forwardBuffer({
        body,
        requestId,
        headers: {
          'X-Feedservice-Transport': 'tcp',
          'X-Forwarded-For': socket.remoteAddress ?? '',
        },
      });

      socket.write(upstream.body || 'OK');
      socket.end();

      logEvent('feed.tcp_forwarded', {
        requestId,
        upstreamStatus: upstream.statusCode,
        durationMs: Date.now() - startedAt,
      });
    } catch (error) {
      const reason = error instanceof Error ? error.message : 'unknown_error';

      socket.write('ERR');
      socket.end();
      logEvent('feed.tcp_forward_failed', {
        requestId,
        reason,
        durationMs: Date.now() - startedAt,
      });
    }
  };

  if (total > config.maxPayloadBytes) {
    failTooLarge();
    return;
  }

  socket.setTimeout(config.tcpIdleTimeoutMs, () => {
    void finalize();
  });
  socket.on('data', (chunk) => {
    const buffer = Buffer.isBuffer(chunk) ? chunk : Buffer.from(chunk);

    total += buffer.length;

    if (total > config.maxPayloadBytes) {
      failTooLarge();
      return;
    }

    chunks.push(buffer);
    socket.setTimeout(config.tcpIdleTimeoutMs, () => {
      void finalize();
    });
  });
  socket.on('end', () => {
    void finalize();
  });
  socket.on('error', (error) => {
    logEvent('feed.tcp_error', {
      requestId,
      reason: error.message,
    });
  });

  if (ended) {
    void finalize();
  }
}

const httpServer = http.createServer((request, response) => {
  handleRequest(request, response).catch((error) => {
    const reason = error instanceof Error ? error.message : 'unknown_error';

    logEvent('feed.error', { reason });
    jsonResponse(response, 500, {
      status: 'failed',
      reason: 'Feedservice internal error.',
    });
  });
});

function routeSocket(socket: net.Socket): void {
  const chunks: Buffer[] = [];
  let total = 0;
  let routed = false;

  const cleanup = (): void => {
    socket.removeListener('data', onData);
    socket.removeListener('end', onEnd);
    socket.removeListener('error', onError);
    socket.setTimeout(0);
  };

  const routeHttp = (): void => {
    routed = true;
    cleanup();
    socket.pause();
    socket.unshift(Buffer.concat(chunks));
    httpServer.emit('connection', socket);
    socket.resume();
  };

  const routeRaw = (ended = false): void => {
    routed = true;
    cleanup();
    startRawTcpSession(socket, chunks, total, ended);
  };

  const onData = (chunk: Buffer): void => {
    chunks.push(chunk);
    total += chunk.length;

    if (total > config.maxPayloadBytes) {
      routeRaw();
      return;
    }

    const status = classifyHttpStart(Buffer.concat(chunks));

    if (status === 'http') {
      routeHttp();
      return;
    }

    if (status === 'raw') {
      routeRaw();
    }
  };

  const onEnd = (): void => {
    if (routed) {
      return;
    }

    if (chunks.length === 0) {
      socket.destroy();
      return;
    }

    routeRaw(true);
  };

  const onError = (error: Error): void => {
    logEvent('feed.socket_error', {
      reason: error.message,
    });
  };

  socket.setTimeout(config.socketDetectionTimeoutMs, () => {
    if (routed) {
      return;
    }

    if (chunks.length === 0) {
      socket.destroy();
      return;
    }

    routeRaw();
  });
  socket.on('data', onData);
  socket.on('end', onEnd);
  socket.on('error', onError);
}

const frontServer = net.createServer({ allowHalfOpen: true }, routeSocket);

function startSecondaryTcpServer(): void {
  if (!config.tcpPort || config.tcpPort === config.port) {
    return;
  }

  const tcpServer = net.createServer({ allowHalfOpen: true }, (socket) => {
    startRawTcpSession(socket);
  });

  tcpServer.listen(config.tcpPort, '0.0.0.0', () => {
    logEvent('feedservice.tcp_started', {
      port: config.tcpPort,
      ingestUrl: config.ingestUrl,
    });
  });
}

frontServer.listen(config.port, '0.0.0.0', () => {
  logEvent('feedservice.started', {
    port: config.port,
    publicUrl: config.publicUrl,
    ingestUrl: config.ingestUrl,
    forwardMethod: config.forwardMethod,
    protocols: ['http', 'raw-tcp-auto-detect'],
  });
});

startSecondaryTcpServer();
