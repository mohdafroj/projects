import { Module } from '@nestjs/common';
import { MongooseModule } from '@nestjs/mongoose';
import { GraphQLModule } from '@nestjs/graphql';
import { ApolloDriver, ApolloDriverConfig } from '@nestjs/apollo';
import { join } from 'path';
import { AppController } from './app.controller';
import { AppService } from './app.service';
import { InventoryModule } from './inventory/inventory.module';

@Module({
  imports: [
    MongooseModule.forRoot(
      process.env.MONGODB_URI ||
      'mongodb://admin:admin123@localhost:27017/inventory_db?authSource=admin',
    ),
    GraphQLModule.forRoot<ApolloDriverConfig>({
      driver: ApolloDriver,
      //graphql-ws: true,
      path: '/api/inventory',
      autoSchemaFile: join(process.cwd(), 'src/schema.gql'),
      sortSchema: true,
      playground: true,
      // 👈 Custom formatError handler
      formatError: (error) => {
        const originalError = error.extensions?.originalError as
          | {
            message?: string | string[];
            statusCode?: number;
            error?: string;
          }
          | undefined;
        console.log(error)
        if (!originalError) {
          let errorDetails: unknown = null;
          if (error.extensions?.code === 'GRAPHQL_VALIDATION_FAILED') {
            const match = error.message.match(/argument "([^"]+)"/);
            const field_name = match ? match[1] : 'general';
            errorDetails = [
              {
                field_name,
                message: `The ${field_name} is required`,
              },
            ];
          } else {
            errorDetails = {
              message: error.message,
            };
          }
          return {
            success: false,
            message: error.message,
            error: errorDetails,
          };
        }
        const rawMessage = originalError.message || error.message;
        const errors = Array.isArray(rawMessage) ? rawMessage : [rawMessage];
        const errorDetails = errors.map((msg) => {
          const field =
            typeof msg === 'string'
              ? msg.split(' ')[0] || 'general'
              : 'general';
          return {
            field_name: field,
            message: msg,
          };
        });
        return {
          success: false,
          message:
            originalError.error || error.message || 'Internal Server Error',
          error: errorDetails,
        };
      },
    }),
    InventoryModule,
  ],
  controllers: [AppController],
  providers: [AppService],
})
export class AppModule { }
