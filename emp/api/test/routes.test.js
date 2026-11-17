const jwt = require('jsonwebtoken');
const app = require('../src/app');

describe('REST API input handling', () => {
  let server;
  let baseUrl;

  beforeAll((done) => {
    process.env.JWT_SECRET = 'test-secret-with-enough-entropy';
    server = app.listen(0, () => {
      baseUrl = `http://127.0.0.1:${server.address().port}`;
      done();
    });
  });

  afterAll((done) => {
    server.close(done);
  });

  test('returns a client error for an empty login payload', async () => {
    const response = await fetch(`${baseUrl}/api/auth/login`, {
      method: 'POST'
    });
    expect(response.status).toBe(400);
    await expect(response.json()).resolves.toEqual({ message: 'Email and password are required' });
  });

  test('returns a client error for malformed JSON', async () => {
    const response = await fetch(`${baseUrl}/api/auth/login`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: '{"email":'
    });
    expect(response.status).toBe(400);
    await expect(response.json()).resolves.toEqual({ message: 'Request body must be valid JSON' });
  });

  test('rejects invalid employee filters before querying MongoDB', async () => {
    const token = jwt.sign({ role: 'viewer', name: 'Viewer', email: 'viewer@example.com' }, process.env.JWT_SECRET, { subject: 'viewer-1' });
    const response = await fetch(`${baseUrl}/api/employees?status=unknown`, {
      headers: { Authorization: `Bearer ${token}` }
    });
    expect(response.status).toBe(400);
    await expect(response.json()).resolves.toEqual({ message: 'Invalid employee status filter' });
  });
});
