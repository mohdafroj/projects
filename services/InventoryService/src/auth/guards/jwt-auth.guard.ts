import { Injectable } from "@nestjs/common";
import { AuthGuard } from "@nestjs/passport";

// guards/jwt-auth.guard.ts
@Injectable()
export class JwtAuthGuard extends AuthGuard('jwt') { }