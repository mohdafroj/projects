import React from "react";
import ReactDOM from "react-dom/client";
import IAM from "./IAM";

function mount(el) {
  if (!el) {
    console.error("Root element not found");
    return;
  }

  const root = ReactDOM.createRoot(el);
  root.render(
    <React.StrictMode>
      <IAM />
    </React.StrictMode>
  );

  return root;
}

const el = document.getElementById("root");
if (el) {
  mount(el);
}

export { mount };
