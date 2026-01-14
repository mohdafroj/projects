import axios from "axios";
import forge from "node-forge";
let publicKeyPem = "";
let privateAES = null;
let isPublicKeyLoaded = false;
async function fetchPublicKey() {
    if (isPublicKeyLoaded) return;
    try {
        const response = await axios.get(import.meta.env.VITE_BASE_URL_LOGIN + 'api/get-public-key', {
            skipEncryption: true,
        });
        publicKeyPem = response.data.data.public_key;
        isPublicKeyLoaded = true;
        console.log(":white_check_mark: Public Key Loaded");
    } catch (error) {
        console.error(":x: Failed to fetch public key:", error);
    }
}
function generateAESKey() {
    return {
        key: crypto.getRandomValues(new Uint8Array(32)),
        iv: crypto.getRandomValues(new Uint8Array(12)),
    };
}
function base64EncodeBytes(bytes) {
    return btoa(String.fromCharCode(...bytes));
}
function base64DecodeBytes(str) {
    return Uint8Array.from(atob(str), (c) => c.charCodeAt(0));
}
async function encryptAES_GCM(data, key, iv) {
    const encoded = new TextEncoder().encode(JSON.stringify(data));
    const cryptoKey = await crypto.subtle.importKey("raw", key, "AES-GCM", false, ["encrypt"]);
    const encrypted = await crypto.subtle.encrypt(
        {
            name: "AES-GCM",
            iv,
            tagLength: 128,
        },
        cryptoKey,
        encoded
    );
    return {
        ciphertext: base64EncodeBytes(new Uint8Array(encrypted)),
    };
}
async function encryptFile_GCM(file, key, iv) {
    const fileBuffer = await file.arrayBuffer();
    const cryptoKey = await crypto.subtle.importKey("raw", key, "AES-GCM", false, ["encrypt"]);
    const encrypted = await crypto.subtle.encrypt(
        { name: "AES-GCM", iv, tagLength: 128 },
        cryptoKey,
        fileBuffer
    );
    return new Blob([new Uint8Array(encrypted)], { type: "application/octet-stream" });
}
function encryptAESKey(aesKey) {
    if (!isPublicKeyLoaded || !publicKeyPem) {
        console.error(":x: Public key not loaded.");
        return null;
    }
    try {
        const publicKey = forge.pki.publicKeyFromPem(publicKeyPem);
        const aesKeyString = forge.util.binary.raw.encode(aesKey);
        const encryptedKey = publicKey.encrypt(aesKeyString, "RSAES-PKCS1-V1_5");
        return forge.util.encode64(encryptedKey);
    } catch (error) {
        console.error(":x: RSA Encryption failed:", error);
        return null;
    }
}
async function encryptFormData(formData, key, iv) {
    const encryptedFormData = new FormData();
    const entries = {};
    for (const [field, value] of formData.entries()) {
        if (value instanceof File) {
            const encryptedBlob = await encryptFile_GCM(value, key, iv);
            encryptedFormData.append(field, encryptedBlob, value.name);
        } else {
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
    const { ciphertext } = await encryptAES_GCM(entries, key, iv);
    encryptedFormData.append("encrypted_data", ciphertext);
    encryptedFormData.append("iv", base64EncodeBytes(iv));
    encryptedFormData.append("encrypted_key", encryptAESKey(key));
    return encryptedFormData;
}
async function decryptAES_GCM(encryptedData, key, iv) {
    try {
        const encryptedBytes = base64DecodeBytes(encryptedData);
        const cryptoKey = await crypto.subtle.importKey("raw", key, "AES-GCM", false, ["decrypt"]);
        const decrypted = await crypto.subtle.decrypt(
            { name: "AES-GCM", iv },
            cryptoKey,
            encryptedBytes
        );
        return JSON.parse(new TextDecoder().decode(decrypted));
    } catch (err) {
        console.error(":x: AES-GCM Decryption Error:", err.message);
        return null;
    }
}
async function attachEncryptionInterceptors(instance) {
    instance.interceptors.request.use(
        async (config) => {
            const encrypted = localStorage.getItem("encrypted");
            const skipEncryption = encrypted !== "true";
            if (skipEncryption) return config;
            if (!isPublicKeyLoaded) await fetchPublicKey();
            privateAES = generateAESKey();
            const ivEncoded = base64EncodeBytes(privateAES.iv);
            const encryptedKey = encryptAESKey(privateAES.key);
            if (config.data) {
                if (config.data instanceof FormData) {
                    config.data = await encryptFormData(config.data, privateAES.key, privateAES.iv);
                } else {
                    const { ciphertext } = await encryptAES_GCM(config.data, privateAES.key, privateAES.iv);
                    config.data = {
                        encrypted_data: ciphertext,
                        iv: ivEncoded,
                        encrypted_key: encryptedKey,
                    };
                }
            }
            if (config.method === "get" && config.params) {
                const { ciphertext } = await encryptAES_GCM(config.params, privateAES.key, privateAES.iv);
                config.params = {
                    encrypted_data: ciphertext,
                    iv: ivEncoded,
                    encrypted_key: encryptedKey,
                };
            }
            return config;
        },
        async (error) => Promise.reject(await tryDecryptError(error))
    );
    instance.interceptors.response.use(
        async (response) => {
            if (response.data?.encrypted_data && response.data?.iv && privateAES) {
                const decrypted = await decryptAES_GCM(
                    response.data.encrypted_data,
                    privateAES.key,
                    base64DecodeBytes(response.data.iv)
                );
                return { ...response, data: decrypted };
            }
            return response;
        },
        async (error) => Promise.reject(await tryDecryptError(error))
    );
}
async function tryDecryptError(error) {
    if (
        error.response?.data?.encrypted_data &&
        error.response?.data?.encrypted_key &&
        error.response?.data?.iv
    ) {
        if (!privateAES) throw new Error("No AES key for decryption");
        const decrypted = await decryptAES_GCM(
            error.response.data.encrypted_data,
            privateAES.key,
            base64DecodeBytes(error.response.data.iv)
        );
        return {
            ...error,
            response: {
                ...error.response,
                data: decrypted,
            },
        };
    }
    return error;
}
export async function createEncryptedFileAxios(instance) {
    await fetchPublicKey();
    await attachEncryptionInterceptors(instance);
    return instance;
}