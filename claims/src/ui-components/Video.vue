<template>
  <div class="video-container">
    <div class="video-wrapper">
    <video-player
      class="video-player vjs-big-play-centered mx-auto"
      :src="src"
      :poster="poster"
      :playsinline="playsinline"
      :controls="controls"
      :loop="loop"
      :volume="volume"
      :height="height"
      :playback-rates="playbackRates"
      :responsive="responsive"
      :fluid="fluid"
    />
  </div>
  </div>
  </template>
  
  <script setup>
import { VideoPlayer } from "@videojs-player/vue";
import "video.js/dist/video-js.css";

defineProps({
  src: {
    type: String,
    required: true,
  },
  poster: {
    type: String,
    default: "",
  },
  playsinline: {
    type: Boolean,
    default: true,
  },
  controls: {
    type: Boolean,
    default: true,
  },
  loop: {
    type: Boolean,
    default: false,
  },
  volume: {
    type: Number,
    default: 0.6,
  },
  height: {
    type: Number,
    default: 320,
  },
  playbackRates: {
    type: Array,
    default: () => [0.7, 1.0, 1.5, 2.0],
  },
  responsive: {
    type: Boolean,
    default: true,
  },
  fluid: {
    type: Boolean,
    default: true,
  },
});
</script>

<style scoped>
.video-container {
  width: 100%;
}
.video-wrapper {
  display: flex;
  justify-content: center;
  align-items: center;
}
</style>

  