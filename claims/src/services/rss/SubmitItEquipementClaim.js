import { getMethod, postMethod } from '@/composables/useApi'
import axios from 'axios';
import { createEncryptedFileAxios } from "@/composables/encreptionFileupload";

// Create encrypted axios instance
let encryptedAxiosInstance = null;

// Initialize encrypted axios
const initializeEncryptedAxios = async () => {
    if (!encryptedAxiosInstance) {
        encryptedAxiosInstance = await createEncryptedFileAxios(axios.create());
    }
    return encryptedAxiosInstance;
}

// Get members list for search
const getMembers = () => {
    return getMethod({ url: '/users/get-members', client: 'apims' });
}

// Get finance details for selected member
const getFinanceDetails = (core_user_id) => {
    return getMethod({ url: `/entitlement/get-finance-details?core_user_id=${core_user_id}`, client: 'apims' });
}

const getInvoiceDetail = (payload) => {
    return postMethod({ url: `/entitlement/get-invoice-detail`, payload, client: 'apims' });
}

// Get account details for selected member
const getAccountDetails = (core_user_id) => {
    return getMethod({ url: `entitlement/get-account-details?core_user_id=${core_user_id}`, client: 'apims' });
}

const getClaimTrackId = () => {
    return getMethod({ url: `entitlement/get-claim-track-id`, client: 'apims' });
}

// Get child categories for a specific parent category
const getChildCategories = (param) => {
    return getMethod({ url: `/common/get-child`, options: { params: param }, client: 'apims' });
}

// Search for IT equipment products
const searchProducts = () => {
    return getMethod({ url: `/common/get-vals-by-kw/IT-EQUIPMENT`, client: 'apims' });
}

// get DA allowence based on session and committee
const getSessionCommitteeDa = (coreUserId, purposeOfVisit) => {
    return getMethod({ 
        url: `/rss/ta-da/get-session-committee-da`,
        options: {
            params: {
                core_user_id: coreUserId,
                purpose_of_visit: purposeOfVisit
            }
        }
    });
}

// Submit a new claim
const submitClaim = (payload) => {
    return postMethod({ url: '/entitlement/new-claim', payload, client: 'v2' });
}
const addNewClaim = (payload) => {
    
     return postMethod({ url: `entitlement/ta-da/new-claim`, client: 'apims', payload });
}

const uploadChunkFiles = (payload = {}) => {
    return postMethod({
        url: 'dms/uploadchunksfile', payload, options: {
            headers: {
                'Content-Type': 'multipart/form-data',
                'Accept-Language': 'en'
            },
        }, client: 'dms'
    });
}
// Upload a chunk to DMS server
const uploadFileChunk = async (formData) => {
    try {
        const response = await uploadChunkFiles(formData);

        return response;
    } catch (error) {
        console.error('Error uploading file chunk:', error);
        throw error;
    }
}

const removeFiles = (payload) => {
    return postMethod({
        url: 'dms/removefiles', payload: payload, options: {
            headers: {
                'Accept-Language': 'en'
            }
        }, client: 'dms'
    })
}

// Upload claim invoice/document
const uploadInvoice = (formData) => {
    return postMethod({
        url: '/upload-claim-invoice',
        payload: formData,
        headers: {
            'Content-Type': 'multipart/form-data'
        }
    });
}

export {
    getMembers,
    getFinanceDetails,
    getAccountDetails,
    getInvoiceDetail,
    getClaimTrackId,
    searchProducts,
    submitClaim,
    addNewClaim,
    uploadInvoice,
    getChildCategories,
    uploadFileChunk,
    removeFiles,
    getSessionCommitteeDa
};