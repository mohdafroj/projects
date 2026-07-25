import { Test, TestingModule } from '@nestjs/testing';
import { getModelToken } from '@nestjs/mongoose';
import { getQueueToken } from '@nestjs/bullmq';
import { OcrService } from './ocr.service';
import { OpenSearchService } from './opensearch.service';
import { EmbeddingService } from './embedding.service';
import { OcrDocument } from '../database/schemas/document.schema';

describe('OcrService', () => {
  let service: OcrService;
  let mockOcrDocumentModel: any;
  let mockOcrQueue: any;
  let mockOpenSearchService: any;
  let mockEmbeddingService: any;

  beforeEach(async () => {
    // Helper to mock mongoose instantiation
    function MockDoc(this: any, dto: any) {
      Object.assign(this, dto);
      this._id = 'mock-doc-id';
      this.save = jest.fn().mockResolvedValue(this);
    }
    
    mockOcrDocumentModel = MockDoc;
    mockOcrDocumentModel.findById = jest.fn();

    mockOcrQueue = {
      add: jest.fn().mockResolvedValue({ id: 'mock-job-id' }),
    };

    mockOpenSearchService = {
      searchKeyword: jest.fn(),
      searchVector: jest.fn(),
      searchHybrid: jest.fn(),
    };

    mockEmbeddingService = {
      isVectorSearchEnabled: jest.fn().mockReturnValue(true),
      generateEmbedding: jest.fn().mockResolvedValue([0.1, 0.2, 0.3]),
    };

    const module: TestingModule = await Test.createTestingModule({
      providers: [
        OcrService,
        {
          provide: getModelToken(OcrDocument.name),
          useValue: mockOcrDocumentModel,
        },
        {
          provide: getQueueToken('ocr-queue'),
          useValue: mockOcrQueue,
        },
        {
          provide: OpenSearchService,
          useValue: mockOpenSearchService,
        },
        {
          provide: EmbeddingService,
          useValue: mockEmbeddingService,
        },
      ],
    }).compile();

    service = module.get<OcrService>(OcrService);
  });

  it('should be defined', () => {
    expect(service).toBeDefined();
  });

  describe('getStatus', () => {
    it('should return document status if found', async () => {
      const mockDoc = { _id: 'doc-1', filename: 'test.pdf', status: 'PENDING' };
      mockOcrDocumentModel.findById.mockResolvedValue(mockDoc);

      const result = await service.getStatus('doc-1');
      expect(result).toEqual(mockDoc);
      expect(mockOcrDocumentModel.findById).toHaveBeenCalledWith('doc-1');
    });

    it('should throw NotFoundException if document not found', async () => {
      mockOcrDocumentModel.findById.mockResolvedValue(null);
      await expect(service.getStatus('non-existent')).rejects.toThrow();
    });
  });

  describe('search', () => {
    it('should perform keyword search', async () => {
      mockOpenSearchService.searchKeyword.mockResolvedValue([{ text: 'match' }]);
      const results = await service.search('query', 'keyword');
      expect(results).toEqual([{ text: 'match' }]);
      expect(mockOpenSearchService.searchKeyword).toHaveBeenCalledWith('query', 10, 0);
    });

    it('should perform semantic search and call embedding service', async () => {
      mockOpenSearchService.searchVector.mockResolvedValue([{ text: 'semantic-match' }]);
      const results = await service.search('query', 'semantic');
      expect(results).toEqual([{ text: 'semantic-match' }]);
      expect(mockEmbeddingService.generateEmbedding).toHaveBeenCalledWith('query');
      expect(mockOpenSearchService.searchVector).toHaveBeenCalledWith([0.1, 0.2, 0.3], 10, 0);
    });
  });
});
