import { Injectable, Logger, OnModuleInit } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { Client } from '@opensearch-project/opensearch';

@Injectable()
export class OpenSearchService implements OnModuleInit {
  private readonly logger = new Logger(OpenSearchService.name);
  private client: Client;
  private readonly indexName = 'ocr_documents';

  constructor(private configService: ConfigService) {
    const node = this.configService.get<string>('opensearchUrl', 'http://localhost:9200');
    this.client = new Client({ node });
  }

  async onModuleInit() {
    try {
      this.logger.log(`Connecting to OpenSearch and verifying index: "${this.indexName}"...`);
      await this.ensureIndexExists();
    } catch (error) {
      this.logger.error('Failed to initialize OpenSearch index.', error);
    }
  }

  private async ensureIndexExists() {
    try {
      const { body: exists } = await this.client.indices.exists({
        index: this.indexName,
      });

      if (exists) {
        this.logger.log(`OpenSearch index "${this.indexName}" already exists.`);
        return;
      }
    } catch (err) {
      this.logger.log(`Index does not exist or error checking it. Proceeding with creation...`);
    }

    this.logger.log(`Creating OpenSearch index "${this.indexName}"...`);

    const hasVectorSupport = this.configService.get<boolean>('enableVectorSearch', true);

    const settings: any = {
      index: {
        number_of_shards: 1,
        number_of_replicas: 0,
      },
    };

    const properties: any = {
      documentId: { type: 'keyword' },
      filename: { type: 'text', analyzer: 'standard' },
      pageNumber: { type: 'integer' },
      chunkIndex: { type: 'integer' },
      text: { type: 'text', analyzer: 'standard' },
    };

    if (hasVectorSupport) {
      settings.index.knn = true;
      properties.embedding = {
        type: 'knn_vector',
        dimension: 384,
        method: {
          name: 'hnsw',
          space_type: 'l2',
          engine: 'nmslib',
          parameters: {
            ef_construction: 128,
            m: 16,
          },
        },
      };
    }

    try {
      await this.client.indices.create({
        index: this.indexName,
        body: {
          settings,
          mappings: {
            properties,
          },
        },
      });
      this.logger.log(`OpenSearch index "${this.indexName}" created successfully (Vector support: ${hasVectorSupport}).`);
    } catch (err) {
      this.logger.error(`Failed to create OpenSearch index "${this.indexName}"`, err);
    }
  }

  async deleteDocumentChunks(documentId: string) {
    try {
      await this.client.deleteByQuery({
        index: this.indexName,
        body: {
          query: {
            term: {
              documentId,
            },
          },
        },
        refresh: true,
      });
      this.logger.log(`Deleted existing OpenSearch chunks for document: ${documentId}`);
    } catch (error) {
      this.logger.error(`Error deleting chunks for document ID: ${documentId}`, error);
    }
  }

  async indexChunk(chunk: {
    documentId: string;
    filename: string;
    pageNumber: number;
    chunkIndex: number;
    text: string;
    embedding?: number[];
  }) {
    try {
      await this.client.index({
        index: this.indexName,
        body: chunk,
        refresh: true,
      });
    } catch (error) {
      this.logger.error(`Failed to index chunk ${chunk.chunkIndex} of page ${chunk.pageNumber} for document ${chunk.documentId}`, error);
      throw error;
    }
  }

  async searchKeyword(query: string, limit: number, offset: number) {
    const { body } = await this.client.search({
      index: this.indexName,
      from: offset,
      size: limit,
      body: {
        query: {
          multi_match: {
            query,
            fields: ['text^2', 'filename'],
            fuzziness: 'AUTO',
          },
        },
        highlight: {
          fields: {
            text: {},
          },
        },
      },
    });
    return this.formatHits(body.hits.hits);
  }

  async searchVector(embedding: number[], limit: number, offset: number) {
    const { body } = await this.client.search({
      index: this.indexName,
      from: offset,
      size: limit,
      body: {
        query: {
          knn: {
            embedding: {
              vector: embedding,
              k: limit,
            },
          },
        },
      },
    });
    return this.formatHits(body.hits.hits);
  }

  async searchHybrid(query: string, embedding: number[], limit: number, offset: number) {
    const { body } = await this.client.search({
      index: this.indexName,
      from: offset,
      size: limit,
      body: {
        query: {
          bool: {
            should: [
              {
                multi_match: {
                  query,
                  fields: ['text^2', 'filename'],
                  fuzziness: 'AUTO',
                  boost: 1.0,
                },
              },
              {
                knn: {
                  embedding: {
                    vector: embedding,
                    k: limit,
                    boost: 1.5,
                  },
                },
              },
            ],
          },
        },
        highlight: {
          fields: {
            text: {},
          },
        },
      },
    });
    return this.formatHits(body.hits.hits);
  }

  private formatHits(hits: any[]) {
    return hits.map((hit) => ({
      score: hit._score,
      documentId: hit._source.documentId,
      filename: hit._source.filename,
      pageNumber: hit._source.pageNumber,
      chunkIndex: hit._source.chunkIndex,
      text: hit._source.text,
      highlight: hit.highlight?.text || [],
    }));
  }
}
