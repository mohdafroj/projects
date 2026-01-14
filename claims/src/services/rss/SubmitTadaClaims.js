
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

// Get child categories for a specific parent category
const getChildCategories = (param) => {
    return getMethod({ url: `/common/get-child`, options: { params: param }, client: 'apims' });
}

// Search for IT equipment products
const searchProducts = () => {
    return getMethod({ url: `/common/get-vals-by-kw/IT-EQUIPMENT`, client: 'apims' });
}

// Submit a new claim
const submitClaim = (payload) => {
    return postMethod({ url: '/entitlement/new-claim', payload, client: 'v2' });
}
const addNewClaim = (payload) => {
    
     return postMethod({ url: `entitlement/ta-da/new-claim`, client: 'apims', payload });
}

// POST: TA-DA allowance based on journeys + purpose
const getDaAllowance = (payload = {}) => {
  return postMethod({
    url: 'entitlement/ta-da/get-session-committee-da',
    client: 'apims',
    payload
  });
};

// Updated service function to handle multiple query parameters
const getTicketDetails = (params) => {
    return getMethod({ 
        url: '/airlines/gettickerdetails', 
        options: { params: params },
        client: 'airlines' 
    });
}


export {
    getMembers,
    getFinanceDetails,
    getAccountDetails,
    getInvoiceDetail,
    searchProducts,
    submitClaim,
    getTicketDetails,
    addNewClaim,
   getDaAllowance,
    getChildCategories

};

