import type { ComputedRef } from 'vue';

export interface ValidationRule {
  validate: (value: any, allValues?: Record<string, any>) => boolean;
  message: string | (() => string);
}

export interface ValidationSchema {
  [field: string]: ValidationRule[];
}

export interface UseValidationReturn {
  errors: Record<string, string>;
  touched: Record<string, boolean>;
  validateField: (field: string) => string;
  validateAll: () => Promise<boolean>;
  resetValidation: () => void;
  revalidateAll: () => void;  // Add this line
  isValid: ComputedRef<boolean>;
}

export interface Messages {
  en: string;
  hi: string;
}