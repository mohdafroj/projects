import { Injectable } from '@nestjs/common';
import { PrismaService } from '../prisma/prisma.service';
import { Module, Prisma } from '../generated/prisma/client';

@Injectable()
export class ModulesService {
  constructor(private prisma: PrismaService) { }

  create(data: Prisma.ModuleCreateInput): Promise<Module> {
    return this.prisma.module.create({ data });
  }

  update(params: { data: Prisma.ModuleUpdateInput, where: Prisma.ModuleWhereUniqueInput }): Promise<Module> {
    const { data, where } = params;
    return this.prisma.module.update({ where, data });
  }

  async findAll(params: {
    skip?: number;
    take?: number;
    cursor?: Prisma.ModuleWhereUniqueInput;
    where?: Prisma.ModuleWhereInput;
    orderBy?: Prisma.ModuleOrderByWithRelationInput;
  }): Promise<Module[]> {
    const { skip, take, cursor, where, orderBy } = params;
    return this.prisma.module.findMany({ skip, take, cursor, where, orderBy });
  }

  findByUnique(where: Prisma.ModuleWhereUniqueInput): Promise<Module> {
    return this.prisma.module.findUnique({ where });
  }

  removeByUnique(where: Prisma.ModuleWhereUniqueInput): Promise<Module> {
    return this.prisma.module.delete({ where });
  }
}
