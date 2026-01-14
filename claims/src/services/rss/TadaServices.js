import { getMethod, postMethod, patchMethod } from '@/composables/useApi'


//  TADA API Calls
const TadaClaimList = (options) => {
    let filter = { member_id: 1 | 2, claimed_amount: 34 | 90, claim_date: 'yy|yy', claim_status_id: 1 | 2 }
    let sort_or_search = { order_by: 'member_name|claimed_amount|claim_date|claim_code|claim_status_id', order: 'asc|desc', search_by: 'member_name|claimed_amount|claim_code', search: 'keyword' }
    return getMethod({ url: '/rss/ta-da/claimlist', options });
}

const claimProgressById = (id) => {
    return getMethod({ url: '/rss/ta-da/claim-status/' + id });
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

const uploadFileChunk = async (formData) => {
    try {
        // const encryptedAxios = await initializeEncryptedAxios();

        const response = await uploadChunkFiles(formData);
        console.log("sad", response);

        return response;
    } catch (error) {
        console.error('Error uploading file chunk:', error);
        throw error;
    }
}
const claimDetailsById = (id) => {
    return getMethod({ url: '/rss/ta-da/claim-details/' + id });
}

const claimMemberFinanceById = (id) => {
    return getMethod({ url: '/rss/ta-da/membe-finance-details/' + id });
}


const getRulesByClaim = (id = 0, pnr = 'test') => {
  return getMethod({
    url: '/rss/ta-da/get-rules-by-claim/' + id,
    options: { params: { pnr: pnr || 'test' } }
  });
}



const SubmitRuleEngineRemarks = (payload = {}) => {
  return postMethod({
    url: '/rss/ta-da/accepted-rules',  // No id in URL
    payload: payload                    // Send payload as is, no id manipulation
  });
};

const getMemberAttendance  = (session, mpcode) => {
    return getMethod({ url: `/rss/ta-da/member-attendance/`, params: {
        session: session,
        mpcode: mpcode
      }
    });
}




const claimDivisionBudget = (id = 0) => {
    return getMethod({ url: '/rss/ta-da/division-budget-details' });
}

const getTemplates = (id = 0) => {
    return getMethod({ url: '/rss/ta-da/gettemplates' });
}

const addNote = (payload) => {
    return postMethod({ url: '/rss/ta-da/addnote', payload });
}


const getTadaPurposeVisit = () => {
    return getMethod({ url: `common/get-vals-by-kw/TADA_PURPOSE_VISIT`, client: 'apims' });
}

const updateNote = (id = 0, payload) => {
    return patchMethod({ url: '/rss/ta-da/addnote/' + id, payload });
}

const getUsers = () => {
    return getMethod({ url: '/rss/ta-da/getusers' });
}

const processClaim = (id, payload) => {
    return postMethod({ url: '/rss/ta-da/processclaim/' + id, payload });
}

const forwardedClaim = (options = {}) => {
    return getMethod({ url: '/rss/ta-da/forwardedclaim', options });
}

const pullbackClaim = (id = 0) => {
    return getMethod({ url: '/rss/ta-da/pullbackclaim/' + id });
}

const processEsign = (id = 0, payload) => {
    return postMethod({ url: '/rss/ta-da/processesign/' + id, payload });
}

const checkeSignStatus = (id = 0) => {
    return getMethod({ url: '/rss/ta-da/checkssignstatus/' + id });
}

const resendEsignNotification = (id = 0) => {
    return getMethod({ url: '/rss/ta-da/resendesignnotification/' + id });
}

const getStatusHistory = (id = 0, options = {}) => {
    return getMethod({ url: '/rss/ta-da/statushistory/' + id, options });
}

const getTemplateContent = (claimId, templateId = 0, options = {}) => {
    return getMethod({ url: '/rss/ta-da/gettemplatecontent/' + claimId + '/' + templateId, options });
}

export {
    TadaClaimList,
    claimProgressById,
    claimDetailsById,
    claimMemberFinanceById,
    claimDivisionBudget,
    getTemplates,
    addNote,
    updateNote,
    getUsers,
    uploadFileChunk,
    processClaim,
    getTadaPurposeVisit,
    forwardedClaim,
    pullbackClaim,
    processEsign,
    SubmitRuleEngineRemarks,
    getMemberAttendance,
    getRulesByClaim,
    checkeSignStatus,
    resendEsignNotification,
    getStatusHistory,
    getTemplateContent
    
};

