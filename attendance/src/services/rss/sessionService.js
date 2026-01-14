import { getMethod, patchMethod, postMethod } from '@/composables/useApi'
// session
const client = 'session';
const fetchSessionList = async (options) => {
    return await getMethod({ url: 'sessions/list', options, client })
}

const fetchSessionTypes = async () => {
    return await getMethod({ url: 'sessions/sessontypelist', client })
}

const fetchSessionNumber = async () => {
    return await getMethod({ url: 'sessions/sessionnumberlist', client })
}

const fetchCurrentSessionNumber = async () => {
    return await getMethod({ url: 'sessions/nextsessionnumber', client })
}

const postCreateSession = async (payload) => {
    return await postMethod({ url: 'sessions/add', payload, client })
}

const getSessionData = async (payload) => {
    return await getMethod({ url: 'sessions/details/' + `${payload}`, client })
}
const postSaveSessionData = async (id, payload) => {
    return await postMethod({ url: 'sessions/updatesittingdata/' + `${id}`, payload, client })
}


export {
    fetchSessionList,
    fetchSessionTypes,
    postCreateSession,
    fetchCurrentSessionNumber,
    getSessionData,
    postSaveSessionData,
    fetchSessionNumber
};
