import axios from "axios";
import forge from "node-forge";

let publicKeyPem = "\n-----BEGIN PUBLIC KEY-----\nMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAiVstOcHDFYpXf4cEaqXx\nP\/t7m+PBtdiy8aU8yGYD68gcUzWoyxSAhQKFTf6cdW2YZcSvlN99yzVsQMeyd4Lg\nEsEh4G6b\/yNWhyc2vrFPCxNfTETxbSMarxNvNy5pNSopytTgXvss1vvAtVqbsyxq\nr\/Z\/29arCkW0bkriTplVYH\/ybq\/nPNDmLfKPq7GWdTmASjIjGFIU6cnibAuzG8zZ\n9PdiQgKxbHmMShQUmAu1A9kY2m04FJjD9ggH8Q2KRq1+5bjPxJ80NE10pozjvh2G\niIVYAXWnfeQAnL5q354pm5wdrl2KHU\/vACkP7RE2gdUSysYbJFmfha6gTbm5dGFm\nzQIDAQAB\n-----END PUBLIC KEY-----";
let privateAES = null;
let isPublicKeyLoaded = true;

export const fetchPublicKey = async () => {
    if (isPublicKeyLoaded) return;
    let returnResponse = null;
    try {
        const response = await axios.get(import.meta.env.VITE_BASE_URL_LOGIN + 'api/get-public-key', {
            skipEncryption: true,
        });
        const res = response?.data?.data?.public_key;
        if (res) {
            publicKeyPem = res;
            isPublicKeyLoaded = true;
            returnResponse = "✅ Public Key Loaded";
        } else {
            returnResponse = "Failed to fetch public key: " + res;
        }
    } catch (error) {
        returnResponse = "❌ Failed to fetch public key:" + error;
    }
    return returnResponse;
}

function generateAESKey() {
    return {
        key: crypto.getRandomValues(new Uint8Array(32)),
        iv: crypto.getRandomValues(new Uint8Array(16)),
    };
}

function encryptAES(data, key, iv) {
    const cipher = forge.cipher.createCipher("AES-CBC", forge.util.createBuffer(key));
    cipher.start({ iv: forge.util.createBuffer(iv) });
    cipher.update(forge.util.createBuffer(forge.util.encodeUtf8(JSON.stringify(data))));
    cipher.finish();
    return forge.util.encode64(cipher.output.getBytes());
}

function encryptAESKey(aesKey) {
    if (!isPublicKeyLoaded || !publicKeyPem) {
        console.error("❌ Public key not loaded.");
        return null;
    }

    try {
        const publicKey = forge.pki.publicKeyFromPem(publicKeyPem);
        const aesKeyString = forge.util.binary.raw.encode(aesKey);
        const encryptedKey = publicKey.encrypt(aesKeyString, "RSAES-PKCS1-V1_5");
        return forge.util.encode64(encryptedKey);
    } catch (error) {
        console.log("Encryption failed:", error);
        return null;
    }
}

async function encryptFile(file, key, iv) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        const chunkSize = 64 * 1024;
        let offset = 0;
        let encryptedChunks = [];

        reader.onload = (event) => {
            if (!event.target || !event.target.result) return reject("File read error");

            const chunkData = new Uint8Array(event.target.result);
            const binaryData = forge.util.binary.raw.encode(chunkData);

            const cipher = forge.cipher.createCipher("AES-CBC", forge.util.createBuffer(key));
            cipher.start({ iv: forge.util.createBuffer(iv) });
            cipher.update(forge.util.createBuffer(binaryData));
            cipher.finish();

            const encryptedChunk = new Uint8Array(forge.util.binary.raw.decode(cipher.output.getBytes()));
            encryptedChunks.push(encryptedChunk);

            offset += chunkSize;
            if (offset < file.size) {
                readNextChunk();
            } else {
                resolve(new Blob(encryptedChunks, { type: "application/octet-stream" }));
            }
        };

        reader.onerror = () => reject("File reading failed");

        function readNextChunk() {
            const slice = file.slice(offset, offset + chunkSize);
            reader.readAsArrayBuffer(slice);
        }

        readNextChunk();
    });
}

async function encryptFormData(formData, key, iv) {
    const entries = {};

    for (const [field, value] of formData.entries()) {
        if (value instanceof File) {
            if (!entries[field]) entries[field] = [];

            const encryptedFileBlob = await encryptFile(value, key, iv);
            const fileBase64 = await blobToBase64(encryptedFileBlob);
            entries[field].push(fileBase64);
        } else {
            // If the field has multiple values (like checkboxes or repeated keys)
            if (entries[field]) {
                if (Array.isArray(entries[field])) {
                    entries[field].push(value.toString());
                } else {
                    entries[field] = [entries[field], value.toString()];
                }
            } else {
                entries[field] = value.toString();
            }
        }
    }

    return {
        encrypted_data: encryptAES(entries, key, iv),
        iv: forge.util.encode64(forge.util.createBuffer(iv).getBytes()),
    };
}

function blobToBase64(blob) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.readAsDataURL(blob);
        reader.onloadend = () => resolve(reader.result);
        reader.onerror = (error) => reject(error);
    });
}

function decryptAES(encryptedData, key, iv) {
    try {
        const rawEncryptedData = forge.util.decode64(encryptedData);
        const decipher = forge.cipher.createDecipher("AES-CBC", forge.util.createBuffer(key));
        decipher.start({ iv: forge.util.createBuffer(iv) });
        decipher.update(forge.util.createBuffer(rawEncryptedData));

        if (!decipher.finish()) {
            throw new Error("Decryption failed! Possible incorrect key or corrupted data.");
        }
        return JSON.parse(decipher.output.toString());
    } catch (error) {
        console.error("❌ AES Decryption Error:", error.message);
        return null;
    }
}
privateAES = generateAESKey();
function attachEncryptionInterceptors(instance) {
    instance.interceptors.request.use(
        async (config) => {
            const encrypted = localStorage.getItem('encrypted');
            const skipEncryption = (encrypted && encrypted === 'true') ? false : true;
            // const paramsString = Object.entries(config.params || {})
            //     .map(([k, v]) => `${encodeURIComponent(k)}=${encodeURIComponent(v)}`)
            //     .join('&');

            // const fullUrl = `${config.baseURL || ''}${config.url || ''}${paramsString ? '?' + paramsString : ''}`;
            // const urlLength = fullUrl.length;

            if (skipEncryption || !isPublicKeyLoaded) return config;
            const methodName = config.method.toLowerCase();
            if (['post', 'put', 'patch'].includes(methodName)) {
                let encryptedData;

                if (config.data instanceof FormData) {
                    encryptedData = await encryptFormData(config.data, privateAES.key, privateAES.iv);
                } else {
                    encryptedData = {
                        encrypted_data: encryptAES(config.data, privateAES.key, privateAES.iv),
                        iv: forge.util.encode64(forge.util.createBuffer(privateAES.iv).getBytes()),
                    };
                }
                config.data = {
                    ...encryptedData,
                    encrypted_key: encryptAESKey(privateAES.key),
                };
            } else {
                const paramsData = config.params ? config.params : {};
                //console.log(paramsData);
                const encryptedQuery = {
                    encrypted_data: encryptAES(paramsData, privateAES.key, privateAES.iv),
                    iv: forge.util.encode64(forge.util.createBuffer(privateAES.iv).getBytes()),
                    encrypted_key: encryptAESKey(privateAES.key),
                };
                // Replace the `params` object with encrypted data
                config.params = encryptedQuery;
                const paramsString = Object.entries(config.params || {})
                    .map(([k, v]) => `${encodeURIComponent(k)}=${encodeURIComponent(v)}`)
                    .join('&');

                const fullUrl = `${config.baseURL || ''}${config.url || ''}${paramsString ? '?' + paramsString : ''}`;
                const urlLength = fullUrl.length;
                //console.log("After Encrypt Length:", urlLength, fullUrl);
            }
            return config;
        },
        (error) => Promise.reject(error)
    );

    instance.interceptors.response.use(
        async (response) => {
            if (
                response.data?.encrypted_data &&
                response.data?.encrypted_key &&
                response.data?.iv
            ) {
                if (!privateAES) throw new Error("No AES key for decryption");

                const decryptedData = await decryptAES(
                    response.data.encrypted_data,
                    privateAES.key,
                    new Uint8Array(privateAES.iv)
                );
                //console.log("Encrypt: ", decryptedData);
                return { ...response, data: decryptedData };
            }

            return response;
        },
        async (error) => {
            if (
                error.response?.data?.encrypted_data &&
                error.response?.data?.encrypted_key &&
                error.response?.data?.iv
            ) {
                if (!privateAES) throw new Error("No AES key for decryption");
                const decryptedData = await decryptAES(
                    error.response?.data.encrypted_data,
                    privateAES.key,
                    new Uint8Array(privateAES.iv)
                );
                return Promise.reject({
                    ...error, response: {
                        ...error.response,
                        data: decryptedData
                    }
                });
            }
            return Promise.reject(error);
        }
    );
}

export function createEncryptedAxios(instance) {
    attachEncryptionInterceptors(instance);
    return instance;
}
