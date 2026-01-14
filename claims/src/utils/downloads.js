
// npm install xlsx
import * as XLSX from 'xlsx';

const exportToXlsx = (data, filename = 'records') => {
    // Convert JSON data to worksheet
    const worksheet = XLSX.utils.json_to_sheet(data);

    // Create a new workbook and append the worksheet
    const workbook = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(workbook, worksheet, 'Sheet1');

    // Write the workbook to a file
    XLSX.writeFile(workbook, filename + '.xlsx');
};

const downloadFile = ({ url, name, ext = '' }) => {
    const link = document.createElement('a')
    let fileName = name;
    if (ext != '') {
        fileName = fileName + '.' + ext;
    }
    link.href = url
    link.download = fileName // optional: change filename
    link.target = '_blank' // optional: open in new tab
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
}

export const downloadFileByUrl = async (url, name = "Invoice") => {
    try {
        const response = await fetch(url)
        if (!response.ok) throw new Error("Failed to fetch file")

        const blob = await response.blob()
        const blobUrl = URL.createObjectURL(blob)

        const link = document.createElement("a")
        link.href = blobUrl
        link.download = name
        document.body.appendChild(link)
        link.click()
        document.body.removeChild(link)

        URL.revokeObjectURL(blobUrl) // cleanup
    } catch (err) {
        console.error("Download failed:", err)
    }
}

export const isImage = (name) => {
    return /\.(jpe?g|png|gif|bmp|webp)$/i.test(name)
}

export const printFileByUrl = async (url) => {
    if (isImage(url)) {
        // For images: print the modal preview
        const imgHtml = `<img src="${url}" style="width:100%;height:auto;" />`
        const w = window.open('', '', 'width=800,height=600')
        w.document.write(imgHtml)
        w.document.close()
        w.focus()
        w.print()
        w.close()
    } else {
        try {
            const response = await fetch(url)
            const blob = await response.blob()
            const blobUrl = URL.createObjectURL(blob);

            const w = window.open(blobUrl, '_blank')
            w.focus()
        } catch (err) {
            console.error('Failed to print PDF:', err)
        }
    }
}

export {
    exportToXlsx,
    downloadFile
}