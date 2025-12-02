import { Injectable } from '@nestjs/common';
import { PrismaService } from '../prisma/prisma.service';
import { User, Prisma } from '../generated/prisma/client';

@Injectable()
export class UsersService {
  private users: User[] = [];
  constructor(private prisma: PrismaService) { }
  create(data: Prisma.UserCreateInput): Promise<User> {
    return this.prisma.user.create({ data });
  }

  async findAll(): Promise<User[]> {
    return this.prisma.user.findMany({});
  }

  findByUnique(where: Prisma.UserWhereUniqueInput): Promise<User> {
    return this.prisma.user.findUnique({ where })
  }

  removeByUnique(where: Prisma.UserWhereUniqueInput): Promise<User> {
    return this.prisma.user.delete({ where });
  }
}
