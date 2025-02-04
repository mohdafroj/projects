export interface User {
  id: number;
  name: string;
  email: string;
  mobile?: string;
  password: string; // In a real application, you'd never return this
}
