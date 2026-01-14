import Swal from "sweetalert2";
export const isSwal = (err, isError = 'error') => {
    Swal.fire({
        toast: true,
        position: "top-end",
        icon: isError,
        title: err,
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        customClass: {
            popup: "bg-white text-gray-800 dark:bg-slate-800 dark:text-slate-300 shadow-lg rounded-lg",
            title: "text-gray-900 dark:text-slate-100",
        },
    });
}