import { Injectable } from '@nestjs/common';

@Injectable()
export class AppService {
  getHello(): Object {
    return { node_env: process.env.NODE_ENV, id: 1, name: "Mohd Afroj", email: "mohd.afroj@gmail.com" }
  }
}
