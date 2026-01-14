export const allowOnlyDigits = (event) => {
    const char = String.fromCharCode(event.which || event.keyCode);
    if (!/[0-9]/.test(char)) {
        event.preventDefault();
    }
};