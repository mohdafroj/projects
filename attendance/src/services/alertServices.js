import { getMethod } from "@/composables/useApi";

const fetchMessages = async () => {
  return await getMethod({ url: "/a9d60a0d742ea99cd427", client: "test" });
};

const fetchNotifications = async () => {
  return await getMethod({ url: "/cbf81d3c68e6b53cddd7", client: "test" });
}

export {
  fetchMessages,
  fetchNotifications,
};
