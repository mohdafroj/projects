<template>
  <div>
    <form @submit.prevent="handleSubmit" class="space-y-4">
      <!-- Grid layout for two inputs per row -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <!-- Employee ID -->
        <TextInput
          v-model="form.employee_id"
          v-model:error="errors.employee_id"
          :label="$t('lobbyOffice.employee_id')"
          name="employee_id"
          :placeholder="$t('lobbyOffice.employee_id_placeholder')"
          :isRequired="true"
          classInput="w-full"
          :disabled="true"
          :disableBuiltinValidation="true"
          @blur="validateField('employee_id')"
          @input="validateField('employee_id')"
        />

        <!-- Designation (Select) -->
        <TextInput
          v-model="form.designation"
          v-model:error="errors.designation"
          :label="$t('lobbyOffice.designation')"
          name="designation"
          :options="designationOptions"
          :placeholder="$t('lobbyOffice.designation_placeholder')"
          :isRequired="true"
          :disableBuiltinValidation="true"
          classInput="w-full"
          @blur="validateField('designation')"
        />

        <!-- Full Name -->
        <TextInput
          v-model="form.full_name"
          v-model:error="errors.full_name"
          :label="$t('lobbyOffice.full_name')"
          name="full_name"
          :placeholder="$t('lobbyOffice.full_name_placeholder')"
          :isRequired="true"
          :disableBuiltinValidation="true"
          classInput="w-full"
          @blur="validateName"
          @input="validateField('full_name')"
        />

        <!-- Official Email ID -->
        <TextInput
          v-model="form.official_email_id"
          v-model:error="errors.official_email_id"
          :label="$t('lobbyOffice.official_email_id')"
          name="official_email_id"
          type="email"
          :placeholder="$t('lobbyOffice.official_email_id_placeholder')"
          :isRequired="true"
          :disableBuiltinValidation="true"
          classInput="w-full"
          :validate="form.official_email_id && !errors.official_email_id ? $t('lobbyOffice.email_verified') : ''"
          @blur="validateField('official_email_id')"
          @input="validateField('official_email_id')"
        />

        <!-- Division/Branch (Select) -->
        <TextInput
          v-model="form.division_branch"
          v-model:error="errors.division_branch"
          :label="$t('lobbyOffice.division_branch')"
          name="division_branch"
          :options="divisionBranchOptions"
          :placeholder="$t('lobbyOffice.division_branch_placeholder')"
          :isRequired="true"
          :disableBuiltinValidation="true"
          classInput="w-full"
          @blur="validateField('division_branch')"
          :disabled="true"
        />

        <!-- Mobile Number -->
        <TextInput
          v-model="form.mobile_number"
          v-model:error="errors.mobile_number"
          :label="$t('lobbyOffice.mobile_number')"
          name="mobile_number"
          type="tel"
          :placeholder="$t('lobbyOffice.mobile_number_placeholder')"
          :isRequired="true"
          :disableBuiltinValidation="true"
          classInput="w-full"
          :validate="form.mobile_number && !errors.mobile_number ? $t('lobbyOffice.phone_verified') : ''"
          @blur="validateField('mobile_number')"
          @input="validateField('mobile_number')"
        />
      </div>

      <!-- Footer with Buttons -->
      <div class="px-4 py-3 bg-gray-50 border-t border-gray-200 flex justify-end space-x-3 shrink-0">
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
import { useValidation, required, email, mobile, nameMinLength, maxLength, pattern } from '@sds/oneui-validation';
import { useI18n } from 'vue-i18n';

const { t } = useI18n(); // Access translation function
const emit = defineEmits(["update:modelValue", "save"]);

// Reactive form data
const form = reactive({
  employee_id: "",
  designation: "",
  full_name: "",
  official_email_id: "",
  division_branch: "",
  mobile_number: "",
});

const EMP_REGEX = /^\d+$/;
const NAME_REGEX = /^[A-Za-z .']+$/;
// Validation schema
const validationSchema = {
  employee_id: [required(), pattern(EMP_REGEX), nameMinLength(2), maxLength(100) ],
  designation: [required()],
  full_name: [required(), nameMinLength(2), maxLength(100), pattern(NAME_REGEX)],
  official_email_id: [required(), email()],
  division_branch: [required()],
  mobile_number: [required(), mobile()],
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

// Options for select fields
const designationOptions = ref([]); // Assuming this is populated elsewhere, e.g., via API
const divisionBranchOptions = ref([
  { value: "System Division", label: t('lobbyOffice.system_division') },
  { value: "Lobby Office", label: t('lobbyOffice.lobby_office') },
  { value: "Table Office", label: t('lobbyOffice.table_office') },
]);

// Sync form with userData when it changes
watch(
  () => props.userData,
  (newUserData) => {
    if (newUserData) {
      form.employee_id = newUserData.employee_id || "";
      form.full_name = newUserData.full_name || newUserData.name || "";
      form.official_email_id = newUserData.official_email_id || newUserData.email || "";
      form.mobile_number = newUserData.mobile_number || newUserData.mobile || "";
      form.designation = newUserData.designation || "";
      const divisionMatch = divisionBranchOptions.value.find(opt =>
        opt.value.toString() === (newUserData.division_id || newUserData.division_branch)?.toString()
      );
      form.division_branch = divisionMatch?.label || newUserData.division_branch || "";
    }
  },
  { immediate: true }
);

// Custom validation for full_name
const validateName = () => {
  validateField('full_name');
};

// Handle form submission with mobile number validation
const handleSubmit = async () => {
  const isFormValid = await validateAll();
  if (!isFormValid) {
    Swal.fire({
      title: t('lobbyOffice.validation_error'),
      text: t('lobbyOffice.validation_user_error_message'),
      icon: "error",
      timer: 2000,
      showConfirmButton: false,
    });
    return;
  }

  if (!/^\d{10}$/.test(form.mobile_number)) {
    Swal.fire({
      title: t('lobbyOffice.validation_error'),
      text: t('lobbyOffice.mobile_number_invalid'),
      icon: "error",
      timer: 2000,
      showConfirmButton: false,
    });
    return;
  }

  try {
    const payload = {
      employee_id: form.employee_id,
      designation: form.designation,
      full_name: form.full_name,
      official_email_id: form.official_email_id,
      division_branch: form.division_branch,
      mobile_number: form.mobile_number,
    };

    emit("save", { ...payload });
    Swal.fire({
      title: t('lobbyOffice.success'),
      text: t('lobbyOffice.user_success_message'),
      icon: "success",
      timer: 1000,
      showConfirmButton: false,
    });
    emit("update:modelValue", false); // Close modal
  } catch (error) {
    Swal.fire({
      title: t('lobbyOffice.error'),
      text: error.message || t('lobbyOffice.user_error_message'),
      icon: "error",
      timer: 2000,
      showConfirmButton: false,
    });
  }
};
</script>