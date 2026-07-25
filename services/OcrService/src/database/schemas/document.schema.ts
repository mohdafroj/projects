import { Prop, Schema, SchemaFactory } from '@nestjs/mongoose';
import { Document } from 'mongoose';

export enum ProcessingStatus {
  PENDING = 'PENDING',
  PROCESSING = 'PROCESSING',
  COMPLETED = 'COMPLETED',
  FAILED = 'FAILED',
}

@Schema()
export class OcrPage {
  @Prop({ required: true })
  pageNumber: number;

  @Prop({ required: true })
  text: string;
}

export const OcrPageSchema = SchemaFactory.createForClass(OcrPage);

@Schema({ timestamps: true })
export class OcrDocument extends Document {
  @Prop({ required: true })
  filename: string;

  @Prop({ required: true })
  filepath: string;

  @Prop({ required: true })
  mimeType: string;

  @Prop({ required: true, enum: ProcessingStatus, default: ProcessingStatus.PENDING })
  status: ProcessingStatus;

  @Prop()
  error?: string;

  @Prop({ type: [OcrPageSchema], default: [] })
  pages: OcrPage[];

  @Prop({ default: '' })
  rawText: string;

  createdAt: Date;
  updatedAt: Date;
}

export const OcrDocumentSchema = SchemaFactory.createForClass(OcrDocument);
