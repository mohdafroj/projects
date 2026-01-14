<template>
  <div class="h-14 w-14 mx-auto" v-if="!isLoading">
    <img src="/assets/images/svg/loader.svg" alt="" />
  </div>
</template>
<script>
export default {
  name: "LoaderElement",
  data() {
    return {
      isLoading: false,
    };
  },
  mounted() {
    // onreadystatechange
    document.onreadystatechange = () => {
      if (document.readyState === "complete") {
        this.isLoading = true;
      }
    };
  },
};
</script>

