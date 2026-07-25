import { Controller, Post, Get, Param, Query, UseInterceptors, UploadedFile, BadRequestException } from '@nestjs/common';
import { FileInterceptor } from '@nestjs/platform-express';
import { diskStorage } from 'multer';
import { extname } from 'path';
import * as fs from 'fs';
import { OcrService } from './ocr.service';

@Controller('ocr')
export class OcrController {
  constructor(private readonly ocrService: OcrService) {
    // Ensure uploads directory exists
    if (!fs.existsSync('uploads')) {
      fs.mkdirSync('uploads', { recursive: true });
    }
  }

  @Post('upload')
  @UseInterceptors(
    FileInterceptor('file', {
      storage: diskStorage({
        destination: './uploads',
        filename: (req, file, cb) => {
          const uniqueSuffix = Date.now() + '-' + Math.round(Math.random() * 1e9);
          cb(null, `${file.fieldname}-${uniqueSuffix}${extname(file.originalname)}`);
        },
      }),
      fileFilter: (req, file, cb) => {
        const allowedMimeTypes = [
          'application/pdf',
          'image/png',
          'image/jpeg',
          'image/jpg',
          'image/tiff',
          'image/webp',
        ];
        if (allowedMimeTypes.includes(file.mimetype)) {
          cb(null, true);
        } else {
          cb(new BadRequestException('Invalid file type. Only PDF and image files are allowed.'), false);
        }
      },
      limits: {
        fileSize: 50 * 1024 * 1024, // 50 MB
      },
    }),
  )
  async uploadFile(@UploadedFile() file: Express.Multer.File) {
    if (!file) {
      throw new BadRequestException('File is required.');
    }
    const document = await this.ocrService.handleUpload(file);
    return {
      success: true,
      message: 'File uploaded and queued for processing.',
      data: {
        id: document._id,
        filename: document.filename,
        mimeType: document.mimeType,
        status: document.status,
      },
    };
  }

  @Get('status/:id')
  async getStatus(@Param('id') id: string) {
    const document = await this.ocrService.getStatus(id);
    return {
      success: true,
      data: {
        id: document._id,
        filename: document.filename,
        mimeType: document.mimeType,
        status: document.status,
        error: document.error,
        rawText: document.rawText,
        pages: document.pages,
        createdAt: document.createdAt,
        updatedAt: document.updatedAt,
      },
    };
  }

  @Get('search')
  async search(
    @Query('q') query: string,
    @Query('type') type?: 'keyword' | 'semantic' | 'hybrid',
    @Query('limit') limit?: string,
    @Query('offset') offset?: string,
  ) {
    if (!query) {
      throw new BadRequestException('Query parameter "q" is required.');
    }
    const searchLimit = limit ? parseInt(limit, 10) : 10;
    const searchOffset = offset ? parseInt(offset, 10) : 0;
    const searchType = type || 'keyword';

    const results = await this.ocrService.search(query, searchType, searchLimit, searchOffset);

    return {
      success: true,
      data: results,
      query: {
        q: query,
        type: searchType,
        limit: searchLimit,
        offset: searchOffset,
      },
    };
  }
}
