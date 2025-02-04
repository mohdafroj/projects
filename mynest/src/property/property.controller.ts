import {
  Body,
  Controller,
  Get,
  Param,
  ParseBoolPipe,
  ParseIntPipe,
  Post,
  Put,
  Query,
  UsePipes,
  ValidationPipe,
} from '@nestjs/common';
import { CreatePropertyDto } from './dto/create.property.dto';

@Controller('property')
export class PropertyController {
  @Get()
  findAll() {
    return 'Get all properties!';
  }

  @Get(':id')
  findOne(@Param('id', ParseIntPipe) id, @Query('sort', ParseBoolPipe) sort) {
    console.log(typeof id, typeof sort);
    return `Id: ${id} and Slug: ${sort}`;
  }

  @Post()
  @UsePipes(new ValidationPipe({ groups: ['create'] }))
  create(@Body() body: CreatePropertyDto) {
    return body;
  }

  @Put()
  @UsePipes(new ValidationPipe({ groups: ['update'] }))
  update(@Body() body: CreatePropertyDto) {
    return body;
  }
}
