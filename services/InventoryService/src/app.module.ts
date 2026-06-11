import { Module } from '@nestjs/common';
import { AppController } from './app.controller';
import { AppService } from './app.service';
import { AutorizationModule } from './autorization/autorization.module';
import { DevtoolsModule } from '@nestjs/devtools-integration';
import { UsersModule } from './users/users.module';
import { PrismaModule } from './prisma/prisma.module';
//import { TypeOrmModule } from '@nestjs/typeorm';
import { AuthModule } from './auth/auth.module';
import { EventsModule } from './events/events.module';

@Module({
  imports: [
    AutorizationModule,
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
    AuthModule,
    EventsModule
  ],
  controllers: [AppController],
  providers: [AppService],
})
export class AppModule { }
