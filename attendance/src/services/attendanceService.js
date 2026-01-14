import { deleteMethod, getMethod, patchMethod, postMethod, putMethod } from "@/composables/useApi";
import { useApiStore } from "@/store/apiData";
const apiStore = useApiStore();

const client = 'session';
const fetchReasons = async (type = 'leave') => {
  const response = await getMethod({ url: "get-reason", options: { params: { type } }, client: 'rs' });
  let result = [];
  if (response.isError == false && response.success_code == 200 && response?.data && response?.data.length) {
    //apiStore.setReason({ ...apiStore.reason, [type]: response.data });
    result = response.data.map(item => ({ ...item, label: item.text, value: item.text }));
  }
  return result;
}

const fetchSessions = async () => {
  let result = [];
  const response = await getMethod({ url: "attendance/session-list", client });
  if (response.isError == false && response.success_code == 200 && response?.data && response?.data.length) {
    result = await response.data.map((item) => {
      //return { ...item, name: item.session_type + ' (' + item.session_year + '- ' + item.session_number + ')' };
      return { ...item, name: 'Session ' + item.session_number };
    });
  }
  if (result.length) {
    apiStore.setSession({ ...result[0], list: result })
  };
  return result;
}

const fetchActiveMemberList = async (id, options = {}) => {
  options.params = { ...options.params, is_minister: 1 }
  return await getMethod({ url: "attendance/getactivememberlist", options, client });
}

const fetchStylusSign = async (options) => {
  return await getMethod({ url: "attendance/stylussigndata", options, client });
}

const resetAttendance = async (id) => {
  return await patchMethod({ url: "attendance/resetattendance/" + id, payload: {}, client });
}

const fetchHistoryFilters = async (id) => {
  return await getMethod({ url: "attendance/sessiondetails/" + id, client });
}

const fetchAttendanceHistory = async (id, options) => {
  return await getMethod({ url: "attendance/list/" + id, options, client });
}

const fetchLeaveRequestFilters = async (id) => {
  const options = { params: { session_id: id } };
  return await getMethod({ url: "leave-request/filter-data", options, client });
}

const fetchAttendanceLeaveRequest = async (options) => {
  return await getMethod({ url: "leave-request/list", options, client });
}

const addLeaveRequest = async (payload) => {
  return await postMethod({ url: 'leave-request/add', payload, client });
}

const leaveRequestProcess = async (id) => {
  return await patchMethod({ url: 'leave-request/processleave/' + id, client });
}

export const leaveRequestDetail = async (id) => {
  return await getMethod({ url: 'leave-request/details/' + id, client });
}

export const memberAbsentlist = async (options) => {
  return await getMethod({ url: 'attendance/absentmeberlist', options, client });
}

export const leaveRequestUpdateDraft = async (id) => {
  return await patchMethod({ url: 'leave-request/updatedraft/' + id, client });
}

const approveLeaveRequest = async (id, payload) => {
  return await postMethod({ url: 'leave-request/approve/' + id, payload, client });
}

const fetchRegularizationFilters = async (id) => {
  const options = { params: { session_id: id } };
  return await getMethod({ url: "leave-regularization/filter-data", options, client });
}

const fetchRegularizationList = async (options) => {
  return await getMethod({ url: "leave-regularization/list", options, client });
}

const addLeaveRegularizationRequest = async (payload) => {
  return await postMethod({ url: 'leave-regularization/add', payload, client });
}

const approveRegulizationRequest = async (id, payload) => {
  return await postMethod({ url: 'leave-regularization/approve/' + id, payload, client });
}

// const fetchDailyAttendance = async ({ session_id, date }) => {
//   const options = { params: { date, session_id } };
//  return await getMethod({ url: 'attendance/dashboard', options, client });
// };

const fetchDailyAttendance = async ({ date }) => {
  const options = { params: { date } };
  return await getMethod({ url: 'attendance/dashboard', options, client });
};

export const fetchRecordCount = async ({ date }) => {
  const options = { params: { date } };
  return await getMethod({ url: 'attendance/getrecordcount', options, client });
};

const fetchDailyPartywiseAttendance = async ({ session_id, date, party_code }) => {
  const options = { params: { session_id, date, party_code } };
  return await getMethod({ url: 'attendance/partywiseattendance', options, client });
};

const getFilterReportDashboard = async (options) => {
  return await getMethod({ url: 'attendance/getattendancedropdowndata', options, client });
}
const getReportDashboard = async (options) => {
  //const options = { params: { session_id } };
  return await getMethod({ url: 'attendance/attendancepdfdata', options, client });

}
const publishReportDashboard = async (payload) => {
  return await postMethod({ url: 'attendance/publishattedance', payload, client });
}

const downloadReport = async (payload) => {
  // const payload = { params: { session_id } };
  return await postMethod({ url: '/attendance/generateattendancepdf', payload, client });
}
const downloadsignedFile = async (media_id) => {
  return await getMethod({ url: '/attendance/getqrdata/' + `${media_id}`, client });
}
const updateEsign = async (requestId) => {
  const options = { params: { requestId } };
  return await getMethod({ url: 'attendance/esign-status', options, client });
}
const resendNotificationApi = async (media_id) => {
  return await getMethod({ url: 'attendance/resendnotification/' + `${media_id}`, client });
}

const fetchPaperSigned = async (options) => {
  return await getMethod({ url: 'attendance/getpapersingdata', options, client });
}

const approvePaperSigned = async (id, payload) => {
  return await postMethod({ url: 'attendance/approvepapersign/' + id, payload, client });
}

const msaDownloadReport = async (date) => {
  const options = { params: { date } };
  return await getMethod({
    url: "attendance/getattendancepdfurl",
    options,
    client,
  });
};

const shareReportByEmail = async (payload) => {
  return await postMethod({ url: 'attendance/emailpdffile', payload, client });
}

//Start of attendance reminder section
const lobbyOfficeReminderList = async (params) => {
  //const params = { session_number: "269" };
  return await getMethod({ url: 'attendance/list-lobby-office-reminder', options: { params }, client });
}

const lobbyOfficeReminderCreate = async (payload) => {
  //const payload = { reminder_time: "14:39", session_number: "269" };
  return await postMethod({ url: 'attendance/create-lobby-office-reminder', payload, client });
}

const lobbyOfficeReminderUpdate = async (id, payload) => {
  //const payload = { reminder_time: "14:39", session_number: "269", status: "1/0" };
  return await putMethod({ url: 'attendance/update-lobby-office-reminder/' + id, payload, client });
}

const lobbyOfficeReminderDelete = async (id) => {
  return await deleteMethod({ url: 'attendance/delete-lobby-office-reminder/' + id, client });
}

const lobbyOfficeReminderChangeStatus = async (payload) => {
  //const payload = { date: "2025-11-10", session_number: "269" };
  return await postMethod({ url: 'attendance/change-sitting-reminder-status', payload, client });
}

const sendLobbyOfficeReminder = async (payload) => {
  return await postMethod({ url: 'attendance/send-lobby-office-reminder', payload, client });
}

const disableAutoReminder = async (payload) => {
  return await postMethod({ url: 'attendance/disable-auto-reminder', payload, client });
}
//End of attendance reminder section

const staffList = async (options) => {
  return await getMethod({ url: '/rbac/users-by-division', options, client: 'rbac' });
}

const toFinalizeAteendace = async (options) => {
  return await getMethod({ url: 'attendance/addabsentattendance', options, client });
}

const fetchConsolidatedReport = async (options = {}) => {
  return await getMethod({ url: "/attendance-after-publish", options, client: 'rs' });
}

export {
  fetchReasons,
  fetchSessions,
  fetchActiveMemberList,
  fetchStylusSign,
  resetAttendance,
  fetchHistoryFilters,
  fetchAttendanceHistory,
  fetchLeaveRequestFilters,
  addLeaveRequest,
  fetchRegularizationFilters,
  fetchAttendanceLeaveRequest,
  fetchRegularizationList,
  addLeaveRegularizationRequest,
  approveRegulizationRequest,
  leaveRequestProcess,
  approveLeaveRequest,
  fetchDailyAttendance,
  fetchDailyPartywiseAttendance,
  getFilterReportDashboard,
  getReportDashboard,
  publishReportDashboard,
  downloadReport,
  updateEsign,
  downloadsignedFile,
  resendNotificationApi,
  fetchPaperSigned,
  approvePaperSigned,
  msaDownloadReport,
  shareReportByEmail,
  lobbyOfficeReminderList,
  lobbyOfficeReminderCreate,
  lobbyOfficeReminderUpdate,
  lobbyOfficeReminderDelete,
  lobbyOfficeReminderChangeStatus,
  sendLobbyOfficeReminder,
  disableAutoReminder,
  staffList,
  toFinalizeAteendace,
  fetchConsolidatedReport
};

