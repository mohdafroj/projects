import { getMethod, postMethod } from "@/composables/useApi";
const client = 'ms';
const getMembers = () => {
  return getMethod({ url: '/users/get-members', client });
}

const getAccountDetails = (core_user_id) => {
  const options = { params: { core_user_id } };
  return getMethod({ url: `entitlement/get-account-details`, options, client });
}

const getFinanceDetails = (core_user_id) => {
  const options = { params: { core_user_id } };
  return getMethod({ url: `entitlement/get-finance-details`, options, client });
}

export {
  getMembers,
  getAccountDetails,
  getFinanceDetails
};
