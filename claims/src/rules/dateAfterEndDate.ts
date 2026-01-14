import { createRule } from '../rules/core';
import type { ValidationRule } from '../rules/types';

export const dateAfterEndDate = (
  compareToField: string,
  customMessage?: string
): ValidationRule =>
  createRule(
    (value, allValues = {}) => {
      if (!value || !allValues[compareToField]) return true;
      const currentDate = new Date(value);
      const compareDate = new Date(allValues[compareToField]);
      return currentDate > compareDate;
    },
    {
      en: customMessage || 'This date must be after the previous end date',
      hi: customMessage || 'यह तिथि पिछले समाप्ति दिनांक के बाद होनी चाहिए'
    },
    () => ({ field: compareToField })
  );