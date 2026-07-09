import { Module } from '@nestjs/common';
import { MongooseModule } from '@nestjs/mongoose'; // 👈 Import MongooseModule
import { GraphQLModule } from '@nestjs/graphql';
import { ApolloDriver, ApolloDriverConfig } from '@nestjs/apollo';
import { join } from 'path';
import { AppController } from './app.controller';
import { AppService } from './app.service';
import { InventoryResolver } from './inventory/inventory.resolver';
import { InventoryItemDoc, InventoryItemSchema } from './inventory/schemas/inventory-item.schema';

@Module({
  imports: [
    // 👈 Connect to MongoDB using the URI from environment variables
    MongooseModule.forRoot(
      process.env.MONGODB_URI || 'mongodb://admin:admin123@localhost:27017/inventory_db?authSource=admin'
    ),
    // 👈 Register the schema for injection
    MongooseModule.forFeature([{ name: InventoryItemDoc.name, schema: InventoryItemSchema }]),
    GraphQLModule.forRoot<ApolloDriverConfig>({
      driver: ApolloDriver,
      //graphql-ws: true,
      path: '/api/inventory',
      autoSchemaFile: join(process.cwd(), 'src/schema.gql'),
      sortSchema: true,
      playground: true,
      // 👈 Custom formatError handler
      formatError: (error) => {
        const originalError = error.extensions?.originalError as any;
        const rawMessage = originalError?.message || error.message;
        const errors = Array.isArray(rawMessage) ? rawMessage : [rawMessage];
        const status = originalError?.statusCode || error.extensions?.status || 500;
        return {
          message: originalError?.error || error.message || 'Internal Server Error',
          success: false,
          status: status,
          errors: errors,
        };
      },
    }),
  ],
  controllers: [AppController],
  providers: [AppService, InventoryResolver],
})
export class AppModule { }
