import React from "react";
import { useNavigate, useLocation } from "react-router-dom";

const Header = () => {
  const navigate = useNavigate();
  const location = useLocation();

  const headerStyle: React.CSSProperties = {
    background: "linear-gradient(135deg, #667eea 0%, #764ba2 100%)",
    color: "white",
    padding: "0",
    boxShadow: "0 2px 8px rgba(0,0,0,0.1)",
  };

  const navStyle: React.CSSProperties = {
    display: "flex",
    justifyContent: "space-between",
    alignItems: "center",
    padding: "1rem 2rem",
  };

  const logoStyle: React.CSSProperties = {
    fontSize: "1.5rem",
    fontWeight: "bold",
    cursor: "pointer",
  };

  const navLinksStyle: React.CSSProperties = {
    display: "flex",
    gap: "2rem",
    listStyle: "none",
  };

  const navLinkStyle = (isActive: boolean): React.CSSProperties => ({
    cursor: "pointer",
    paddingBottom: "0.5rem",
    borderBottom: isActive ? "3px solid white" : "3px solid transparent",
    transition: "border-color 0.3s",
    fontWeight: isActive ? "bold" : "normal",
  });

  const userSectionStyle: React.CSSProperties = {
    display: "flex",
    gap: "1rem",
    alignItems: "center",
  };

  const buttonStyle: React.CSSProperties = {
    background: "rgba(255,255,255,0.2)",
    color: "white",
    border: "1px solid white",
    padding: "0.5rem 1rem",
    borderRadius: "4px",
    cursor: "pointer",
    transition: "background 0.3s",
  };

  return (
    <header style={headerStyle}>
      <nav style={navStyle}>
        <div style={logoStyle} onClick={() => navigate("/")}>
          🎓 School CRM
        </div>

        <ul style={navLinksStyle}>
          <li
            style={navLinkStyle(location.pathname === "/")}
            onClick={() => navigate("/")}
          >
            Dashboard
          </li>
          <li
            style={navLinkStyle(location.pathname === "/students")}
            onClick={() => navigate("/students")}
          >
            Students
          </li>
          <li
            style={navLinkStyle(location.pathname === "/teachers")}
            onClick={() => navigate("/teachers")}
          >
            Teachers
          </li>
          <li
            style={navLinkStyle(location.pathname === "/classes")}
            onClick={() => navigate("/classes")}
          >
            Classes
          </li>
          <li
            style={navLinkStyle(location.pathname === "/attendance")}
            onClick={() => navigate("/attendance")}
          >
            Attendance
          </li>
        </ul>

        <div style={userSectionStyle}>
          <span>👤 Admin User</span>
          <button
            style={buttonStyle}
            onMouseEnter={(e) => {
              e.currentTarget.style.background = "rgba(255,255,255,0.3)";
            }}
            onMouseLeave={(e) => {
              e.currentTarget.style.background = "rgba(255,255,255,0.2)";
            }}
          >
            Logout
          </button>
        </div>
      </nav>
    </header>
  );
};

export default Header;
