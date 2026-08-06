import React, { useEffect } from "react";
import { Routes, Route, Navigate, useNavigate } from "react-router-dom";
import Login from "./pages/Login";
import Register from "./pages/Register";
import IAMDashboard from "./pages/Dashboard";
import { LINKS } from "./routes/routes";

const AppRouter = () => {
  const token = sessionStorage.getItem("access_token");
  return (
    <Routes>
      {token ? (
        <>
          <Route path="/" key="Dashboard" element={<IAMDashboard />} />
          <Route path="*" key={'NotFound'} element={<Navigate to="/" replace />} />
        </>
      ) : (
        <>
          <Route path="/login" key="Login" element={<Login />} />
          <Route path="/register" key="Register" element={<Register />} />
          <Route path="*" key={'NotFound'} element={<Navigate to={LINKS.LOGIN.path} replace />} />
        </>
      )}
    </Routes>
  );
};
export default AppRouter;
