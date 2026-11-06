import { Module } from '@nestjs/common';
import { ClientsModule, Transport } from '@nestjs/microservices';
import { join } from 'path';
import {
  INVENTORY_GRPC_CLIENT,
  InventoryGrpcClientService,
} from './inventory-grpc-client.service';
import { InventoryController } from './inventory.controller';

@Module({
  imports: [
    ClientsModule.register([
      {
        name: INVENTORY_GRPC_CLIENT,
        transport: Transport.GRPC,
        options: {
          package: 'inventory.v1',
          protoPath: join(
            process.cwd(),
            '../contracts/proto/inventory/v1/inventory.proto',
          ),
          url: process.env.INVENTORY_GRPC_URL ?? 'localhost:50051',
        },
      },
    ]),
  ],
  controllers: [InventoryController],
  providers: [InventoryGrpcClientService],
  exports: [InventoryGrpcClientService],
})
export class InventoryGrpcClientModule { }
