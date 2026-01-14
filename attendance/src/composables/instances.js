import axios from 'axios'
import { cookieService, createEncryptedFileAxios } from '@sds/oneui-layout';
import { createEncryptedAxios } from './encryption';

const normalizeBase = (b) => {
    if (!b) return '';                 // no base
    b = b.trim();
    b = b.replace(/^\/+|\/+$/g, '');   // strip leading/trailing slashes
    return b ? `/${b}` : '';
}

const BASE = normalizeBase(import.meta.env.VITE_BASE_PATH);
const UNAUTHORIZED = `${window.location.origin}${BASE}/unauthorized`;

// --- Common request interceptor ---
const requestInterceptor = (config) => {
    config.headers["Sds-Language"] = localStorage.getItem("language") || "en";
    config.headers["mode"] = localStorage.getItem("encrypted") == 'true' ? "1" : "0";

    const userAppData = cookieService.getData({
        name: "userAppData",
        non_primitive: 1,
        decode: 1,
    });

    if (userAppData?.id && userAppData?.token) {
        config.headers.Authorization = `Bearer ${userAppData.token}`;
    } else {
        if ([import.meta.env.VITE_LOBBYOFFICE_BASE_URL, import.meta.env.VITE_DEVICEMAPPING_BASE_URL].includes(config.baseURL)) {
            config.headers.Authorization = `Bearer ${import.meta.env.VITE_LOCAL_TOKEN}`
        }
    }
    return config;
};

// --- Common response interceptor ---
const responseInterceptor = {
    success: (response) => response,
    error: (error) => {
        const config = error.config;
        // if (!config || config.__retry) {
        //     return Promise.reject(error);
        // }
        if (import.meta.env.VITE_ENV_MODE != 'local' && error.status === 401) {
            window.location.href = UNAUTHORIZED;
        }
        return Promise.reject(error);
    },
};

// --- Factory function to create axios clients ---
const createClient = (baseURL) => {
    const client = axios.create({
        baseURL,
        allowAbsoluteUrls: true,
        headers: { "Content-Type": "application/json" },
    });

    client.interceptors.request.use(requestInterceptor);
    client.interceptors.response.use(
        responseInterceptor.success,
        responseInterceptor.error
    );

    return client;
};

// --- Instances ---
const APIClient = createClient(import.meta.env.VITE_API_BASE_URL);
const RAJYASABHAClient = createClient(import.meta.env.VITE_BASE_URL_LOGIN + 'api/');
const SessionClient = createClient(import.meta.env.VITE_SESSION_BASE_URL);
const ApprovalClient = createClient(import.meta.env.VITE_APPROVAL_BASE_URL);
const LOBBYClient = createClient(import.meta.env.VITE_LOBBYOFFICE_BASE_URL);
const DEVICEClient = createClient(import.meta.env.VITE_DEVICEMAPPING_BASE_URL);
const FileDMSClient = createClient(import.meta.env.VITE_DMS_CLIENT + 'dms/uploadchunksfile');
const MSClient = createClient(import.meta.env.VITE_MS_BASE_URL);
const RBACClient = createClient(import.meta.env.VITE_RBAC_BASE_URL);

createEncryptedFileAxios(FileDMSClient);
createEncryptedAxios(APIClient);
createEncryptedAxios(RAJYASABHAClient);
createEncryptedAxios(SessionClient);
createEncryptedAxios(ApprovalClient);
createEncryptedAxios(MSClient);
createEncryptedAxios(RBACClient);

export { APIClient, RAJYASABHAClient, FileDMSClient, SessionClient, ApprovalClient, LOBBYClient, DEVICEClient, MSClient, RBACClient }