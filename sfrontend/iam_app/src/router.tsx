import React from "react";
import { Routes, Route, Navigate } from "react-router-dom";
import Login from "./pages/Login";
import Register from "./pages/Register";
import IAMDashboard from "./pages/Dashboard";
import { LINKS } from "./routes/routes";
console.log(LINKS)
const AppRouter = () => {
  return (
    <Routes>
      <Route path="/" key="Dashboard" element={<IAMDashboard />} />
      <Route path="/register" key="Register" element={<Register />} />
      <Route path="/login" key="Login" element={<Login />} />
      {/* Redirect any unknown sub-paths back to dashboard */}
      <Route path="*" key={'NotFound'} element={<Navigate to={LINKS.DASHBOARD.path} replace />} />
    </Routes>
  );
};

export default AppRouter;
