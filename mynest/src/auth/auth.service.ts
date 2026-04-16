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

    async login_old(user: any) {
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

    async refresh_old(data: any) {
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

    async login(@Request() req: any, @Response() res: any) {
        const userFromDb = await this.validateUser(req.body?.username, req.body?.password);
        const payload = { sub: userFromDb.id };
        const accessToken = await this.generateAccessToken(payload);
        const refreshToken = await this.generateRefreshToken(payload);

        // hash refresh token
        const token_hash = await bcrypt.hash(refreshToken, parseInt(process.env.HASH_SALT));
        const refreshTokenPayload = {
            user_id: userFromDb.id,
            token_hash,
            device: req.headers['user-agent'],
            ip: req.ip,
            expires_at: new Date(Date.now() + 7 * 24 * 60 * 60 * 1000),
        }
        const added = await this.refreshTokenService.create(refreshTokenPayload);
        // send via http-only cookie
        // res.cookie('refresh_token', refreshToken, {
        //     httpOnly: true,
        //     secure: true,
        //     sameSite: 'strict',
        // });
        // console.log(res.cookies);

        return { accessToken };
    }

    async refresh(@Request() req: any, @Response() res: any) {
        try {
            const refreshToken = req.cookies['refresh_token'];
            if (!refreshToken) {
                throw new HttpException(
                    'Refresh token is invalid',
                    HttpStatus.BAD_REQUEST
                );
            }

            let payload = this.jwtService.verify(refreshToken, { secret: process.env.JWT_REFRESH_SECRET, });

            const user_id = payload.sub;

            const tokens = await this.refreshTokenService.findAll({
                where: { user_id }
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
                await this.refreshTokenService.remove({ user_id });
                throw new HttpException(
                    'Token reuse detected',
                    HttpStatus.BAD_REQUEST
                );
            }

            // 🔁 ROTATION
            await this.refreshTokenService.removeByUnique({ id: matchedToken.id });
            const newPayload = { sub: user_id };

            const newAccessToken = await this.generateAccessToken(newPayload);

            const newRefreshToken = await this.generateRefreshToken(newPayload);

            const newHash = await bcrypt.hash(newRefreshToken, 10);

            const newRefreshPayload = {
                user_id,
                token_hash: newHash,
                expires_at: new Date(Date.now() + 7 * 24 * 60 * 60 * 1000),
                device: req.headers['user-agent'],
                ip: req.ip
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

    async logout(@Request() req: any, @Response() res: any) {
        const refreshToken = req.cookies['refresh_token'];
        if (!refreshToken) return;
        const payload = this.jwtService.verify(refreshToken, { secret: process.env.JWT_REFRESH_SECRET });
        const user_id = payload.sub;
        const tokens = await this.refreshTokenService.findAll({ where: { user_id } });

        for (const t of tokens) {
            const isMatch = await bcrypt.compare(refreshToken, t.token_hash);
            if (isMatch) {
                await this.refreshTokenService.removeByUnique({ id: t.id });
                break;
            }
        }

        res.clearCookie('refresh_token');
    }
}
