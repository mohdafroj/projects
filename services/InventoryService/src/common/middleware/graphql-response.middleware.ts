import { Injectable, NestMiddleware } from '@nestjs/common';
import { Request, Response, NextFunction } from 'express';

interface GraphQLErrorExtension {
  originalError?: {
    message?: string | string[];
    statusCode?: number;
    error?: string;
  };
}

interface GraphQLErrorItem {
  message?: string;
  extensions?: GraphQLErrorExtension;
}

interface GraphQLResponseBody {
  data?: Record<string, unknown> | null;
  errors?: GraphQLErrorItem[];
}

interface FormattedSuccessResponse {
  success: boolean;
  message: string;
  data: unknown;
}

interface FormattedErrorResponse {
  success: boolean;
  message: string;
  data: null;
  error?: Array<{ field_name: string; message: string }>;
}

@Injectable()
export class GraphQLResponseMiddleware implements NestMiddleware {
  use(req: Request, res: Response, next: NextFunction) {
    const originalSend = res.send;

    res.send = function (this: Response, body?: unknown): Response {
      if (body && typeof body === 'string') {
        try {
          const parsed = JSON.parse(body) as GraphQLResponseBody;

          if (
            parsed &&
            (parsed.data !== undefined || parsed.errors !== undefined)
          ) {
            if (parsed.errors && Array.isArray(parsed.errors)) {
              const firstError = parsed.errors[0];
              const originalError = firstError.extensions?.originalError;
              const errorMessage =
                firstError.message || 'Internal Server Error';

              const newResponse: FormattedErrorResponse = {
                success: false,
                message: errorMessage,
                data: null,
              };

              if (originalError?.message) {
                const rawMsg = originalError.message;
                const errors = Array.isArray(rawMsg) ? rawMsg : [rawMsg];
                newResponse.error = errors.map((msg: string) => ({
                  field_name:
                    typeof msg === 'string'
                      ? msg.split(' ')[0] || 'general'
                      : 'general',
                  message: msg,
                }));
              } else {
                console.log('Afroj');
                const match = errorMessage.match(/argument "([^"]+)"/);
                const field_name = match ? match[1] : 'general';
                newResponse.error = [
                  {
                    field_name,
                    message: errorMessage,
                  },
                ];
              }

              return originalSend.call(
                this,
                JSON.stringify(newResponse),
              ) as Response;
            } else if (parsed.data) {
              const keys = Object.keys(parsed.data);
              if (keys.length === 1) {
                const key = keys[0];
                const innerData = parsed.data[key];

                const newResponse: FormattedSuccessResponse = {
                  success: true,
                  message: 'Operation successful',
                  data: innerData,
                };
                return originalSend.call(
                  this,
                  JSON.stringify(newResponse),
                ) as Response;
              }
            }
          }
        } catch {
          // If parsing or rewriting fails, fall back to the original response
        }
      }
      return originalSend.call(this, body) as Response;
    };

    next();
  }
}
