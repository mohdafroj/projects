import axios, { AxiosError, AxiosInstance, InternalAxiosRequestConfig, AxiosResponse } from "axios";

const API_BASE_URL = process.env.API_BASE_URL || "/";

const api: AxiosInstance = axios.create({
  baseURL: API_BASE_URL,
  timeout: 10000,
  headers: {
    "Content-Type": "application/json",
    "Accept": "application/json",
  },
});

// Request Interceptor: Attach tokens or log requests
api.interceptors.request.use(
  (config: InternalAxiosRequestConfig) => {
    const token = sessionStorage.getItem("access_token");
    if (token) config.headers.Authorization = `Bearer ${token}`;
    return config;
  },
  (error: AxiosError) => {
    return Promise.reject(error);
  }
);

// Response Interceptor: Global error handling
api.interceptors.response.use(
  (response: AxiosResponse) => {
    return response;
  },
  async (error: AxiosError) => {
    const status = error.response?.status;
    const data: any = error.response?.data;
    if (status === 401) {
      const refresh_token = localStorage.getItem("refresh_token");
      const access_token = sessionStorage.getItem("access_token");
      if (refresh_token && access_token) {
        try {
          const res = await api.post("/auth/refresh", {
            refresh_token,
            access_token,
          });
          if (res.data?.token) {
            sessionStorage.setItem("access_token", res.data.token);
            if (error.config) {
              error.config.headers.Authorization = `Bearer ${res.data.token}`;
            }
            return api.request(error.config as InternalAxiosRequestConfig);
          }
        } catch (error) {
          sessionStorage.removeItem("access_token");
          window.location.href = "/login";
        }
      }
    }

    const customError = {
      message: data?.message || "An unexpected error occurred",
      status: status,
      originalError: error,
      data: error?.response?.data,
    };

    return Promise.reject(customError);
  }
);

export default api;
