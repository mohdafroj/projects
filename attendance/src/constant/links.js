export const DS = '/';
const HOME = DS + 'home';
const SESSIONS = DS + 'sessions';

export default {
    "base_path": DS,
    "dashboard": DS + "dashboard",
    "login": DS + "login",
    "access_denied": DS + "access-denied",
    "unauthorized": DS + 'unauthorized',
    "thank_you": DS + "thankyou",
    "home": HOME,
    "tab_1": {
        "tab_1_1": HOME + DS + "regularization",
        "tab_1_2": HOME + DS + "history",
        "tab_1_3": HOME + DS + "leave-report",
        "tab_1_4": HOME,
        "tab_1_5": HOME + DS + "attendence-report",
        "tab_1_6": HOME + DS + "papper-signed",
        "tab_1_7": HOME + DS + "specimen-signature",
        "tab_1_8": HOME + DS + "reset",
        "tab_1_9": HOME + DS + "schedule-reminder",
        "tab_1_10": HOME + DS + "staff-list"
    },
    "tab_3": {
        "tab_3_1": DS + "new-user",
        "tab_3_2": DS + "register-tab",

    },
    "tab_4": {
        "tab_4_1": SESSIONS,
        "tab_4_2": SESSIONS + DS + "create-session",
        "tab_4_3": SESSIONS + DS + "manage-sitting",
    },
    "notifications": DS + "notifications",
    "session": SESSIONS,
    "committee": HOME + DS + "committee",
    "CommitteeMeeting": DS + "committee-meeting",
    "error": DS + "error"
};

