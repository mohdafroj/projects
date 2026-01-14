import { getMethod, postMethod, deleteMethod } from "@/composables/useApi";
const client = 'lobby';
const logoutUser = async () => {
  const payload = { "type": "W" };
  return await postMethod({ url: '/logout', payload, client: 'rs' });
}
const fetchUser = async () => {

  return await getMethod({ url: "rbac/users", client });
};

const fetchDivision = async () => {

  return await getMethod({ url: "rbac/divisions", client });
};

const fetchDesignation = async () => {

  return await getMethod({ url: "rbac/designation-by-ou/75", client });
};


const createUser = async (payload) => {

  // console.log("createUser payload:", JSON.stringify(payload, null, 2)); // Log payload

  const response = await postMethod({ url: "users/create", payload, client });
  // console.log("createUser response:", JSON.stringify(response, null, 2)); // Log response
  return response;
};

const deleteUser = async (userId) => {
  return await deleteMethod({ url: `users/remove/${userId}`, client });
};

const updateUser = async (core_user_id, payload) => {
  return await postMethod({ url: `users/update/${core_user_id}`, payload, client });
};
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
  logoutUser, fetchUser, createUser, fetchDivision, fetchDesignation, deleteUser, updateUser,
  fetchTabdata, deleteTab, updateTab, createTab

};
