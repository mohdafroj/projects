import { cookieService } from '@sds/oneui-layout';

export function getAuthToken() {
    const userAppData = cookieService.getData({ name: 'userAppData', non_primitive: 1, decode: 1 });
    return userAppData?.id && userAppData?.token ? `Bearer ${userAppData.token}` : null;
};