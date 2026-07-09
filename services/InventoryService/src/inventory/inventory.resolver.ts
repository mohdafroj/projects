import { Resolver, Query, Mutation, Args } from '@nestjs/graphql';
import { InjectModel } from '@nestjs/mongoose';
import { Model } from 'mongoose';
import { InventoryItem } from './models/inventory-item.model';
import { InventoryItemDoc, InventoryItemDocument } from './schemas/inventory-item.schema';

@Resolver(() => InventoryItem)
export class InventoryResolver {
    constructor(
        @InjectModel(InventoryItemDoc.name)
        private inventoryModel: Model<InventoryItemDocument>,
    ) { }

    @Query(() => [InventoryItem], { name: 'items' })
    async getItems(): Promise<InventoryItem[]> {
        return [{ id: '12', name: 'afroj', quantity: 1, description: 'test' }];
        //return this.inventoryModel.find().exec();
    }

    // Example mutation to create an item in MongoDB
    @Mutation(() => InventoryItem)
    async createItem(
        @Args('name') name: string,
        @Args('quantity') quantity: number,
        @Args('description', { nullable: true }) description?: string,
    ): Promise<InventoryItem> {
        const newItem = new this.inventoryModel({ name, quantity, description });
        return newItem.save();
    }
}
