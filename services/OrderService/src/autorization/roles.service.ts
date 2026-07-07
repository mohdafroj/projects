import { Injectable } from '@nestjs/common';
import { PrismaService } from '../prisma/prisma.service';
import { Role, Prisma } from '../generated/prisma/client';

@Injectable()
export class RolesService {
  constructor(private prisma: PrismaService) { }

  create(data: Prisma.RoleCreateInput): Promise<Role> {
    return this.prisma.role.create({ data });
  }

  update(params: { data: Prisma.RoleUpdateInput, where: Prisma.RoleWhereUniqueInput }): Promise<Role> {
    const { data, where } = params;
    return this.prisma.role.update({ where, data });
  }

  async findAll(params: {
    skip?: number;
    take?: number;
    cursor?: Prisma.RoleWhereUniqueInput;
    where?: Prisma.RoleWhereInput;
    orderBy?: Prisma.RoleOrderByWithRelationInput;
  }): Promise<Role[]> {
    const { skip, take, cursor, where, orderBy } = params;
    return this.prisma.role.findMany({ skip, take, cursor, where, orderBy });
  }

  findByUnique(where: Prisma.RoleWhereUniqueInput): Promise<Role> {
    return this.prisma.role.findUnique({ where });
  }

  removeByUnique(where: Prisma.RoleWhereUniqueInput): Promise<Role> {
    return this.prisma.role.delete({ where });
  }
}
