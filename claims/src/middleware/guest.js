import { cookieService } from '@sds/oneui-layout';
const userAppData = cookieService.getData({ name: "userAppData", non_primitive: 1, decode: 1 });

export default function guest({ next, store }) {
  if (userAppData.id && userAppData.token) {
    return next({ name: 'Dashboard' })
  }
  return next()
}