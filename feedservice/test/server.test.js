import assert from 'node:assert/strict';
import test from 'node:test';

test('feedservice package is configured for node runtime', async () => {
  const packageJson = await import('../package.json', { with: { type: 'json' } });

  assert.equal(packageJson.default.type, 'module');
  assert.equal(packageJson.default.scripts.build, 'tsc --project tsconfig.json');
  assert.equal(packageJson.default.scripts.start, 'node dist/server.js');
});
