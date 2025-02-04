import React from "react";
import "./App.css";
import Header from "./components/Header";
//import Example from "./components/Example";

function App() {
  return (
    <React.Fragment>
      <Header />
      {/* <Example /> */}
      <div className="container">
        <h2>Contact Us</h2>
        <p>
          LET’S CONNECT: WE’RE HERE TO HELP, AND WE’D LOVE TO HEAR FROM YOU!
          WHETHER YOU HAVE A QUESTION, COMMENT, OR JUST WANT TO CHAT , YOU CAN
          REACH OUT TO US THROUGH THE CONTACT FORM OF THIS PAGE, OR BY PHONE,
          EMAIL, OR SOCIAL MEDIA.{" "}
        </p>
        <div>
          <div></div>
          <div></div>
        </div>
      </div>
    </React.Fragment>
  );
}

export default App;
