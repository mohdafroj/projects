import { Module } from '@nestjs/common';
import { RolesService } from './roles.service';
import { PermissionsService } from './permissions.service';
import { RolesController } from './roles.controller';
import { PermissionsController } from './permissions.controller';

@Module({
    controllers: [RolesController, PermissionsController],
    providers: [RolesService, PermissionsService],
})
export class AutorizationModule { }
