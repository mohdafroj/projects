<template>
    <div class="w-full bg-white rounded-xl shadow-lg mt-10">
        <h1 class="text-2xl font-bold mb-6 text-center text-gray-800">E-Signature Verification</h1>

        <div v-if="loading" class="text-center p-6 bg-blue-50 rounded-lg border border-blue-200">
            <svg class="animate-spin h-5 w-5 text-blue-500 mx-auto mb-2" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor"
                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                </path>
            </svg>
            <p class="text-blue-700">Checking signature status...</p>
        </div>

        <div v-else-if="isEsign" class="text-center p-6 bg-red-50 rounded-lg border border-red-300">
            <svg class="h-8 w-8 text-red-500 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <h3 class="text-xl font-semibold text-red-800 mb-2">Signature Pending / Failed</h3>
            <p class="text-red-600">The e-signature process was not completed successfully. Please review the steps and
                try again.</p>
        </div>

        <div v-else class="space-y-4">
            <div class="text-center p-6 bg-green-50 rounded-lg border border-green-300">
                <svg class="h-8 w-8 text-green-500 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <h3 class="text-xl font-semibold text-green-800 mb-2">E-Signature Successful!</h3>
                <p class="text-green-600">The document has been successfully signed and verified. Below is the final
                    document.</p>
            </div>

            <iframe :src="pdfFile" class="w-full h-[100vh] border border-gray-300 rounded-lg shadow-inner"
                title="Signed PDF Preview" @error="handleIframeError" allow="autoplay; fullscreen">
            </iframe>
            <p v-if="iframeError" class="text-red-500 text-sm mt-2 text-center">
                {{ iframeError }}
            </p>
        </div>
    </div>
</template>

<script setup>
import { onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { getAuthToken } from './getTokenCookies';
import { ref } from 'vue';
import { fetchtEsignStatus } from '@/services/rss/esignServices';

const route = useRoute();
const pdfFile = ref('');
const isEsign = ref(false); // True if signature is NOT done/pending/failed
const loading = ref(true);   // Tracks API loading state
const iframeError = ref(''); // Tracks iframe loading failure

// Handle iframe loading error
const handleIframeError = () => {
    iframeError.value = 'Could not load PDF file. The file may be corrupt or inaccessible.';
}

const checkResponseStatus = async (id) => {
    loading.value = true;
    iframeError.value = ''; // Reset iframe error

    // Ensure ID is present before proceeding
    if (!id) {
        isEsign.value = true; // Treat as failure/pending
        loading.value = false;
        return;
    }

    const token = getAuthToken();
    if (!token) {
        isEsign.value = true;
        loading.value = false;
        return;
    }

    try {
        // const response = await fetch(`https://dev-auth.rajyasabha.digital/api/pdf/check-address/${id}`, {
        //     method: 'GET',
        //     headers: {
        //         'Content-Type': 'application/json',
        //         Authorization: token,
        //     },
        // });
        const response = await fetchtEsignStatus(id)

        if (response.isError) {
            // Read body for specific error message
          //  const errorBody = await response.json().catch(() => ({ message: 'Server check failed' }));
            throw new Error(response.message || `API error! Status: ${response.errors}`);
        }

        // 1. Check if the file is signed and the URL is provided
        if (response.data?.is_signed && response.data?.file_url) {
            pdfFile.value = response.data.file_url;
            isEsign.value = false; // Successfully signed
        } else {
            // 2. Not signed or file URL is missing
            isEsign.value = true; // Signature is pending/failed
        }

    } catch (err) {
        console.error("Error checking status:", err.message);
        // Treat any fetch/API error as a failure/pending state
        isEsign.value = true;
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    // Check status immediately on component mount, passing the ID from the route params
    // Using setTimeout is generally not necessary unless you have a specific UI reason 
    // to delay the API call slightly, but calling it directly is cleaner.
    checkResponseStatus(route.params?.id);
})
</script>