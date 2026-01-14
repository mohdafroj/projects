<template>
  <div class="p-6 bg-white min-h-screen text-center">
    <!-- Header Section -->
    <div class="flex items-center justify-center h-full">
      <img src="/public/assets/images/all-img/watch-circle.gif" alt="watch sign" class="watch-sign w-50 h-36" />
    </div>

    <p class="text-align-center text-2xl font-bold mb-4 mt-4">
      A notification has been sent to your linked phone number.
      <br />Please open your authenticator app and enter the
      <br />
      PIN to digitally sign the document.
    </p>
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from "vue";
import { useRoute, useRouter } from "vue-router";
import { getEsignStatus } from "@/services/rss/esignServices";

const route = useRoute();
const router = useRouter();

const message = ref("");
let intervalId = null;

const getSignStatusDetails = async (requestId, claimId) => {
  try {
    const response = await getEsignStatus(requestId, claimId);

    if (response.success_code === 200) {
      message.value = response.data?.errorCode;

      // ✅ Example: success when errorCode === 2107
      if (message.value === 2107) {
        clearInterval(intervalId);
        intervalId = null;

        // 🔹 Add redirect or success action here
        router.replace({ name: "TADAClaims" });
      }
      if (message.value == 0) {
        clearInterval(intervalId);
        intervalId = null;
        router.replace({ name: "TADAClaims" });
      }
    }
    // if (response.isError) {
    //   clearInterval(intervalId);
    //   intervalId = null;
    //   router.replace({ name: "ITClaimList" });
    // }
  } catch (error) {
    //console.log("error", error);
    // router.replace({ name: "ITClaimList" });
    // Retry only on specific error
    if (error?.error_code === 1723) {
      setTimeout(() => {
        getSignStatusDetails(route.query?.requestId, route.query?.claimId);
      }, 15000);
    }

    clearInterval(intervalId);
    intervalId = null;
  }
};

onMounted(() => {
  // Access navigation state (not query)
  const historyState = router.options.history.state;

  const requestId = historyState?.requestId;
  const claimId = historyState?.claimId;

  if (requestId && claimId) {
    getSignStatusDetails(requestId, claimId);

    intervalId = setInterval(() => {
      getSignStatusDetails(requestId, claimId);
    }, 15000);
  } else {
    // fallback if state is missing (like on page reload)
    router.replace({ name: "ITClaimList" });
  }
});

onBeforeUnmount(() => {
  if (intervalId) {
    clearInterval(intervalId);
    intervalId = null;
  }
});
</script>
