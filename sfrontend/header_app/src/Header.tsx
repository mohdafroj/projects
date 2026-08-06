import React, { useState } from "react";
import { useLocation, Link } from "react-router-dom";
import "./index.css";

const iamPath = process.env.IAM_BASE_PATH || "/iam";

const Header = () => {
  const location = useLocation();
  const isLogged = sessionStorage.getItem("access_token");
  const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);

  const handleLogout = () => {
    sessionStorage.removeItem("access_token");
    localStorage.removeItem("refresh_token");
    window.location.href = iamPath + "/login";
  };

  const menuLinks = [
    { path: "/", label: "AI Chatbot" },
    { path: "/chatapp", label: "Chat App" },
    { path: "/resume-analyzer", label: "Resume Analyzer" },
    { path: "/web-scraping", label: "Web Scraping" },
    { path: "/finance-tracker", label: "Finance Tracker" },
    { path: "/tools", label: "Tools" },
  ];

  return (
    <header id="header-app">
      <div className="bg-blue-950 text-white shadow-md">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <nav className="flex justify-between items-center h-14">
            {/* Left: Brand/Logo */}
            <div className="flex-shrink-0 text-xl font-bold">
              <Link to="/" className="no-underline hover:text-gray-300">
                Softwares
              </Link>
            </div>

            {/* Center: Desktop Navigation */}
            <ul className="hidden lg:flex items-center gap-5 list-none m-0 p-0">
              {menuLinks.map((link) => {
                const isActive = location.pathname === link.path;
                return (
                  <li
                    key={link.path}
                    className={`${isActive ? "border-b-2" : ""
                      } hover:border-b-2 border-white`}
                  >
                    <Link to={link.path} className="no-underline text-white font-medium text-sm">
                      {link.label}
                    </Link>
                  </li>
                );
              })}
              {isLogged && (
                <li
                  className={`${location.pathname.includes(iamPath) &&
                    !["login", "register"].some((page) =>
                      location.pathname.split("/").includes(page)
                    )
                    ? "border-b-2"
                    : ""
                    } hover:border-b-2 border-white`}
                >
                  <Link to={iamPath} className="no-underline text-white font-medium text-sm">
                    IAM
                  </Link>
                </li>
              )}
            </ul>

            {/* Right: Desktop Auth Status */}
            <div className="hidden lg:flex items-center gap-3">
              {isLogged ? (
                <div className="flex items-center gap-3">
                  <span className="text-sm">👤 Admin</span>
                  <button
                    className="px-3 py-1 bg-gray-900 hover:bg-gray-700 border border-gray-800 rounded-md cursor-pointer text-xs font-semibold"
                    onClick={handleLogout}
                  >
                    Logout
                  </button>
                </div>
              ) : (
                <div className="flex items-center gap-3">
                  <span className="text-sm">👤 Guest</span>
                  <a
                    className="px-3 py-1 bg-gray-900 hover:bg-gray-700 border border-gray-800 rounded-md no-underline text-white text-xs font-semibold"
                    href={`${iamPath}/login`}
                  >
                    Login
                  </a>
                </div>
              )}
            </div>

            {/* Hamburger Button (Mobile viewports) */}
            <div className="flex items-center lg:hidden">
              <button
                onClick={() => setIsMobileMenuOpen(!isMobileMenuOpen)}
                className="text-slate-100 p-2 hover:bg-blue-900 rounded-lg focus:outline-none cursor-pointer"
                aria-expanded={isMobileMenuOpen}
              >
                {isMobileMenuOpen ? (
                  <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                  </svg>
                ) : (
                  <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
                  </svg>
                )}
              </button>
            </div>
          </nav>
        </div>

        {/* Mobile Dropdown Menu (Collapsible) */}
        {isMobileMenuOpen && (
          <div className="lg:hidden bg-blue-950 border-t border-blue-900 px-4 py-3 space-y-3">
            <ul className="flex flex-col gap-2 list-none m-0 p-0">
              {menuLinks.map((link) => {
                const isActive = location.pathname === link.path;
                return (
                  <li key={link.path} className="w-full">
                    <Link
                      to={link.path}
                      onClick={() => setIsMobileMenuOpen(false)}
                      className={`block py-2 px-3 rounded-lg no-underline text-white text-sm font-medium ${isActive ? "bg-blue-900 border-l-4 border-indigo-400" : "hover:bg-blue-900/55"
                        }`}
                    >
                      {link.label}
                    </Link>
                  </li>
                );
              })}
              {isLogged && (
                <li className="w-full">
                  <Link
                    to={iamPath}
                    onClick={() => setIsMobileMenuOpen(false)}
                    className={`block py-2 px-3 rounded-lg no-underline text-white text-sm font-medium ${location.pathname.includes(iamPath) &&
                      !["login", "register"].some((page) =>
                        location.pathname.split("/").includes(page)
                      )
                      ? "bg-blue-900 border-l-4 border-indigo-400"
                      : "hover:bg-blue-900/55"
                      }`}
                  >
                    IAM
                  </Link>
                </li>
              )}
            </ul>

            {/* Profile / Auth Status for Mobile Dropdown */}
            <div className="border-t border-blue-900 pt-3 flex items-center justify-between">
              {isLogged ? (
                <>
                  <span className="text-sm">👤 Admin</span>
                  <button
                    className="px-4 py-1.5 bg-gray-900 hover:bg-gray-700 border border-gray-800 rounded-md cursor-pointer text-xs font-semibold"
                    onClick={handleLogout}
                  >
                    Logout
                  </button>
                </>
              ) : (
                <>
                  <span className="text-sm">👤 Guest</span>
                  <a
                    className="px-4 py-1.5 bg-gray-900 hover:bg-gray-700 border border-gray-800 rounded-md no-underline text-white text-xs font-semibold"
                    href={`${iamPath}/login`}
                    onClick={() => setIsMobileMenuOpen(false)}
                  >
                    Login
                  </a>
                </>
              )}
            </div>
          </div>
        )}
      </div>
    </header>
  );
};

export default Header;
