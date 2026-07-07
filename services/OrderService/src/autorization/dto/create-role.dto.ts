import { IsBoolean, IsInt, IsOptional, IsString, Length, MinLength } from 'class-validator';

export class CreateRoleDto {
  @IsString()
  @MinLength(3, { message: 'The title field is required.', groups: ['create', 'update'] })
  title: string;

  @IsString()
  @MinLength(3, { message: 'The name field is required.', groups: ['create'] })
  name: string;

  @IsString()
  @MinLength(10, { message: 'The description field is required.', groups: ['create', 'update'] })
  description: string;

  @IsBoolean({ message: 'The active field is required.', groups: ['create', 'update'] })
  is_active: boolean;
}
