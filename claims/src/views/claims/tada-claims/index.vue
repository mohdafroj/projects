<template>
  <div
    class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6 container max-w-full w-full">

    <div class="container text-center space-y-8 ">
      <!-- Header -->
      <div>
        <h1 class="text-2xl font-semibold text-gray-800">Travel Claim</h1>
        <p class="text-gray-500 mt-2">Please select Claim Detail</p>
      </div>

      <!-- Cards Section -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 ">

        <!-- Card 1 -->
        <div
          class="bg-theme-light rounded-2xl shadow-md p-6 flex flex-col items-center space-y-4 hover:shadow-lg transition">
          <img src="/public/assets/images/all-img/flight_journey.png" class="w-32 h-32">
          <Button variant="primary" size="lg" label="Add Flight by PNR Number" aria-label="Add Flight by PNR Number"
            @click="addFlightByPnr"
            class="theme-bg text-white px-2 p-2 rounded-2xl hover:bg-indigo-700 transition w-full text-center block" />




        </div>


        <!-- Card 2 -->
        <div
          class="bg-theme-light rounded-2xl shadow-md p-6 flex flex-col items-center space-y-4 hover:shadow-lg transition">
          <!-- Inline SVG icon replacing the img -->
          <img src="/public/assets/images/all-img/manual_journey.png" class="w-32 h-32">

          <Button variant="primary" size="lg" label="Add Manual Form" aria-label="Add Manual Form"
            @click="addManualForm"
            class="theme-bg text-white px-2 p-2 rounded-2xl hover:bg-indigo-700 transition w-full text-center block" />
        </div>



        <!-- Card 3 -->
        <div
          class="bg-theme-light rounded-2xl shadow-md p-6 flex flex-col items-center space-y-4 hover:shadow-lg transition">
          <img src="/public/assets/images/all-img/road_journey.png" class="w-32 h-32"></img>

          <Button variant="primary" size="lg" label="Add Road Journey" aria-label="Add Road Journey"
            @click="addRoadJourney"
            class="theme-bg text-white px-2 p-2 rounded-2xl hover:bg-indigo-700 transition w-full text-center block" />


        </div>

      </div>

      <!-- Footer Note -->
      <p class="text-gray-500 text-lg">
        The Bar Code will be automatically detected when you position it between the guide lines
      </p>
    </div>


  </div>
</template>


<script setup>
// import { ref } from 'vue'
import { useRouter } from 'vue-router'
const router = useRouter();
import { Button } from '@sds/oneui-common-ui'

const addFlightByPnr = () => {
  router.push({ name: 'addFlightByPNR' });
}
const addRoadJourney = () => {

  router.push({ name: 'addRoadJourney' });
}
const addManualForm = () => {
  router.push({ name: 'TADAClaimsAdd' });
}
</script>

<style scoped>
.bg-theme-light {
  background: #EBEEFF;
}

.theme-bg {
  background: #3E4F88;
}
</style>
