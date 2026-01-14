const useLocalCurrency = (amount, options = {}) => {
    let finalAmount = Number(amount) || 0;
    return `${finalAmount.toLocaleString('en-IN', {
        style: 'currency',
        currency: options?.currency || 'INR',
        minimumFractionDigits: options?.minfrDigit || 2,
        maximumFractionDigits: options?.maxfrDigit || 2
    })}`;
};
export default useLocalCurrency;

