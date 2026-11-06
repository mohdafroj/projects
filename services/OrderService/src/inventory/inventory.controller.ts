import { Controller, Get, Param, ParseIntPipe } from '@nestjs/common';
import { InventoryGrpcClientService } from './inventory-grpc-client.service';

@Controller('inventory')
export class InventoryController {
  constructor(private readonly inventoryClient: InventoryGrpcClientService) {}

  @Get('categories/:id')
  getCategory(@Param('id', ParseIntPipe) id: number) {
    return this.inventoryClient.getCategory(id);
  }

  @Get('products/:id')
  getProduct(@Param('id') id: string) {
    return this.inventoryClient.getProduct(id);
  }
}
