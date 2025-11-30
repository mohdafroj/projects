import { Injectable } from '@nestjs/common';
import { User } from './interfaces/user.interface';

@Injectable()
export class UsersService {
  private users: User[] = [];

  create(user: User) {
    this.users.push(user);
  }

  findAll(): User[] {
    return this.users;
  }

  findById(id: Number) {
    return this.users.filter((item) => item.id == id);
  }

  removeById(id: Number) {
    return this.users.filter((item) => item.id != id);
  }
}
