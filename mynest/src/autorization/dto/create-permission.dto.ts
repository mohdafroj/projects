import { IsBoolean, IsInt, IsOptional, IsString, Length, MinLength } from 'class-validator';

export class CreatePermissionDto {
  @IsString()
  @MinLength(3, { message: 'The name field is required.', groups: ['create'] })
  name: string;

  @IsString()
  @MinLength(3, { message: 'The permission code field is required.', groups: ['create'] })
  permission_code: string;

  @IsBoolean({ message: 'The active field is required.', groups: ['create', 'update'] })
  is_active: boolean;
}
