import { GatewayTimeoutException, NotFoundException } from '@nestjs/common';
import { ClientGrpc } from '@nestjs/microservices';
import { Test, TestingModule } from '@nestjs/testing';
import { status } from '@grpc/grpc-js';
import { Observable, of, TimeoutError, throwError } from 'rxjs';
import {
  INVENTORY_GRPC_CLIENT,
  InventoryGrpcClientService,
} from './inventory-grpc-client.service';

describe('InventoryGrpcClientService', () => {
  let service: InventoryGrpcClientService;
  let getProduct: jest.Mock<
    Observable<{ product: Record<string, unknown> }>,
    [unknown]
  >;
  let getCategory: jest.Mock<
    Observable<{
      category: { id: number; name: string; slug: string; status: boolean };
    }>,
    [unknown]
  >;

  beforeEach(async () => {
    getCategory = jest.fn();
    getProduct = jest.fn();
    const client = {
      getService: jest.fn().mockReturnValue({ getCategory, getProduct }),
    };
    const module: TestingModule = await Test.createTestingModule({
      providers: [
        InventoryGrpcClientService,
        { provide: INVENTORY_GRPC_CLIENT, useValue: client },
      ],
    }).compile();

    service = module.get(InventoryGrpcClientService);
    await module.init();
  });

  it('calls InventoryService with the requested category id', async () => {
    const category = { id: 7, name: 'Books', slug: 'books', status: true };
    getCategory.mockReturnValue(of({ category }));

    await expect(service.getCategory(7)).resolves.toEqual(category);
    expect(getCategory).toHaveBeenCalledWith({ id: 7 });
  });

  it('maps gRPC NOT_FOUND to HTTP 404', async () => {
    getCategory.mockReturnValue(
      throwError(() =>
        Object.assign(new Error('Category 99 was not found'), {
          code: status.NOT_FOUND,
          details: 'Category 99 was not found',
        }),
      ),
    );

    await expect(service.getCategory(99)).rejects.toBeInstanceOf(
      NotFoundException,
    );
  });

  it('maps gRPC deadlines to HTTP 504', async () => {
    getCategory.mockReturnValue(
      throwError(() =>
        Object.assign(new Error('Deadline exceeded'), {
          code: status.DEADLINE_EXCEEDED,
          details: 'Deadline exceeded',
        }),
      ),
    );

    await expect(service.getCategory(7)).rejects.toBeInstanceOf(
      GatewayTimeoutException,
    );
  });

  it('maps the local RPC timeout to HTTP 504', async () => {
    getCategory.mockReturnValue(throwError(() => new TimeoutError()));

    await expect(service.getCategory(7)).rejects.toBeInstanceOf(
      GatewayTimeoutException,
    );
  });

  it('fetches a product by id and returns its document fields', async () => {
    getProduct.mockReturnValue(
      of({
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
      }),
    );

    await expect(service.getProduct('product-7')).resolves.toEqual({
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
    });
    expect(getProduct).toHaveBeenCalledWith({ id: 'product-7' });
  });

  it('maps a missing product to HTTP 404', async () => {
    getProduct.mockReturnValue(
      throwError(() =>
        Object.assign(new Error('Product missing was not found'), {
          code: status.NOT_FOUND,
          details: 'Product missing was not found',
        }),
      ),
    );

    await expect(service.getProduct('missing')).rejects.toBeInstanceOf(
      NotFoundException,
    );
  });
});
