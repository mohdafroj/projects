// composables/useFieldWatcher.js
import { watch } from 'vue';

export function formFieldValidator(form, validateFieldFn) {
  let previousFormData = JSON.parse(JSON.stringify(form));

  watch(
    () => form,
    newVal => {
      for (const key in newVal) {
        if (
          previousFormData[key] !== undefined &&
          JSON.stringify(newVal[key]) !== JSON.stringify(previousFormData[key])
        ) {
          validateFieldFn(key);
        }
      }
      previousFormData = JSON.parse(JSON.stringify(newVal));
    },
    { deep: true }
  );
}
