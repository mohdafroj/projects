import { Controller } from '@nestjs/common';
import { GrpcMethod, RpcException } from '@nestjs/microservices';
import { status } from '@grpc/grpc-js';
import { InjectModel } from '@nestjs/mongoose';
import { Model } from 'mongoose';
import { CategoryDocument, CategoryDoc } from './schemas/category.schema';
import { ProductDocument, ProductDoc } from './schemas/product.schema';

interface GetCategoryRequest {
  id: number;
}

interface GetCategoryResponse {
  category: {
    id: number;
    name: string;
    slug: string;
    status: boolean;
  };
}

interface GetProductRequest {
  id: string;
}

interface GetProductResponse {
  product: {
    id: number;
    category_id: number;
    name: string;
    slug: string;
    description: string;
    price: number;
    quantity: number;
    image: string;
    status: boolean;
    soft_deleted: boolean;
    sort: number;
    created_by: number;
    updated_by: number;
    deleted_by: number;
  };
}

@Controller()
export class InventoryGrpcController {
  constructor(
    @InjectModel(CategoryDoc.name)
    private readonly categoryModel: Model<CategoryDocument>,
    @InjectModel(ProductDoc.name)
    private readonly productModel: Model<ProductDocument>,
  ) { }

  @GrpcMethod('InventoryService', 'GetCategory')
  async getCategory(request: GetCategoryRequest): Promise<GetCategoryResponse> {
    if (!Number.isInteger(request.id) || request.id < 1) {
      throw new RpcException({
        code: status.INVALID_ARGUMENT,
        message: 'Category id must be a positive integer',
      });
    }

    const category = await this.categoryModel
      .findOne({ id: request.id })
      .exec();

    if (!category) {
      throw new RpcException({
        code: status.NOT_FOUND,
        message: `Category ${request.id} was not found`,
      });
    }

    return {
      category: {
        id: category.id,
        name: category.name,
        slug: category.slug,
        status: category.status,
      },
    };
  }

  @GrpcMethod('InventoryService', 'GetProduct')
  async getProduct(request: GetProductRequest): Promise<GetProductResponse> {
    const id = request.id?.trim();
    if (!id) {
      throw new RpcException({
        code: status.INVALID_ARGUMENT,
        message: 'Product id must not be empty',
      });
    }

    const product = await this.productModel
      .findOne({ id: +id, softDeleted: false })
      .exec();

    if (!product) {
      throw new RpcException({
        code: status.NOT_FOUND,
        message: `Product ${id} was not found`,
      });
    }
    console.log('product', product);
    return {
      product: {
        id: product.id,
        category_id: product?.category_id || 0,
        name: product.name,
        slug: product.slug,
        description: product.description,
        price: product?.price || 0,
        quantity: product?.quantity || 0,
        image: product?.image || '',
        status: product.status,
        soft_deleted: product?.soft_deleted || true,
        sort: product?.sort || 0,
        created_by: product?.created_by || 0,
        updated_by: product?.updated_by || 0,
        deleted_by: product?.deleted_by || 0,
      },
    };
  }
}
