import { Controller, Get, Param, ParseIntPipe } from '@nestjs/common';
import { UsersService } from './users.service';
import { User } from '../generated/prisma/client';

@Controller('users')
export class UsersController {
  constructor(private userService: UsersService) { }

  @Get()
  async findAll(): Promise<User[]> {
    return this.userService.findAll();
  }

  @Get(':id')
  findOne(@Param('id', ParseIntPipe) id) {
    return this.userService.findByUnique(id);
  }
}
