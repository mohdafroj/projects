import React, { useState, useEffect } from "react";
import { authService, CaptchaResponse } from "../services/authService";

const Login = () => {
  const [email, setEmail] = useState("admin@example.com");
  const [password, setPassword] = useState("admin123");
  const [captchaCode, setCaptchaCode] = useState("");
  const [captchaData, setCaptchaData] = useState<CaptchaResponse | null>(null);
  const [isLoadingCaptcha, setIsLoadingCaptcha] = useState(false);
  const [isLoggingIn, setIsLoggingIn] = useState(false);
  const [errorMessage, setErrorMessage] = useState("");

  const fetchCaptcha = async () => {
    setIsLoadingCaptcha(true);
    try {
      const data = await authService.getCaptcha();
      setCaptchaData(data);
    } catch (error: any) {
      setErrorMessage(error.message || "Failed to load captcha");
    } finally {
      setIsLoadingCaptcha(false);
    }
  };

  useEffect(() => {
    fetchCaptcha();
  }, []);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsLoggingIn(true);
    setErrorMessage("");

    const payload = {
      username: email,
      password: password,
      captcha_id: captchaData?.key || "",
      captcha_code: captchaCode,
    };

    try {
      const data = await authService.login(payload);
      alert("Login successful!");
      console.log("Logged in user:", data.user);
    } catch (error: any) {
      setErrorMessage(error.message || "Login failed");
      fetchCaptcha();
    } finally {
      setIsLoggingIn(false);
    }
  };

  const containerStyle: React.CSSProperties = {
    display: "flex",
    flexDirection: "column",
    alignItems: "center",
    justifyContent: "center",
    minHeight: "calc(100vh - 200px)",
    padding: "20px",
  };

  const cardStyle: React.CSSProperties = {
    background: "white",
    padding: "40px",
    borderRadius: "12px",
    boxShadow: "0 10px 25px rgba(0,0,0,0.1)",
    width: "100%",
    maxWidth: "400px",
    textAlign: "center",
  };

  const titleStyle: React.CSSProperties = {
    fontSize: "24px",
    fontWeight: "bold",
    marginBottom: "10px",
    color: "#333",
  };

  const subtitleStyle: React.CSSProperties = {
    color: "#666",
    marginBottom: "30px",
    fontSize: "14px",
  };

  const formGroupStyle: React.CSSProperties = {
    width: "100%",
    marginBottom: "20px",
    textAlign: "left",
  };

  const labelStyle: React.CSSProperties = {
    display: "block",
    marginBottom: "8px",
    fontSize: "14px",
    fontWeight: "600",
    color: "#555",
  };

  const inputStyle: React.CSSProperties = {
    width: "100%",
    padding: "12px 15px",
    borderRadius: "6px",
    border: "1px solid #ddd",
    fontSize: "16px",
    outline: "none",
    transition: "border-color 0.3s",
  };

  const captchaContainerStyle: React.CSSProperties = {
    display: "flex",
    alignItems: "center",
    gap: "10px",
    marginTop: "10px",
  };

  const captchaImageStyle: React.CSSProperties = {
    height: "45px",
    borderRadius: "6px",
    border: "1px solid #ddd",
    cursor: "pointer",
    background: "#f9f9f9",
  };

  const loginButtonStyle: React.CSSProperties = {
    width: "100%",
    padding: "12px",
    background: "linear-gradient(135deg, #667eea 0%, #764ba2 100%)",
    color: "white",
    border: "none",
    borderRadius: "6px",
    fontSize: "16px",
    fontWeight: "bold",
    cursor: "pointer",
    transition: "transform 0.2s, box-shadow 0.2s",
    marginTop: "10px",
  };

  const footerStyle: React.CSSProperties = {
    marginTop: "20px",
    fontSize: "14px",
    color: "#888",
  };

  return (
    <div style={containerStyle}>
      <div style={cardStyle}>
        <div style={{ fontSize: "40px", marginBottom: "15px" }}>🔐</div>
        <h2 style={titleStyle}>Welcome Back</h2>
        <p style={subtitleStyle}>Please enter your credentials to access the CRM</p>

        {errorMessage && (
          <div style={{ color: "#e53e3e", background: "#fff5f5", padding: "10px", borderRadius: "6px", marginBottom: "20px", fontSize: "14px", border: "1px solid #fed7d7" }}>
            {errorMessage}
          </div>
        )}

        <form onSubmit={handleSubmit}>
          <div style={formGroupStyle}>
            <label style={labelStyle}>Email Address</label>
            <input
              type="email"
              placeholder="admin@example.com"
              style={inputStyle}
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              required
            />
          </div>

          <div style={formGroupStyle}>
            <label style={labelStyle}>Password</label>
            <input
              type="password"
              placeholder="••••••••"
              style={inputStyle}
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              required
            />
          </div>

          <div style={formGroupStyle}>
            <label style={labelStyle}>Captcha</label>
            <div style={captchaContainerStyle}>
              <input
                type="text"
                placeholder="Enter code"
                style={{ ...inputStyle, flex: 1 }}
                value={captchaCode}
                onChange={(e) => setCaptchaCode(e.target.value)}
                required
              />
              {captchaData ? (
                <img
                  src={captchaData.img}
                  alt="captcha"
                  style={captchaImageStyle}
                  onClick={fetchCaptcha}
                  title="Click to refresh"
                />
              ) : (
                <div
                  style={{ ...captchaImageStyle, width: "120px", display: "flex", alignItems: "center", justifyContent: "center", fontSize: "12px", color: "#999" }}
                  onClick={fetchCaptcha}
                >
                  {isLoadingCaptcha ? "Loading..." : "Reload"}
                </div>
              )}
            </div>
          </div>

          <button
            type="submit"
            style={{ ...loginButtonStyle, opacity: isLoggingIn ? 0.7 : 1, cursor: isLoggingIn ? "not-allowed" : "pointer" }}
            disabled={isLoggingIn}
            onMouseEnter={(e) => {
              if (!isLoggingIn) {
                e.currentTarget.style.transform = "translateY(-1px)";
                e.currentTarget.style.boxShadow = "0 4px 12px rgba(0,0,0,0.15)";
              }
            }}
            onMouseLeave={(e) => {
              e.currentTarget.style.transform = "translateY(0)";
              e.currentTarget.style.boxShadow = "none";
            }}
          >
            {isLoggingIn ? "Signing In..." : "Sign In"}
          </button>
        </form>

        <div style={footerStyle}>
          Don't have an account? <span style={{ color: "#667eea", cursor: "pointer" }}>Contact Admin</span>
        </div>
      </div>
    </div>
  );
};

export default Login;
