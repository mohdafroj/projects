import { Module } from '@nestjs/common';
import { MongooseModule } from '@nestjs/mongoose';
import { BullModule } from '@nestjs/bullmq';
import { OcrController } from './ocr.controller';
import { OcrService } from './ocr.service';
import { OcrProcessor } from './ocr.processor';
import { EmbeddingService } from './embedding.service';
import { OpenSearchService } from './opensearch.service';
import { OcrDocument, OcrDocumentSchema } from '../database/schemas/document.schema';

@Module({
  imports: [
    MongooseModule.forFeature([{ name: OcrDocument.name, schema: OcrDocumentSchema }]),
    BullModule.registerQueue({
      name: 'ocr-queue',
    }),
  ],
  controllers: [OcrController],
  providers: [OcrService, OcrProcessor, EmbeddingService, OpenSearchService],
  exports: [OcrService],
})
export class OcrModule { }
