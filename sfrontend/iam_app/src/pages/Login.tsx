import React, { useState, useEffect } from "react";
import { authService, CaptchaResponse } from "../services/authService";
import { useNavigate, Link } from "react-router-dom";
import { LINKS } from "../routes/routes";

const Login = () => {
  const navigate = useNavigate();
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

  return (
    <div className="flex flex-col items-center justify-center min-h-[calc(100vh-200px)] p-5">
      <div className="bg-white p-10 rounded-xl shadow-xl w-full max-w-[400px] text-center">
        <div className="text-4xl mb-4">🔐</div>
        <h2 className="text-2xl font-bold mb-2 text-gray-800">Welcome Back</h2>
        <p className="text-sm text-gray-600 mb-8">Please enter your credentials to access the CRM</p>

        {errorMessage && (
          <div className="text-red-600 bg-red-50 p-2.5 rounded-md mb-5 text-sm border border-red-200">
            {errorMessage}
          </div>
        )}

        <form onSubmit={handleSubmit}>
          <div className="w-full mb-5 text-left">
            <label className="block mb-2 text-sm font-semibold text-gray-700">Email Address</label>
            <input
              type="email"
              placeholder="admin@example.com"
              className="w-full py-3 px-4 rounded-md border border-gray-300 text-base outline-none transition-colors duration-300 focus:border-indigo-500"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              required
            />
          </div>

          <div className="w-full mb-5 text-left">
            <label className="block mb-2 text-sm font-semibold text-gray-700">Password</label>
            <input
              type="password"
              placeholder="••••••••"
              className="w-full py-3 px-4 rounded-md border border-gray-300 text-base outline-none transition-colors duration-300 focus:border-indigo-500"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              required
            />
          </div>

          <div className="w-full mb-5 text-left">
            <label className="block mb-2 text-sm font-semibold text-gray-700">Captcha</label>
            <div className="flex items-center gap-2.5 mt-2.5">
              <input
                type="text"
                placeholder="Enter code"
                className="w-full py-3 px-4 rounded-md border border-gray-300 text-base outline-none transition-colors duration-300 focus:border-indigo-500 flex-1"
                value={captchaCode}
                onChange={(e) => setCaptchaCode(e.target.value)}
                required
              />
              {captchaData ? (
                <img
                  src={captchaData.img}
                  alt="captcha"
                  className="h-[45px] rounded-md border border-gray-300 cursor-pointer bg-gray-50"
                  onClick={fetchCaptcha}
                  title="Click to refresh"
                />
              ) : (
                <div
                  className="h-[45px] w-[120px] flex items-center justify-center rounded-md border border-gray-300 cursor-pointer bg-gray-50 text-xs text-gray-400"
                  onClick={fetchCaptcha}
                >
                  {isLoadingCaptcha ? "Loading..." : "Reload"}
                </div>
              )}
            </div>
          </div>

          <button
            type="submit"
            disabled={isLoggingIn}
            className={`w-full py-3 mt-2.5 bg-gradient-to-r from-indigo-500 to-purple-600 text-white font-bold rounded-md text-base transition-all duration-200 transform active:translate-y-0 hover:shadow-lg disabled:opacity-70 ${isLoggingIn ? "cursor-not-allowed opacity-70" : "cursor-pointer hover:-translate-y-0.5"
              }`}
          >
            {isLoggingIn ? "Signing In..." : "Sign In"}
          </button>
        </form>

        <div className="mt-10 text-sm text-gray-600">
          Don't have an account?{" "}
          <Link to={LINKS.REGISTER.path} className="text-blue-950 hover:text-blue-800">
            Click to Register
          </Link>
        </div>
      </div>
    </div>
  );
};

export default Login;
