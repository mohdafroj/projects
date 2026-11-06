import { Module } from '@nestjs/common';
import { MongooseModule } from '@nestjs/mongoose';

import { CategoryResolver } from './category.resolver';
import { InventoryGrpcController } from './inventory.grpc.controller';

import { CategoryDoc, CategorySchema } from './schemas/category.schema';
import { ProductDoc, ProductSchema } from './schemas/product.schema';

@Module({
  imports: [
    MongooseModule.forFeature([
      {
        name: CategoryDoc.name,
        schema: CategorySchema,
      },
      {
        name: ProductDoc.name,
        schema: ProductSchema,
      },
    ]),
  ],
  controllers: [InventoryGrpcController],
  providers: [CategoryResolver],
})
export class InventoryModule {}
