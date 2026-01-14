import { createRule } from '../rules/core'; 
import type { ValidationRule } from '../rules/types';

export const dateWithinFourMonths = (
  compareToField: string,
  customMessage?: string
): ValidationRule =>
  createRule(
    (value, allValues = {}) => {
      if (!value || !allValues[compareToField]) return true;

      const currentDate = new Date(value);
      const compareDate = new Date(allValues[compareToField]);

      // Add 4 months to compareDate
      const maxDate = new Date(compareDate);
      maxDate.setMonth(maxDate.getMonth() + 4);

      // Check if currentDate is NOT beyond maxDate
      return currentDate <= maxDate;
    },
    {
      en: customMessage || 'The date must be within 4 months after the compared date',
      hi: customMessage || 'तिथि तुलना की गई तिथि के बाद 4 महीने के भीतर होनी चाहिए'
    },
    () => ({ field: compareToField })
  );