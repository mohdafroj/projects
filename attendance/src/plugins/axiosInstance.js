import axios from "axios";

// Create Axios instance
export const axiosInstance = axios.create({
  baseURL: import.meta.env.VITE_RBAC_BASE_URL,
  allowAbsoluteUrls: true,
  timeout: 10000,
});

// Request Interceptor
axiosInstance.interceptors.request.use(
  config => {
    // Example: Add token and content-type if needed
    const token = localStorage.getItem("authToken");
    if (token) {
      config.headers["Authorization"] = `Bearer ${token}`;
    }
    config.headers["Content-Type"] =
      config.headers["Content-Type"] || "application/json";
    config.headers["Access-Control-Allow-Origin"] = "*";
    return config;
  },
  error => Promise.reject(error),
);

// Response Interceptor
axiosInstance.interceptors.response.use(
  response => response,
  error => {
    console.error("API Error:", error.response || error.message);

    return Promise.reject(error);
  },
);
