
import { getMethod, postMethod, patchMethod } from '@/composables/useApi'

const fetchCommitteeAttendances = async (options) => {

    return await getMethod({ url: '/meetings/dashboard', options, client: 'session' });
}

const fetchCommitteeList = async (options) => {

    return await getMethod({ url: '/meetings/list', options, client: 'session' });
}

const createMeeting = async (payload) => {
    return await postMethod({ url: '/meetings/add', payload, client: 'session' })
}
const editMeeting = async (id, payload) => {
    return await getMethod({ url: `/meetings/meetingbyid/${id}`, payload, client: 'session' })
}
const updateMeeting = async (id, payload) => {
    return await patchMethod({ url: `/meetings/update/${id}`, payload, client: 'session' })
}
const fetchmeetingNo = async (payload) => {
    return await getMethod({ url: `/meetings/meetingnolist`, payload, client: 'session' })
}
const fetchCategoryList = async (house_id) =>{
    const options = { params: { house_id } };
    // console.log("options", options);
    return await  getMethod({ url: 'meetings/categorylist', options, client: 'session' })
}
const fetchCommitteeOption = async (house_id,category_id) =>{
   const options = { params: { house_id,category_id } };
//    console.log("payload", payload);
   
 return await getMethod({ url: `meetings/committeelist`, options, client: 'session' })
}


const fetchVenueList = (params) => getMethod({ url: 'meetings/vanuelist', params, client: 'session' })
const fetchMeetingNOupdate = (committee_id) => getMethod({ url: `meetings/nextmeeting/${committee_id}`, client: 'session' })



export {
    fetchCommitteeAttendances,
    fetchCommitteeList,
    fetchCategoryList,
    fetchCommitteeOption,
    fetchVenueList,
    createMeeting,
    updateMeeting,
    editMeeting,
    fetchmeetingNo,
    fetchMeetingNOupdate
};




