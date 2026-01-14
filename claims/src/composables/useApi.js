
import { APIClient, RSClient, ReportClient, APIClientV2, APIMSCClient, DMSClient, FileDMSClient, ESIGNClient, AIRLINESCLIENT } from './instances'

const request = async ({ method, url, payload = null, options = {}, client = null }) => {
    const finalOptions = {
        ...options,
        method,
        url,
        data: payload,
    }

    try {
        let response;
        // console.log('clienttttt',client);
        switch (client) {
            case 'rs':
                response = await RSClient(finalOptions);
                break;
            case 'report':
                response = await ReportClient(finalOptions);
                break;

            case 'v2': // handle v2
                response = await APIClientV2(finalOptions);
                break;
            case 'apims': // handle apims
                response = await APIMSCClient(finalOptions);
                break;
            case 'dms': // handle dms
                response = await DMSClient(finalOptions);
                break;
            case 'fdms': // handle files dms
                response = await FileDMSClient(finalOptions);
                break;
            case 'airlines': // handle airlines
                response = await AIRLINESCLIENT(finalOptions);
                break;
            case 'esign':
                response = await ESIGNClient(finalOptions);
                break;
            default:
                response = await APIClient(finalOptions);
        }
        return { isError: false, ...response.data, status: response.status, statusText: response.statusText }
    } catch (error) {
        return { isError: true, ...error.response?.data, status: error.status, statusText: error.statusText };
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
