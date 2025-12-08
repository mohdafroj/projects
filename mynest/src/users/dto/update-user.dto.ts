import {
  IsEmail,
  IsNotEmpty,
  IsString,
  MinLength,
  IsMobilePhone,
  IsOptional,
} from 'class-validator';

export class UpdateUserDto {
  @IsString()
  @IsNotEmpty({ message: 'Name is required.' })
  @IsOptional()
  name: string;

  @IsEmail({}, { message: 'Email must be valid.' })
  @IsOptional()
  email: string;

  @IsString()
  @IsOptional()
  @MinLength(6, { message: 'Password must be at least 6 characters.' })
  password: string;

  @IsMobilePhone('en-IN')
  @IsOptional()
  mobile: string;
}
