import { Processor, WorkerHost } from '@nestjs/bullmq';
import { Injectable, Logger } from '@nestjs/common';
import { InjectModel } from '@nestjs/mongoose';
import { Job } from 'bullmq';
import { Model } from 'mongoose';
import * as fs from 'fs';
import * as path from 'path';
import { exec } from 'child_process';
import { promisify } from 'util';
import * as pdfParse from 'pdf-parse';

import { OcrDocument, ProcessingStatus } from '../database/schemas/document.schema';
import { EmbeddingService } from './embedding.service';
import { OpenSearchService } from './opensearch.service';

const execPromise = promisify(exec);

@Processor('ocr-queue')
@Injectable()
export class OcrProcessor extends WorkerHost {
  private readonly logger = new Logger(OcrProcessor.name);

  constructor(
    @InjectModel(OcrDocument.name) private ocrDocumentModel: Model<OcrDocument>,
    private embeddingService: EmbeddingService,
    private opensearchService: OpenSearchService,
  ) {
    super();
  }

  async process(job: Job<any, any, string>): Promise<any> {
    const { documentId } = job.data;
    this.logger.log(`Starting job ${job.id} for document: ${documentId}`);

    const document = await this.ocrDocumentModel.findById(documentId);
    if (!document) {
      this.logger.error(`Document with ID ${documentId} not found in database.`);
      return;
    }

    // Update state to PROCESSING
    document.status = ProcessingStatus.PROCESSING;
    await document.save();

    // Clean existing chunks in OpenSearch for this document
    await this.opensearchService.deleteDocumentChunks(documentId);

    try {
      if (!fs.existsSync(document.filepath)) {
        throw new Error(`Physical file not found at ${document.filepath}`);
      }

      let ocrPages: { pageNumber: number; text: string }[] = [];

      const isPdf = document.mimeType === 'application/pdf';

      if (isPdf) {
        this.logger.log(`Parsing digital PDF text directly for document: ${documentId}`);
        const fileBuffer = fs.readFileSync(document.filepath);
        
        const tempPages: string[] = [];
        const pagerender = async (pageData: any) => {
          const textContent = await pageData.getTextContent();
          let lastY = 0;
          let text = '';
          for (const item of textContent.items) {
            if (lastY === item.transform[5] || !lastY) {
              text += item.str;
            } else {
              text += '\n' + item.str;
            }
            lastY = item.transform[5];
          }
          tempPages[pageData.pageIndex] = text;
          return text;
        };

        try {
          await pdfParse(fileBuffer, { pagerender });
        } catch (pdfErr) {
          this.logger.warn(`Failed to parse PDF text directly. Will try OCR. Error: ${pdfErr.message}`);
        }

        const parsedPagesCount = tempPages.filter(Boolean).length;
        const totalExtractedLength = tempPages.reduce((acc, text) => acc + (text || '').trim().length, 0);

        if (parsedPagesCount > 0 && totalExtractedLength >= 100) {
          this.logger.log(`Extracted ${totalExtractedLength} characters directly from digital PDF.`);
          ocrPages = tempPages.map((text, idx) => ({
            pageNumber: idx + 1,
            text: this.cleanText(text || ''),
          }));
        } else {
          this.logger.log(`Digital parsing yielded too little text (${totalExtractedLength} chars). Treating as SCANNED PDF...`);
          ocrPages = await this.processScannedPdf(document);
        }
      } else {
        // Assume file is an image
        this.logger.log(`Processing image file with OCR for document: ${documentId}`);
        const text = await this.runOcrOnImage(document.filepath);
        ocrPages = [{
          pageNumber: 1,
          text: this.cleanText(text),
        }];
      }

      // 4. Index Chunks to OpenSearch
      let chunkCount = 0;
      for (const page of ocrPages) {
        if (!page.text) continue;
        const chunks = this.chunkText(page.text, 1000, 200);

        for (const chunkTextContent of chunks) {
          let embedding: number[] | undefined;
          if (this.embeddingService.isVectorSearchEnabled()) {
            embedding = await this.embeddingService.generateEmbedding(chunkTextContent);
          }

          await this.opensearchService.indexChunk({
            documentId,
            filename: document.filename,
            pageNumber: page.pageNumber,
            chunkIndex: chunkCount++,
            text: chunkTextContent,
            embedding,
          });
        }
      }

      // 5. Update MongoDB
      document.pages = ocrPages;
      document.rawText = ocrPages.map((p) => p.text).join('\n\n');
      document.status = ProcessingStatus.COMPLETED;
      document.error = undefined;
      await document.save();

      this.logger.log(`Successfully completed OCR and Indexing for document: ${documentId}`);
    } catch (error) {
      this.logger.error(`Error processing document ${documentId}:`, error);
      document.status = ProcessingStatus.FAILED;
      document.error = error.message || String(error);
      await document.save();
    }
  }

  private async processScannedPdf(document: OcrDocument): Promise<{ pageNumber: number; text: string }[]> {
    const ocrPages: { pageNumber: number; text: string }[] = [];
    const uploadDir = path.dirname(document.filepath);
    const tmpDirName = `tmp_${document._id}`;
    const tmpDir = path.join(uploadDir, tmpDirName);

    if (!fs.existsSync(tmpDir)) {
      fs.mkdirSync(tmpDir, { recursive: true });
    }

    try {
      this.logger.log(`Rendering PDF pages to images in temporary folder: ${tmpDir}`);
      const outputPrefix = path.join(tmpDir, 'page');
      // Render pages as PNG at 150 DPI
      const pdftoppmCommand = `pdftoppm -png -r 150 "${document.filepath}" "${outputPrefix}"`;
      await execPromise(pdftoppmCommand);

      const files = fs.readdirSync(tmpDir);
      const imageFiles = files
        .filter((f) => f.startsWith('page-') && f.endsWith('.png'))
        .sort((a, b) => {
          const numA = parseInt(a.replace('page-', '').replace('.png', ''), 10);
          const numB = parseInt(b.replace('page-', '').replace('.png', ''), 10);
          return numA - numB;
        });

      this.logger.log(`Found ${imageFiles.length} rendered pages. Starting Tesseract OCR...`);

      for (const imgFile of imageFiles) {
        const pageNum = parseInt(imgFile.replace('page-', '').replace('.png', ''), 10);
        const imgPath = path.join(tmpDir, imgFile);
        
        try {
          const text = await this.runOcrOnImage(imgPath);
          ocrPages.push({
            pageNumber: pageNum,
            text: this.cleanText(text),
          });
        } catch (ocrErr) {
          this.logger.error(`OCR failed on page ${pageNum} for document ${document._id}:`, ocrErr);
          ocrPages.push({
            pageNumber: pageNum,
            text: `[OCR Error on Page ${pageNum}]`,
          });
        }
      }
    } finally {
      // Clean up temporary images folder
      try {
        fs.rmSync(tmpDir, { recursive: true, force: true });
      } catch (rmErr) {
        this.logger.warn(`Failed to clean up temporary directory ${tmpDir}:`, rmErr);
      }
    }

    return ocrPages;
  }

  private async runOcrOnImage(imagePath: string): Promise<string> {
    // Run Tesseract CLI command
    const tesseractCommand = `tesseract "${imagePath}" stdout -l eng`;
    const { stdout } = await execPromise(tesseractCommand);
    return stdout;
  }

  private cleanText(text: string): string {
    return text
      .replace(/\r\n/g, '\n')
      .replace(/\n{3,}/g, '\n\n')
      .trim();
  }

  private chunkText(text: string, chunkSize = 1000, overlap = 200): string[] {
    const chunks: string[] = [];
    if (text.length <= chunkSize) {
      return [text];
    }
    
    let start = 0;
    while (start < text.length) {
      const end = Math.min(start + chunkSize, text.length);
      chunks.push(text.substring(start, end));
      start += chunkSize - overlap;
      if (start >= text.length - overlap) {
        break;
      }
    }
    return chunks;
  }
}
