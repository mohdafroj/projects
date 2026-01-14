import Swal from "sweetalert2";

const swalWithBootstrapButtons = Swal.mixin({
  customClass: {
    confirmButton: "bg-green-600 px-4 py-2 mr-2 rounded-md text-white hover:bg-green-700",
    cancelButton: "bg-red-400 px-4 py-2 ml-2 font-semibold rounded-md text-white hover:bg-red-500",
  },
  buttonsStyling: false,
});

export default swalWithBootstrapButtons;