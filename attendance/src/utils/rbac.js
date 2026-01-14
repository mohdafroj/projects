// utils/rbac.js - Single file for all RBAC logic
import { cookieService } from '@sds/oneui-layout';

const userAppData = cookieService.getData({ name: "userAppData", non_primitive: 1, decode: 1 });
const rbacAppData = cookieService.getLocalStorageData({ name: "rbacAppData", non_primitive: 1, decode: 1 });
//const rbacTestData = cookieService.getDataByToken({ token: import.meta.env.VITE_TESTTOKEN, non_primitive: 1, decode: 1 });
//console.log(rbacAppData);
// Get user data
const getUserData = () => {
    return { userAppData, rbacAppData };
};

// Check if user is authenticated
export const isAuthenticated = () => {
    const { userAppData } = getUserData();
    return !!(userAppData?.id && userAppData?.token);
};

// Check super admin
export const isSuperAdmin = () => {
    const roles = rbacAppData?.roles_permissions?.roles || [];
    return (Array.isArray(roles) && roles.includes('super-admin'));
};

// Get user permissions as Set for fast lookup
export const getUserPermissions = () => {
    const { rbacAppData } = getUserData();
    const permissions = new Set();

    if (!rbacAppData?.roles_permissions?.modules) return permissions;

    rbacAppData.roles_permissions.modules.forEach(module => {
        module.processes?.forEach(process => {
            process.permissions?.forEach(permission => {
                //const permissionString = `${module.module_code}:${process.process_code}:${permission.permission_code}`;
                const permissionString = `${permission.permission_name}`;
                permissions.add(permissionString);
            });
        });
    });

    return permissions;
};

// Main permission checking function
export const hasPermission = (requiredPermissions = []) => {
    if (!isAuthenticated()) return false;
    if (!requiredPermissions || requiredPermissions.length === 0) return true;

    if (isSuperAdmin()) {
        return true;
    }

    const userPermissions = getUserPermissions();
    const permissions = Array.isArray(requiredPermissions) ? requiredPermissions : [requiredPermissions];

    return permissions.every(permission => userPermissions.has(permission));
};

// Check if user has ANY of the permissions (OR logic)
export const hasAnyPermission = (permissions = []) => {
    if (!isAuthenticated()) return false;
    if (!permissions || permissions.length === 0) return true;

    if (isSuperAdmin()) {
        return true;
    }

    const userPermissions = getUserPermissions();
    return permissions.some(permission => userPermissions.has(permission));
};

// Permission constants based on actual RBAC response
export const PERMISSIONS = {
    //Kindly map with permission_name only
    // Attendance Management
    ATTENDANCE: {
        DASHBOARD: "mem_att_db",
        VIEW_REGULARIZATION: "mem_attreg_vw",
        DOWNLOAD_REGULARIZATION: "mem_attreg_dw",
        VIEW_HISTORY: "mem_att_vw",
        DOWNLOAD_HISTORY: "mem_att_dw",
        VIEW_LEAVE_REPORT: "mem_lea_vw",
        DOWNLOAD_LEAVE_REPORT: "mem_lea_dw",
        DOWNLOAD_ATTENDANCE_FINAL_REPORT: 'mem_att_fnlrep',
        LOBBY_OFFICE: ['mem_esgn_his'],
        MSA_BRANCH: ['Download_attendancehistory', 'View_attendancehistory', 'mem_att_dw', 'mem_att_vw'],
        FINALIZE_ATTENDANCE: 'att_fat_pub',
    },

    APPROVAL: {
        LEAVE: {
            INITIATE: 'ATT_RSS_INIT',
            REVIEW: 'ATT_RSS_REVIEW',
            APPROVE: 'ATT_RSS_APPROVE',
        },
        ANS: {
            INITIATE: 'ATT_RSS_INIT',
            REVIEW: 'ATT_RSS_REVIEW',
            APPROVE: 'ATT_RSS_APPROVE',
        }
    },

    // Session Management
    SESSION: {
        CREATE: "not_sess_cr",
        EDIT: "not_sess_ed",
        VIEW: "not_sess_vw"
    },

    // Committee Meeting
    COMMITTEE: {
        CREATE_MEETING: "not_cmeet_cr",
        EDIT_MEETING: "not_cmeet_ed",
        VIEW_MEETING: "not_cmeet_vw"
    }
};

// Enhanced middleware factory
export const createPermissionMiddleware = (requiredPermissions = [], options = {}) => {
    const { redirectTo = "Access_Denied", requireAll = true } = options;

    return ({ next }) => {
        const checkFunction = requireAll ? hasPermission : hasAnyPermission;
        return checkFunction(requiredPermissions) ? next() : next({ name: redirectTo });
    };
};

// Legacy support for existing code - updated with real permissions
export const useAttendance = () => ({
    tab_1_1: hasPermission([PERMISSIONS.ATTENDANCE.VIEW_REGULARIZATION]),
    tab_1_2: hasPermission([PERMISSIONS.ATTENDANCE.VIEW_HISTORY]),
    tab_1_3: hasPermission([PERMISSIONS.ATTENDANCE.VIEW_LEAVE_REPORT]),
    tab_1_4: hasAnyPermission([
        PERMISSIONS.ATTENDANCE.VIEW_REGULARIZATION,
        PERMISSIONS.ATTENDANCE.VIEW_HISTORY,
        PERMISSIONS.ATTENDANCE.VIEW_LEAVE_REPORT
    ]),
    tab_1_5: hasAnyPermission([
        PERMISSIONS.ATTENDANCE.VIEW_REGULARIZATION,
        PERMISSIONS.ATTENDANCE.VIEW_HISTORY,
        PERMISSIONS.ATTENDANCE.VIEW_LEAVE_REPORT,
        PERMISSIONS.ATTENDANCE.VIEW_LEAVE_REPORT
    ]) // Show general attendance if user has any attendance permission
});

export const useUserRegistration = () => ({
    tab_3_1: hasPermission([]), // Add specific permissions when user registration module is available
    tab_3_2: hasPermission([])
});

// New composables for other modules
export const useSession = () => ({
    canCreate: hasPermission([PERMISSIONS.SESSION.CREATE]),
    canEdit: hasPermission([PERMISSIONS.SESSION.EDIT]),
    canView: hasPermission([PERMISSIONS.SESSION.VIEW]),
    canAccess: hasAnyPermission([
        PERMISSIONS.SESSION.CREATE,
        PERMISSIONS.SESSION.EDIT,
        PERMISSIONS.SESSION.VIEW
    ])
});

export const useCommittee = () => ({
    canCreateMeeting: hasPermission([PERMISSIONS.COMMITTEE.CREATE_MEETING]),
    canEditMeeting: hasPermission([PERMISSIONS.COMMITTEE.EDIT_MEETING]),
    canViewMeeting: hasPermission([PERMISSIONS.COMMITTEE.VIEW_MEETING]),
    canAccess: hasAnyPermission([
        PERMISSIONS.COMMITTEE.CREATE_MEETING,
        PERMISSIONS.COMMITTEE.EDIT_MEETING,
        PERMISSIONS.COMMITTEE.VIEW_MEETING
    ])
});

export const useRBAC = () => ({
    canManageOU: hasPermission([PERMISSIONS.RBAC.MANAGE_OU]),
    canManageDivision: hasPermission([PERMISSIONS.RBAC.MANAGE_DIVISION]),
    canCreate: hasPermission([PERMISSIONS.RBAC.CREATE]),
    canEdit: hasPermission([PERMISSIONS.RBAC.EDIT]),
    canView: hasPermission([PERMISSIONS.RBAC.VIEW]),
    canManageDesignation: hasPermission([PERMISSIONS.RBAC.MANAGE_DESIGNATION]),
    canManageModules: hasPermission([PERMISSIONS.RBAC.MANAGE_MODULES]),
    canManageProcess: hasPermission([PERMISSIONS.RBAC.MANAGE_PROCESS]),
    canDelegateUsers: hasPermission([PERMISSIONS.RBAC.USER_DELEGATION]),
    canAccess: hasAnyPermission([
        PERMISSIONS.RBAC.MANAGE_OU,
        PERMISSIONS.RBAC.MANAGE_DIVISION,
        PERMISSIONS.RBAC.CREATE,
        PERMISSIONS.RBAC.EDIT,
        PERMISSIONS.RBAC.VIEW
    ])
});

export const useUserType = () => {
    const route = useRoute();
    //const apiStore = useApiStore();
    let userType = route?.query.user_type || 'initiator'; // viewer, initiator, reviewer, approver, super
    return userType;
};

