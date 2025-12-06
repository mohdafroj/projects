import {
  IsEmail,
  IsNotEmpty,
  IsString,
  MinLength,
  IsMobilePhone,
} from 'class-validator';

export class CreateUserDto {
  @IsString()
  @IsNotEmpty({ message: 'Name is required.' })
  name: string;

  @IsEmail({}, { message: 'Email must be valid.' })
  email: string;

  @IsString()
  @MinLength(6, { message: 'Password must be at least 6 characters.' })
  password: string;

  @IsMobilePhone()
  mobile: string;
}
