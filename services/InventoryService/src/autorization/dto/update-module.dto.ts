import { IsBoolean, IsString, MinLength } from 'class-validator';

export class UpdateModuleDto {
  @IsString()
  @MinLength(3, { message: 'The name field is required.', groups: ['update'] })
  name: string;

  @IsBoolean({ message: 'The active field is required.', groups: ['update'] })
  is_active: boolean;
}
