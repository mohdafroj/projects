import { defineStore } from 'pinia';

export const useApiStore = defineStore('apiData', {
    state: () => ({
        user: {},
        rbac: {},
        session: { id: 269, session_number: 269, list: [] },
        member: { list: [] },
        reason: {},
        attendance: { filters: {}, list: {}, detail: {} },
    }),
    actions: {
        setUser(data) {
            this.user = data;
        },
        setRbac(data) {
            this.rbac = data;
        },
        setSession(data) {
            this.session = data;
        },
        setMember(data) {
            this.member = data;
        },
        setReason(data) {
            this.reason = data;
        },
        setAttendace(data) {
            this.attendance = data;
        },
    },
});