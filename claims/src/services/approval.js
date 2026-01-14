import { getMethod, postMethod, patchMethod } from '@/composables/useApi'
const reqoptions = {
  headers: { 'Accept-Language': 'en' },
};

const fetchSendTo = (options = {}) => {
  options = { ...reqoptions, ...options };
  return getMethod({
    url: '/approval/get-send-to',
    options,
   
  });
};

const fetchApproval = (act, options = {}) => {
  options = { ...reqoptions, ...options };
  console.log('Action', act);
  return getMethod({
    url: '/approval' + '?action=' + (act ?? 0),
    options,
   
  });
};

const fetchApprovalView = (options = {}) => {
  options = { ...reqoptions, ...options };
  return getMethod({
    url: '/approval/view',
    options,
   
  });
};

const fetchNotes = (mod_id, options = {}) => {
  options = { ...reqoptions, ...options };
  return getMethod({
    url: '/approval/remark/' + mod_id,
    options,
   
  });
};

const postNotes = (mod_id, payload = {}) => {
  const options = { ...reqoptions };
  return postMethod({
    url: '/approval/remarks/' + mod_id,
    options,
    payload,
   
  });
};

const getAllNotes = (mod_id, options = {}) => {
  options = { ...reqoptions, ...options };
  return getMethod({
    url: '/approval/remarks/' + mod_id,
    options,
  });
};

const postConvertNote = (mod_id, payload = {}) => {
  const options = { ...reqoptions };
  return postMethod({
    url: '/approval/remarks/convert/' + mod_id,
    options,
    payload,
   
  });
};

const discardNotes = (mod_id, note_id) => {
  const options = { ...reqoptions };
  return getMethod({
    url: '/approval/remarks/discard/' + mod_id + '/' + note_id,
    options,
  });
};

const submitReview = (module_id, payload = {}) => {
  const options = { ...reqoptions };
  return postMethod({
    url: '/approval/review/' + module_id,
    options,
    payload,
   
  });
};

const submitApproval = (module_id, draft_id, payload = {}) => {
  const options = { ...reqoptions };
  return postMethod({
    url: '/approval/approve' + module_id + '/' + draft_id,
    options,
    payload,
   
  });
};

const sendBackToApproval = (mod_id, payload = {}) => {
  const options = { ...reqoptions };
  return postMethod({
    url: '/approval/sendback/' + mod_id,
    options,
    payload,
   
  });
};

const PullBackApproval = (mod_id, payload = {}) => {
  const options = { ...reqoptions };
  return postMethod({
    url: '/approval/pullback/' + mod_id,
    options,
    payload,
   
  });
};

const sendEsign = (module_id, draft_id, payload = {}) => {
  const options = { ...reqoptions };
  return postMethod({
    url: '/approval/status-esign/' + module_id + '/' + draft_id,
    options,
    payload,
   
  });
};

const statusEsign = (module_id, payload = {}) => {
  const options = { ...reqoptions };
  return getMethod({
    url: '/approval/status-esign/' + module_id,
    options,
    payload,
   
  });
};

const postNoticeUpdate = (notice_id, payload = {}) => {
  const options = { ...reqoptions };
  return postMethod({
    url: '/approval/meeting-notice/notice-update/' + notice_id,
    options,
    payload,
   
  });
};

const getDraftList = (module_id, options = {}) => {
  options = { ...reqoptions, ...options };
  return getMethod({
    url: '/approval/draft/list/' + module_id,
    options,
   
  });
};

const getDraftAll = (module_id, options = {}) => {
  options = { ...reqoptions, ...options };
  return getMethod({
    url: '/approval/draft/all/' + module_id,
    options,
   
  });
};

const postDraftSave = (module_id, payload = {}) => {
  const options = { ...reqoptions };
  return postMethod({
    url: '/approval/draft/save/' + module_id,
    options,
    payload,
   
  });
};

const getTocList = (module_id, options = {}) => {
  options = { ...reqoptions, ...options };
  return getMethod({
    url: '/approval/toc/list/' + module_id,
    options,
   
  });
};

const getTocView = (toc_id, payload = {}) => {
  const options = { ...reqoptions };
  return getMethod({
    url: '/approval/toc/view/' + toc_id,
    options,
    payload,
   
  });
};

const postTocSave = (module_id, payload = {}) => {
  const options = { ...reqoptions };
  return postMethod({
    url: '/approval/toc/save/' + module_id,
    options,
    payload,
   
  });
};

const postTocMark = (payload = {}) => {
  const options = { ...reqoptions };
  return postMethod({
    url: '/approval/toc/mark',
    options,
    payload,
   
  });
};

export {
  fetchSendTo,
  fetchApproval,
  fetchApprovalView,
  fetchNotes,
  postNotes,
  getAllNotes,
  postConvertNote,
  discardNotes,
  submitReview,
  submitApproval,
  sendBackToApproval,
  PullBackApproval,
  sendEsign,
  statusEsign,
  postNoticeUpdate,
  getDraftList,
  getDraftAll,
  postDraftSave,
  getTocList,
  getTocView,
  postTocSave,
  postTocMark,
};
