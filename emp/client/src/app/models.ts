export type EmployeeStatus = 'active' | 'on-leave' | 'pending' | 'inactive';
export type UserRole = 'admin' | 'hr' | 'manager' | 'viewer';

export interface Employee {
  _id: string;
  employeeId: string;
  name: string;
  email: string;
  department: string;
  jobTitle: string;
  joinedAt: string;
  status: EmployeeStatus;
}

export interface EmployeeInput extends Omit<Employee, '_id'> {}

export interface EmployeePage {
  items: Employee[];
  total: number;
  page: number;
  pages: number;
}

export interface EmployeeSummary {
  total: number;
  active: number;
  inactive: number;
  departments: number;
  departmentNames: string[];
  months: string[];
  hiresByMonth: number[];
  exitsByMonth: number[];
}

export interface SessionUser {
  id: string;
  name: string;
  email: string;
  role: UserRole;
}

export interface AuthResponse {
  token: string;
  user: SessionUser;
}
