const useLocalCurrency = (amount) => {
    return `₹${amount.toLocaleString('en-IN')}`;
};
export default useLocalCurrency;

