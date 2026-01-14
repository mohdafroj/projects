import { getMethod, postMethod } from '@/composables/useApi'
//import { useApiStore } from '@/store/apiData';

const local = 'test';
const getMemberCardRequest = (options = {}) => {
   return getMethod({ url: '/member/dashboard/cghs/get-cghs-card-member-listing', options }); 
} 

//member detail
const getFamilyCardRequest = (memberId, options = {}) => {
   return getMethod({ url: `/member/dashboard/cghs/get-cghs-card-member-family-details/${memberId}`, local });
}

//member verify and forword to approver
const verifyAndForwardToApprover = (memberId, options = {}) => {
   return getMethod({ url: `/member/dashboard/cghs/get-card-details-for-verify/${memberId}`, options });
}

//member verify and forword to approver
const saveDetailsForwardByApprover = (payload, options = {}) => {
   return postMethod({ url: `/member/dashboard/cghs/cghs-card-forward-to-approver`,payload,options}); 
} 

//Dashboard Counts for all status
const getCountCardByStatus = (options = {}) => {
   return getMethod({ url: `/member/dashboard/cghs/get-count-card-by-status`,options}); 
} 

//for approver screen pass I as nwe request and A for process request
const getProcessedCardsList = (mode, options = {}) => {
   return getMethod({ url: `/member/dashboard/cghs/get-processed-cghs-card-list/${mode}`, options });
}

const getAllCardsList = (options = {}) => {
   const queryString = new URLSearchParams(options).toString();
   return getMethod({ url: `/member/dashboard/cghs/get-cghs-all-card-listing?${queryString}`,options}); 
}

const getAllRemarks = (reqestId, options = {}) => {
   return getMethod({ url: `/member/dashboard/cghs/get-cghs-card-approvel-remarks/${reqestId}`, options });
}

const Approver_action = (payload,options = {}) => {
   options.headers = {'Content-Type':'multipart/form-data'}
   return postMethod({ url: `/member/dashboard/cghs/cghs-cards-approved-rejected`,payload,options}); 
} 

const eSign_approval = (payload,options = {}) => {
   return postMethod({ url: `/member/dashboard/cghs/check-esign-status`,payload,options}); 
}

// get member privew details
const getMemberPreviewDetails = (requestId, options = {}) => {
   return getMethod({ url: `/member/dashboard/cghs/get-member-preview-details/${requestId}`, options });
}

// get filter data for cghs_card_request_no list
const getAllCghsRequestList = (options = {}) => {
   return getMethod({ url: `/member/dashboard/cghs/getcardrequestno`, options });
}


// get filter data for full name list
const getAllMemberList = (options = {}) => {
   return getMethod({ url: `/member/dashboard/cghs/getmembername`, options });
}

// get filter data for Status list
const getAllStatus = (options = {}) => {
   return getMethod({ url: `/member/dashboard/cghs/getallstatus`, options });
}

// ==================web version add cards routs starts ======================//
const getAllUsersList = (options = {},client='report') => {
   return getMethod({ url: `/users/get-members`, options,client });
}
const getUserDetails = (requestId,options = {},client='report') => { 
   const qryStr = `?core_user_id=${requestId}`;
   return getMethod({ url: `/entitlement/get-member-details`+qryStr, options,client });
}
const saveMemberWebData = (payload,options = {},client='v2') => {
   return postMethod({ url: `/cghs/addcard`,payload,options,client}); 
}

const esignProcess = (payload,options = {},client='report') => {
   return postMethod({ url: `/cghs/check-esign-status`,payload,options,client}); 
}

// ==================View card API=================//
const getCghsCardDetails = (requestId,options = {},client='report') => { 
   const qryStr = `?core_user_id=${requestId}`;
   return getMethod({ url: `/entitlement/get-cghs-card-details`+qryStr, options,client });
}
// ==================View card API=================//
//DMS SERVICE
// const dmsFIleUpload = (payload,options = {},client='dms') => {
//    return postMethod({ url: `/member/dashboard/cghs/check-esign-status`,payload,options}); 
// }
// ==================web version add cards routs starts ======================//

// ==================Download card API=================//
const getDownloadCard = (memberId, options = {},client='report') => {
   return getMethod({ url: `/cghs/downloadcard/${memberId}`, options,client });
}
// ==================Download card API=================//

const addFamilyMember = (payload,options = {},client='v2') => {
   return postMethod({ url: `/cghs/add-family-card`,payload,options,client}); 
}

const getRelatives = (options = {},client='report') => {
   return getMethod({ url: `/common/get-vals-by-kw/MEMBER-RELATIONS`, options,client });
} 

export {
   getMemberCardRequest,
   getFamilyCardRequest,
   verifyAndForwardToApprover,
   saveDetailsForwardByApprover,
   getCountCardByStatus,
   getProcessedCardsList,
   getAllCardsList,getAllRemarks,
   Approver_action,
   eSign_approval,
   getMemberPreviewDetails,
   getAllCghsRequestList,
   getAllMemberList,
   getAllStatus,
   getAllUsersList,
   getUserDetails,saveMemberWebData,
   esignProcess,
   getCghsCardDetails,
   getDownloadCard,
   addFamilyMember,
   getRelatives
}