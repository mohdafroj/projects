import { Injectable } from '@nestjs/common';
import { UsersService } from '../users/users.service';
import { JwtService } from '@nestjs/jwt';

@Injectable()
export class AuthService {
    constructor(private usersService: UsersService, private jwtService: JwtService) { }

    async validateUser(username: string, pass: string): Promise<any> {
        const user = await this.usersService.findOne(username);
        if (!user) {
            return { message: 'User is not found!' };
        }
        if (user && user.password === pass) {
            const { password, created_at, updated_at, ...result } = user;
            return result;
        }
        if (user && user.password !== pass) {
            return { message: 'Password is not match!' };
        }
        return null;
    }

    async login(user: any) {
        try {
            const payload = { username: user?.name ?? 'abc name', sub: user?.id ?? 1000 };
            const access_token = this.jwtService.sign(payload, { secret: process.env.JWT_SECRET || 'abc' });
            return { access_token };
        } catch (err) {
            console.error('JWT sign error:', err?.message ?? err);
            throw err;
        }
    }
}
