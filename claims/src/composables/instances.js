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

    // if (userAppData?.id && userAppData?.token) {
    //     config.headers.Authorization = `Bearer ${userAppData.token}`;
    // }

    //  if ( config.url.includes('airlines/gettickerdetails') ) {
    //     config.headers.Authorization = `Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJhdWQiOiI2IiwianRpIjoiN2IyZjU5NjUzMjNiNzg1OGE0YTdiNmU2ODNmNWE5MTQxYzI2YmYzZDc2OWQwODdmNGNjMzYxOWZlMDU0OWE3N2ZlMmI3YzRjODlmM2ExMmUiLCJpYXQiOjE3NTgxOTkwODEuNzU4MzE2LCJuYmYiOjE3NTgxOTkwODEuNzU4MzE4LCJleHAiOjE3ODk3MzUwODEuNzUxNTU1LCJzdWIiOiI1NjkiLCJzY29wZXMiOltdfQ.css5cpHL-uU_-GaSwU5pzHUi_n6EihiFU_ZUQPzNFwdPl28_esS8nd6ATbRnifAykY4oSgPSXGoqib_qYNvYqGAz4Gj_QroRN-gVp5yz1tlS_VsEv4ERrf51xPD0qlPUEax4Bd5HYI4t0cD7JB1cw9faHch58GcR6G-SnS81irhsWgTfSDDUo0nDaYZIRW4iPWnaCnepCNA2bZnIwhImVUlbhNjPKBIBqALiipg-Yw3BgB0fqsX3uf6DrDG54wtEAajVmZhk5R0UGLPViteV_aqLb40O7qszFZpe_U1S_bgwAKAGsvgWQUN21sXf1xEuLa8fKr2IBy4oH84FAY5VSG9cem47fy9wG1ozv6c5DbZ_8ALVSLC7jf7qGJt5iIilJMXCGY69-6d5OL5OeQxpaSq9xwg3s20E7Hu2fXlgVpeHAYY2I1puby9PLvo0t4h7ACZ-FuNMMadicIx2iKunh7XPixjbtxdPwzvaYkLWXa1XemDgMVin7i1N7cVQqq3knv7fTb28AwCqC66Xh2P28LUKAUsPnPH9vH3F9ZL2m4Ino5xwGPxC9EAsaZyfbzUnFqeV4tpG3yAVGM3nyr27HFZTH60I8SoeD_7MiEX-Gj4baTjMA5bBPd43EIEDbsvtin0sxgTjSQN-53vhZb1FS0JZ0UGqMxh5dmr61RkeB8c`;
    // } else if (userAppData?.id && userAppData?.token) {
    //     config.headers.Authorization = `Bearer ${userAppData.token}`;
    // }
    if (userAppData?.id && userAppData?.token) {
        config.headers.Authorization = `Bearer ${userAppData.token}`;
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
const APIClient = createClient(import.meta.env.VITE_API_CLIENT_BASE_URL);
const RSClient = createClient(import.meta.env.VITE_RS_CLIENT_BASE_URL);
const ReportClient = createClient(import.meta.env.VITE_REPORT_CLIENT_BASE_URL);
const APIClientV2 = createClient(import.meta.env.VITE_API_CLIENT_BASE_URL_V2);
const APIMSCClient = createClient(import.meta.env.VITE_MS_BASE_URL);
const DMSClient = createClient(import.meta.env.VITE_DMS_CLIENT);
const FileDMSClient = createClient(import.meta.env.VITE_DMS_CLIENT + 'dms/uploadchunksfile');
const ESIGNClient = createClient(import.meta.env.VITE_ESIGN_URL_LOGIN);

// Airline Staging Client
const AIRLINESCLIENT = createClient(import.meta.env.VITE_API_AIRLINES);


createEncryptedAxios(APIClient);
createEncryptedAxios(RSClient);
createEncryptedAxios(ReportClient);
createEncryptedAxios(APIClientV2);
createEncryptedAxios(APIMSCClient);
createEncryptedFileAxios(DMSClient);
createEncryptedFileAxios(FileDMSClient);
createEncryptedAxios(AIRLINESCLIENT);
createEncryptedAxios(ESIGNClient);

export { APIClient, RSClient, ReportClient, APIClientV2, APIMSCClient, DMSClient, FileDMSClient, AIRLINESCLIENT, ESIGNClient };
