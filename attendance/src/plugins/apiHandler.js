import { ref } from "vue";
import axios from "axios";

import {
  cookieService, createEncryptedAxios,
} from "@sds/oneui-layout";

export const useApiHandler = () => {
  const data = ref(null);
  const error = ref(null);

  const apiHandler = async (urlKeyName, apiMethod, requestBody = null, params = "") => {
    try {
      const apiUrl = `${import.meta.env[urlKeyName]}` + params;
      console.log("Constructed API URL:", apiUrl);

      const isFile = apiMethod === "file";
      const headers = {
        Accept: "application/json",
        ...(isFile ? {} : { "Content-Type": "application/json" }),
      };

      const axiosConfig = {
        method: isFile ? "POST" : apiMethod.toUpperCase(),
        url: apiUrl,
        headers,
        data: isFile ? requestBody : requestBody ? JSON.stringify(requestBody) : null,
        timeout: 10000,
      };

      console.log("Axios Request Config:", axiosConfig);
      const response = await axios(axiosConfig);

      data.value = response.data;
      error.value = null;
      
      return response.data; 
    } catch (e) {
      if (e.response) {
        const status = e.response.status;
        error.value = status === 400 ? "Invalid Request" :
                      status === 500 ? "Server Error" : 
                      `Error: ${status}`;
      } else if (e.request) {
        error.value = "No response from the server.";
      } else {
        error.value = "Something went wrong.";
      }

      console.error("Error in API call:", e);
      data.value = [];
      return null; //  Return null on error
    }
  };

  return { apiHandler, data, error };
};
