import React, { useState, useEffect } from "react";
import { authService, CaptchaResponse } from "../services/authService";
import { useNavigate, Link } from "react-router-dom";
import { LINKS } from "../routes/routes";
const formSchema = {
  email: "",
  password: "",
  captcha_code: "",
}
const Login = () => {
  const navigate = useNavigate();
  const [formData, setFormData] = useState<typeof formSchema>(formSchema);
  const [errorForm, setErrorForm] = useState<typeof formSchema>(formSchema);
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
    const token = sessionStorage.getItem("iam_token");
    if (token) {
      navigate(LINKS.DASHBOARD.path);
    }
    fetchCaptcha();
  }, []);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsLoggingIn(true);
    setErrorMessage("");

    const payload = {
      username: formData.email,
      password: formData.password,
      captcha_id: captchaData?.key || "",
      captcha_code: formData.captcha_code,
    };

    try {
      const data = await authService.login(payload);
      console.log("Logged Data: ", data);
      sessionStorage.setItem("iam_token", "121212");
    } catch (error: any) {
      setErrorMessage(error.message || "Login failed");
      fetchCaptcha();
    } finally {
      setIsLoggingIn(false);
    }
  };

  return (
    <div className="flex flex-col items-center justify-center min-h-[calc(100vh-200px)] p-5">
      <div className="bg-white p-4 rounded-xl shadow-xl w-full max-w-[370px] text-center">
        <div className="text-4xl mb-2">🔐</div>
        <h2 className="text-2xl font-bold mb-2 text-gray-800">Welcome Back</h2>
        <p className="text-sm text-gray-600 mb-2">Please enter your credentials to login</p>

        {errorMessage && (
          <div className="text-red-600 bg-red-50 p-2 rounded-md mb-2 text-sm border border-red-200">
            {errorMessage}
          </div>
        )}

        <form onSubmit={handleSubmit}>
          <div className="w-full mb-4 text-left">
            <label htmlFor="email" className="block mb-0 text-sm font-semibold text-gray-700">Email:</label>
            <input
              id="email"
              type="email"
              placeholder="admin@example.com"
              className="w-full py-2 px-3 rounded-md border border-gray-300 text-sm outline-none transition-colors duration-300 focus:border-indigo-500"
              value={formData.email}
              onChange={(e) => { setFormData({ ...formData, email: e.target.value }); setErrorForm({ ...errorForm, email: '' }) }}
              required
            />
            {errorForm.email && <p className="text-red-600 text-sm">{errorForm.email}</p>}
          </div>

          <div className="w-full mb-4 text-left">
            <label htmlFor="password" className="block mb-0 text-sm font-semibold text-gray-700">Password:</label>
            <input
              id="password"
              type="password"
              placeholder="••••••••"
              className="w-full py-2 px-3 rounded-md border border-gray-300 text-sm outline-none transition-colors duration-300 focus:border-indigo-500"
              value={formData.password}
              onChange={(e) => { setFormData({ ...formData, password: e.target.value }); setErrorForm({ ...errorForm, password: '' }) }}
              required
            />
            {errorForm.password && <p className="text-red-600 text-sm">{errorForm.password}</p>}
          </div>

          <div className="w-full mb-4 text-left">
            <label className="block mb-0 text-sm font-semibold text-gray-700">Captcha</label>
            <div className="flex items-center gap-2 mt-0">
              <input
                type="text"
                placeholder="Enter code"
                className="w-full p-2 rounded-md border border-gray-300 text-sm outline-none transition-colors duration-300 focus:border-indigo-500 flex-1"
                value={formData.captcha_code}
                onChange={(e) => { setFormData({ ...formData, captcha_code: e.target.value }); setErrorForm({ ...errorForm, captcha_code: '' }) }}
                required
              />
              {captchaData ? (
                <img
                  src={captchaData.img}
                  alt="captcha"
                  className="h-10 w-30 rounded-md cursor-pointer bg-gray-50 p-1"
                  onClick={fetchCaptcha}
                  title="Click to refresh"
                />
              ) : (
                <div
                  className="h-10 w-30 flex items-center justify-center rounded-md border border-gray-300 cursor-pointer bg-gray-50 text-xs text-gray-400"
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
            className={`w-full py-2 mt-2 bg-blue-950 text-white font-bold rounded-md disabled:opacity-70 ${isLoggingIn ? "cursor-not-allowed opacity-70" : "cursor-pointer"
              }`}
          >
            {isLoggingIn ? "Signing In..." : "Sign In"}
          </button>
        </form>

        <div className="mt-2 text-sm text-gray-600">
          Don't have an account?{" "}
          <Link to={LINKS.REGISTER.path} className="text-blue-950 hover:text-blue-800">
            Click to Signup
          </Link>
        </div>
      </div>
    </div>
  );
};

export default Login;
