export const debounce = (fn, delay = 300) => {
  let timeout;

  return (...args) => {
    // Clear the previous timer
    clearTimeout(timeout);

    // Set a new timer
    timeout = setTimeout(() => {
      fn(...args); // Call the function after the delay
    }, delay);
  };
};
