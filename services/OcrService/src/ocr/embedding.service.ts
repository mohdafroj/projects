import { Injectable, Logger, OnModuleInit } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';

@Injectable()
export class EmbeddingService implements OnModuleInit {
  private readonly logger = new Logger(EmbeddingService.name);
  private pipeline: any = null;
  private isEnabled = true;

  constructor(private configService: ConfigService) {
    this.isEnabled = this.configService.get<boolean>('enableVectorSearch', true);
  }

  async onModuleInit() {
    if (!this.isEnabled) {
      this.logger.log('Vector search is disabled via configuration.');
      return;
    }

    try {
      this.logger.log('Initializing local vector embedding model (Xenova/all-MiniLM-L6-v2)...');
      const { pipeline } = await import('@xenova/transformers');
      this.pipeline = await pipeline('feature-extraction', 'Xenova/all-MiniLM-L6-v2');
      this.logger.log('Vector embedding model initialized successfully.');
    } catch (error) {
      this.logger.error('Failed to initialize vector embedding model. Vector search will be disabled.', error);
      this.isEnabled = false;
    }
  }

  async generateEmbedding(text: string): Promise<number[]> {
    if (!this.isEnabled || !this.pipeline) {
      return [];
    }

    try {
      const output = await this.pipeline(text, { pooling: 'mean', normalize: true });
      return Array.from(output.data);
    } catch (error) {
      this.logger.error(`Error generating embedding for text: "${text.substring(0, 50)}..."`, error);
      return [];
    }
  }

  isVectorSearchEnabled(): boolean {
    return this.isEnabled;
  }
}
