import { Injectable } from '@nestjs/common';
import { PrismaService } from '../prisma/prisma.service';
import { User } from './interfaces/user.interface';
import { users, Prisma } from '../generated/client';
@Injectable()
export class UsersService {
  private users: User[] = [];
  constructor(private prisma: PrismaService) {}
  create(user: User) {
    this.users.push(user);
  }

  async findAll(): Promise<users[]> {
    return await this.prisma.users.findMany({});
  }

  findById(id: Number) {
    return this.users.filter((item) => item.id == id);
  }

  removeById(id: Number) {
    return this.users.filter((item) => item.id != id);
  }
}
