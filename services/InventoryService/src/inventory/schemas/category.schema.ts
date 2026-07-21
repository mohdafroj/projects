import { Prop, Schema, SchemaFactory } from '@nestjs/mongoose';
import { Document } from 'mongoose';

export type CategoryDocument = CategoryDoc & Document;

@Schema({
    timestamps: true,
    collection: 'categories',
    autoIndex: true,
    versionKey: false,
}) // Adds createdAt and updatedAt fields automatically
export class CategoryDoc {
    @Prop({ required: true, unique: true })
    id: number;

    @Prop({ required: true, default: 0 })
    parent_id?: number;

    @Prop({ required: true })
    name: string;

    @Prop({ required: true, unique: true })
    slug: string;

    @Prop({ default: '' })
    title: string;

    @Prop({ default: '' })
    description: string;

    @Prop({ default: '' })
    meta_title: string;

    @Prop({ default: '' })
    meta_description: string;

    @Prop({ default: '' })
    meta_keywords: string;

    @Prop({ default: '' })
    image: string;

    @Prop({ default: true })
    status: boolean;

    @Prop({ default: false })
    soft_deleted?: boolean;

    @Prop({ default: 0 })
    sort: number;

    @Prop({ default: 0 })
    created_by?: number;

    @Prop({ default: 0 })
    updated_by?: number;

    @Prop({ default: 0 })
    deleted_by?: number;
}

export const CategorySchema = SchemaFactory.createForClass(CategoryDoc);
