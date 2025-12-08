import { Injectable } from '@nestjs/common';
import { PrismaService } from '../prisma/prisma.service';
import { Permission, Prisma } from '../generated/prisma/client';

@Injectable()
export class PermissionsService {
  constructor(private prisma: PrismaService) { }

  create(data: Prisma.PermissionCreateInput): Promise<Permission> {
    return this.prisma.permission.create({ data });
  }

  update(params: { data: Prisma.PermissionUpdateInput, where: Prisma.PermissionWhereUniqueInput }): Promise<Permission> {
    const { data, where } = params;
    return this.prisma.permission.update({ where, data });
  }

  async findAll(params: {
    skip?: number;
    take?: number;
    cursor?: Prisma.PermissionWhereUniqueInput;
    where?: Prisma.PermissionWhereInput;
    orderBy?: Prisma.PermissionOrderByWithRelationInput;
  }): Promise<Permission[]> {
    const { skip, take, cursor, where, orderBy } = params;
    return this.prisma.permission.findMany({ skip, take, cursor, where, orderBy });
  }

  findByUnique(where: Prisma.PermissionWhereUniqueInput): Promise<Permission> {
    return this.prisma.permission.findUnique({ where });
  }

  removeByUnique(where: Prisma.PermissionWhereUniqueInput): Promise<Permission> {
    return this.prisma.permission.delete({ where });
  }
}
