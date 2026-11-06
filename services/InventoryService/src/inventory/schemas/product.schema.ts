import { Prop, Schema, SchemaFactory } from '@nestjs/mongoose';
import { Document } from 'mongoose';

export type ProductDocument = ProductDoc & Document;

@Schema({
  timestamps: true,
  collection: 'products',
  autoIndex: true,
  versionKey: false,
})
export class ProductDoc {
  @Prop({ required: true, unique: true, trim: true })
  id: number;

  @Prop({ required: true })
  category_id: number;

  @Prop({ required: true, trim: true })
  name: string;

  @Prop({ required: true, unique: true, trim: true, lowercase: true })
  slug: string;

  @Prop({ default: '' })
  description: string;

  @Prop({ required: true, min: 0 })
  price: number;

  @Prop({ default: 0, min: 0 })
  quantity: number;

  @Prop({ default: '' })
  image: string;

  @Prop({ default: true })
  status: boolean;

  @Prop({ default: false })
  soft_deleted: boolean;

  @Prop({ default: 0 })
  sort: number;

  @Prop({ default: 0 })
  created_by: number;

  @Prop({ default: 0 })
  updated_by: number;

  @Prop({ default: 0 })
  deleted_by: number;
}

export const ProductSchema = SchemaFactory.createForClass(ProductDoc);
