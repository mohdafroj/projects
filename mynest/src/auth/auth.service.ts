import { HttpException, HttpStatus, Injectable, Request, Response } from '@nestjs/common';
import { UsersService } from '../users/users.service';
import { RefreshTokenService } from './refresh-token.service';
import { JwtService } from '@nestjs/jwt';
import * as bcrypt from 'bcrypt';

@Injectable()
export class AuthService {
    constructor(private usersService: UsersService, private refreshTokenService: RefreshTokenService, private jwtService: JwtService) { }
    async generateAccessToken(payload: any) {
        return this.jwtService.sign(payload, { secret: process.env.JWT_ACCESS_SECRET, expiresIn: '5m' });
    };

    async generateRefreshToken(payload: any) {
        return this.jwtService.sign(payload, { secret: process.env.JWT_REFRESH_SECRET, expiresIn: '7d' });
    };

    async validateUser(username: string, pass: string): Promise<any> {
        const user = await this.usersService.findOne(username);
        if (!user) {
            throw new HttpException(
                'User is not found!',
                HttpStatus.UNAUTHORIZED
            );
        }

        const valid = await bcrypt.compare(pass, user.password);
        if (!valid) throw new HttpException(
            'Invalid password',
            HttpStatus.UNAUTHORIZED
        );

        const { password, created_at, updated_at, refresh_token, ...result } = user;
        return result;
    }

    async login(user: any) {
        try {
            const userFromDb = await this.validateUser(user?.username, user?.password);
            const access_token = await this.generateAccessToken(userFromDb);
            const refresh_token = await this.generateRefreshToken(userFromDb);
            const updated = await this.usersService.update({
                where: { id: userFromDb.id },
                data: { refresh_token }
            });
            if (!updated) {
                throw new HttpException(
                    'Failed to update refresh token',
                    HttpStatus.INTERNAL_SERVER_ERROR
                );
            }
            return { access_token, refresh_token };
        } catch (err) {
            throw err;
        }
    }

    async refreshToken(data: any) {
        try {
            const { authorization } = data;
            const token = authorization?.split(' ')[1];
            if (!token) {
                throw new HttpException(
                    'Refresh token is required',
                    HttpStatus.BAD_REQUEST
                );
            }
            const { iat, exp, ...payload } = this.jwtService.verify(token, { secret: process.env.JWT_REFRESH_SECRET });
            if (!payload) {
                throw new HttpException(
                    'Invalid refresh token',
                    HttpStatus.UNAUTHORIZED
                );
            }
            const user = await this.usersService.findOne(payload.email);
            if (!user) {
                throw new HttpException(
                    'Invalid refresh token',
                    HttpStatus.UNAUTHORIZED
                );
            }

            const access_token = await this.generateAccessToken(payload);
            const refresh_token = await this.generateRefreshToken(payload);
            const updated = await this.usersService.update({
                where: { id: payload.id },
                data: { refresh_token }
            });
            if (!updated) {
                throw new HttpException(
                    'Failed to update refresh token',
                    HttpStatus.UNAUTHORIZED
                );
            }
            return {
                access_token,
                refresh_token
            };
        } catch (err) {
            throw err;
        }
    }

    async login1(user: User, req: Request, res: Response) {
        const payload = { sub: user.id, email: user.email };

        const accessToken = this.jwtService.sign(payload, {
            secret: process.env.JWT_ACCESS_SECRET,
            expiresIn: '15m',
        });

        const refreshToken = this.jwtService.sign(payload, {
            secret: process.env.JWT_REFRESH_SECRET,
            expiresIn: '7d',
        });

        // hash refresh token
        const tokenHash = await bcrypt.hash(refreshToken, 10);

        await this.prisma.refreshToken.create({
            data: {
                userId: user.id,
                tokenHash,
                device: req.headers['user-agent'],
                ip: req.ip,
                expiresAt: new Date(Date.now() + 7 * 24 * 60 * 60 * 1000),
            },
        });

        // send via http-only cookie
        res.cookie('refresh_token', refreshToken, {
            httpOnly: true,
            secure: true,
            sameSite: 'strict',
        });

        return { accessToken };
    }

    async refresh(@Request() req: Request, @Response() res: any) {
        try {
            const refreshToken = req.cookies['refresh_token'];
            if (!refreshToken) {
                throw new HttpException(
                    'Refresh token is invalid',
                    HttpStatus.BAD_REQUEST
                );
            }

            let payload = this.jwtService.verify(refreshToken, { secret: process.env.JWT_REFRESH_SECRET, });

            const userId = payload.sub;

            const tokens = await this.refreshTokenService.findAll({
                where: { user_id: userId },
            });

            let matchedToken = null;

            for (const t of tokens) {
                const isMatch = await bcrypt.compare(refreshToken, t.token_hash);
                if (isMatch) {
                    matchedToken = t;
                    break;
                }
            }

            if (!matchedToken) {
                // possible token theft
                await this.refreshTokenService.remove({ user_id: userId });
                throw new HttpException(
                    'Token reuse detected',
                    HttpStatus.BAD_REQUEST
                );
            }

            // 🔁 ROTATION
            await this.refreshTokenService.removeByUnique({ id: matchedToken.id });
            const newPayload = { sub: userId };

            const newAccessToken = await this.generateAccessToken(newPayload);

            const newRefreshToken = await this.generateRefreshToken(newPayload);

            const newHash = await bcrypt.hash(newRefreshToken, 10);

            const newRefreshPayload = {
                user_id: userId,
                token_hash: newHash,
                expires_at: new Date(Date.now() + 7 * 24 * 60 * 60 * 1000),
                device: req.headers['user-agent'],
                ip_address: req.ip
            };
            await this.refreshTokenService.create(newRefreshPayload);

            res.cookie('refresh_token', newRefreshToken, {
                httpOnly: true,
                secure: true,
                sameSite: 'strict',
            });

            return { accessToken: newAccessToken };
        } catch (err) {
            throw err;
        }
    }


    async logout(req: Request, res: Response) {
        const refreshToken = req.cookies['refresh_token'];
        if (!refreshToken) return;

        const tokens = await this.prisma.refreshToken.findMany();

        for (const t of tokens) {
            const isMatch = await bcrypt.compare(refreshToken, t.tokenHash);
            if (isMatch) {
                await this.prisma.refreshToken.delete({ where: { id: t.id } });
                break;
            }
        }

        res.clearCookie('refresh_token');
    }
}
