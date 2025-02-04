import { IsInt, IsOptional, IsString, Length } from 'class-validator';

export class CreatePropertyDto {
  @IsInt()
  @IsOptional()
  id: number;

  @IsString()
  @Length(1, 10, { message: 'The name field is required.', groups: ['create'] })
  @Length(2, 10, { message: 'The name field is required.', groups: ['update'] })
  name: string;

  @IsString()
  @Length(5, 50, {
    message: 'The description field is required.',
    groups: ['create'],
  })
  description: string;

  @IsInt()
  area: number;
}
