import { Module } from '@nestjs/common';
import { MongooseModule } from '@nestjs/mongoose';

import { CategoryResolver } from './category.resolver';

import { CategoryDoc, CategorySchema } from './schemas/category.schema';

@Module({
  imports: [
    MongooseModule.forFeature([
      {
        name: CategoryDoc.name,
        schema: CategorySchema,
      },
    ]),
  ],
  providers: [CategoryResolver],
})
export class InventoryModule {}
