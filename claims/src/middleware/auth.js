import { cookieService } from '@sds/oneui-layout';
import { useApiStore } from '@/store/apiData';
const userAppData = cookieService.getData({ name: "userAppData", non_primitive: 1, decode: 1 });
const rbacAppData = cookieService.getLocalStorageData({ name: "rbacAppData", non_primitive: 1, decode: 1 });
//console.log('AUTH CHECK====>>',rbacAppData);
export default function auth({ next, store }) {
  if (userAppData.id && userAppData.token) {
    const apiData = useApiStore();
    apiData.setUser(userAppData);
    apiData.setRbac(rbacAppData);
    return next();
  }
  return next({ name: "Login" });
}