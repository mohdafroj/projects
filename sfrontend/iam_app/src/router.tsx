import React, { useEffect } from "react";
import { Routes, Route, Navigate } from "react-router-dom";
import Login from "./pages/Login";
import Register from "./pages/Register";
import IAMDashboard from "./pages/Dashboard";
import { LINKS } from "./routes/routes";
console.log(LINKS)

const Logout = () => {
  useEffect(() => {
    localStorage.removeItem("iam_token");
    sessionStorage.clear();
  }, []);

  return <Navigate to={LINKS.LOGIN.path} replace />;
};
const AppRouter = () => {
  const token = sessionStorage.getItem("iam_token");
  return (
    <Routes>
      {token ? (
        <>
          <Route path="/" key="Dashboard" element={<IAMDashboard />} />
          <Route path="/logout" key="Logout" element={<Logout />} />
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
