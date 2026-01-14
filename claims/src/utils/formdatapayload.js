export const formDataPayload = (
  obj,
  formData = new FormData(),
  parentKey = ''
) => {
  if (obj === null || obj === undefined) {
    formData.append(parentKey, '');
    return formData;
  }

  if (obj instanceof Date) {
    formData.append(parentKey, obj.toISOString());
  } else if (obj instanceof File || obj instanceof Blob) {
    formData.append(parentKey, obj);
  } else if (Array.isArray(obj)) {
    obj.forEach((value, index) => {
      const arrayKey = `${parentKey}[${index}]`;
      formDataPayload(value, formData, arrayKey);
    });
  } else if (typeof obj === 'object') {
    Object.keys(obj).forEach(key => {
      const fullKey = parentKey ? `${parentKey}[${key}]` : key;
      formDataPayload(obj[key], formData, fullKey);
    });
  } else {
    formData.append(parentKey, obj);
  }

  return formData;
};
