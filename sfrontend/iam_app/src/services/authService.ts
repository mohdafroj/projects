import api from "../api/axios";

// --- Types ---
export interface CaptchaResponse {
  img: string;
  key: string;
}

export interface LoginPayload {
  username: string;
  password: string;
  captcha_id: string;
  captcha_code: string;
}

export interface LoginResponse {
  token: string;
  user: {
    id: string;
    email: string;
    role: string;
  };
  message?: string;
}

// --- Endpoints ---
const BASE_PATH = "/auth";
const ENDPOINTS = {
  CAPTCHA: `${BASE_PATH}/captcha`,
  LOGIN: `${BASE_PATH}/login`,
};

// --- Service ---
export const authService = {
  /**
   * Fetches a new captcha image and key
   */
  async getCaptcha(): Promise<CaptchaResponse> {
    const response = await api.get<CaptchaResponse>(ENDPOINTS.CAPTCHA, {
      responseType: "blob", // Important to handle image data correctly
    });
    const imgUrl = URL.createObjectURL(response.data);
    return { img: imgUrl, key: response.headers["x-captcha-id"] };
  },

  /**
   * Performs user login
   */
  async login(payload: LoginPayload): Promise<LoginResponse> {
    const response = await api.post<LoginResponse>(ENDPOINTS.LOGIN, payload);
    return response.data;
  }
};
