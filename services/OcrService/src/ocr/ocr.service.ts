import { Injectable, NotFoundException } from '@nestjs/common';
import { InjectModel } from '@nestjs/mongoose';
import { Model } from 'mongoose';
import { InjectQueue } from '@nestjs/bullmq';
import { Queue } from 'bullmq';
import { OcrDocument, ProcessingStatus } from '../database/schemas/document.schema';
import { OpenSearchService } from './opensearch.service';
import { EmbeddingService } from './embedding.service';

@Injectable()
export class OcrService {
  constructor(
    @InjectModel(OcrDocument.name) private ocrDocumentModel: Model<OcrDocument>,
    @InjectQueue('ocr-queue') private ocrQueue: Queue,
    private opensearchService: OpenSearchService,
    private embeddingService: EmbeddingService,
  ) {}

  async handleUpload(file: Express.Multer.File): Promise<OcrDocument> {
    const document = new this.ocrDocumentModel({
      filename: file.originalname,
      filepath: file.path,
      mimeType: file.mimetype,
      status: ProcessingStatus.PENDING,
    });

    await document.save();

    // Add job to BullMQ
    await this.ocrQueue.add(
      'process-ocr',
      { documentId: document._id.toString() },
      {
        attempts: 3,
        backoff: {
          type: 'exponential',
          delay: 5000,
        },
      },
    );

    return document;
  }

  async getStatus(id: string): Promise<OcrDocument> {
    const document = await this.ocrDocumentModel.findById(id);
    if (!document) {
      throw new NotFoundException(`Document with ID ${id} not found.`);
    }
    return document;
  }

  async search(query: string, type: 'keyword' | 'semantic' | 'hybrid' = 'keyword', limit = 10, offset = 0) {
    if (!query) {
      return [];
    }

    const isVectorEnabled = this.embeddingService.isVectorSearchEnabled();
    
    // Graceful fallback to keyword search if vector search is not enabled or failed to load
    const resolvedType = !isVectorEnabled && type !== 'keyword' ? 'keyword' : type;

    switch (resolvedType) {
      case 'semantic': {
        const embedding = await this.embeddingService.generateEmbedding(query);
        if (embedding.length === 0) {
          return this.opensearchService.searchKeyword(query, limit, offset);
        }
        return this.opensearchService.searchVector(embedding, limit, offset);
      }
      case 'hybrid': {
        const embedding = await this.embeddingService.generateEmbedding(query);
        if (embedding.length === 0) {
          return this.opensearchService.searchKeyword(query, limit, offset);
        }
        return this.opensearchService.searchHybrid(query, embedding, limit, offset);
      }
      case 'keyword':
      default:
        return this.opensearchService.searchKeyword(query, limit, offset);
    }
  }
}
