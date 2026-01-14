import { getMethod, postMethod, patchMethod } from '@/composables/useApi'


const claimCount = () => {
    return getMethod({ url: '/rss/itequipments/claimcount' });
}

const memberList = () => {
    return getMethod({ url: '/rss/itequipments/memberlist' });
}
const claimStatusList = () => {
    return getMethod({ url: '/rss/itequipments/claimstatuslist' });
}
const claimList = (options) => {
    let filter = { member_id: 1 | 2, claimed_amount: 34 | 90, claim_date: 'yy|yy', claim_status_id: 1 | 2 }
    let sort_or_search = { order_by: 'member_name|claimed_amount|claim_date|claim_code|claim_status_id', order: 'asc|desc', search_by: 'member_name|claimed_amount|claim_code', search: 'keyword' }
    return getMethod({ url: '/rss/itequipments/claimlist', options });
}

const claimProgressById = (id) => {
    return getMethod({ url: '/rss/itequipments/claimprogress1/' + id });
}

const claimDetailsById = (id) => {
    return getMethod({ url: '/rss/itequipments/claimdetails/' + id });
}

const claimMemberFinanceById = (id) => {
    return getMethod({ url: '/rss/itequipments/membe-finance-details/' + id });
}

const claimDivisionBudget = (id = 0) => {
    return getMethod({ url: '/rss/itequipments/division-budget-details' });
}

const getTemplates = (id = 0) => {
    return getMethod({ url: '/rss/itequipments/gettemplates' });
}

const addNote = (payload) => {
    return postMethod({ url: '/rss/itequipments/addnote', payload });
}

const updateNote = (id = 0, payload) => {
    return patchMethod({ url: '/rss/itequipments/addnote/' + id, payload });
}

const getUsers = () => {
    return getMethod({ url: '/rss/itequipments/getusers' });
}

const processClaim = (id, payload) => {
    return postMethod({ url: '/rss/itequipments/processclaim/' + id, payload });
}

const forwardedClaim = (options = {}) => {
    return getMethod({ url: '/rss/itequipments/forwardedclaim', options });
}

const pullbackClaim = (id = 0) => {
    return getMethod({ url: '/rss/itequipments/pullbackclaim/' + id });
}

const processEsign = (id = 0, payload) => {
    return postMethod({ url: '/rss/itequipments/processesign/' + id, payload });
}

const checkeSignStatus = (id = 0) => {
    return getMethod({ url: '/rss/itequipments/checkssignstatus/' + id });
}

const resendEsignNotification = (id = 0) => {
    return getMethod({ url: '/rss/itequipments/resendesignnotification/' + id });
}

const getStatusHistory = (id = 0, options = {}) => {
    return getMethod({ url: '/rss/itequipments/statushistory/' + id, options });
}

const getTemplateContent = (claimId, templateId = 0, options = {}) => {
    return getMethod({ url: '/rss/itequipments/gettemplatecontent/' + claimId + '/' + templateId, options });
}

const updateClaimItems = (claimId, payload) => {
    return patchMethod({ url: '/rss/itequipments/updateclaimitems/' + claimId, payload });
}

const memberClaims = (claimId) => {
    return getMethod({ url: '/rss/itequipments/memberclaims/' + claimId });
}

const backToMember = (claimId) => {
    return getMethod({ url: '/rss/itequipments/claimreturntomember/' + claimId });
}

export {
    claimCount,
    memberList,
    claimStatusList,
    claimList,
    claimDetailsById,
    claimProgressById,
    claimMemberFinanceById,
    claimDivisionBudget,
    getTemplates,
    addNote,
    updateNote,
    getUsers,
    processClaim,
    forwardedClaim,
    pullbackClaim,
    processEsign,
    checkeSignStatus,
    resendEsignNotification,
    getStatusHistory,
    getTemplateContent,
    updateClaimItems,
    memberClaims,
    backToMember
};

