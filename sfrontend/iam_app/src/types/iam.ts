export interface User {
  id: string;
  name: string;
  email: string;
  role: string;
  status: "active" | "inactive";
}

export interface Service {
  id: string;
  name: string;
  description: string;
  features: string[];
  icon: string;
}

export interface Feature {
  id: string;
  name: string;
  description: string;
  service: string;
  module: string;
  status: "active" | "inactive";
}

export interface Role {
  id: string;
  name: string;
  description: string;
  permissions: number;
}

export interface Permission {
  id: string;
  name: string;
  admin: boolean;
  teacher: boolean;
  student: boolean;
}
