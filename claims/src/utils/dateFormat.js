
// const dateFormated = (rawDate)=> { 
//   console.log('dateStr-----',rawDate);
//     const date = new Date(rawDate);
//       console.log('dateCalc-----',date);
//     if (isNaN(date.getTime())) return 'No data'
//     const day = String(date.getDate()).padStart(2, '0');
//     const month = date.toLocaleString('en-US', { month: 'short' }); // e.g., 'Nov'
//     const year = date.getFullYear();
//     return `${day} ${month} ${year}`;

// };

const dateFormated = (rawDate) => {
  // console.log('dateStr-----', rawDate);

  if (!rawDate) return 'No data';

  // Detect DD/MM/YYYY format
  const parts = rawDate.split("/");
  if (parts.length === 3) {
    const [dd, mm, yyyy] = parts;
    rawDate = `${yyyy}-${mm}-${dd}`; // Convert format
  }

  const date = new Date(rawDate);
  // console.log('dateCalc-----', date);

  if (isNaN(date.getTime())) return 'No data';

  const day = String(date.getDate()).padStart(2, '0');
  const month = date.toLocaleString('en-US', { month: 'short' });
  const year = date.getFullYear();

  return `${day} ${month} ${year}`;
};


const timeFormated = (datetimeString) => {
  if (!datetimeString) return 'No time';
  const date = new Date(datetimeString);
  return date.toLocaleTimeString('en-US', {
    hour: '2-digit',
    minute: '2-digit',
    hour12: true, // use false if you want 24-hour format
  });
};


const getMemberCghsCardRequest = (string) => {
  if (string) return 'No Status';
  return string === null || string == '' || string == 9999999 ? "New Request" : "Updated Request";
};

const dbDateFormat = (dtStr) => {
  let formattedDate = '';
  if (dtStr) {
    const input = dtStr; // dd/MM/yyyy
    // Step 1: Split into parts
    const [day, month, year] = input.split('/');

    // Step 2: Reformat to yyyy-MM-dd
    formattedDate = `${year}-${month}-${day}`;
  }
  return formattedDate;
}




export { dateFormated, timeFormated, getMemberCghsCardRequest, dbDateFormat };