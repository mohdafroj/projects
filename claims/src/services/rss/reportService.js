import { getMethod } from '@/composables/useApi'

const claimReportList = (options) => {
    return getMethod({ url: 'entitlement/reports/get-claims/it-equipment', options, client: 'report' });
}
const claimDetailsList = (options, id) => {
    return getMethod({ url: 'entitlement/reports/get-claim-by-id/' + id, options, client: 'report' });
}
const cghsReportList = (options) => {
    return getMethod({ url: 'entitlement/reports/get-claims/cghs', options, client: 'report' });
}

export {
    claimReportList,
    claimDetailsList,
    cghsReportList,
};

