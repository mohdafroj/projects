<template>
  <div class="error-page">
    <div class="fixed-text">
      <div class="code">404</div>
      <div class="message">Page Not Found</div>
    </div>
    <div class="torch-wrapper">
      <div class="meta"></div>
      <div class="head"></div>
    </div>
    <div class="message-box">
      <p>We’re sorry, but the page you’re looking for doesn’t exist or has been moved.</p>
      <p>
        Please check the URL for errors or return to
        <a href="/" class="home-link">homepage</a>
      </p>
      <p>If you believe this is an error, feel free to contact us.</p>
    </div>
  </div>
</template>



<style scoped lang="scss">
.error-page {
  background: #1A1A1A;
  font-family: Arial, "Helvetica Neue", Helvetica, sans-serif;
  overflow: hidden;
  color: #1A1A1A;
  position: relative;
  height: 100vh;
}

.fixed-text {
  position: absolute;
  bottom: 320px;
  left: 0;
  right: 0;
  text-align: center;
  z-index: 200;

  .code {
    font-size: 100px;
    font-weight: 600;
  }

  .message {
    margin-top:-20px;
    font-size: 22px;
    font-weight: 400;
  }
}

.torch-wrapper {
  position: absolute;
  left: 0;
  right: 0;
  bottom: -5px;
  width: 100%;
  animation: find 5s linear infinite;
  transform-origin: center bottom;
}

.head {
  display: block;
  width: 35px;
  height: 140px;
  background: #646464;
  border-bottom: 10px solid #333;
  margin: auto;
  transform-origin: 50% 100%;
  border-bottom-left-radius: 4px;
  border-bottom-right-radius: 4px;
  position: relative;

  &::after {
    content: '';
    position: absolute;
    border-style: solid;
    border-width: 25px 25px 0 25px;
    border-color: #333 transparent transparent transparent;
    width: 85px;
    left: -25px;
    top: 0;
    box-shadow: 0 -10px 0 #FEAF33;
  }

  &::before {
    content: '';
    position: absolute;
    width: 8px;
    height: 15px;
    background: #FEAF33;
    left: 0;
    right: 0;
    margin: auto;
    top: 40px;
    border-radius: 6px;
    box-shadow: 0 0 1px 2px #333;
  }
}

.meta {
  display: block;
  width: 250px;
  height: 200px;
  background: #FFFEE2;
  border-radius: 50%;
  position: absolute;
  left: 50%;
  transform: translateX(-50%);
  bottom: 270px;
  text-align: center;
  color: #333;
  font-size: 90px;
  line-height: 200px;
  font-weight: 600;
  z-index: 1;

  &::after {
    content: '';
    position: absolute;
    border-style: solid;
    border-width: 190px 75px 0 75px;
    border-color: rgba(255, 254, 226, 0.3) transparent transparent transparent;
    width: 230px;
    left: 10px;
    top: 100%;
    transform: translateY(-60px);
  }
}

@keyframes find {
  0%, 50%, 100% {
    transform: rotate(0deg);
  }
  25% {
    transform: rotate(25deg);
  }
  75% {
    transform: rotate(-25deg);
  }
}
.message-box {
  position: absolute;
  bottom: 40px;
  right: 40px;
  width: 400px;
  color: #ccc;
  font-size: 17px;
  line-height: 1.5;
  z-index: 10;

  p {
    margin: 0 0 10px;
  }

  .home-link {
    background-color: #f8901a; 
    color: #000;
    padding: 2px 6px;
    border-radius: 4px;
    text-decoration: none;
    font-weight: 500;

    &:hover {
      background-color: #B45309; 
    }
  }
}


</style>