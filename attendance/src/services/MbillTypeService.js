import { axiosInstance } from "@/plugins/axiosInstance"
const API_MEMBER = import.meta.env.VITE_API_MOTHER_MASTER;
export const getMbillTypeDetails = (id) => {
  return axiosInstance.get(`${API_MEMBER}getdata/byid/${id}`)
}