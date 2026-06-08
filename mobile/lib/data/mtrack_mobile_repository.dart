import 'dart:async';
import 'dart:convert';
import 'dart:io';

class MobileAuthSession {
  const MobileAuthSession({
    required this.accessToken,
    required this.refreshToken,
    required this.tokenType,
    required this.issuedAt,
    this.expiresInSeconds,
  });

  final String accessToken;
  final String refreshToken;
  final String tokenType;
  final DateTime issuedAt;
  final int? expiresInSeconds;

  bool get expiresSoon {
    if (expiresInSeconds == null) {
      return false;
    }

    return issuedAt
        .add(Duration(seconds: expiresInSeconds!))
        .isBefore(DateTime.now().add(const Duration(minutes: 5)));
  }
}

class MobileAuthClient {
  const MobileAuthClient({required this.baseUrl});

  final Uri baseUrl;

  Future<MobileAuthSession> refresh({
    required String refreshToken,
    String deviceName = 'mTrack mobile',
  }) async {
    final request = await HttpClient().postUrl(
      baseUrl.resolve('/api/auth/mobile/refresh'),
    );
    request.headers.contentType = ContentType.json;
    request.write(
      jsonEncode({'refresh_token': refreshToken, 'device_name': deviceName}),
    );

    final response = await request.close();
    final body = await response.transform(utf8.decoder).join();

    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw HttpException(
        'Mobile token refresh failed: ${response.statusCode}',
      );
    }

    final payload = jsonDecode(body) as Map<String, dynamic>;

    return MobileAuthSession(
      accessToken: payload['access_token'] as String,
      refreshToken: refreshToken,
      tokenType: payload['token_type'] as String? ?? 'Bearer',
      expiresInSeconds: payload['expires_in'] as int?,
      issuedAt: DateTime.now(),
    );
  }
}

class RealtimeTrackerClient {
  RealtimeTrackerClient({required this.websocketUrl});

  final Uri websocketUrl;
  WebSocket? _socket;

  Stream<TrackerLocation> connect({required String accessToken}) async* {
    _socket = await WebSocket.connect(
      websocketUrl.toString(),
      headers: {'Authorization': 'Bearer $accessToken'},
    );

    await for (final message in _socket!) {
      final decoded = jsonDecode(message as String) as Map<String, dynamic>;
      final payload =
          decoded['data'] is Map<String, dynamic>
              ? decoded['data'] as Map<String, dynamic>
              : decoded;

      yield TrackerLocation.fromJson(payload);
    }
  }

  Future<void> disconnect() async {
    await _socket?.close();
    _socket = null;
  }
}

class MTrackMobileRepository {
  MTrackMobileRepository()
    : authClient = MobileAuthClient(baseUrl: Uri.parse('http://10.0.2.2:8000')),
      realtimeClient = RealtimeTrackerClient(
        websocketUrl: Uri.parse('ws://10.0.2.2:8080/app/local'),
      );

  final MobileAuthClient authClient;
  final RealtimeTrackerClient realtimeClient;

  final MobileAuthSession demoSession = MobileAuthSession(
    accessToken: 'demo-access-token',
    refreshToken: 'demo-refresh-token',
    tokenType: 'Bearer',
    issuedAt: DateTime(2026, 6, 8, 8),
    expiresInSeconds: 3600,
  );

  List<TrackerDevice> trackers() => _trackers;

  List<AlertEvent> alerts() => _alerts;

  List<ReportItem> reports() => _reports;

  List<RoutePoint> routePoints() => _routePoints;

  TripAnalytics analytics() => TripAnalytics.fromRoute(_routePoints);

  Stream<TrackerLocation> simulatedRealtime() async* {
    var tick = 0;

    while (tick < 8) {
      final base = _routePoints[tick % _routePoints.length];

      yield TrackerLocation(
        trackerId: base.trackerId,
        trackerName: base.trackerName,
        latitude: base.latitude + (tick * 0.0006),
        longitude: base.longitude + (tick * 0.0004),
        speed: base.speed + tick,
        occurredAt: DateTime.now(),
      );

      tick += 1;
      await Future<void>.delayed(const Duration(seconds: 2));
    }
  }
}

class TrackerDevice {
  const TrackerDevice({
    required this.id,
    required this.name,
    required this.group,
    required this.status,
    required this.latitude,
    required this.longitude,
    required this.speed,
    required this.lastSeen,
    required this.batteryPercent,
  });

  final int id;
  final String name;
  final String group;
  final String status;
  final double latitude;
  final double longitude;
  final double speed;
  final DateTime lastSeen;
  final int batteryPercent;
}

class TrackerLocation {
  const TrackerLocation({
    required this.trackerId,
    required this.trackerName,
    required this.latitude,
    required this.longitude,
    required this.speed,
    required this.occurredAt,
  });

  factory TrackerLocation.fromJson(Map<String, dynamic> json) {
    return TrackerLocation(
      trackerId: json['tracker_id'] as int? ?? json['id'] as int,
      trackerName:
          json['tracker_name'] as String? ??
          json['display_name'] as String? ??
          'Tracker',
      latitude: (json['latitude'] as num).toDouble(),
      longitude: (json['longitude'] as num).toDouble(),
      speed: (json['speed'] as num?)?.toDouble() ?? 0,
      occurredAt:
          DateTime.tryParse(json['occurred_at'] as String? ?? '') ??
          DateTime.now(),
    );
  }

  final int trackerId;
  final String trackerName;
  final double latitude;
  final double longitude;
  final double speed;
  final DateTime occurredAt;
}

class AlertEvent {
  const AlertEvent({
    required this.type,
    required this.trackerName,
    required this.status,
    required this.occurredAt,
  });

  final String type;
  final String trackerName;
  final String status;
  final DateTime occurredAt;
}

class ReportItem {
  const ReportItem({
    required this.title,
    required this.description,
    required this.count,
    required this.iconName,
  });

  final String title;
  final String description;
  final int count;
  final String iconName;
}

class RoutePoint {
  const RoutePoint({
    required this.trackerId,
    required this.trackerName,
    required this.latitude,
    required this.longitude,
    required this.speed,
    required this.timestamp,
  });

  final int trackerId;
  final String trackerName;
  final double latitude;
  final double longitude;
  final double speed;
  final DateTime timestamp;
}

class TripSegment {
  const TripSegment({
    required this.type,
    required this.startAt,
    required this.endAt,
    required this.points,
  });

  final String type;
  final DateTime startAt;
  final DateTime endAt;
  final int points;
}

class TripAnalytics {
  const TripAnalytics({
    required this.distanceKm,
    required this.movingMinutes,
    required this.idleMinutes,
    required this.maxSpeed,
    required this.averageSpeed,
    required this.stops,
    required this.segments,
  });

  factory TripAnalytics.fromRoute(List<RoutePoint> points) {
    final moving = points.where((point) => point.speed > 1).length * 6;
    final idle = points.where((point) => point.speed <= 1).length * 6;
    final maxSpeed = points
        .map((point) => point.speed)
        .fold<double>(0, (a, b) => a > b ? a : b);
    final average =
        points.isEmpty
            ? 0.0
            : points.map((point) => point.speed).reduce((a, b) => a + b) /
                points.length;

    return TripAnalytics(
      distanceKm: 9.6,
      movingMinutes: moving,
      idleMinutes: idle,
      maxSpeed: maxSpeed,
      averageSpeed: average,
      stops: points.where((point) => point.speed <= 1).toList(),
      segments: _segments(points),
    );
  }

  final double distanceKm;
  final int movingMinutes;
  final int idleMinutes;
  final double maxSpeed;
  final double averageSpeed;
  final List<RoutePoint> stops;
  final List<TripSegment> segments;
}

List<TripSegment> _segments(List<RoutePoint> points) {
  final segments = <TripSegment>[];
  TripSegment? current;

  for (final point in points) {
    final type = point.speed > 1 ? 'moving' : 'idle';

    if (current == null || current.type != type) {
      if (current != null) {
        segments.add(current);
      }

      current = TripSegment(
        type: type,
        startAt: point.timestamp,
        endAt: point.timestamp,
        points: 1,
      );
      continue;
    }

    current = TripSegment(
      type: current.type,
      startAt: current.startAt,
      endAt: point.timestamp,
      points: current.points + 1,
    );
  }

  if (current != null) {
    segments.add(current);
  }

  return segments;
}

final _trackers = <TrackerDevice>[
  TrackerDevice(
    id: 1,
    name: 'Van 12',
    group: 'Delivery',
    status: 'moving',
    latitude: 24.8607,
    longitude: 67.0011,
    speed: 35,
    lastSeen: DateTime(2026, 6, 8, 11, 42),
    batteryPercent: 78,
  ),
  TrackerDevice(
    id: 2,
    name: 'Vessel 04',
    group: 'Harbor',
    status: 'idle',
    latitude: 24.8334,
    longitude: 67.0203,
    speed: 0,
    lastSeen: DateTime(2026, 6, 8, 11, 20),
    batteryPercent: 52,
  ),
  TrackerDevice(
    id: 3,
    name: 'Buggy 7',
    group: 'Field',
    status: 'offline',
    latitude: 24.875,
    longitude: 67.042,
    speed: 0,
    lastSeen: DateTime(2026, 6, 8, 9, 15),
    batteryPercent: 18,
  ),
];

final _alerts = <AlertEvent>[
  AlertEvent(
    type: 'overspeed',
    trackerName: 'Van 12',
    status: 'open',
    occurredAt: DateTime(2026, 6, 8, 11, 42),
  ),
  AlertEvent(
    type: 'geofence entrance',
    trackerName: 'Vessel 04',
    status: 'resolved',
    occurredAt: DateTime(2026, 6, 8, 10, 30),
  ),
  AlertEvent(
    type: 'offline',
    trackerName: 'Buggy 7',
    status: 'open',
    occurredAt: DateTime(2026, 6, 8, 9, 15),
  ),
];

final _reports = <ReportItem>[
  ReportItem(
    title: 'Geofence',
    description: 'Entrance, exit, and speed-limit events',
    count: 12,
    iconName: 'map',
  ),
  ReportItem(
    title: 'Overspeed',
    description: 'Speed violations by tracker and time',
    count: 4,
    iconName: 'speed',
  ),
  ReportItem(
    title: 'Devices Status',
    description: 'Online, offline, stale, and battery states',
    count: 3,
    iconName: 'devices',
  ),
  ReportItem(
    title: 'Routes',
    description: 'Route table with expandable map detail',
    count: 8,
    iconName: 'route',
  ),
  ReportItem(
    title: 'Logs',
    description: 'Operational and tracker activity logs',
    count: 18,
    iconName: 'logs',
  ),
  ReportItem(
    title: 'Device Logs',
    description: 'Raw payload diagnostics and identifiers',
    count: 24,
    iconName: 'payload',
  ),
  ReportItem(
    title: 'Audit Log',
    description: 'User, action, and date filtered activity',
    count: 9,
    iconName: 'audit',
  ),
  ReportItem(
    title: 'Analysis',
    description: 'Distance, moving time, stops, and speeds',
    count: 6,
    iconName: 'analysis',
  ),
];

final _routePoints = List<RoutePoint>.generate(8, (index) {
  return RoutePoint(
    trackerId: 1,
    trackerName: 'Van 12',
    latitude: 24.84 + (index * 0.004),
    longitude: 67.0 + (index * 0.003),
    speed: index % 3 == 0 ? 0 : 28 + index,
    timestamp: DateTime(2026, 6, 8, 10, 48).add(Duration(minutes: index * 6)),
  );
});
