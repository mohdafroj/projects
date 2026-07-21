import {
  CallHandler,
  ExecutionContext,
  Injectable,
  NestInterceptor,
} from '@nestjs/common';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';

export interface Response<T> {
  message: string;
  success: boolean;
  status: number;
  data: T;
}

@Injectable()
export class TransformInterceptor<T> implements NestInterceptor<
  T,
  Response<T>
> {
  intercept(
    context: ExecutionContext,
    next: CallHandler,
  ): Observable<Response<T>> {
    // 👈 Bypass interceptor for GraphQL requests
    if (context.getType<string>() === 'graphql') {
      return next.handle();
    }
    const response = context.switchToHttp().getResponse();
    const status = response.statusCode || 200;

    return next.handle().pipe(
      map((data) => {
        // If data already contains a custom message or custom shape
        const message = data?.message || 'Operation completed successfully';
        const payload = data?.data !== undefined ? data.data : data;

        return {
          message,
          success: true,
          status,
          data: payload,
        };
      }),
    );
  }
}
