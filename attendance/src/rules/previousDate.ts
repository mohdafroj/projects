import { createRule } from '../rules/core';
import type { ValidationRule } from '../rules/types';

export const dateNotBeforeToday = (message?: string): ValidationRule =>
  createRule(
    (value) => {
      if (!value) return true; // Let 'required' handle empty
      const selectedDate = new Date(value);
      const today = new Date();
      // Zero out time part for comparison
      today.setHours(0, 0, 0, 0);
      return selectedDate >= today;
    },
    {
      en: message || 'Date cannot be before today',
      hi: message || 'तिथि आज से पहले नहीं हो सकती'
    }
  );
