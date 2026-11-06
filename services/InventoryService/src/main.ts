import { NestFactory } from '@nestjs/core';
import { AppModule } from './app.module';
import { ValidationPipe } from '@nestjs/common'; // 👈 Import ValidationPipe
import { TransformInterceptor } from './common/interceptors/transform.interceptor'; // 👈 Import
import { HttpExceptionFilter } from './common/filters/http-exception.filter'; // 👈 Import
import { MicroserviceOptions, Transport } from '@nestjs/microservices';
import { join } from 'path';

async function bootstrap() {
  const app = await NestFactory.create(AppModule);
  try {
    app.connectMicroservice<MicroserviceOptions>({
      transport: Transport.GRPC,
      options: {
        package: 'inventory.v1',
        protoPath: join(
          process.cwd(),
          '../contracts/proto/inventory/v1/inventory.proto',
        ),
        url: process.env.INVENTORY_GRPC_BIND_ADDRESS ?? '0.0.0.0:50051',
      },
    });

    app.useGlobalPipes(
      new ValidationPipe({
        transform: true,
        whitelist: true,
      }),
    );
    app.useGlobalInterceptors(new TransformInterceptor());
    app.useGlobalFilters(new HttpExceptionFilter());
    await app.startAllMicroservices();
    await app.listen(process.env.PORT ?? 3000);
  } catch (error) {
    await app.close();
    throw error;
  }
}

void bootstrap().catch((error: unknown) => {
  console.error('Failed to start InventoryService', error);
  process.exitCode = 1;
});
