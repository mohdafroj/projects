import {
    Injectable,
    NestInterceptor,
    ExecutionContext,
    CallHandler,
} from '@nestjs/common';
import { response } from 'express';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';

@Injectable()
export class ResponseTransformInterceptor<T>
    implements NestInterceptor<T, any> {
    intercept(context: ExecutionContext, next: CallHandler): Observable<any> {
        const now = Date.now();

        return next.handle().pipe(
            map((data) => {
                const { message, ...rest } = data || {};
                const timestamp = new Date().toISOString();
                const path = context.switchToHttp().getRequest().url;
                const duration = `${Date.now() - now}ms`;
                let response = {
                    success: true,
                    message,
                    data: rest,
                    timestamp,
                    path,
                    duration
                };
                if (process.env.NODE_ENV != 'development') {
                    let { timestamp, path, duration, ...responseRes } = response;
                    return responseRes;
                }
                return response;
            }),
        );
    }
}
