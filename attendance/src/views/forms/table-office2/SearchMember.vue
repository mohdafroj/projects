<template>
    <div class="form-group col-span-3">
      <label class="block text-sm font-semibold mb-2">Search by</label>
      <RadioGroup 
        v-model="SearchMethods"
        :options="SearchMethodsOptions"
        name="search_method"
        gridClass="grid grid-cols-2 md:grid-cols-4 gap-4"
      />
      <p v-if="errors.search_method" class="text-red-500 text-sm">
        {{ errors.search_method }}
      </p>
    </div>
  
    <!-- Conditionally Render Inputs Based on Search Method -->
    <div v-if="SearchMethods === 'search-by-ic'" class="form-group col-span-3">
        <div class="grid md:grid-cols-3">
      <Textinput
        label="IC Number"
        type="text"
        placeholder="Enter IC Number"
        name="ic_number"
        v-model="icNumber"
        :error="errors.icNumber"
      />
    </div>

    </div>
  
    <div v-if="SearchMethods === 'search-by-name'" class="form-group col-span-3 grid grid-cols-1 md:grid-cols-4 gap-4">
      <Textinput
        label=" First Name"
        type="text"
        placeholder="Enter First Name"
        name="first_name"
        v-model="firstName"
        :error="errors.firstName"
      />
      <Textinput
        label="Middle Name"
        type="text"
        placeholder="Enter Middle Name"
        name="middle_name"
        v-model="middleName"
        :error="errors.middleName"
      />
      <Textinput
        label="Last Name"
        type="text"
        placeholder="Enter Last Name"
        name="last_name"
        v-model="lastName"
        :error="errors.lastName"
      />
      <Textinput
        label="Date of Birth"
        type="date"
        placeholder="Date of Birth"
        name="last_name"
        v-model="dateofBirth"
        :error="errors.dateofBirth"
      />
    </div>
  
    <div v-if="SearchMethods === 'search-by-email'" class="form-group col-span-3">
        <div class="grid md:grid-cols-3">
      <Textinput
        label="Email"
        type="email"
        placeholder="Enter Email Address"
        name="email"
        v-model="email"
        :error="errors.email"
      />
    </div>
    </div>
  
    <!-- Single Search Button for All Methods -->
    <div class="col-span-3 flex justify-end">
      <Button text="Search" btnClass="btn-primary" @click="performSearch" />
    </div>
  </template>
  
  <script setup>
  import { ref } from "vue";
  import Button from "@/ui-components/Button.vue";
  import Textinput from "@/ui-components/Textinput.vue";
  import RadioGroup from "@/ui-components/RadioGroup.vue";
  
  // Define search method options
  const SearchMethodsOptions = ref([
    { label: "Search by IC Number", value: "search-by-ic" },
    { label: "Search by Name", value: "search-by-name" },
    { label: "Search by Email", value: "search-by-email" }
  ]);
  
  // ✅ Set the first search method as default
  const SearchMethods = ref(SearchMethodsOptions.value[0].value);
  
  // Define input fields
  const icNumber = ref("");
  const firstName = ref("");
  const middleName = ref("");
  const lastName = ref("");
  const email = ref("");
  const dateofBirth = ref("");
  
  // Define errors object
  const errors = ref({});
  
  // Function to validate inputs
  const validateInputs = () => {
    errors.value = {}; // Reset errors
  
    if (SearchMethods.value === "search-by-ic" && !icNumber.value) {
      errors.value.icNumber = "IC Number is required";
      return false;
    }
    if (SearchMethods.value === "search-by-name") {
      if (!firstName.value) errors.value.firstName = "First Name is required";
      if (!lastName.value) errors.value.lastName = "Last Name is required";
      if (Object.keys(errors.value).length > 0) return false;
    }
    if (SearchMethods.value === "search-by-email" && !email.value) {
      errors.value.email = "Email is required";
      return false;
    }
  
    return true;
  };
  
  // Search function
  const performSearch = () => {
    if (!validateInputs()) return;
  
    console.log("Performing search based on", SearchMethods.value);
    if (SearchMethods.value === "search-by-ic") {
      console.log("Searching by IC Number:", icNumber.value);
    } else if (SearchMethods.value === "search-by-name") {
      console.log("Searching by Name:", firstName.value, middleName.value, lastName.value);
    } else if (SearchMethods.value === "search-by-email") {
      console.log("Searching by Email:", email.value);
    }
  };
  </script>
  