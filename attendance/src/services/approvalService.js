import {
  getMethod, postMethod
} from '@/composables/useApi';

const API = {
  GET_LIST_APPROVAL: `/approval`,
  GET_APPROVAL_VIEW: `/approval/view`,
  GET_SEND_TO: `/approval/get-send-to`,
  GET_REMARK_APPROVAL: `/approval/remarks/`,
  POST_REMARK_APPROVAL: `/approval/remarks/`,
  GET_REMARK_DISCARD: `/approval/remarks/discard/`,
  POST_REMARK_CONVERT: `/approval/remarks/convert/`,
  POST_APPROVAL_SEND_REVIEW: `/approval/review/`,
  POST_APPROVED: `/approval/approve/`,
  POST_APPROVAL_SEND_BACK: `/approval/sendback/`,
  POST_APPROVAL_PULL_BACK: `/approval/pullback/`,
  POST_APPROVAL_ESIGN: `/approval/send-esign/`,
  GET_STATUS_ESIGN: `/approval/status-esign/`,
  POST_NOTICE_UPDATE: `/meeting-notice/notice-update/`,
  GET_DRAFT_LIST: `/approval/draft/list/`,
  GET_DRAFT_ALL: `/approval/draft/all/`,
  POST_DRAFT_SAVE: `/approval/draft/save/`,
  GET_TOC_LIST: `/approval/toc/list/`,
  GET_TOC_VIEW: `/approval/toc/view/`,
  POST_TOC_SAVE: `/approval/toc/save/`,
  POST_TOC_MARK: `/approval/toc/mark`,
};

const reqoptions = {
  headers: { 'Accept-Language': 'en' },
};

const client = 'approval'
const clientRSS = 'session';
let prefixClientRSS = '';

export const initClient = (param) => {
  prefixClientRSS = (clientRSS == 'session') ? param : '';
}

export const fetchSendTo = async (options = {}) => {
  options = { ...reqoptions, ...options };
  return await getMethod({
    url: API.GET_SEND_TO,
    options,
    client
  });
};

export const fetchApproval = async (act, options = {}) => {
  options = { ...reqoptions, ...options };
  return await getMethod({
    url: API.GET_LIST_APPROVAL + '?action=' + (act ?? 0),
    options,
    client
  });
};

export const fetchApprovalView = async (options = {}) => {
  options = { ...reqoptions, ...options };
  return await getMethod({
    url: API.GET_APPROVAL_VIEW,
    options,
    client
  });
};

export const fetchNotes = async (mod_id, options = {}) => {
  options = { ...reqoptions, ...options };
  return await getMethod({
    url: API.GET_REMARK_APPROVAL + mod_id,
    options,
    client
  });
};

export const postNotes = async (mod_id, payload = {}) => {
  const options = { ...reqoptions };
  return await postMethod({
    url: API.POST_REMARK_APPROVAL + mod_id,
    options,
    payload,
    client
  });
};

export const postConvertNote = async (mod_id, payload = {}) => {
  const options = { ...reqoptions };
  return await postMethod({
    url: API.POST_REMARK_CONVERT + mod_id,
    options,
    payload,
    client
  });
};

export const discardNotes = async (mod_id, note_id) => {
  const options = { ...reqoptions };
  return await getMethod({
    url: API.GET_REMARK_DISCARD + mod_id + '/' + note_id,
    options,
    client
  });
};

export const submitReview = async (module_id, payload = {}) => {
  const options = { ...reqoptions };
  return await postMethod({
    url: prefixClientRSS + API.POST_APPROVAL_SEND_REVIEW + module_id,
    options,
    payload,
    client: clientRSS
  });
};

export const submitApproval = async (module_id, draft_id, payload = {}) => {
  const options = { ...reqoptions };
  return await postMethod({
    url: API.POST_APPROVED + module_id + '/' + draft_id,
    options,
    payload,
    client
  });
};

export const sendBackToApproval = async (mod_id, payload = {}) => {
  const options = { ...reqoptions };
  return await postMethod({
    url: prefixClientRSS + API.POST_APPROVAL_SEND_BACK + mod_id,
    options,
    payload,
    client: clientRSS
  });
};

export const PullBackApproval = async (mod_id, payload = {}) => {
  const options = { ...reqoptions };
  return await postMethod({
    url: API.POST_APPROVAL_PULL_BACK + mod_id,
    options,
    payload,
    client
  });
};

export const sendEsign = async (module_id, draft_id, payload = {}) => {
  const options = { ...reqoptions };
  return await postMethod({
    url: API.POST_APPROVAL_ESIGN + module_id + '/' + draft_id,
    options,
    payload,
    client
  });
};

export const statusEsign = async (module_id, payload = {}) => {
  const options = { ...reqoptions };
  return await getMethod({
    url: API.GET_STATUS_ESIGN + module_id,
    options,
    payload,
    client
  });
};

export const postNoticeUpdate = async (notice_id, payload = {}) => {
  const options = { ...reqoptions };
  return await postMethod({
    url: API.POST_NOTICE_UPDATE + notice_id,
    options,
    payload,
    client
  });
};

export const getDraftList = async (module_id, options = {}) => {
  options = { ...reqoptions, ...options };
  return await getMethod({
    url: API.GET_DRAFT_LIST + module_id,
    options,
    client
  });
};

export const getDraftAll = async (module_id, options = {}) => {
  options = { ...reqoptions, ...options };
  return await getMethod({
    url: API.GET_DRAFT_ALL + module_id,
    options,
    client
  });
};

export const postDraftSave = async (module_id, payload = {}) => {
  const options = { ...reqoptions };
  return await postMethod({
    url: API.POST_DRAFT_SAVE + module_id,
    options,
    payload,
    client
  });
};

export const getTocList = async (module_id, options = {}) => {
  options = { ...reqoptions, ...options };
  return await getMethod({
    url: API.GET_TOC_LIST + module_id,
    options,
    client
  });
};

export const getTocView = async (toc_id, payload = {}) => {
  const options = { ...reqoptions };
  return await getMethod({
    url: API.GET_TOC_VIEW + toc_id,
    options,
    payload,
    client
  });
};

export const postTocSave = async (module_id, payload = {}) => {
  const options = { ...reqoptions };
  return await postMethod({
    url: API.POST_TOC_SAVE + module_id,
    options,
    payload,
    client
  });
};

export const postTocMark = async (payload = {}) => {
  const options = { ...reqoptions };
  return await postMethod({
    url: API.POST_TOC_MARK,
    options,
    payload,
    client
  });
};
