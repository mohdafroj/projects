<template>
  <div class="h-14 w-14 mx-auto" v-if="!isLoading">
    <img src="/assets/images/svg/loader.svg" alt="" />
  </div>
</template>

<script setup>
import { ref, onMounted } from "vue";

const isLoading = ref(false);

onMounted(() => {
  // onreadystatechange
  document.onreadystatechange = () => {
    if (document.readyState === "complete") {
      isLoading.value = true;
    }
  };
});
</script>
