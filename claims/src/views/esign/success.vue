<template>
  <div
    class="min-h-screen flex flex-col justify-center items-center text-center py-20 bg-slate-900"
  >
    <div class="max-w-[546px] mx-auto w-full mt-12">
      <h3 class="text-white text-[40px] leading-[50px] mb-4">
        Esign Successful.
      </h3>
    </div>
  </div>
</template>
<script setup>
import { onMounted } from 'vue';
import { useRoute } from 'vue-router';

const route = useRoute();

const sendToParent = () => {
  if (window.opener && !window.opener.closed) {
    console.log(route.query);
    // Check if opener exists and is not closed
    window.opener.updateParent(route.query); // Call the parent function
    setTimeout(() => {
      window.close();
    }, 500);
    // Optionally close the popup after sending data
  } else {
    alert('Parent window is not available or closed.');
  }
};

onMounted(() => {
  sendToParent();
});
</script>
