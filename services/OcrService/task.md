# Task Checklist: OCR & Search System in OcrService

- `[x]` Install npm dependencies in `OcrService`
- `[x]` Update `Dockerfile` to install system packages (`poppler-utils`, `tesseract-ocr`, `tesseract-ocr-data-eng`)
- `[x]` Create local environment file `OcrService/.env`
- `[x]` Implement configurations (`src/config/configuration.ts`)
- `[x]` Implement document schema (`src/database/schemas/document.schema.ts`)
- `[x]` Implement local vector embeddings (`src/ocr/embedding.service.ts`)
- `[x]` Implement OpenSearch Client and mapping logic (`src/ocr/opensearch.service.ts`)
- `[x]` Implement OCR background job worker (`src/ocr/ocr.processor.ts`)
- `[x]` Implement OCR core service (`src/ocr/ocr.service.ts`)
- `[x]` Implement upload, status, search endpoints in controller (`src/ocr/ocr.controller.ts`)
- `[x]` Implement OCR module wiring (`src/ocr/ocr.module.ts`)
- `[x]` Update App Module (`src/app.module.ts`)
- `[x]` Update bootstrap routine (`src/main.ts`)
- `[x]` Create test suite and run verification tests
- `[x]` Document the changes in walkthrough.md
