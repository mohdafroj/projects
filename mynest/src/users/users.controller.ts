import {
  Body,
  Controller,
  Delete,
  Get,
  Param,
  ParseIntPipe,
  Post,
  Put,
} from '@nestjs/common';
import { UsersService } from './users.service';
import { User } from '../generated/prisma/client';
import { CreateUserDto } from './dto/create-user.dto';

@Controller('users')
export class UsersController {
  constructor(private userService: UsersService) {}

  @Get()
  async findAll() {
    const users = await this.userService.findAll({
      where: { is_active: true },
    });
    return {
      users,
      message: users.length > 0 ? 'users found.' : 'No users found.',
    };
  }

  @Get(':id')
  async findOne(@Param('id') id: number): Promise<User> {
    let response = this.userService.findByUnique({ id });

    return response;
  }

  @Post()
  async createUser(
    @Body()
    userData: CreateUserDto,
  ) {
    let newUser = await this.userService.create(userData);
    return {
      user: newUser,
      message: newUser ? 'User created successfully.' : 'User creation failed.',
    };
  }

  @Put(':id')
  async updateUser(@Param('id') id: string): Promise<User> {
    return this.userService.update({
      where: { id: Number(id) },
      data: { is_active: true },
    });
  }

  @Delete(':id')
  async deleteUser(@Param('id') id: string): Promise<User> {
    return this.userService.removeByUnique({ id: Number(id) });
  }
}
