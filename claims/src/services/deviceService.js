import { getMethod, postMethod, deleteMethod } from "@/composables/useApi";
const client = 'rs';



const fetchTabdata = async () => {

  return await getMethod({ url: "common/device/list", client });
};

const deleteTab = async (id) => {
  return await deleteMethod({ url: `common/device/remove/${id}`, client });
};

const updateTab = async (id, payload) => {
  return await postMethod({ url: `common/device/update/${id}`, payload, client });
};
const createTab = async (payload) => {

  const response = await postMethod({ url: "common/device/add-device", payload, client });
  // console.log("createTab response:", JSON.stringify(response, null, 2)); // Log response
  return response;
};



export {
  fetchTabdata, deleteTab, updateTab, createTab,
};
