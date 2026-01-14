<template>
    <form @submit.prevent="onSubmit" class="max-w-md mx-auto p-6 bg-white rounded-xl shadow-md">
        <h2 class="text-lg font-semibold mb-4 text-gray-800">Submit Form</h2>

        <!-- Name -->
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
            <input v-model.trim="form.name" type="text" placeholder="Enter your name"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-400 focus:outline-none"
                @input="validateField('name')" />
            <p v-if="errors.name" class="text-xs text-red-600 mt-1">{{ errors.name }}</p>
        </div>

        <!-- Mobile -->
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Mobile</label>
            <input v-model.trim="form.mobile" type="tel" placeholder="Enter 10-digit mobile" maxlength="10"
                @keypress="allowOnlyDigits" @input="debouncedValidateMobile"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-400 focus:outline-none" />
            <p v-if="errors.mobile" class="text-xs text-red-600 mt-1">{{ errors.mobile }}</p>
        </div>

        <!-- Address -->
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
            <textarea v-model.trim="form.address" rows="3" maxlength="100" placeholder="Enter your address"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-400 focus:outline-none resize-none"
                @input="validateField('address')"></textarea>
            <p v-if="errors.address" class="text-xs text-red-600 mt-1">{{ errors.address }}</p>
        </div>

        <!-- Actions -->
        <div class="flex items-center gap-3 mt-5">
            <button type="submit" :disabled="isSubmitting || hasErrors"
                class="px-4 py-2 rounded-lg bg-blue-600 text-white font-medium hover:bg-blue-700 disabled:opacity-60 transition-all">
                {{ isSubmitting ? 'Submitting...' : 'Submit' }}
            </button>
            <button type="button" @click="reset"
                class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-100 transition-all">
                Reset
            </button>
        </div>

        <!-- Messages -->
        <p v-if="successMessage" class="text-green-600 text-sm mt-4">{{ successMessage }}</p>
        <p v-if="submitError" class="text-red-600 text-sm mt-4">{{ submitError }}</p>
    </form>
    <!--PDF modal -->
    <PDFModal :isFilePath="isFile" :file-path="file_path" :reference-id="responseData" @esignDone="handleEsignResult"
        @close="handleClose">
    </PDFModal>
</template>

<script setup>
import { shallowReactive, ref, computed } from 'vue';
import { debounce } from 'lodash-es';
import PDFModal from './PDFModal.vue';
import { getAuthToken } from './getTokenCookies';
import { allowOnlyDigits } from '@/utils/allowOnlyDigit';
import { saveEsignAddress } from '@/services/rss/esignServices';

// Form state
const form = shallowReactive({
    name: '',
    mobile: '',
    address: '',
});

// Validation errors
const errors = shallowReactive({
    name: '',
    mobile: '',
    address: '',
});

// Submission states
const isSubmitting = ref(false);
const successMessage = ref('');
const submitError = ref('');
const responseData = ref('');
const file_path = ref('');
const isFile = ref(false)
// Validation rules
const validationRules = {
    name: {
        required: 'Name is required.',
        minLength: { value: 2, message: 'Name must be at least 2 characters.' },
    },
    mobile: {
        required: 'Mobile number is required.',
        pattern: {
            value: /^[6-9]\d{9}$/,
            message: 'Enter a valid 10-digit mobile number.',
        },
    },
    address: {
        required: 'Address is required.',
        minLength: { value: 5, message: 'Address must be at least 5 characters.' },
    },
};

// Validate single field
const validateField = (field) => {
    const value = form[field].trim();
    const rules = validationRules[field];

    if (!value) {
        errors[field] = rules.required;
        return false;
    }

    if (rules.minLength && value.length < rules.minLength.value) {
        errors[field] = rules.minLength.message;
        return false;
    }

    if (rules.pattern && !rules.pattern.value.test(value)) {
        errors[field] = rules.pattern.message;
        return false;
    }

    errors[field] = '';
    return true;
};

// Debounced mobile validation
const debouncedValidateMobile = debounce(() => validateField('mobile'), 300);

// Validate all fields
const validate = () => {
    const isValid = Object.keys(validationRules).every((field) => validateField(field));
    return isValid;
};

// Computed property for form validity
const hasErrors = computed(() => Object.values(errors).some((error) => error));

// Reset form
const reset = () => {
    Object.keys(form).forEach((key) => (form[key] = ''));
    Object.keys(errors).forEach((key) => (errors[key] = ''));
    successMessage.value = '';
    submitError.value = '';
};

// Handle submit
const onSubmit = async () => {
    successMessage.value = '';
    submitError.value = '';

    if (!validate()) return;

    const token = getAuthToken();
    if (!token) {
        submitError.value = 'Authentication token is missing.';
        return;
    }

    isSubmitting.value = true;

    try {

        const res = await saveEsignAddress(form)

        if (res.status === 200) {
            const storeData = res.dms_response;
            responseData.value = res.reference_id;
            isFile.value = true;
            file_path.value = storeData?.data?.file_path;
            isSubmitting.value = false;
        }

        if (res.isError) {
            throw new Error(res.message || `Submission failed with status ${res.errors}`);
        }

        successMessage.value = 'Form submitted successfully!';
        reset();
    } catch (err) {
        submitError.value = err.message || 'An unexpected error occurred.';
    } finally {
        isSubmitting.value = false;
    }
};

const handleEsignResult = (payload) => {
    // This function runs when the child component calls emit('esignDone', payload)

    if (payload.success) {
        isFile.value = false;
        window.location.href = payload.data?.body?.data?.redirectUrl;
    } else {
        console.error('E-sign failed. Error:', payload.error);
        // Handle the failure case (e.g., show an error notification)
        isFile.value = true; // Optionally keep the modal open to show the error
    }
}

const handleClose = () => {
    isFile.value = false;
}

</script>

<style scoped>
form {
    background-color: #f9fafb;
}

input,
textarea {
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

button:disabled {
    cursor: not-allowed;
}
</style>