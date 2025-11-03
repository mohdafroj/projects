import { Module } from '@nestjs/common';
import { AppController } from './app.controller';
import { AppService } from './app.service';
import { PropertyModule } from './property/property.module';
import { DevtoolsModule } from '@nestjs/devtools-integration';
import { UsersModule } from './users/users.module';

@Module({
  imports: [
    PropertyModule,
    UsersModule,
    DevtoolsModule.register({ http: process.env.NODE_ENV != 'prouction' }),
  ],
  controllers: [AppController],
  providers: [AppService],
})
export class AppModule { }
