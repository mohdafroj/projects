import {
  BadGatewayException,
  BadRequestException,
  GatewayTimeoutException,
  Injectable,
  NotFoundException,
  OnModuleInit,
  ServiceUnavailableException,
} from '@nestjs/common';
import { ClientGrpc } from '@nestjs/microservices';
import { ServiceError, status } from '@grpc/grpc-js';
import { Inject } from '@nestjs/common';
import { firstValueFrom, Observable, TimeoutError, timeout } from 'rxjs';

export const INVENTORY_GRPC_CLIENT = 'INVENTORY_GRPC_CLIENT';

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
    id: string;
    categoryId: number;
    name: string;
    slug: string;
    description: string;
    price: number;
    quantity: number;
    image: string;
    status: boolean;
    softDeleted: boolean;
    sort: number;
    createdBy: number;
    updatedBy: number;
    deletedBy: number;
  };
}

interface InventoryGrpcService {
  getCategory(
    request: GetCategoryRequest,
    metadata?: unknown,
  ): Observable<GetCategoryResponse>;
  getProduct(request: GetProductRequest): Observable<GetProductResponse>;
}

function isGrpcServiceError(error: unknown): error is ServiceError {
  return (
    error instanceof Error &&
    'code' in error &&
    typeof error.code === 'number' &&
    'details' in error &&
    typeof error.details === 'string'
  );
}

function throwMappedGrpcError(error: unknown): never {
  if (error instanceof TimeoutError) {
    throw new GatewayTimeoutException('Inventory service timed out');
  }

  if (!isGrpcServiceError(error)) {
    throw error;
  }

  switch (error.code) {
    case status.INVALID_ARGUMENT:
      throw new BadRequestException(error.details);
    case status.NOT_FOUND:
      throw new NotFoundException(error.details);
    case status.DEADLINE_EXCEEDED:
      throw new GatewayTimeoutException('Inventory service timed out');
    case status.UNAVAILABLE:
      throw new ServiceUnavailableException('Inventory service is unavailable');
    default:
      throw new BadGatewayException('Inventory service request failed');
  }
}

@Injectable()
export class InventoryGrpcClientService implements OnModuleInit {
  private inventoryService: InventoryGrpcService;

  constructor(
    @Inject(INVENTORY_GRPC_CLIENT)
    private readonly client: ClientGrpc,
  ) { }

  onModuleInit(): void {
    this.inventoryService =
      this.client.getService<InventoryGrpcService>('InventoryService');
  }

  async getCategory(id: number): Promise<GetCategoryResponse['category']> {
    try {
      const response = await firstValueFrom(
        this.inventoryService
          .getCategory({ id })
          .pipe(timeout({ first: 5000 })),
      );
      return response.category;
    } catch (error) {
      throwMappedGrpcError(error);
    }
  }

  async getProduct(id: string): Promise<Record<string, unknown>> {
    try {
      const response = await firstValueFrom(
        this.inventoryService.getProduct({ id }).pipe(timeout({ first: 5000 })),
      );
      return response.product;
    } catch (error) {
      throwMappedGrpcError(error);
    }
  }
}
