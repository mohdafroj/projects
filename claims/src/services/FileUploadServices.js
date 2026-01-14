import { postMethod } from "@/composables/useApi";

async function calculateFileHash(file) {
    const buffer = await file.arrayBuffer();
    const digest = await crypto.subtle.digest("SHA-256", buffer);
    return Array.from(new Uint8Array(digest))
        .map((b) => b.toString(16).padStart(2, "0"))
        .join("");
}
const removeFiles = (payload) => {
    return postMethod({
        url: '/dms/removefiles', payload: payload, options: {
            headers: {
                'Accept-Language': 'en'
            }
        }, client: 'dms'
    })
}
const uploadChunkFile = (payload = {}) => {
    return postMethod({
        url: '/dms/uploadchunksfile', payload, options: {
            headers: {
                'Content-Type': 'multipart/form-data',
                'Accept-Language': 'en'
            }
        }, client: 'dms'
    });
}
const uploadFileInChunks = async (file, onProgress) => {
    const chunkSize = 1 * 1024 * 1024 - 128; // ~1MB
    const totalChunks = Math.ceil(file.size / chunkSize);
    const identifier = `${file.name}-${file.size}-${Date.now()}`;
    const fileHash = await calculateFileHash(file);
    let lastResponse = null;
    for (let i = 0; i < totalChunks; i++) {
        const start = i * chunkSize;
        const end = Math.min(start + chunkSize, file.size);
        const chunk = file.slice(start, end);
        const formData = new FormData();
        formData.append("file", chunk, file.name);
        formData.append("resumableFilename", file.name);
        formData.append("resumableIdentifier", identifier);
        formData.append("resumableChunkNumber", (i + 1).toString());
        formData.append("resumableTotalChunks", totalChunks.toString());
        formData.append("file_hash", fileHash);
        const response = await uploadChunkFile(formData, {
            onUploadProgress: (e) => {
                const chunkProgress = e.loaded / e.total;
                const overall = (i + chunkProgress) / totalChunks;
                if (onProgress) onProgress(overall);
            }
        });
        console.log("response :", response);
        if (response?.done === true) {
            lastResponse = {
                path: response.path,
                filename: response.file_name
            };
        }
        else {
            if (onProgress) onProgress((i + 1) / totalChunks);
            //  throw new Error(`Upload failed at chunk ${i + 1}`);
        }
    }
    return lastResponse;
};
export {
    uploadChunkFile, uploadFileInChunks, removeFiles
};