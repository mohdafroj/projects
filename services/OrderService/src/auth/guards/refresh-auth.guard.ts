import { Injectable } from "@nestjs/common";
import { AuthGuard } from "@nestjs/passport";

// guards/refresh-auth.guard.ts
@Injectable()
export class RefreshAuthGuard extends AuthGuard('jwt-refresh') { }