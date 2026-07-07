import { Resolver, Query, Args } from '@nestjs/graphql';
import { InventoryItem } from './models/inventory-item.model';

@Resolver(() => InventoryItem)
export class InventoryResolver {
    // A simple query to fetch all inventory items
    @Query(() => [InventoryItem], { name: 'items' })
    async getItems(): Promise<InventoryItem[] | Object[]> {
        return [
            {
                id: '1',
                name: 'Developer Laptop',
                description: 'High-performance Macbook Pro',
                quantity: 15,
            },
            {
                id: '2',
                name: 'Ergonomic Chair',
                description: 'Office chair with lumbar support',
                quantity: 30,
            },
        ];
    }
}
