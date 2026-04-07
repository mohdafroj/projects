import { Controller, HttpCode, Post, Request, Response, UseGuards } from "@nestjs/common";
import { AuthService } from "./auth.service";
import { JwtAuthGuard } from "./guards/jwt-auth.guard";
import { RefreshAuthGuard } from "./guards/refresh-auth.guard";

@Controller('auth')
export class AuthController {
    constructor(
        private authService: AuthService
    ) { }

    @Post('login')
    @HttpCode(200)
    async login(@Request() req: any) {
        return this.authService.login(req.body);
    }

    @UseGuards(RefreshAuthGuard)
    @Post('refresh')
    @HttpCode(200)
    async refreshToken(@Request() req: any, @Response() res: any) {
        return this.authService.refresh(req, res);
    }

    @UseGuards(JwtAuthGuard)
    @Post('logout')
    async logout(@Request() req: any) {
        return req.logout();
    }

}