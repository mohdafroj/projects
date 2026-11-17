const mongoose = require('mongoose');

const employeeSchema = new mongoose.Schema(
  {
    employeeId: { type: String, required: true, unique: true, trim: true, uppercase: true },
    name: { type: String, required: true, trim: true, maxlength: 100 },
    email: { type: String, required: true, unique: true, lowercase: true, trim: true },
    department: { type: String, required: true, trim: true, maxlength: 80 },
    jobTitle: { type: String, required: true, trim: true, maxlength: 100 },
    joinedAt: { type: Date, required: true },
    status: {
      type: String,
      enum: ['active', 'on-leave', 'pending', 'inactive'],
      default: 'active'
    },
    terminatedAt: { type: Date, default: null }
  },
  { timestamps: true }
);

employeeSchema.index({ name: 'text', email: 'text', employeeId: 'text', department: 'text', jobTitle: 'text' });

module.exports = mongoose.model('Employee', employeeSchema);
