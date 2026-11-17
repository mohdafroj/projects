const express = require('express');
const mongoose = require('mongoose');
const Employee = require('../models/Employee');
const { authenticate, requireRole } = require('../middleware/auth');

const router = express.Router();
const canManageEmployees = requireRole('admin', 'hr');
const allowedSortFields = new Set(['name', 'employeeId', 'email', 'department', 'jobTitle', 'joinedAt', 'status']);
const allowedStatuses = new Set(['active', 'on-leave', 'pending', 'inactive']);
const monthLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

router.use(authenticate);

router.get('/summary', async (req, res) => {
  const year = new Date().getFullYear();
  const [total, active, inactive, departments, hires, exits] = await Promise.all([
    Employee.countDocuments(),
    Employee.countDocuments({ status: 'active' }),
    Employee.countDocuments({ status: 'inactive' }),
    Employee.distinct('department'),
    Employee.aggregate([
      { $match: { joinedAt: { $gte: new Date(year, 0, 1), $lt: new Date(year + 1, 0, 1) } } },
      { $group: { _id: { $month: '$joinedAt' }, count: { $sum: 1 } } }
    ]),
    Employee.aggregate([
      { $match: { terminatedAt: { $gte: new Date(year, 0, 1), $lt: new Date(year + 1, 0, 1) } } },
      { $group: { _id: { $month: '$terminatedAt' }, count: { $sum: 1 } } }
    ])
  ]);
  const toMonthlyCounts = (records) => monthLabels.map((_, index) =>
    records.find((record) => record._id === index + 1)?.count || 0
  );
  return res.json({
    total,
    active,
    inactive,
    departments: departments.length,
    departmentNames: departments.sort(),
    months: monthLabels,
    hiresByMonth: toMonthlyCounts(hires),
    exitsByMonth: toMonthlyCounts(exits)
  });
});

router.get('/', async (req, res) => {
  const requestedPage = req.query.page === undefined ? 1 : Number(req.query.page);
  const requestedLimit = req.query.limit === undefined ? 8 : Number(req.query.limit);
  if (!Number.isInteger(requestedPage) || requestedPage < 1 ||
      !Number.isInteger(requestedLimit) || requestedLimit < 1 || requestedLimit > 100) {
    return res.status(400).json({ message: 'Page must be positive and limit must be between 1 and 100' });
  }
  if (req.query.status && !allowedStatuses.has(req.query.status)) {
    return res.status(400).json({ message: 'Invalid employee status filter' });
  }
  if (req.query.sortBy && !allowedSortFields.has(req.query.sortBy)) {
    return res.status(400).json({ message: 'Invalid employee sort field' });
  }
  if (req.query.sortOrder && !['asc', 'desc'].includes(req.query.sortOrder)) {
    return res.status(400).json({ message: 'Sort order must be asc or desc' });
  }
  const page = requestedPage;
  const limit = requestedLimit;
  const search = typeof req.query.search === 'string' ? req.query.search.trim().slice(0, 100) : '';
  const status = typeof req.query.status === 'string' ? req.query.status : '';
  const department = typeof req.query.department === 'string' ? req.query.department.trim() : '';
  const sortBy = allowedSortFields.has(req.query.sortBy) ? req.query.sortBy : 'joinedAt';
  const sortOrder = req.query.sortOrder === 'asc' ? 1 : -1;
  const query = {};

  if (search) {
    const escaped = search.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    query.$or = ['name', 'email', 'employeeId', 'department', 'jobTitle']
      .map((field) => ({ [field]: { $regex: escaped, $options: 'i' } }));
  }
  if (status && allowedStatuses.has(status)) query.status = status;
  if (department) query.department = department;

  const [items, total] = await Promise.all([
    Employee.find(query).sort({ [sortBy]: sortOrder, _id: 1 }).skip((page - 1) * limit).limit(limit).lean(),
    Employee.countDocuments(query)
  ]);
  return res.json({ items, total, page, pages: Math.ceil(total / limit) });
});

router.post('/', canManageEmployees, async (req, res) => {
  if (!req.body || typeof req.body !== 'object' || Array.isArray(req.body)) {
    return res.status(400).json({ message: 'Employee details must be a JSON object' });
  }
  const employee = await Employee.create(req.body);
  return res.status(201).json({ employee });
});

router.put('/:id', canManageEmployees, async (req, res) => {
  if (!mongoose.isValidObjectId(req.params.id)) {
    return res.status(400).json({ message: 'Invalid employee identifier' });
  }
  if (!req.body || typeof req.body !== 'object' || Array.isArray(req.body)) {
    return res.status(400).json({ message: 'Employee details must be a JSON object' });
  }
  const employee = await Employee.findByIdAndUpdate(req.params.id, req.body, {
    new: true,
    runValidators: true
  });
  if (!employee) return res.status(404).json({ message: 'Employee not found' });
  return res.json({ employee });
});

router.delete('/:id', canManageEmployees, async (req, res) => {
  if (!mongoose.isValidObjectId(req.params.id)) {
    return res.status(400).json({ message: 'Invalid employee identifier' });
  }
  const employee = await Employee.findByIdAndDelete(req.params.id);
  if (!employee) return res.status(404).json({ message: 'Employee not found' });
  return res.status(204).end();
});

module.exports = router;
