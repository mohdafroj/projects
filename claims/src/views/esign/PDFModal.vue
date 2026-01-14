<template>
    <Modal v-model="isFilePath" :size="'xl'" disable-backdrop="true" :title="'E-sign Modal'"
        class="w-full max-w-4xl h-full sm:w-[48rem] dark:text-slate-300">
        <!-- PDF Display -->
        <div class="relative">
            <iframe :src="props.filePath" class="w-full h-[60vh] border border-gray-300 rounded-lg" title="PDF Preview"
                @error="handleIframeError"></iframe>
            <p v-if="pdfError" class="text-red-600 text-sm mt-2">{{ pdfError }}</p>
        </div>
        <!-- Footer with E-sign Button -->
        <template #footer>
            <div class="flex justify-end gap-3 mt-4">
                <button @click="onEsign"
                    class="px-4 py-2 rounded-lg bg-blue-600 text-white font-medium hover:bg-blue-700 transition-all">
                    E-sign
                </button>
                <button @click="closeModal"
                    class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-100 transition-all">
                    Close
                </button>
            </div>

        </template>
    </Modal>
</template>

<script setup>
import { Modal } from '@sds/oneui-common-ui';
import { defineEmits, ref } from 'vue';
import { getAuthToken } from './getTokenCookies';
import links from '@/constant/links';
import { proceedEsignAddress } from '@/services/rss/esignServices';

const isFilePath = defineModel('isFilePath');

const props = defineProps({
    filePath: {
        type: String,
        default: '',
    },
    referenceId: {
        type: String,
        default: ''
    },
    redirectURL: {
        type: String,
        default: ''
    }
});

// 1. Define Emits
// Define an event that will be triggered when the e-sign process is complete.
// The parent component will listen for this event.
const emit = defineEmits(['esignDone', 'close']);

const pdfError = ref('');

// Handle iframe load error
const handleIframeError = () => {
    pdfError.value = 'Failed to load PDF. The URL may be invalid or inaccessible.';
};

const currentUrl = window.location.origin + '/rssms' + links.ESIGN;
const redirectURLs = props.redirectURL || currentUrl;

// Handle E-sign button click (placeholder)
const onEsign = async () => {

    // For this example, we'll assume the data to be sent includes the filePath.
    const esignData = {
        "reference_id": props.referenceId,
        "redirect_url": redirectURLs + `/${props.referenceId}`

    }
    const token = getAuthToken()
    if (!token) {
        return;
    }

    try {
        // 2. Call API
        // Replace with your actual e-signature API endpoint
        const response = await proceedEsignAddress(esignData)

        if (response.isError) {
            // const errorBody = await response.json().catch(() => ({ message: 'E-sign failed' }));
            throw new Error(response.message || `API error! Status: ${response.errors}`);
        }
        // 3. Emit Event to Parent and Close Modal
        // The payload (result) can be any data the parent needs (e.g., a transaction ID)
        emit('esignDone', { success: true, data: response?.esign_response });

        // Close the modal by updating the model value
        //isFilePath.value = false;

    } catch (err) {
        // error.value = err.message || 'E-signature process failed.';
        // Optionally, still emit to the parent if they need to handle the failure
        emit('esignDone', { success: false, error: err.message });
    } finally {
        //isSigning.value = false;
    }
};

// Close modal
const closeModal = () => {
    pdfError.value = '';
    emit('close', isFilePath.value = false)
};
</script>

<style scoped>
iframe {
    max-width: 100%;
}
</style>