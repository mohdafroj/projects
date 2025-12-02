import { Module } from '@nestjs/common';
import { AppController } from './app.controller';
import { AppService } from './app.service';
import { PropertyModule } from './property/property.module';
import { DevtoolsModule } from '@nestjs/devtools-integration';
import { UsersModule } from './users/users.module';
//import { TypeOrmModule } from '@nestjs/typeorm';
import { PrismaModule } from './prisma/prisma.module';

@Module({
  imports: [
    PropertyModule,
    UsersModule,
    // TypeOrmModule.forRoot({
    //   type: 'postgres',
    //   host: process.env.POSTGRES_DB_HOST || 'db',
    //   port: +process.env.POSTGRES_DB_PORT || 5432,
    //   username: process.env.POSTGRES_DB_USER || 'postgres',
    //   password: process.env.POSTGRES_DB_PASSWORD || 'postgres',
    //   database: process.env.POSTGRES_DB_NAME || 'postgres',
    //   entities: [],
    //   synchronize: process.env.NODE_ENV != 'prouction',
    //   autoLoadEntities: true,
    // }),
    DevtoolsModule.register({ http: process.env.NODE_ENV != 'prouction' }),
    PrismaModule,
  ],
  controllers: [AppController],
  providers: [AppService],
})
export class AppModule { }
