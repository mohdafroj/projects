// departmentData.js
import { ref } from 'vue'

export const useDeppartmentData = () => {
  // Create dummy data with 4 levels of nesting
  const data = ref([
    {
      id: 1,
      claimId: "#951112",
      name: "Member G",
      submissionDate: "3/26/2025",
      billAmount: '₹50000',
      claimStatus: "Submitted"
    },
    {
      id: 2,
      claimId: "#232328",
      name: "Member A",
      submissionDate: "2/2/2025",
      billAmount: '₹20000',
      claimStatus: "Initiated"
    },
    {
      id: 3,
      claimId: "#323139",
      name: "Member B",
      submissionDate: "1/1/2025",
      billAmount: '₹25000',
      claimStatus: "Initiated"
    },
    {
      id: 4,
      claimId: "#312365",
      name: "Member C",
      submissionDate: "12/13/2024",
      billAmount: '₹119000',
      claimStatus: "Approved"
    },
    {
      id: 5,
      claimId: "#513453",
      name: "Member D",
      submissionDate: "12/15/2024",
      billAmount: '₹40000',
      claimStatus: "Submitted"
    },
    {
      id: 6,
      claimId: "#566534",
      name: "Member E",
      submissionDate: "12/11/2024",
      billAmount: '₹90000',
      claimStatus: "Canceled"
    },
    {
      id: 7,
      claimId: "#533234",
      name: "Member F",
      submissionDate: "12/11/2024",
      billAmount: '₹104000',
      claimStatus: "Canceled"
    }
  ])
  

  // Function to toggle row expansion
  const toggleExpand = (row) => {
    row.expanded = !row.expanded
  }

  // Function to check if a row has children
  const hasChildren = (row) => {
    return row.subRows && row.subRows.length > 0
  }

  return {
    data,
    toggleExpand,
    hasChildren
  }
}