const jwt = require('jsonwebtoken');
const { authenticate, requireRole } = require('../src/middleware/auth');

describe('authentication and role guards', () => {
  const secret = 'test-secret-with-enough-entropy';
  beforeEach(() => {
    process.env.JWT_SECRET = secret;
  });

  function response() {
    return {
      statusCode: 200,
      body: null,
      status(code) { this.statusCode = code; return this; },
      json(body) { this.body = body; return this; },
      get() { return this.authorization; }
    };
  }

  test('rejects a request without a bearer token', () => {
    const res = response();
    authenticate({ get: () => undefined }, res, jest.fn());
    expect(res.statusCode).toBe(401);
  });

  test('sets the authenticated user from a valid token', () => {
    const req = { get: () => `Bearer ${jwt.sign({ role: 'hr', name: 'Pat', email: 'pat@example.com' }, secret, { subject: 'user-1' })}` };
    const next = jest.fn();
    authenticate(req, response(), next);
    expect(req.user).toEqual({ id: 'user-1', role: 'hr', name: 'Pat', email: 'pat@example.com' });
    expect(next).toHaveBeenCalledTimes(1);
  });

  test('blocks viewers from a manager-only action', () => {
    const res = response();
    const next = jest.fn();
    requireRole('admin', 'hr')({ user: { role: 'viewer' } }, res, next);
    expect(res.statusCode).toBe(403);
    expect(next).not.toHaveBeenCalled();
  });
});
