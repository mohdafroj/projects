import { APIClient, RAJYASABHAClient, FileDMSClient, SessionClient, ApprovalClient, LOBBYClient, DEVICEClient, MSClient, RBACClient } from './instances'

const request = async ({ method, url, payload = null, options = {}, client = null }) => {
    const finalOptions = {
        ...options,
        method,
        url,
        data: payload,
    }

    try {
        let response;
        switch (client) {
            case 'rbac':
                response = await RBACClient(finalOptions);
                break;
            case 'ms':
                response = await MSClient(finalOptions);
                break;
            case 'fdms':
                response = await FileDMSClient(finalOptions);
                break;
            case 'rs':
                response = await RAJYASABHAClient(finalOptions);
                break;
            case 'session':
                response = await SessionClient(finalOptions);
                break;
            case 'approval':
                response = await ApprovalClient(finalOptions);
                break;
            case 'lobby':
                response = await LOBBYClient(finalOptions);
                break;
            case 'device':
                response = await DEVICEClient(finalOptions);
                break;
            default:
                response = await APIClient(finalOptions);
        }

        return { isError: false, ...response.data, status: response.status, statusText: response.statusText }
    } catch (error) {
        let customMessage = '';
        let description = error.response?.data.description || error.response?.data.errors || error.response?.data.message;
        if (Array.isArray(description) && description.length) {
            customMessage = description[0];
        } else if (typeof description == 'object') {
            Object.keys(description).map(k => {
                if (description[k] != '') {
                    customMessage = description[k];
                }
            })
        } else if (typeof description == 'string') {
            customMessage = description.trim() != "" ? description : error.response?.data.error;
        } else {
            customMessage = error.response?.data.error;
        }

        return { isError: true, ...error.response?.data, customMessage, status: error.status, statusText: error.statusText };
    }
}

const getMethod = async ({ url, options = {}, client = null }) => await request({ method: 'GET', url, options, client });
const postMethod = async ({ url, payload, options = {}, client = null }) => await request({ method: 'POST', url, payload, options, client });
const putMethod = async ({ url, payload, options = {}, client = null }) => await request({ method: 'PUT', url, payload, options, client });
const deleteMethod = async ({ url, options = {}, client = null }) => await request({ method: 'DELETE', url, options, client });
const patchMethod = async ({ url, payload, options = {}, client = null }) => await request({ method: 'PATCH', url, payload, options, client });

export {
    getMethod,
    postMethod,
    putMethod,
    patchMethod,
    deleteMethod
};
