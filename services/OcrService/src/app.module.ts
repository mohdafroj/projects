import { Module } from '@nestjs/common';
import { ConfigModule, ConfigService } from '@nestjs/config';
import { MongooseModule } from '@nestjs/mongoose';
import { BullModule } from '@nestjs/bullmq';
import { AppController } from './app.controller';
import { AppService } from './app.service';
import { OcrModule } from './ocr/ocr.module';
import configuration from './config/configuration';

@Module({
  imports: [
    ConfigModule.forRoot({
      isGlobal: true,
      load: [configuration],
    }),
    MongooseModule.forRootAsync({
      imports: [ConfigModule],
      useFactory: async (configService: ConfigService) => ({
        uri: configService.get<string>('mongodbUri'),
      }),
      inject: [ConfigService],
    }),
    BullModule.forRootAsync({
      imports: [ConfigModule],
      useFactory: async (configService: ConfigService) => {
        const redisUrl = configService.get<string>('redisUrl', 'redis://localhost:6379/0');
        try {
          const url = new URL(redisUrl);
          const host = url.hostname || 'localhost';
          const port = parseInt(url.port || '6379', 10);
          const password = url.password ? decodeURIComponent(url.password) : undefined;
          const db = parseInt(url.pathname.substring(1) || '0', 10);
          
          return {
            connection: {
              host,
              port,
              password,
              db,
            },
          };
        } catch (e) {
          // Fallback if URL parsing fails (e.g. host:port format)
          return {
            connection: {
              host: 'localhost',
              port: 6379,
            },
          };
        }
      },
      inject: [ConfigService],
    }),
    OcrModule,
  ],
  controllers: [AppController],
  providers: [AppService],
})
export class AppModule {}
