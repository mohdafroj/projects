 <template>
   
    <div v-if="showSplash" class="splash" id="splash"></div>
  
 
    <div class="main-content" :class="{ 'show': !showSplash }">
      <div class="slider-wrapper">
        <div class="bg-slide"></div>
        <div class="bg-slide"></div>
      </div>
  
      <div class="overlay"></div>
     
  
      <div class="center-content">
        <h1 class="text-xl font-bold ">Thank you for visiting https://secretariat.rajyasabha.digital </h1>
        <!-- <button @click="goToLogin">Login Again</button> -->
        <Button
  label="Sign In Again"
size="xl"

  color="blue"
  @click="goToLogin"
/>
      </div>
    </div>
  </template>
  
  <script setup>
  import { ref, onMounted } from 'vue'
  import { Button } from '@sds/oneui-common-ui'

  import { useRouter } from 'vue-router'
  
  const showSplash = ref(true)
  const router = useRouter()
  
  const goToLogin = () => {
    router.push('/login')
  }
  
  onMounted(() => {
    // Wait for the splash image to load and then show the main content
    setTimeout(() => {
      showSplash.value = false
    }, 50) // Keep splash for 50ms (you can adjust this)
  })
  </script>
  
  <style scoped>
  * {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
  }
  
  html, body {
    height: 100%;
    overflow: hidden;
    font-family: Arial, sans-serif;
  }
  
  /* Splash screen */
  .splash {
    position: fixed;
    top: 0; 
    left: 0;
    width: 100%;
    height: 100%;
  /*  background: url('/assets/images/all-img/New Project (1).png') no-repeat center center; */
    background-size: cover;
    z-index: 9999;
  }
  
  .main-content {
    opacity: 0;
    transition: opacity 1s ease-in;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
  }
  
  .main-content.show {
    opacity: 1;
  }
  
  .slider-wrapper {
    position: fixed;
    top: 20px; 
    left: 0;
    width: 200%;
    height: calc(100% - 20px);
    display: flex;
    animation: slideContinuous 15s linear infinite;
    z-index: -2;
  }
  
  .slider-wrapper div {
    width: 50%;
    height: 100%;
    background-position: top center;
    background-repeat: no-repeat;
    background-size: cover;
  }
  
  .bg-slide {
    background-image: url('/assets/images/all-img/landing-bg2.jpg');
  }
  
  @keyframes slideContinuous {
    0% { transform: translateX(0); }
    100% { transform: translateX(-50%); }
  }
  
  .overlay {
    background: url('/assets/images/all-img/project.png') no-repeat center top;
    background-size: cover;
    position: fixed;
    top: 0; 
    left: 0;
    width: 100%;
    height: calc(100%);
    z-index: -1;
  }
  
  .center-content { 
    text-align: center;
    color: #687095;
    padding-top: 27%;
    position: relative;
    z-index: 1;
  }
  
  .center-content h1 {
    font-size: 16px;
    margin-bottom: 30px;
    letter-spacing: 2px;
    display: block;
  }
  
  .center-content button {
    padding: 12px 28px;
    /* font-size: 14px; */
   /*  background-color: #3E4F88;
    color: #fff;
    border: none;
    border-radius: 24px;
    cursor: pointer;
    transition: background-color 0.3s ease;
    display: block;*/
    margin: 0 auto;
  } 
  
  /* .center-content button:hover {
    background-color: #233265;
  } */
  </style>


  