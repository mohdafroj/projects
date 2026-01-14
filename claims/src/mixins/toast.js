// src/utils/toast.js
import Swal from 'sweetalert2';

export const fireSwal = () => {
  return Swal.mixin();
};

export const fireToast = (ob = {}) => {
  const { type, message } = ob;
  const Toast = Swal.mixin({
    toast: true,
    position: 'top',
    timer: 5000,
    timerProgressBar: true,
    showConfirmButton: false,
    didOpen: toast => {
      toast.addEventListener('mouseenter', Swal.stopTimer);
      toast.addEventListener('mouseleave', Swal.resumeTimer);
    },
  });
  if (message && type) Toast.fire({ icon: type, title: message });
};

export const fireDelete = (ob = {}) => {
  fireSwal().fire({
    position: 'center',
    timer: null,
    icon: ob?.icon ?? 'question',
    title: ob?.title ?? 'Are you sure?',
    text: ob?.text ?? 'You want to delete this record!',
    showConfirmButton: true,
    confirmButtonText: ob?.confirmButtonText ?? 'Yes, Sure',
    showCancelButton: true,
    cancelButtonText: ob?.cancelButtonText ?? 'Cancel',
    showLoaderOnConfirm: true,
    customClass: {
      popup: 'w-[350px] text-sm',
      title: 'text-xl text-base font-semibold',
      confirmButton: 'bg-red-500 hover:bg-red-500 px-4 py-2 rounded',
      cancelButton: 'bg-gray-500 hover:bg-gray-500 px-4 py-2 rounded',
    },
    preConfirm:
      ob?.api ??
      (async () => {
        console.log('Default preConfirm called');
      }),
  });
  // .then((response)=>{
  //     if (response.isConfirmed == false) console.log();
  // })
};
