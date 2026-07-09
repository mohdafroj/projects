import { ExceptionFilter, Catch, ArgumentsHost, HttpException, HttpStatus } from '@nestjs/common';
import { Response } from 'express';

@Catch()
export class HttpExceptionFilter implements ExceptionFilter {
    catch(exception: any, host: ArgumentsHost) {
        // 👈 Bypass filter for GraphQL requests (GraphQL uses formatError in app.module.ts)
        if (host.getType<string>() === 'graphql') {
            return exception;
        }
        const ctx = host.switchToHttp();
        const response = ctx.getResponse<Response>();

        let status = HttpStatus.INTERNAL_SERVER_ERROR;
        let message = 'Internal Server Error';
        let errors: any = {};

        if (exception instanceof HttpException) {
            status = exception.getStatus();
            const resBody = exception.getResponse() as any;

            message = typeof resBody === 'object' ? (resBody.error || resBody.message) : resBody;
            errors = typeof resBody === 'object' && resBody.message ? resBody.message : { error: exception.message };
        } else if (exception instanceof Error) {
            message = exception.message;
            errors = { details: exception.message };
        }

        // Standardize error list/object formats
        const errorDetails = Array.isArray(errors) ? errors : [errors];

        response.status(status).json({
            message,
            success: false,
            status,
            errors: errorDetails,
        });
    }
}
