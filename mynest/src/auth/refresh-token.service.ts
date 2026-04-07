import { Injectable } from '@nestjs/common';
import { PrismaService } from '../prisma/prisma.service';
import { RefreshToken, Prisma } from '../generated/prisma/client';

@Injectable()
export class RefreshTokenService {

    constructor(private prisma: PrismaService) { }

    create(data: Prisma.RefreshTokenCreateInput): Promise<RefreshToken> {
        return this.prisma.refreshToken.create({ data });
    }

    update(params: { data: Prisma.RefreshTokenUpdateInput, where: Prisma.RefreshTokenWhereUniqueInput }): Promise<RefreshToken> {
        const { data, where } = params;
        return this.prisma.refreshToken.update({ where, data });
    }

    async findAll(params: {
        skip?: number;
        take?: number;
        cursor?: Prisma.RefreshTokenWhereUniqueInput;
        where?: Prisma.RefreshTokenWhereInput;
        orderBy?: Prisma.RefreshTokenOrderByWithRelationInput;
    }): Promise<RefreshToken[]> {
        const { skip, take, cursor, where, orderBy } = params;
        return this.prisma.refreshToken.findMany({ skip, take, cursor, where, orderBy });
    }

    findByUnique(where: Prisma.RefreshTokenWhereUniqueInput): Promise<RefreshToken> {
        return this.prisma.refreshToken.findUnique({ where });
    }

    removeByUnique(where: Prisma.RefreshTokenWhereUniqueInput): Promise<RefreshToken> {
        return this.prisma.refreshToken.delete({ where });
    }

    remove(where: Prisma.RefreshTokenWhereInput): Promise<Prisma.BatchPayload> {
        return this.prisma.refreshToken.deleteMany({ where });
    }
}
