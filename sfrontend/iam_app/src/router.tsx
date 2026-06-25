import React from "react";
import { Routes, Route, Navigate } from "react-router-dom";
import Login from "./pages/Login";
import IAMDashboard from "./pages/Dashboard";

const AppRouter = () => {
  return (
    <Routes>
      <Route path="/" element={<IAMDashboard />} />
      <Route path="/login" element={<Login />} />
      {/* Redirect any unknown sub-paths back to dashboard */}
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  );
};

export default AppRouter;
