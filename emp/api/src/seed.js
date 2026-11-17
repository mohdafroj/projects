require('dotenv').config();
const User = require('./models/User');
const Employee = require('./models/Employee');
const { connectDatabase } = require('./config/db');

async function seed() {
  if (!process.env.ADMIN_EMAIL || !process.env.ADMIN_PASSWORD) {
    throw new Error('ADMIN_EMAIL and ADMIN_PASSWORD are required');
  }
  await connectDatabase();
  const email = process.env.ADMIN_EMAIL.toLowerCase();
  const existingAdmin = await User.findOne({ email });
  if (!existingAdmin) {
    const passwordHash = await User.hashPassword(process.env.ADMIN_PASSWORD);
    await User.create({
      name: process.env.ADMIN_NAME || 'System Administrator',
      email,
      passwordHash,
      role: 'admin'
    });
    console.log(`Created admin account ${email}`);
  } else {
    console.log(`Admin account ${email} already exists`);
  }

  if (await Employee.countDocuments() === 0) {
    const year = new Date().getFullYear();
    await Employee.insertMany([
      { employeeId: 'EMP-1001', name: 'Jordan Lee', email: 'jordan.lee@example.com', department: 'Engineering', jobTitle: 'Software Engineer', joinedAt: new Date(year, 0, 8), status: 'active' },
      { employeeId: 'EMP-1002', name: 'Taylor Morgan', email: 'taylor.morgan@example.com', department: 'People', jobTitle: 'HR Manager', joinedAt: new Date(year, 1, 12), status: 'on-leave' },
      { employeeId: 'EMP-1003', name: 'Alex Rivera', email: 'alex.rivera@example.com', department: 'Design', jobTitle: 'Product Designer', joinedAt: new Date(year, 2, 4), status: 'pending' },
      { employeeId: 'EMP-1004', name: 'Casey Patel', email: 'casey.patel@example.com', department: 'Marketing', jobTitle: 'Marketing Lead', joinedAt: new Date(year, 3, 18), status: 'active' },
      { employeeId: 'EMP-1005', name: 'Jamie Chen', email: 'jamie.chen@example.com', department: 'Finance', jobTitle: 'Financial Analyst', joinedAt: new Date(year, 5, 2), status: 'active' },
      { employeeId: 'EMP-1006', name: 'Sam Wilson', email: 'sam.wilson@example.com', department: 'Engineering', jobTitle: 'QA Engineer', joinedAt: new Date(year, 7, 11), status: 'inactive', terminatedAt: new Date(year, 9, 2) },
      { employeeId: 'EMP-1007', name: 'Riley Kim', email: 'riley.kim@example.com', department: 'People', jobTitle: 'Recruiter', joinedAt: new Date(year, 9, 15), status: 'active' },
      { employeeId: 'EMP-1008', name: 'Drew Brooks', email: 'drew.brooks@example.com', department: 'Design', jobTitle: 'UX Designer', joinedAt: new Date(year, 10, 3), status: 'active' }
    ]);
    console.log('Added sample employees');
  }
  process.exit(0);
}

seed().catch((error) => {
  console.error('Unable to seed database:', error);
  process.exitCode = 1;
});
