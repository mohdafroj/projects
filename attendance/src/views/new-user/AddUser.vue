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
          :disableBuiltinValidation="true"
          classInput="w-full"
          @blur="validateField('employee_id')"
          @input="validateField('employee_id')"
        />

        <!-- Designation (Select) -->
        <TextInput
          v-model="form.designation"
          v-model:error="errors.designation"
          :label="$t('lobbyOffice.designation')"
          name="designation"
          type="select"
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
          type="select"
          :options="divisionBranchOptions"
          :placeholder="$t('lobbyOffice.division_branch_placeholder')"
          :isRequired="true"
          :disableBuiltinValidation="true"
          classInput="w-full"
          @blur="validateField('division_branch')"
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
          :label="$t('lobbyOffice.reset')"
          size="sm"
          color="blue-outline"
          @click="resetForm"
        />
        <Button
          :label="$t('lobbyOffice.add_user')"
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
import { onMounted, ref, reactive } from "vue";
import { Button, TextInput } from "@sds/oneui-common-ui";
import Swal from "sweetalert2";
import { createUser, fetchDivision, fetchDesignation } from "@/services/lobbyService";
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

// Options for select fields
const designationOptions = ref([]);
const divisionBranchOptions = ref([]);

onMounted(async () => {
  await loadDesignationOptions();
  await loadDivisionOptions();
});

const loadDesignationOptions = async () => {
  const response = await fetchDesignation();
  if (!response.isError && Array.isArray(response.data)) {
    designationOptions.value = response.data.map(d => ({
      value: d.id.toString(),
      label: d.designation // Assuming API returns localized or static labels
    }));
  }
};

const loadDivisionOptions = async () => {
  const response = await fetchDivision();
  if (!response.isError && Array.isArray(response.data)) {
    divisionBranchOptions.value = response.data
      .filter(d => d.ou_id === 75)
      .map(d => ({
        value: d.id.toString(),
        label: d.division 
      }));
  }
};

// Reset form fields
const resetForm = () => {
  form.employee_id = "";
  form.designation = "";
  form.full_name = "";
  form.official_email_id = "";
  form.division_branch = "";
  form.mobile_number = "";
  emit("update:modelValue", false); // Close modal
};

// Custom validation for full_name to handle blur event
const validateName = () => {
  validateField('full_name');
};

// Handle form submission
const handleSubmit = async () => {
  const isFormValid = await validateAll();
  if (!isFormValid) {
    Swal.fire({
      title: t('lobbyOffice.validation_error'),
      text: t('lobbyOffice.validation_user_create_error_message'),
      icon: "error",
      timer: 2000,
      showConfirmButton: false,
    });
    return;
  }

  // Prepare payload for API
  const payload = {
    ou_id: 75,
    employee_id: form.employee_id,
    full_name: form.full_name,
    email: form.official_email_id,
    mobile: parseInt(form.mobile_number),
    designation: parseInt(form.designation),
    division: parseInt(form.division_branch),
  };

  try {
    const response = await createUser(payload);
    if (response.success_code === 200) {
      Swal.fire({
        title: t('lobbyOffice.success'),
        text: response.message || t('lobbyOffice.user_create_success_message'),
        icon: "success",
        timer: 1000,
        showConfirmButton: false,
      });

      // Map API response to table format
      const newUser = {
        employee_id: response.data.user.id.toString(),
        designation: form.designation,
        full_name: response.data.user.displayname || response.data.user.name["en"],
        official_email_id: response.data.user.email,
        division_branch: form.division_branch,
        mobile_number: response.data.user.mobile.toString(),
      };
      emit("save", newUser);
      emit("update:modelValue", false); // Close modal
    } else {
      throw new Error(response.message || t('lobbyOffice.user_create_error_message'));
    }
  } catch (error) {
    Swal.fire({
      title: t('lobbyOffice.error'),
      text: error.message || t('lobbyOffice.user_create_error_message'),
      icon: "error",
      timer: 2000,
      showConfirmButton: false,
    });
  }
};
</script>