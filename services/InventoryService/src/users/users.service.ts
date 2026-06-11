import { Injectable } from '@nestjs/common';
import { PrismaService } from '../prisma/prisma.service';
import { User, Prisma } from '../generated/prisma/client';

@Injectable()
export class UsersService {

  constructor(private prisma: PrismaService) { }

  create(data: Prisma.UserCreateInput): Promise<User> {
    return this.prisma.user.create({ data });
  }

  update(params: { data: Prisma.UserUpdateInput, where: Prisma.UserWhereUniqueInput }): Promise<User> {
    const { data, where } = params;
    return this.prisma.user.update({ where, data });
  }

  async findAll(params: {
    skip?: number;
    take?: number;
    cursor?: Prisma.UserWhereUniqueInput;
    where?: Prisma.UserWhereInput;
    orderBy?: Prisma.UserOrderByWithRelationInput;
  }): Promise<User[]> {
    const { skip, take, cursor, where, orderBy } = params;
    return this.prisma.user.findMany({ skip, take, cursor, where, orderBy });
  }

  findByUnique(where: Prisma.UserWhereUniqueInput): Promise<User> {
    return this.prisma.user.findUnique({ where });
  }

  removeByUnique(where: Prisma.UserWhereUniqueInput): Promise<User> {
    return this.prisma.user.delete({ where });
  }

  findOne(username: string): Promise<User> {
    return this.prisma.user.findUnique({ where: { email: username } });
  }
}
