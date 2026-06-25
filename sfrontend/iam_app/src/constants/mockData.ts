import { User, Role, Permission, Service, Feature } from "../types/iam";

export const mockUsers: User[] = [
  { id: "1", name: "John Admin", email: "john@school.com", role: "Admin", status: "active" },
  { id: "2", name: "Jane Teacher", email: "jane@school.com", role: "Teacher", status: "active" },
  { id: "3", name: "Bob Student", email: "bob@school.com", role: "Student", status: "inactive" },
];

export const mockRoles: Role[] = [
  { id: "1", name: "Admin", description: "Full access to all features and settings", permissions: 25 },
  { id: "2", name: "Teacher", description: "Access to teaching, grading, and classroom management", permissions: 15 },
  { id: "3", name: "Student", description: "Limited access to course content and grades", permissions: 5 },
];

export const mockPermissions: Permission[] = [
  { id: "1", name: "View Users", admin: true, teacher: false, student: false },
  { id: "2", name: "Create Users", admin: true, teacher: false, student: false },
  { id: "3", name: "Manage Roles", admin: true, teacher: false, student: false },
  { id: "4", name: "Create Classes", admin: true, teacher: true, student: false },
  { id: "5", name: "View Grades", admin: true, teacher: true, student: true },
  { id: "6", name: "Edit Attendance", admin: true, teacher: true, student: false },
  { id: "7", name: "Generate Reports", admin: true, teacher: true, student: false },
  { id: "8", name: "View Dashboard", admin: true, teacher: true, student: true },
];

export const mockServices: Service[] = [
  {
    id: "1",
    name: "Student Management",
    description: "Manage student records, enrollment, and progress tracking",
    features: ["Add Students", "View Records", "Update Progress", "Generate Reports"],
    icon: "👥",
  },
  {
    id: "2",
    name: "Teacher Management",
    description: "Manage teacher profiles, assignments, and schedules",
    features: ["Manage Profiles", "Assign Classes", "Create Schedules", "Track Performance"],
    icon: "👨‍🏫",
  },
];

export const mockFeatures: Feature[] = [
  { id: "1", name: "Add Students", description: "Create and add new student records", service: "Student Management", module: "remoteStudent", status: "active" },
  { id: "2", name: "View Records", description: "View and search student records", service: "Student Management", module: "remoteStudent", status: "active" },
];
