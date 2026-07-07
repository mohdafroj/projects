import { Module } from '@nestjs/common';
import { RolesService } from './roles.service';
import { PermissionsService } from './permissions.service';
import { RolesController } from './roles.controller';
import { PermissionsController } from './permissions.controller';
import { ModulesController } from './modules.controller';
import { ModulesService } from './modules.service';

@Module({
    controllers: [RolesController, PermissionsController, ModulesController],
    providers: [RolesService, PermissionsService, ModulesService],
})
export class AutorizationModule { }
