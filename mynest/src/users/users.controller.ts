import { Body, Controller, Delete, Get, Param, ParseIntPipe, Post, Put } from '@nestjs/common';
import { UsersService } from './users.service';
import { User } from '../generated/prisma/client';

@Controller('users')
export class UsersController {
  constructor(private userService: UsersService) { }

  @Get()
  async findAll(): Promise<User[]> {
    return this.userService.findAll({
      where: { is_active: true }
    });
  }

  @Get(':id')
  async findOne(@Param('id') id: number): Promise<User> {
    let response = this.userService.findByUnique({ id });

    return response;
  }

  @Post()
  async createUser(
    @Body() userData: { name: string; email: string; password: string, mobile: string },
  ): Promise<User> {
    const { name, email, password, mobile } = userData;

    return this.userService.create(userData);
  }

  @Put(':id')
  async updateUser(@Param('id') id: string): Promise<User> {
    return this.userService.update({
      where: { id: Number(id) },
      data: { is_active: true }
    });
  }

  @Delete(':id')
  async deleteUser(@Param('id') id: string): Promise<User> {
    return this.userService.removeByUnique({ id: Number(id) });
  }
}
