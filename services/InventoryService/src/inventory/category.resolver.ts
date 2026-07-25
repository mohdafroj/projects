import { Resolver, Query, Mutation, Args, Int } from '@nestjs/graphql';
import { InjectModel } from '@nestjs/mongoose';
import { Model } from 'mongoose';
import { ConflictException, HttpException, HttpStatus } from '@nestjs/common';
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

    @Query(() => Category, { name: 'category' })
    async getCategory(
        @Args('id', { type: () => Int }) id: number,
    ): Promise<Category> {
        const category = await this.categoryModel.findOne({ id }).exec();
        if (!category) {
            throw new ConflictException(`Category with ID ${id} not found`);
        }
        return category;
    }


    @Mutation(() => Category)
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
        //try {
        const slug = name
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-|-$/g, '');
        const category = await this.categoryModel.findOne({ slug }).exec();
        if (category) {
            throw new Error(`Category with name ${name} is already exist`);
        }
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
        return result;
        //}
    }
}
