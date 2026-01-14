<template>
  <div class="min-h-screen w-full bg-[#B2EBF2] flex items-center justify-center">
    <svg class="svg-defs" xmlns="http://www.w3.org/2000/svg" version="1.1">
      <defs>
        <filter id="squiggly-0">
          <feTurbulence id="turbulence" baseFrequency="0.02" numOctaves="3" result="noise" seed="0" />
          <feDisplacementMap id="displacement" in="SourceGraphic" in2="noise" scale="2" />
        </filter>
        <filter id="squiggly-1">
          <feTurbulence id="turbulence" baseFrequency="0.02" numOctaves="3" result="noise" seed="1" />
          <feDisplacementMap in="SourceGraphic" in2="noise" scale="3" />
        </filter>
        <filter id="squiggly-2">
          <feTurbulence id="turbulence" baseFrequency="0.02" numOctaves="3" result="noise" seed="2" />
          <feDisplacementMap in="SourceGraphic" in2="noise" scale="2" />
        </filter>
        <filter id="squiggly-3">
          <feTurbulence id="turbulence" baseFrequency="0.02" numOctaves="3" result="noise" seed="3" />
          <feDisplacementMap in="SourceGraphic" in2="noise" scale="3" />
        </filter>
        <filter id="squiggly-4">
          <feTurbulence id="turbulence" baseFrequency="0.02" numOctaves="3" result="noise" seed="4" />
          <feDisplacementMap in="SourceGraphic" in2="noise" scale="1" />
        </filter>
      </defs>
    </svg>

    <section class="error">
      <div class="stage">
        <div class="key">
          <div class="key__head">
            <div class="key__eye"></div>
            <div class="key__eye"></div>
            <div class="key__mouth"></div>
          </div>
          <div class="key__body">
            <div class="key__indentation"></div>
            <div class="key__indentation"></div>
            <div class="key__indentation"></div>
            <div class="key__arm key__arm--left"></div>
            <div class="key__arm key__arm--right"></div>
          </div>
        </div>
        <div class="lock"></div>
      </div>
      <h1 class="error__header">403</h1>
      <h2 class="error__header error__header--secondary">{{t('permission_denied_title')}}</h2>
      <p class="text-xl max-w-4xl text-center">{{ t('permission_denied_message') }}</p>
      <button
        @click="$router.back()"
        class="go-back-button"
      >
        Go Back
      </button>
    </section>
  </div>
</template>

<script setup>
// export default {
//   name: 'Error403'
// }
import i18n from '@/i18n';
const { t } = i18n.global;
</script>

<style scoped>
html, body, #app {
  height: 100%;
  margin: 0;
  background-color: #B2EBF2 !important;
}

.error {
  align-items: center;
  /* background: #B2EBF2; */
  display: flex;
  flex-direction: column;
  /* height: 100%;
   */
   min-height: 100%;
   align-items: center;
   justify-content: center;
  padding: 1.5rem 0;
  
}
.error__header {
  font-size: 5rem;
  font-weight: 900;
  padding: 0  0 1.5rem 0;
  margin-top: -45px;
}
.error__header--secondary {
  font-size: 2.5rem;
  padding: 0 0 1rem 0;
  text-transform: uppercase;
}


.stage {
  height: 18rem;
  width: 8rem;
}

.key {
  -webkit-animation-name: squigglevision;
          animation-name: squigglevision;
  -webkit-animation-duration: 0.35s;
          animation-duration: 0.35s;
  -webkit-animation-direction: normal;
          animation-direction: normal;
  -webkit-animation-iteration-count: infinite;
          animation-iteration-count: infinite;
  position: relative;
  height: 100%;
  width: 8rem;
}
.key__head {
  background: #FDD835;
  border-radius: 50%;
  box-shadow: 0.85rem 0 0 #caa502;
  height: 8rem;
  position: absolute;
  width: 8rem;
  z-index: 1;
}
.key__head::before {
  background: #B2EBF2;
  border-radius: 50%;
  box-shadow: inset 0.5rem 0.1rem 0 #caa502;
  content: "";
  display: block;
  height: 1rem;
  left: 3.5rem;
  position: absolute;
  width: 1rem;
  top: 1.5rem;
}
.key__eye {
  background-color: #111;
  border-radius: 50%;
  height: 0.75rem;
  left: 1.5rem;
  position: absolute;
  top: 4.5rem;
  width: 0.75rem;
}
.key__eye:nth-child(2) {
  left: 5rem;
}
.key__eye::before {
  -webkit-animation: scale 0.35s linear 0s infinite alternate;
          animation: scale 0.35s linear 0s infinite alternate;
  background: white;
  border-radius: 50%;
  content: "";
  display: block;
  height: 0.15rem;
  left: 0.15rem;
  position: absolute;
  width: 0.15rem;
  top: 0.15rem;
}
.key__eye::after {
  -webkit-animation: scale 0.25s linear 0s infinite alternate;
          animation: scale 0.25s linear 0s infinite alternate;
  background: white;
  border-radius: 50%;
  content: "";
  display: block;
  height: 0.25rem;
  left: 0.35rem;
  position: absolute;
  width: 0.25rem;
  top: 0.35rem;
}
.key__mouth {
  -webkit-animation: sniff 2s linear 0s infinite alternate;
          animation: sniff 2s linear 0s infinite alternate;
  border-radius: 50%;
  border: 0.2rem solid #111111;
  border-bottom-color: transparent;
  border-left-color: transparent;
  height: 1.25rem;
  left: 2.85rem;
  position: relative;
  top: 5rem;
  transform: rotate(-45deg);
  width: 1.25rem;
}
.key__body {
  background: #FDD835;
  box-shadow: 1rem 0 0 #caa502;
  height: 9rem;
  position: relative;
  width: 3rem;
  top: 7rem;
  left: 2.5rem;
}
.key__body::before {
  background: rgba(202, 165, 2, 0.5);
  border-radius: 1rem;
  content: "";
  display: block;
  height: 8.5rem;
  left: 1rem;
  position: absolute;
  top: 2rem;
  width: 0.45rem;
  z-index: 2;
}
.key__body::after {
  background: #FDD835;
  border-radius: 0 0 80% 25%;
  box-shadow: 1rem 0 0 #caa502;
  content: "";
  display: block;
  height: 2rem;
  position: absolute;
  top: 100%;
  width: 3rem;
}
.key__arm {
  border-radius: 50%;
  border: 0.2rem solid #111111;
  border-bottom-color: transparent;
  border-top-color: transparent;
  height: 4.55rem;
  position: absolute;
  width: 1.25rem;
}
.key__arm--left {
  left: 1.9rem;
  top: 1rem;
  border-left-color: transparent;
}
.key__arm--right {
  left: -0.5rem;
  top: 1rem;
  border-right-color: transparent;
}
.key__indentation {
  background: #FDD835;
  box-shadow: 0.5rem -0.5rem 0 #caa502;
  height: 2rem;
  transform: rotate(45deg);
  width: 2rem;
  position: absolute;
  top: 2rem;
  left: 2rem;
}
.key__indentation:nth-child(2) {
  top: 4.5rem;
  width: 1.5rem;
  height: 1.5rem;
}
.key__indentation:nth-child(3) {
  top: 6rem;
  width: 1.75rem;
  height: 1.75rem;
}

@-webkit-keyframes scale {
  from {
    transform: scale(1);
  }
  to {
    transform: scale(1.4);
  }
}

@keyframes scale {
  from {
    transform: scale(1);
  }
  to {
    transform: scale(1.4);
  }
}
@-webkit-keyframes sniff {
  0%, 70%, 100% {
    transform: translateY(0) rotate(-45deg);
  }
  75% {
    transform: translateY(-5%) rotate(-45deg);
  }
}
@keyframes sniff {
  0%, 70%, 100% {
    transform: translateY(0) rotate(-45deg);
  }
  0%, 70%, 100%  {
    transform: translateY(-5%) rotate(-45deg);
  }
}
.svg-defs {
  display: none;
}

@-webkit-keyframes squigglevision {
  0% {
    filter: url("#squiggly-0");
  }
  25% {
    filter: url("#squiggly-1");
  }
  50% {
    filter: url("#squiggly-2");
  }
  75% {
    filter: url("#squiggly-3");
  }
  100% {
    filter: url("#squiggly-4");
  }
}

@keyframes squigglevision {
  0% {
    filter: url("#squiggly-0");
  }
  25% {
    filter: url("#squiggly-1");
  }
  50% {
    filter: url("#squiggly-2");
  }
  75% {
    filter: url("#squiggly-3");
  }
  100% {
    filter: url("#squiggly-4");
  }
}

.go-back-button {
  margin-top: 1.0rem;
  padding: 0.5rem 1rem;
  background-color: #FDD835;
  color: #111111;
  font-weight: 600;
  border-radius: 0.25rem;
  cursor: pointer;
  transition: background-color 0.3s;
}

.go-back-button:hover {
  background-color: #caa502;
}
</style>
