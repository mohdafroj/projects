import { NestFactory } from '@nestjs/core';
import { AppModule } from './app.module';
import { DocumentBuilder, SwaggerModule } from '@nestjs/swagger';
import { ValidationPipe, BadRequestException } from '@nestjs/common';
import { ResponseTransformInterceptor } from './lib/response.transform.interceptor';
import { AllExceptionsFilter } from './lib/filters/http-exception.filter';

async function bootstrap() {
  const app = await NestFactory.create(AppModule, {
    snapshot: true,
  });

  // Swagger config & document
  const config = new DocumentBuilder()
    .setTitle('Ecommerce')
    //.setDescription('Manage all ecommerce apis!')
    .setVersion('1.0')
    .addBearerAuth()
    .build();

  const document = SwaggerModule.createDocument(app, config);
  SwaggerModule.setup('api', app, document);

  // Global validation pipe — throw a proper HttpException with structured errors
  app.useGlobalPipes(
    new ValidationPipe({
      whitelist: true,
      forbidNonWhitelisted: true,
      transform: true,
      exceptionFactory: (validationErrors) => {
        const formattedErrors: Record<string, string[]> = {};

        validationErrors.forEach((err) => {
          if (err.constraints) {
            formattedErrors[err.property] = Object.values(err.constraints);
          } else if (err.children && err.children.length) {
            // basic handling for nested validations (optional)
            formattedErrors[err.property] = ['Invalid value'];
          }
        });

        // Throw a BadRequestException so the global filter treats this as an HTTP error
        throw new BadRequestException({
          message: 'Validation failed',
          errors: formattedErrors,
        });
      },
    }),
  );

  // Global interceptor + filter
  app.useGlobalInterceptors(new ResponseTransformInterceptor());
  app.useGlobalFilters(new AllExceptionsFilter());

  await app.listen(process.env.PORT ?? 3000);
}
bootstrap();
