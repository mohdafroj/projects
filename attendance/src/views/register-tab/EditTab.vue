<template>
  <div>
    <form @submit.prevent="handleSubmit" class="space-y-4">
      <!-- Grid layout for two inputs per row -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <!-- Device ID -->
        <TextInput
          v-model="form.device_id"
          v-model:error="errors.device_id"
          :label="$t('lobbyOffice.device_id')"
          name="device_id"
          :placeholder="$t('lobbyOffice.device_id_placeholder')"
          :isRequired="true"
          :disableBuiltinValidation="true"
          classInput="w-full"
          @blur="validateField('device_id')"
          @input="validateField('device_id')"
        />

        <!-- Tab ID -->
        <TextInput
          v-model="form.device_code"
          v-model:error="errors.device_code"
          :label="$t('lobbyOffice.tab_id')"
          name="tab_id"
          :placeholder="$t('lobbyOffice.tab_id_placeholder')"
          :isRequired="true"
          :disableBuiltinValidation="true"
          classInput="w-full"
          @blur="validateField('tab_id')"
          @input="validateField('tab_id')"
        />
      </div>

      <!-- Footer with Buttons -->
      <div class="ps-4 py-3 border-t border-gray-200 flex justify-end space-x-3 shrink-0">
        <Button
          :label="$t('lobbyOffice.cancel')"
          size="sm"
          color="blue-outline"
          @click="$emit('update:modelValue', false)"
        />
        <Button
          :label="$t('lobbyOffice.save')"
          size="sm"
          color="blue"
          type="submit"
          :disabled="!isValid"
        />
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref, reactive, watch } from "vue";
import { Button, TextInput } from "@sds/oneui-common-ui";
import Swal from "sweetalert2";
import { useValidation, required, minLength, maxLength, pattern } from '@sds/oneui-validation';
import { useI18n } from 'vue-i18n';

const { t } = useI18n(); // Access translation function
const emit = defineEmits(["update:modelValue", "save"]);

// Reactive form data
const form = reactive({
  device_id: "",
  device_code: "",
  tab_id: "",
});

const DEVICE_REGEX = /^[A-Za-z0-9.-]+$/;
const TAB_REGEX = /^[A-Za-z0-9-]+$/;
// Validation schema
const validationSchema = {
  device_id: [required(), minLength(3), maxLength(80), pattern(DEVICE_REGEX)],
  device_code: [required(), minLength(3), maxLength(50), pattern(TAB_REGEX)],
};

// Use validation hook
const { errors, isValid, validateField, validateAll } = useValidation(form, validationSchema);

// Props to receive the user data to edit
const props = defineProps({
  modelValue: Boolean,
  userData: {
    type: Object,
    default: () => ({}),
  },
});

// Sync form with userData when it changes
watch(
  () => props.userData,
  (newUserData) => {
    if (newUserData) {
      form.device_id = newUserData.device_id || "";
      form.device_code = newUserData.device_code || "";
      form.tab_id = newUserData.tab_id || "";
    }
  },
  { immediate: true }
);

// Handle form submission
const handleSubmit = async () => {
  const isFormValid = await validateAll();
  if (!isFormValid) {
    Swal.fire({
      title: t('lobbyOffice.validation_error'),
      text: t('lobbyOffice.validation_error_message'),
      icon: "error",
      timer: 2000,
      showConfirmButton: false,
    });
    return;
  }

  try {
    const payload = {
      device_id: form.device_id,
  device_code: form.device_code, 
  tab_id: form.tab_id,
    };

    emit("save", { ...payload });
    Swal.fire({
      title: t('lobbyOffice.success'),
      text: t('lobbyOffice.success_message'),
      icon: "success",
      timer: 1000,
      showConfirmButton: false,
    });
    emit("update:modelValue", false); // Close modal
  } catch (error) {
    Swal.fire({
      title: t('lobbyOffice.error'),
      text: error.message || t('lobbyOffice.error_message'),
      icon: "error",
      timer: 2000,
      showConfirmButton: false,
    });
  }
};
</script>

<style>
body.swal2-shown > [aria-hidden='true'] {
  transition: 0.1s filter;
  filter: blur(3px);
}
</style>