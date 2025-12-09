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
import { CreatePermissionDto } from './dto/create-permission.dto';
import { PermissionsService } from './permissions.service';
import { UpdatePermissionDto } from './dto/update-permission.dto';

@Controller('permissions')
export class PermissionsController {
    constructor(private service: PermissionsService) { }
    @Get()
    async findAll() {
        const permissions = await this.service.findAll({
            where: { is_active: true },
        });
        return {
            permissions,
            message: permissions.length > 0 ? 'Permissions found.' : 'No permissions found.',
        };
    }

    @Get(':id')
    async findOne(@Param('id') id: number) {
        let permission = await this.service.findByUnique({ id });
        return { permission, message: permission ? 'Permission found' : 'Permission not found' };
    }

    @Post()
    @UsePipes(new ValidationPipe({ groups: ['create'] }))
    async create(@Body() body: CreatePermissionDto) {
        let permission = await this.service.create(body);
        return {
            permission,
            message: permission ? 'Permission created successfully.' : 'Permission creation failed.',
        };
    }

    @Put(':id')
    @UsePipes(new ValidationPipe({ groups: ['update'] }))
    async update(@Param('id') id: number, @Body() body: UpdatePermissionDto) {
        let find = await this.service.findByUnique({ id });
        let updatedData = null;
        if (find) {
            updatedData = await this.service.update({
                where: { id: Number(id) },
                data: body,
            });
        }
        return { permission: find && updatedData, message: updatedData ? 'Permission detail updated successfully' : 'Permission not found' };
    }

    @Delete(':id')
    async delete(@Param('id') id: number) {
        let permission = await this.service.findByUnique({ id });
        if (permission) {
            await this.service.removeByUnique({ id: Number(id) });
        }
        return { permission, message: permission ? 'Permission deleted successfully' : 'Permission not found' };
    }
}
