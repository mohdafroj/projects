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
import { CreateModuleDto } from './dto/create-module.dto';
import { ModulesService } from './modules.service';
import { UpdateModuleDto } from './dto/update-module.dto';

@Controller('modules')
export class ModulesController {
    constructor(private service: ModulesService) { }
    @Get()
    async findAll() {
        const modules = await this.service.findAll({
            //where: { is_active: true },
        });
        return {
            modules,
            message: modules.length > 0 ? 'Modules found.' : 'No modules found.',
        };
    }

    @Get(':id')
    async findOne(@Param('id') id: number) {
        let module = await this.service.findByUnique({ id });
        return { module, message: module ? 'Module found' : 'Module not found' };
    }

    @Post()
    @UsePipes(new ValidationPipe({ groups: ['create'] }))
    async create(@Body() body: CreateModuleDto) {
        let module = await this.service.create(body);
        return {
            module,
            message: module ? 'Module created successfully.' : 'Module creation failed.',
        };
    }

    @Put(':id')
    @UsePipes(new ValidationPipe({ groups: ['update'] }))
    async update(@Param('id') id: number, @Body() body: UpdateModuleDto) {
        let find = await this.service.findByUnique({ id });
        let updatedData = null;
        if (find) {
            updatedData = await this.service.update({
                where: { id: Number(id) },
                data: body,
            });
        }
        return { module: find && updatedData, message: updatedData ? 'Module detail updated successfully' : 'Module not found' };
    }

    @Delete(':id')
    async delete(@Param('id') id: number) {
        let module = await this.service.findByUnique({ id });
        if (module) {
            await this.service.removeByUnique({ id: Number(id) });
        }
        return { module, message: module ? 'Module deleted successfully' : 'Module not found' };
    }
}
