import { cookieService } from '@sds/oneui-layout';
const userAppData = cookieService.getData({ name: "userAppData", non_primitive: 1, decode: 1 });
const rbacAppData = cookieService.getLocalStorageData({ name: "rbacAppData", non_primitive: 1, decode: 1 });
//console.log('rbacAppData', rbacAppData);
const getUserData = () => {
    return { userAppData, rbacAppData };
}
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
    CGHS: {//cghs cards permissions initiator
        MANAGE_CARD: "cgh_rap_ini", //please map with permission_name        
    },
    CGHSAPPROVE: {//cghs cards permissions approver
        MANAGE_CARD: "cgh_rap_ini", //please map with permission_name         
        APPROVE: "cgh_rap_ap"
    },

    ITCLAIM: {
        PAYMENT: 'itc_pay_py',
        INITIATE: 'itc_ini_in',
        REVIEW: 'itc_rev_rv',
        APPROVE: 'itc_app_ap',
        COMPLIANCE: 'itc_cmp_ca'
    },
    TADACLAIM: {
        INITIATE: 'itc_ini_in',
        REVIEW: 'tad_rev_rv',
        APPROVE: 'tad_app_ap'
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
