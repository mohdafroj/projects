import React, { useState, useEffect } from "react";
import { authService } from "../services/authService";
import { useNavigate, Link } from "react-router-dom";
import { LINKS } from "../routes/routes";

const formSchema = {
  fullname: "",
  username: "",
  email: "",
  password: "",
  confirm_password: "",
}
const Register = () => {
  const navigate = useNavigate();
  const [isRegistering, setIsRegistering] = useState(false);
  const [errorMessage, setErrorMessage] = useState("");
  const [formData, setFormData] = useState<typeof formSchema>(formSchema);
  const [errorForm, setErrorForm] = useState<typeof formSchema>(formSchema);
  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsRegistering(true);
    setErrorForm(formSchema);

    if (formData.confirm_password !== formData.password) {
      setErrorForm((prev: any) => ({ ...prev, confirm_password: "Passwords do not match" }));
      setIsRegistering(false);
      return;
    }

    const payload = {
      full_name: formData.fullname,
      username: formData.username,
      email: formData.email,
      password: formData.password,
      confirm_password: formData.confirm_password,
      is_active: true,
    };

    try {
      const data = await authService.register(payload);
      console.log("Registered Data: ", data);
      navigate(LINKS.LOGIN.path);
    } catch (error: any) {
      error.data?.errors?.forEach((item: { loc: Array<string>; msg: string }) => {
        if (formSchema.hasOwnProperty(item.loc[1])) {
          setErrorForm((prev: any) => ({ ...prev, [item.loc[1]]: item.msg }));
        }
      })
    } finally {
      setIsRegistering(false);
    }
  };

  return (
    <div className="flex flex-col items-center justify-center min-h-[calc(100vh-200px)] p-5">
      <div className="bg-white p-4 rounded-xl shadow-xl w-full max-w-[370px] text-center">
        <h2 className="text-2xl font-bold mb-2 text-gray-800">Welcome to Sign Up</h2>
        <p className="text-sm text-gray-600 mb-2">Please enter your details to register.</p>

        {errorMessage && (
          <div className="text-red-600 bg-red-50 p-2 rounded-md mb-2 text-sm border border-red-200">
            {errorMessage}
          </div>
        )}

        <form onSubmit={handleSubmit}>
          <div className="w-full mb-4 text-left">
            <label htmlFor="fullname" className="block mb-0 text-sm font-semibold text-gray-700">Fullname:</label>
            <input
              id="fullname"
              type="text"
              placeholder="Mohd Afroj"
              className="w-full py-2 px-3 rounded-md border border-gray-300 text-sm outline-none transition-colors duration-300 focus:border-indigo-500"
              value={formData.fullname}
              onChange={(e) => { setFormData({ ...formData, fullname: e.target.value }); setErrorForm({ ...errorForm, fullname: '' }) }}
            />
            {errorForm.fullname && <p className="text-red-600 text-sm">{errorForm.fullname}</p>}
          </div>

          <div className="w-full mb-4 text-left">
            <label htmlFor="username" className="block mb-0 text-sm font-semibold text-gray-700">Username:</label>
            <input
              id="username"
              type="text"
              placeholder="mohd.afroj"
              className="w-full py-2 px-3 rounded-md border border-gray-300 text-sm outline-none transition-colors duration-300 focus:border-indigo-500"
              value={formData.username}
              onChange={(e) => { setFormData({ ...formData, username: e.target.value }); setErrorForm({ ...errorForm, username: '' }) }}
            />
            {errorForm.username && <p className="text-red-600 text-sm">{errorForm.username}</p>}
          </div>

          <div className="w-full mb-4 text-left">
            <label htmlFor="email" className="block mb-0 text-sm font-semibold text-gray-700">Email:</label>
            <input
              id="email"
              type="email"
              placeholder="admin@example.com"
              className="w-full py-2 px-3 rounded-md border border-gray-300 text-sm outline-none transition-colors duration-300 focus:border-indigo-500"
              value={formData.email}
              onChange={(e) => { setFormData({ ...formData, email: e.target.value }); setErrorForm({ ...errorForm, email: '' }) }}
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
            />
            {errorForm.password && <p className="text-red-600 text-sm">{errorForm.password}</p>}
          </div>

          <div className="w-full mb-4 text-left">
            <label htmlFor="confirm_password" className="block mb-0 text-sm font-semibold text-gray-700">Confirm Password:</label>
            <input
              id="confirm_password"
              type="password"
              placeholder="••••••••"
              className="w-full py-2 px-3 rounded-md border border-gray-300 text-sm outline-none transition-colors duration-300 focus:border-indigo-500"
              value={formData.confirm_password}
              onChange={(e) => { setFormData({ ...formData, confirm_password: e.target.value }); setErrorForm({ ...errorForm, confirm_password: '' }) }}
            />
            {errorForm.confirm_password && <p className="text-red-600 text-sm">{errorForm.confirm_password}</p>}
          </div>

          <button
            type="submit"
            disabled={isRegistering}
            className={`w-full py-2 mt-2 bg-blue-950 text-white font-bold rounded-md disabled:opacity-70 ${isRegistering ? "cursor-not-allowed opacity-70" : "cursor-pointer"
              }`}
          >
            {isRegistering ? "Signing Up..." : "Sign Up"}
          </button>
        </form>

        <div className="mt-2 text-sm text-gray-600">
          Already have an account?{" "}
          <Link to={LINKS.LOGIN.path} className="text-blue-950 hover:text-blue-800">
            Click to Login
          </Link>
        </div>
      </div>
    </div>
  );
};

export default Register;
