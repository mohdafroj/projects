import {
  Body,
  Controller,
  Delete,
  Get,
  Param,
  Post,
  Put,
  UsePipes,
  ValidationPipe,
} from '@nestjs/common';
import { CreateRoleDto } from './dto/create-role.dto';
import { RolesService } from './roles.service';

@Controller('roles')
export class RolesController {
  constructor(private roleService: RolesService) { }
  @Get()
  async findAll() {
    const roles = await this.roleService.findAll({
      where: { is_active: true },
    });
    return {
      roles,
      message: roles.length > 0 ? 'Roles found.' : 'No roles found.',
    };
  }

  @Get(':id')
  async findOne(@Param('id') id: number) {
    let role = await this.roleService.findByUnique({ id });
    return { role, message: role ? 'Role found' : 'Role not found' };
  }

  @Post()
  @UsePipes(new ValidationPipe({ groups: ['create'] }))
  async create(@Body() body: CreateRoleDto) {
    let role = await this.roleService.create(body);
    return {
      role,
      message: role ? 'Role created successfully.' : 'Role creation failed.',
    };
  }

  @Put(':id')
  @UsePipes(new ValidationPipe({ groups: ['update'] }))
  async update(@Param('id') id: number, @Body() body: CreateRoleDto) {
    let find = await this.roleService.findByUnique({ id });
    let updatesUser = null;
    if (find) {
      updatesUser = await this.roleService.update({
        where: { id: Number(id) },
        data: body,
      });
    }
    return { user: find && updatesUser, message: updatesUser ? 'Role detail updated successfully' : 'Role not found' };
  }

  @Delete(':id')
  async delete(@Param('id') id: number) {
    let user = await this.roleService.findByUnique({ id });
    if (user) {
      await this.roleService.removeByUnique({ id: Number(id) });
    }
    return { user, message: user ? 'Role deleted successfully' : 'Role not found' };
  }
}
