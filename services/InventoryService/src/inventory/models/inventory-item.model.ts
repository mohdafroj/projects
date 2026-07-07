import { Field, ObjectType, ID, Int } from '@nestjs/graphql';

@ObjectType()
export class InventoryItem {
    @Field(() => ID)
    id: string;

    @Field()
    name: string;

    @Field({ nullable: true })
    description?: string;

    @Field(() => Int)
    quantity: number;
}
