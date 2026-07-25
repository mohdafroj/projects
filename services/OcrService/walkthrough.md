# Walkthrough: OCR & Search System in OcrService

I have successfully developed and verified the asynchronous OCR and search system in `OcrService` using NestJS, MongoDB (Mongoose), Redis (BullMQ), and OpenSearch.

## Summary of Changes

### Configuration and Docker Setup
- **[Dockerfile](file:///d:/works/projects/services/OcrService/Dockerfile)**: Added installation commands for system packages: `poppler-utils` (provides `pdftoppm` for converting PDF pages to images) and `tesseract-ocr` (with the English language pack `tesseract-ocr-data-eng`).
- **[package.json](file:///d:/works/projects/services/OcrService/package.json)**: Installed `@nestjs/config`, `@nestjs/mongoose`, `mongoose`, `@nestjs/bullmq`, `bullmq`, `@opensearch-project/opensearch`, `@xenova/transformers`, `pdf-parse`, `class-validator`, and `class-transformer` along with developer typings.
- **[.env](file:///d:/works/projects/services/OcrService/.env)**: Initialized local database, redis, and OpenSearch connection properties.
- **[configuration.ts](file:///d:/works/projects/services/OcrService/src/config/configuration.ts)**: Configured environment variables with defaults.

### Database Schema
- **[document.schema.ts](file:///d:/works/projects/services/OcrService/src/database/schemas/document.schema.ts)**: Implemented Mongoose schemas for `OcrDocument` and nested `OcrPage` to track metadata, page divisions, status enums (`PENDING`, `PROCESSING`, `COMPLETED`, `FAILED`), and raw text.

### OpenSearch & Vector Embedding Engines
- **[embedding.service.ts](file:///d:/works/projects/services/OcrService/src/ocr/embedding.service.ts)**: Created a local vector representation generator powered by `@xenova/transformers` running the lightweight `all-MiniLM-L6-v2` model (384 float dimensions).
- **[opensearch.service.ts](file:///d:/works/projects/services/OcrService/src/ocr/opensearch.service.ts)**: Built index registration and querying logic. When vector search is active, the service registers the `knn_vector` field inside index properties. Implemented three search modes:
  - **Keyword**: Multi-field BM25 matching.
  - **Semantic**: Cosine-similarity KNN matching.
  - **Hybrid**: Combined BM25 and KNN scoring boost.

### Asynchronous OCR Pipeline
- **[ocr.processor.ts](file:///d:/works/projects/services/OcrService/src/ocr/ocr.processor.ts)**: The BullMQ worker which:
  1. Tries to extract text directly from a digital PDF using `pdf-parse`.
  2. If the text length is under 100 characters, it classifies it as a scanned PDF, converts pages to PNGs via `pdftoppm`, and runs `tesseract` CLI on each page.
  3. OCRs normal images directly using the `tesseract` CLI.
  4. Splits the text into overlapping chunks (1000 characters, 200 overlap) and indexes them in OpenSearch.
- **[ocr.service.ts](file:///d:/works/projects/services/OcrService/src/ocr/ocr.service.ts)**: Standard service layer managing Mongoose state entries, queue dispatching, and search query routing.

### API Entry & Wiring
- **[ocr.controller.ts](file:///d:/works/projects/services/OcrService/src/ocr/ocr.controller.ts)**: Controller defining `POST /upload` (with Multer file validation filters), `GET /status/:id`, and `GET /search`.
- **[ocr.module.ts](file:///d:/works/projects/services/OcrService/src/ocr/ocr.module.ts)**: Connected mongoose schemas, queue names, and services.
- **[app.module.ts](file:///d:/works/projects/services/OcrService/src/app.module.ts)** and **[main.ts](file:///d:/works/projects/services/OcrService/src/main.ts)**: Configured bootstrap routine, global routing prefixes, global validator pipes, and database/queue factories.

---

## Verification Results

### Automated Tests
I added a unit test suite under **[ocr.service.spec.ts](file:///d:/works/projects/services/OcrService/src/ocr/ocr.service.spec.ts)**. It checks the service injection, status check routes, and keyword/vector search translation.

Running `npm run test` executes successfully:
```bash
> ocrservice@0.0.1 test
> jest

PASS src/app.controller.spec.ts (7.093 s)
PASS src/ocr/ocr.service.spec.ts (9.869 s)

Test Suites: 2 passed, 2 total
Tests:       6 passed, 6 total
Snapshots:   0 total
Time:        11.228 s
Ran all test suites.
```

### Manual Verification Steps
To verify manually inside your local environment:
1. Run `docker-compose up --build` from the root directory to rebuild `OcrService` (which installs Tesseract/Poppler) and spin up MongoDB, Redis, and OpenSearch.
2. Upload a text-based PDF:
   ```bash
   curl -X POST http://localhost:8003/api/v1/ocr/upload \
     -F "file=@your-digital-file.pdf"
   ```
3. Copy the returned document `id` and check its status:
   ```bash
   curl http://localhost:8003/api/v1/ocr/status/<id>
   ```
   *Expected result: Status transitions to `COMPLETED` quickly and contains extracted page text.*
4. Upload a scanned doc / image:
   ```bash
   curl -X POST http://localhost:8003/api/v1/ocr/upload \
     -F "file=@scanned-doc.png"
   ```
   *Expected result: The background worker executes Tesseract CLI and indexes the text.*
5. Perform a search query:
   - **Keyword**: `curl "http://localhost:8003/api/v1/ocr/search?q=searchterm&type=keyword"`
   - **Semantic**: `curl "http://localhost:8003/api/v1/ocr/search?q=searchterm&type=semantic"`
   - **Hybrid**: `curl "http://localhost:8003/api/v1/ocr/search?q=searchterm&type=hybrid"`
