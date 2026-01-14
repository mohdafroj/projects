<template>
  <swiper
    :effect="'cards'"
    :grabCursor="true"
    :modules="modules"
    class="mySwiper"
  >
    <swiper-slide v-for="(item, i) in cards" :key="i">
      <div
        class="h-[200px] bg-gradient-to-r relative rounded-md z-[1] p-4 text-white"
        :class="item.bg"
      >
        <div class="overlay absolute left-0 top-0 h-full w-full -z-[1]">
          <img
            src="/assets/images/all-img/visa-card-bg.png"
            alt=""
            class="h-full w-full object-cover"
          />
        </div>

        <div class="mt-[18px] font-semibold text-2xl mb-[17px]">
          {{ item.cardTitle }}
        </div>
        <div class="text-xs text-opacity-75 mb-[2px]">{{ item.subTitle }}</div>
        <div class="text-3xl font-semibold">{{ item.Value }}</div>
      </div>
    </swiper-slide>
  </swiper>
</template>

<script setup>
import { Swiper, SwiperSlide } from "swiper/vue";
import "swiper/css";
import "swiper/css/effect-cards";
import { EffectCards } from "swiper";

const cards = [
  {
    bg: "from-primary-500 to-[#1EABEC]",
    cardTitle: "IT Equipment Reimbursement",
    subTitle: "Remaining Fund",
    Value: "₹ 10,9750",
  },
  {
    bg: "from-[#4C33F7] to-[#801FE0] ",
    cardTitle: "Current Session Attendance",
    subTitle: "Attendance in %",
    Value: "85%",
  },
  {
    bg: "from-[#FF9838] to-[#008773]",
    cardTitle: "Total Attendance",
    subTitle: "Attendance in %",
    Value: "75%",
  },
];

const modules = [EffectCards];
</script>

<style lang="scss" scoped>
.bg-full {
  background-size: 100% 100%;
}
</style>
