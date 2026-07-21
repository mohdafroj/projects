import { Resolver, Query, Mutation, Args, Int } from '@nestjs/graphql';
import { InjectModel } from '@nestjs/mongoose';
import { Model } from 'mongoose';
import {
    Category,
    CategoryResponse,
    FieldError,
} from './models/category.model';
import { CategoryDoc, CategoryDocument } from './schemas/category.schema';

@Resolver(() => Category)
export class CategoryResolver {
    constructor(
        @InjectModel(CategoryDoc.name)
        private categoryModel: Model<CategoryDocument>,
    ) { }

    @Query(() => [Category], { name: 'categories' })
    async getCategories(): Promise<Category[]> {
        return this.categoryModel.find().exec();
    }

    @Mutation(() => CategoryResponse)
    async createCategory(
        @Args('name') name: string,
        @Args('created_by', { type: () => Int }) created_by: number,
        @Args('parent_id', { type: () => Int, nullable: true }) parent_id?: number,
        @Args('title', { nullable: true }) title?: string,
        @Args('description', { nullable: true }) description?: string,
        @Args('meta_title', { nullable: true }) meta_title?: string,
        @Args('meta_description', { nullable: true }) meta_description?: string,
        @Args('meta_keywords', { nullable: true }) meta_keywords?: string,
        @Args('image', { nullable: true }) image?: string,
        @Args('status', { nullable: true }) status?: boolean,
        @Args('sort', { type: () => Int, nullable: true }) sort?: number,
    ) {
        try {
            const slug = name
                .toLowerCase()
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-|-$/g, '');

            // Generate next incremental id
            const lastCategory = await this.categoryModel
                .findOne()
                .sort({ id: -1 })
                .exec();
            const nextId = lastCategory ? lastCategory.id + 1 : 1;

            const newItem = new this.categoryModel({
                id: nextId,
                name,
                parent_id: parent_id ?? 0,
                slug,
                title: title ?? '',
                description: description ?? '',
                meta_title: meta_title ?? '',
                meta_description: meta_description ?? '',
                meta_keywords: meta_keywords ?? '',
                image: image ?? '',
                status: status ?? true,
                sort: sort ?? 0,
                created_by,
                updated_by: 0,
                deleted_by: 0,
            });

            const result = await newItem.save();
            return {
                success: true,
                message: 'Category created successfully',
                data: result,
            };
        } catch (err: unknown) {
            const errorsList: FieldError[] = [];
            const mongoError = err as {
                code?: number;
                keyPattern?: Record<string, number>;
                errors?: Record<string, { message?: string }>;
                message?: string;
            };
            console.log(mongoError)
            if (mongoError.code === 11000) {
                const field = Object.keys(mongoError.keyPattern || {})[0] || 'slug';
                errorsList.push({
                    field_name: field,
                    message: `The ${field} must be unique`,
                });
            } else if (mongoError.errors) {
                for (const field of Object.keys(mongoError.errors)) {
                    errorsList.push({
                        field_name: field,
                        message:
                            mongoError.errors[field]?.message || `The ${field} is invalid`,
                    });
                }
            } else {
                errorsList.push({
                    field_name: 'general',
                    message: mongoError.message || 'An unknown error occurred',
                });
            }
            return {
                success: false,
                message: 'Validation failed',
                error: errorsList,
            };
        }
    }
}
