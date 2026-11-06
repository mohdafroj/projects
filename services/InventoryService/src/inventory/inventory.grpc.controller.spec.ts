import { getModelToken } from '@nestjs/mongoose';
import { Test, TestingModule } from '@nestjs/testing';
import { status } from '@grpc/grpc-js';
import { CategoryDoc } from './schemas/category.schema';
import { ProductDoc } from './schemas/product.schema';
import { InventoryGrpcController } from './inventory.grpc.controller';

describe('InventoryGrpcController', () => {
  let controller: InventoryGrpcController;
  let categoryModel: { findOne: jest.Mock };
  let productModel: { findOne: jest.Mock };

  beforeEach(async () => {
    categoryModel = {
      findOne: jest.fn(),
    };
    productModel = {
      findOne: jest.fn(),
    };

    const module: TestingModule = await Test.createTestingModule({
      controllers: [InventoryGrpcController],
      providers: [
        {
          provide: getModelToken(CategoryDoc.name),
          useValue: categoryModel,
        },
        { provide: getModelToken(ProductDoc.name), useValue: productModel },
      ],
    }).compile();

    controller = module.get(InventoryGrpcController);
  });

  it('returns a category by id', async () => {
    categoryModel.findOne.mockReturnValue({
      exec: jest.fn().mockResolvedValue({
        id: 7,
        name: 'Books',
        slug: 'books',
        status: true,
      }),
    });

    await expect(controller.getCategory({ id: 7 })).resolves.toEqual({
      category: { id: 7, name: 'Books', slug: 'books', status: true },
    });
  });

  it('rejects invalid ids with INVALID_ARGUMENT', async () => {
    await expect(controller.getCategory({ id: 0 })).rejects.toMatchObject({
      error: {
        code: status.INVALID_ARGUMENT,
        message: 'Category id must be a positive integer',
      },
    });
  });

  it('returns NOT_FOUND when the category does not exist', async () => {
    categoryModel.findOne.mockReturnValue({
      exec: jest.fn().mockResolvedValue(null),
    });

    await expect(controller.getCategory({ id: 99 })).rejects.toMatchObject({
      error: {
        code: status.NOT_FOUND,
        message: 'Category 99 was not found',
      },
    });
  });

  it('returns a product by id', async () => {
    productModel.findOne.mockReturnValue({
      exec: jest.fn().mockResolvedValue({
        id: 'product-7',
        category_id: 3,
        name: 'Notebook',
        slug: 'notebook',
        description: 'Lined notebook',
        price: 4.5,
        quantity: 20,
        image: 'notebook.png',
        status: true,
        soft_deleted: false,
        sort: 1,
        created_by: 2,
        updated_by: 0,
        deleted_by: 0,
      }),
    });

    await expect(controller.getProduct({ id: 'product-7' })).resolves.toEqual({
      product: {
        id: 'product-7',
        categoryId: 3,
        name: 'Notebook',
        slug: 'notebook',
        description: 'Lined notebook',
        price: 4.5,
        quantity: 20,
        image: 'notebook.png',
        status: true,
        softDeleted: false,
        sort: 1,
        createdBy: 2,
        updatedBy: 0,
        deletedBy: 0,
      },
    });
    expect(productModel.findOne).toHaveBeenCalledWith({
      id: 'product-7',
      soft_deleted: false,
    });
  });

  it('rejects empty product ids with INVALID_ARGUMENT', async () => {
    await expect(controller.getProduct({ id: '  ' })).rejects.toMatchObject({
      error: {
        code: status.INVALID_ARGUMENT,
        message: 'Product id must not be empty',
      },
    });
  });

  it('returns NOT_FOUND when the product does not exist', async () => {
    productModel.findOne.mockReturnValue({
      exec: jest.fn().mockResolvedValue(null),
    });

    await expect(
      controller.getProduct({ id: 'missing' }),
    ).rejects.toMatchObject({
      error: {
        code: status.NOT_FOUND,
        message: 'Product missing was not found',
      },
    });
  });
});
