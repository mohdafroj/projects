import { Injectable } from '@nestjs/common';

@Injectable()
export class AppService {
  getHello(): object {
    return { message: 'Welcome to the Inventory Service!!!' };
  }
}
