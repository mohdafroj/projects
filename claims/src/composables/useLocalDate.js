const useLocalDate = (dateInput, format = '') => {
    const date = new Date(dateInput);
    if (isNaN(date)) return '';
    let formattedDate = '';
    if (format == 'dd-mm-yyyy') {
        const day = String(date.getDate()).padStart(2, '0');        // 01-31
        const month = String(date.getMonth() + 1).padStart(2, '0'); // 01-12
        const year = date.getFullYear();
        formattedDate = `${day}-${month}-${year}`;
    } else {
        formattedDate = date.toLocaleString('en-US', {
            month: 'short',  // "Aug"
            day: '2-digit',  // "10"
            year: 'numeric', // "2025"
            hour: 'numeric',
            minute: '2-digit',
            hour12: true     // AM/PM format
        }).replace(',', ''); // Optional: removes comma between date & year
    }
    return formattedDate;
};

export default useLocalDate;
