import {
  Body,
  Controller,
  Delete,
  Get,
  Param,
  ParseIntPipe,
  Patch,
  Post,
  Put,
} from '@nestjs/common';
import { UsersService } from './users.service';
import { CreateUserDto } from './dto/create-user.dto';
import { UpdateUserDto } from './dto/update-user.dto';

@Controller('users')
export class UsersController {
  constructor(private userService: UsersService) { }

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
  async findOne(@Param('id') id: number) {
    let user = await this.userService.findByUnique({ id });
    return { user, message: user ? 'User detail' : 'User not found' };
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
  async updateUser(@Param('id') id: number, @Body() userData: UpdateUserDto) {
    let user = await this.userService.findByUnique({ id });
    let updatesUser = null;
    if (user) {
      updatesUser = await this.userService.update({
        where: { id: Number(id) },
        data: userData,
      });
    }
    return { user: user && updatesUser, message: updatesUser ? 'User detail updated successfully' : 'User not found' };
  }

  @Delete(':id')
  async deleteUser(@Param('id') id: number) {
    let user = await this.userService.findByUnique({ id });
    if (user) {
      await this.userService.removeByUnique({ id: Number(id) });
    }
    return { user, message: user ? 'User deleted successfully' : 'User not found' };
  }

  @Patch(':id')
  async patchUser(
    @Param('id', ParseIntPipe) id: number,
    @Body() userData: UpdateUserDto
  ) {
    const user = await this.userService.findByUnique({ id });

    if (!user) {
      return {
        message: 'User not found',
      };
    }

    const updatedUser = await this.userService.update({
      where: { id },
      data: userData,
    });

    return {
      success: true,
      message: 'User updated successfully',
      data: updatedUser,
    };
  }


}
