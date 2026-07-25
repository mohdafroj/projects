import { ApolloServerPlugin, GraphQLRequestListener } from '@apollo/server';
import { Plugin } from '@nestjs/apollo';
import { Injectable } from '@nestjs/common';

interface FormattedGraphQLResult {
  success?: boolean;
  message?: string;
  data?: unknown;
  error?: Array<{ field_name: string; message: string }>;
  errors?: unknown;
}

@Plugin()
@Injectable()
export class GraphQLResponsePlugin implements ApolloServerPlugin {
  requestDidStart(): Promise<GraphQLRequestListener<any>> {
    return Promise.resolve({
      willSendResponse: async (requestContext) => {
        await Promise.resolve(); // satisfy require-await
        const { response } = requestContext;
        if (response.body.kind === 'single') {
          const result = response.body.singleResult as FormattedGraphQLResult;

          if (result.errors && Array.isArray(result.errors)) {
            const firstError = result.errors[0] as {
              message?: string;
              extensions?: {
                originalError?: {
                  message?: string | string[];
                  statusCode?: number;
                  error?: string;
                };
              };
            };

            const originalError = firstError.extensions?.originalError;

            result.success = false;
            result.message = firstError.message || 'Internal Server Error';
            result.data = null;

            if (originalError?.message) {
              const rawMsg = originalError.message;
              const errors = Array.isArray(rawMsg) ? rawMsg : [rawMsg];
              result.error = errors.map((msg: string) => ({
                field_name:
                  typeof msg === 'string'
                    ? msg.split(' ')[0] || 'general'
                    : 'general',
                message: msg,
              }));
            } else {
              const errorMessage = firstError.message || '';
              const match = errorMessage.match(/argument "([^"]+)"/);
              const field_name = match ? match[1] : 'general';
              result.error = [
                {
                  field_name,
                  message: errorMessage,
                },
              ];
            }

            // Remove standard errors array to match user's custom formatting
            delete result.errors;
          } else if (result.data && typeof result.data === 'object') {
            // Hoist successful mutation/query output to match REST shape
            const dataObj = result.data as Record<string, unknown>;
            const keys = Object.keys(dataObj);
            if (keys.length === 1) {
              const key = keys[0];
              const innerData = dataObj[key];

              result.success = true;
              result.message = 'Operation successful';
              result.data = innerData;
            }
          }
        }
      },
    });
  }
}
