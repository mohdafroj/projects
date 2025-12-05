import {
    ExceptionFilter,
    Catch,
    ArgumentsHost,
    HttpException,
    HttpStatus,
} from '@nestjs/common';

@Catch()
export class AllExceptionsFilter implements ExceptionFilter {
    catch(exception: any, host: ArgumentsHost) {
        const ctx = host.switchToHttp();
        const response = ctx.getResponse();
        const request = ctx.getRequest();

        let status = HttpStatus.INTERNAL_SERVER_ERROR;
        let message = 'Internal server error';

        if (exception instanceof HttpException) {
            status = exception.getStatus();
            const res: any = exception.getResponse();
            message = res.message || exception.message;
        }

        const errorBody = {
            success: false,
            message,
            errors: exception?.response?.errors || null,
            timestamp: new Date().toISOString(),
            path: request.url,
        };

        // --- HIDE DEBUG FIELDS IN NON-DEVELOPMENT ENV ---
        if (process.env.NODE_ENV !== 'development') {
            const { timestamp, path, ...prodBody } = errorBody;
            return response.status(status).json(prodBody);
        }

        // Development → full output
        return response.status(status).json(errorBody);
    }
}
