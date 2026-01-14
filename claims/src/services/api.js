const ENV = import.meta.env;
const COMMITTEE_API_PREFIX =
  ENV.VITE_COMMITTEE_API_PREFIX || 'committee/api/v1';

export default {
  // *******************************************APPROVAL BEGIN******************************************
  GET_LIST_APPROVAL: `${COMMITTEE_API_PREFIX}/portal/approval`,
  GET_APPROVAL_VIEW: `${COMMITTEE_API_PREFIX}/portal/approval/view`,
  GET_SEND_TO: `${COMMITTEE_API_PREFIX}/portal/approval/get-send-to`,
  GET_REMARK_APPROVAL: `${COMMITTEE_API_PREFIX}/portal/approval/remarks/`,
  POST_REMARK_APPROVAL: `${COMMITTEE_API_PREFIX}/portal/approval/remarks/`,
  GET_REMARK_DISCARD: `${COMMITTEE_API_PREFIX}/portal/approval/remarks/discard/`,
  POST_REMARK_CONVERT: `${COMMITTEE_API_PREFIX}/portal/approval/remarks/convert/`,
  POST_APPROVAL_SEND_REVIEW: `${COMMITTEE_API_PREFIX}/portal/approval/review/`,
  POST_APPROVED: `${COMMITTEE_API_PREFIX}/portal/approval/approve/`,
  POST_APPROVAL_SEND_BACK: `${COMMITTEE_API_PREFIX}/portal/approval/sendback/`,
  POST_APPROVAL_PULL_BACK: `${COMMITTEE_API_PREFIX}/portal/approval/pullback/`,
  POST_APPROVAL_ESIGN: `${COMMITTEE_API_PREFIX}/portal/approval/send-esign/`,
  GET_STATUS_ESIGN: `${COMMITTEE_API_PREFIX}/portal/approval/status-esign/`,
  POST_NOTICE_UPDATE: `${COMMITTEE_API_PREFIX}/portal/meeting-notice/notice-update/`,
  GET_DRAFT_LIST: `${COMMITTEE_API_PREFIX}/portal/approval/draft/list/`,
  GET_DRAFT_ALL: `${COMMITTEE_API_PREFIX}/portal/approval/draft/all/`,
  POST_DRAFT_SAVE: `${COMMITTEE_API_PREFIX}/portal/approval/draft/save/`,
  GET_TOC_LIST: `${COMMITTEE_API_PREFIX}/portal/approval/toc/list/`,
  GET_TOC_VIEW: `${COMMITTEE_API_PREFIX}/portal/approval/toc/view/`,
  POST_TOC_SAVE: `${COMMITTEE_API_PREFIX}/portal/approval/toc/save/`,
  POST_TOC_MARK: `${COMMITTEE_API_PREFIX}/portal/approval/toc/mark`,
  // *******************************************APPROVAL END******************************************
};
