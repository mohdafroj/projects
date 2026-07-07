import { Module } from '@nestjs/common';
import { GraphQLModule } from '@nestjs/graphql';
import { ApolloDriver, ApolloDriverConfig } from '@nestjs/apollo';
import { join } from 'path';
import { AppController } from './app.controller';
import { AppService } from './app.service';
import { InventoryResolver } from './inventory/inventory.resolver';

@Module({
  imports: [
    GraphQLModule.forRoot<ApolloDriverConfig>({
      driver: ApolloDriver,
      autoSchemaFile: join(process.cwd(), 'src/schema.gql'),
      sortSchema: true,
      playground: true,
      // introspection: true,
      // formatError: (error) => {
      //   return {
      //     message: error.extensions?.message || error.message,
      //     code: error.extensions?.code,
      //     status: error.extensions?.status,
      //   };
      // },
    }),
  ],
  controllers: [AppController],
  providers: [AppService, InventoryResolver],
})
export class AppModule { }
