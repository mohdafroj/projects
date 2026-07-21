import { NestFactory } from '@nestjs/core';
import { AppModule } from './app.module';
import { ValidationPipe } from '@nestjs/common'; // 👈 Import ValidationPipe
import { TransformInterceptor } from './common/interceptors/transform.interceptor'; // 👈 Import
import { HttpExceptionFilter } from './common/filters/http-exception.filter'; // 👈 Import

async function bootstrap() {
  const app = await NestFactory.create(AppModule);
  // 👈 Enable ValidationPipe globally
  app.useGlobalPipes(
    new ValidationPipe({
      transform: true,
      whitelist: true,
    }),
  );
  // 👈 Add these globally
  app.useGlobalInterceptors(new TransformInterceptor());
  app.useGlobalFilters(new HttpExceptionFilter());
  await app.listen(process.env.PORT ?? 3000);
}
bootstrap();
