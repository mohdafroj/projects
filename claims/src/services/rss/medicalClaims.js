import { getMethod,postMethod } from '@/composables/useApi' 
//getting counts like- Total Claim, Completed Claims, Pending Claims,Overdue Claims
//Parameter-Total Claim(T), Completed Claims(C),Pending Claims(P), Overdue Cl(O)
const getCountClaimByStatus = (options = {}) => {
   return getMethod({ url: `/member/dashboard/medical-claim/get-count-claim-by-status`, options }); 
}

const getMedicalClaimList = (options = {}) => {
   const queryString = new URLSearchParams(options).toString();
   return getMethod({ url: `/member/dashboard/medical-claim/get-medical-claim-list?${queryString}`, options }); 
}

const getMedicalClaimDetails = (claimid,options = {}) => {
   return getMethod({ url: `/member/dashboard/medical-claim/get-medical-claim-details/${claimid}`, options }); 
}

const getNotesListing = (claimid,options = {}) => {
   return getMethod({ url: `/member/dashboard/medical-claim/getnotehistory/${claimid}`, options }); 
}
 
const addnote = (payload,options = {}) => {
  // options.headers = {'Content-Type':'multipart/form-data'}
   return postMethod({ url: `/member/dashboard/medical-claim/addnote`,payload,options}); 
} 

//initiator saveing data
const initiatorSaveData = (payload,options = {}) => {
  // options.headers = {'Content-Type':'multipart/form-data'}
   return postMethod({ url: `/member/dashboard/medical-claim/medical-claim-process-by-initiator`,payload,options}); 
}
//initiator saveing data 



export{
  getCountClaimByStatus,
  getMedicalClaimList,
  getMedicalClaimDetails,
  getNotesListing,
  addnote,
  initiatorSaveData,
}