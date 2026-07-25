import { NestFactory } from '@nestjs/core';
import { ValidationPipe } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { AppModule } from './app.module';

async function bootstrap() {
  const app = await NestFactory.create(AppModule);
  
  // Set global routing prefix
  app.setGlobalPrefix('api/v1');

  // Enable global request validation pipes
  app.useGlobalPipes(
    new ValidationPipe({
      whitelist: true,
      transform: true,
    }),
  );

  const configService = app.get(ConfigService);
  const port = configService.get<number>('port', 3000);

  await app.listen(port);
  console.log(`OcrService is running on port ${port} with prefix /api/v1`);
}
bootstrap();
