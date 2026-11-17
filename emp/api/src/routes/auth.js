const express = require('express');
const jwt = require('jsonwebtoken');
const rateLimit = require('express-rate-limit');
const User = require('../models/User');
const { authenticate, requireRole } = require('../middleware/auth');

const router = express.Router();
const allowedRoles = new Set(['admin', 'hr', 'manager', 'viewer']);
const loginLimiter = rateLimit({
  windowMs: 15 * 60 * 1000,
  limit: 10,
  standardHeaders: 'draft-7',
  legacyHeaders: false,
  message: { message: 'Too many login attempts. Please try again later.' }
});

router.post('/login', loginLimiter, async (req, res) => {
  const body = req.body && typeof req.body === 'object' && !Array.isArray(req.body) ? req.body : {};
  const email = typeof body.email === 'string' ? body.email.trim().toLowerCase() : '';
  const password = typeof body.password === 'string' ? body.password : '';
  if (!email || !password) {
    return res.status(400).json({ message: 'Email and password are required' });
  }

  const user = await User.findOne({ email }).select('+passwordHash');
  if (!user || !(await user.verifyPassword(password))) {
    return res.status(401).json({ message: 'Email or password is incorrect' });
  }

  const token = jwt.sign(
    { role: user.role, name: user.name, email: user.email },
    process.env.JWT_SECRET,
    { subject: user.id, expiresIn: process.env.JWT_EXPIRES_IN || '8h' }
  );
  return res.json({
    token,
    user: { id: user.id, name: user.name, email: user.email, role: user.role }
  });
});

router.get('/me', authenticate, (req, res) => res.json({ user: req.user }));
router.post('/logout', authenticate, (req, res) => res.status(204).end());

router.post('/users', authenticate, requireRole('admin'), async (req, res) => {
  const body = req.body && typeof req.body === 'object' && !Array.isArray(req.body) ? req.body : {};
  const { name, email, password, role } = body;
  if (
    typeof name !== 'string' || !name.trim() ||
    typeof email !== 'string' || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email) ||
    typeof password !== 'string' || password.length < 12 || Buffer.byteLength(password, 'utf8') > 72 ||
    !allowedRoles.has(role)
  ) {
    return res.status(400).json({
      message: 'Provide a name, valid email, password (12–72 bytes), and valid role'
    });
  }

  const normalizedEmail = email.trim().toLowerCase();
  if (await User.exists({ email: normalizedEmail })) {
    return res.status(409).json({ message: 'An account with that email already exists' });
  }
  const passwordHash = await User.hashPassword(password);
  const user = await User.create({ name: name.trim(), email: normalizedEmail, passwordHash, role });
  return res.status(201).json({
    user: { id: user.id, name: user.name, email: user.email, role: user.role }
  });
});

module.exports = router;
