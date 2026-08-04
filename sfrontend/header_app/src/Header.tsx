import React from "react";
import { useLocation, Link } from "react-router-dom";
import "./index.css";

const iamPath = process.env.IAM_BASE_PATH || "";
const Header = () => {
  const location = useLocation();
  const isLogged = sessionStorage.getItem("access_token");

  return (
    <header id="header-app">
      <div className="bg-blue-950 text-white shadow-md">
        <nav className="flex justify-between items-center h-12">
          <div className="w-1/5 text-xl text-center">
            <Link to="/" className="no-underline hover:text-gray-300">Software</Link>
          </div>

          <ul className="flex gap-4">
            <li
              className={`${location.pathname === "/" ? "border-b-2" : ""} hover:border-b-2 border-white`}
            >
              <Link to="/" className="no-underline">Dashboard</Link>
            </li>
            <li
              className={`${location.pathname === "/students" ? "border-b-2" : ""} hover:border-b-2 border-white`}
            >
              <Link to="/students" className="no-underline">Students</Link>
            </li>
            <li
              className={`${location.pathname === "/teachers" ? "border-b-2" : ""} hover:border-b-2 border-white`}
            >
              <Link to="/teachers" className="no-underline">Teachers</Link>
            </li>
            <li
              className={`${location.pathname === "/classes" ? "border-b-2" : ""} hover:border-b-2 border-white`}
            >
              <Link to="/classes" className="no-underline">Classes</Link>
            </li>
            <li
              className={`${location.pathname === "/attendance" ? "border-b-2" : ""} hover:border-b-2 border-white`}
            >
              <Link to="/attendance" className="no-underline">Attendance</Link>
            </li>
            <li
              className={`${(location.pathname.includes(iamPath) && !["login", "register"].some((page) =>
                location.pathname.split('/').includes(page)
              )) ? "border-b-2" : ""} hover:border-b-2 border-white`}
            >
              <Link to={iamPath} className="no-underline">Access Control</Link>
            </li>
          </ul>

          <div className="w-1/5 flex items-center justify-center">
            {isLogged ? (
              <div className="w-4/5 flex items-center justify-end gap-2">
                <span>👤 Admin</span>
                <Link
                  className="w-15 text-center bg-gray-900 hover:bg-gray-700 border border-gray-900 rounded-md no-underline"
                  to={`${iamPath}/logout`}
                >
                  Logout
                </Link>
              </div>
            ) : (
              <div className="w-4/5 flex items-center justify-end gap-2">
                <span>👤 Guest</span>
                <Link className="w-15 text-center bg-gray-900 hover:bg-gray-700 border border-gray-900 rounded-md no-underline" to={`${iamPath}/login`}>
                  Login
                </Link>
              </div>
            )}
          </div>
        </nav>
      </div>
    </header>
  );
};

export default Header;
