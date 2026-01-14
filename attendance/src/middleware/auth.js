import { cookieService } from '@sds/oneui-layout';
import { useApiStore } from '@/store/apiData';
const userAppData = cookieService.getData({ name: "userAppData", non_primitive: 1, decode: 1 });
const rbacAppData = cookieService.getLocalStorageData({ name: "rbacAppData", non_primitive: 1, decode: 1 });
//console.log("_______", userAppData, rbacAppData);
export default function auth({ next, store }) {
  if (userAppData.id && userAppData.token) {
    const apiData = useApiStore();
    apiData.setUser({ ...userAppData, rbac: rbacAppData });
    return next();
  }
  return next({ name: "Login" });
}