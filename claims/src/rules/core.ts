// src/core.ts - Force re-validation version
import { reactive, computed } from 'vue';
import type { ComputedRef } from 'vue';
import type { ValidationSchema, UseValidationReturn, ValidationRule, Messages } from './types';

const getLang = (): 'en' | 'hi' => {
  try {
    return localStorage.getItem('language') === 'hi' ? 'hi' : 'en';
  } catch {
    return 'en';
  }
};

export function useValidation(
  formData: Record<string, any>, 
  schema: ValidationSchema = {}
): UseValidationReturn {
  const errors = reactive<Record<string, string>>({});
  const touched = reactive<Record<string, boolean>>({});
  
  // Initialize errors and touched for all schema fields
  Object.keys(schema).forEach(field => {
    errors[field] = '';
    touched[field] = false;
  });
  
  const validateField = (field: string): string => {
    if (!schema[field]) return '';
    
    const value = formData[field];
    
    // Clear the error first to force fresh evaluation
    errors[field] = '';
    
    // Mark field as touched
    touched[field] = true;
    
    for (const rule of schema[field]) {
      if (!rule.validate(value, formData)) {
        // ALWAYS call message as function to get fresh language detection
        const errorMessage = typeof rule.message === 'function' ? rule.message() : rule.message;
        errors[field] = errorMessage;
        return errorMessage;
      }
    }
    
    return '';
  };
  
  const validateAll = async (): Promise<boolean> => {
    let isValid = true;
    
    Object.keys(schema).forEach(field => {
      touched[field] = true;
      const error = validateField(field);
      if (error) isValid = false;
    });
    
    return isValid;
  };
  
  // Add a method to force re-validation of all fields (useful for language changes)
  const revalidateAll = (): void => {
    Object.keys(schema).forEach(field => {
      if (touched[field]) {
        validateField(field);
      }
    });
  };
  
  const resetValidation = (): void => {
    Object.keys(schema).forEach(field => {
      errors[field] = '';
      touched[field] = false;
    });
  };
  
  // Computed property for overall form validity
  const isValid = computed(() => {
    return Object.keys(schema).every(field => {
      return !errors[field] && formData[field] !== undefined && formData[field] !== '';
    });
  });
  
  return { 
    errors, 
    touched, 
    validateField, 
    validateAll, 
    resetValidation,
    revalidateAll, 
    isValid 
  };
}

export const createRule = (
  validateFn: (value: any, allValues?: Record<string, any>) => boolean,
  messages: Messages,
  getParams: () => Record<string, any> = () => ({})
): ValidationRule => ({
  validate: validateFn,
  message: () => {
    // Get language fresh every time - CRITICAL for reactivity
    const lang = getLang();
    console.log('🔍 createRule - Creating message with language:', lang, 'at', new Date().toISOString()); // Debug log
    let msg = messages[lang] || messages.en;
    console.log('🔍 createRule - Available messages:', messages); // Debug log
    console.log('🔍 createRule - Selected message:', msg); // Debug log
    const params = getParams();
    Object.keys(params).forEach(key => {
      msg = msg.replace(`{{${key}}}`, String(params[key]));
    });
    console.log('🔍 createRule - Final generated message:', msg); // Debug log
    return msg;
  }
});