import { createRule } from '../rules/core';
import type { ValidationRule } from '../rules/types';

export const dateAfter = (otherField: string, message?: string): ValidationRule =>
  createRule(
    (value, allValues = {}) => {
      if (!value || !allValues[otherField]) return true; // Let 'required' handle emptiness
      const current = new Date(value);
      const compareTo = new Date(allValues[otherField]);
      return current > compareTo;
    },
    {
      en: message || 'This date must be after {{other}}',
      hi: message || 'यह तिथि {{other}} के बाद होनी चाहिए'
    },
    () => ({ other: otherField })
  );