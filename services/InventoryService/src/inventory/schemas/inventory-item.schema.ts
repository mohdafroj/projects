import { Prop, Schema, SchemaFactory } from '@nestjs/mongoose';
import { Document } from 'mongoose';

export type InventoryItemDocument = InventoryItemDoc & Document;

@Schema({ timestamps: true }) // Adds createdAt and updatedAt fields automatically
export class InventoryItemDoc {
    @Prop({ required: true })
    name: string;

    @Prop()
    description?: string;

    @Prop({ required: true, default: 0 })
    quantity: number;
}

export const InventoryItemSchema = SchemaFactory.createForClass(InventoryItemDoc);
