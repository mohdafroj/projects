import React from "react";
import ReactDOM from "react-dom/client";
import { BrowserRouter } from "react-router-dom";
import Header from "./Header";

function mount(el) {
  if (!el) {
    console.error("Root element not found");
    return;
  }

  const root = ReactDOM.createRoot(el);
  root.render(
    <React.StrictMode>
      <BrowserRouter future={{ v7_startTransition: true, v7_relativeSplatPath: true }}>
        <Header />
      </BrowserRouter>
    </React.StrictMode>
  );

  return root;
}

const el = document.getElementById("root");
if (el) {
  mount(el);
}

export { mount };
