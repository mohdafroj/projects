import { IsBoolean, IsInt, IsOptional, IsString, Length, MinLength } from 'class-validator';

export class CreateModuleDto {
  @IsString()
  @MinLength(3, { message: 'The name field is required.', groups: ['create'] })
  name: string;

  @IsString()
  @MinLength(3, { message: 'The code field is required.', groups: ['create'] })
  module_code: string;

  @IsBoolean({ message: 'The active field is required.', groups: ['create'] })
  is_active: boolean;
}
