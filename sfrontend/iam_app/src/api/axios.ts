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
    // Example: const token = localStorage.getItem("token");
    // if (token) config.headers.Authorization = `Bearer ${token}`;
    console.log(`[API Request] ${config.method?.toUpperCase()} ${config.url}`);
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
  (error: AxiosError) => {
    const status = error.response?.status;
    const data: any = error.response?.data;

    // Centralized Error Logic
    if (status === 401) {
      console.warn("Unauthorized! Redirecting to login...");
      // Optional: Clear storage and redirect
    }

    const customError = {
      message: data?.message || "An unexpected error occurred",
      status: status,
      originalError: error
    };

    return Promise.reject(customError);
  }
);

export default api;
