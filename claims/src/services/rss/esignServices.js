import { getMethod, postMethod } from '@/composables/useApi';

// Send claimId + requestId in the request body (not query string)
const getEsignStatus = (requestId, claimId) => {
  return postMethod({
    url: '/entitlement/check-esign-status',
    payload: {
      requestId,
      claim_id: claimId
    },
    client: 'apims' // ✅ if your API needs versioning
  });
};

//saveEsignAddress verify and forword to approver
const saveEsignAddress = (payload, options = {}) => {
  return postMethod({ url: `api/pdf/upload-address`, payload, options, client: 'esign' });
}

//proceedEsignAddress verify and forword to approver
const proceedEsignAddress = (payload, options = {}) => {
  return postMethod({ url: `api/pdf/esign-address`, payload, options, client: 'esign' });
}
//getEsignStatus detail
const fetchtEsignStatus = (id) => {
  return getMethod({ url: `api/pdf/check-address/${id}`, client: 'esign' });
}
export {
  getEsignStatus,
  saveEsignAddress,
  proceedEsignAddress,
  fetchtEsignStatus
};
