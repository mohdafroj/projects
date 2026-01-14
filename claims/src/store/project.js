import { defineStore } from "pinia";
import { v4 as uuidv4 } from "uuid";
import { toast } from "vue3-toastify";

export const useProjectStore = defineStore('project', {
  state: () => ({
    addmodal: false,
    isLoading: null,
    // for edit
    editModal: false,
    editName: "",
    editStartDate: null,
    editEndDate: null,
    editcta: null,
    editId: null,
    editdesc: null,

    projects: [
      {
        id: uuidv4(),
        name: "Management Dashboard",
        des: "Amet minim mollit non deserunt ullamco est sit aliqua dolor do amet sint.",
        startDate: "18-02-2025",
        endDate: "29-02-2025",
        progress: 75,
        category: [
          { value: "low", label: "low" },
        ],
      },
      {
        id: uuidv4(),
        name: "Business Dashboard",
        des: "Amet minim mollit non deserunt ullamco est sit aliqua dolor do amet sint.",
        startDate: "18-02-2025",
        endDate: "19-02-2025",
        progress: 50,
        category: [
          { value: "low", label: "low" },
        ],
      },
    ],
  }),
  actions: {
    //  function to convert date format (yyyy-mm-dd to dd-mm-yyyy)
    formatDateToDDMMYYYY(date) {
      const [year, month, day] = date.split("-");
      return `${day}-${month}-${year}`;
    },

    //  function to convert date format (dd-mm-yyyy to yyyy-mm-dd)
    formatDateToYYYYMMDD(date) {
      const [day, month, year] = date.split("-");
      return `${year}-${month}-${day}`;
    },

    // Add Project
    addProject(data) {
      this.isLoading = true;

      setTimeout(() => {
        this.projects.unshift({
          ...data,
          startDate: this.formatDateToYYYYMMDD(data.startDate),
          endDate: this.formatDateToYYYYMMDD(data.endDate),
        });
        this.isLoading = false;
        toast.success("Project added", {
          timeout: 2000,
        });
      }, 1500);
      this.addmodal = false;
    },

    // Remove Project
    removeProject(data) {
      this.projects = this.projects.filter((item) => item.id !== data.id);
      toast.error("Project Removed", {
        timeout: 2000,
      });
    },

    // Update Project
    updateProject(data) {
      this.projects.findIndex((item) => {
        if (item.id === data.id) {
          // Store data
          this.editId = data.id;
          this.editName = data.name;
          this.editStartDate = this.formatDateToDDMMYYYY(data.startDate); // Convert date for display
          this.editEndDate = this.formatDateToDDMMYYYY(data.endDate); // Convert date for display
          this.editcta = data.category;
          this.editdesc = data.des;
          this.editModal = !this.editModal;

          // Update project data (keep it in yyyy-mm-dd format for storage)
          item.name = data.name;
          item.des = data.des;
          item.startDate = this.formatDateToYYYYMMDD(data.startDate); // Convert back to yyyy-mm-dd
          item.endDate = this.formatDateToYYYYMMDD(data.endDate); // Convert back to yyyy-mm-dd
          item.progress = data.progress;
          item.category = data.category;
        }
      });
    },

    // Open Project Modal
    openProject() {
      this.addmodal = true;
    },

    // Close Modals
    closeModal() {
      this.addmodal = false;
    },

    closeEditModal() {
      this.editModal = false;
    },
  },
});
