import React, { useState } from "react";

const Login = () => {
  const [loginForm, setLoginForm] = useState({ username: "", password: "" });
  return (
    <div className="flex tw-items-center tw-bg-red-400 tw-text-center tw-mx-2 tw-text-gray-700">
      <div className="tw-text-5xl tw-uppercase tw-text-blue-600 tw-font-extrabold tw-italic">
        <span className="tw-text-red-600">P</span>rog
        <span className="tw-text-red-600">a</span>t
        <span className="tw-text-red-600">i</span>
      </div>
      <div className="tw-text-xs">
        Next generation Operating System for Schools
      </div>{" "}
      <div className="tw-text-xl tw-text-center tw-font-semibold tw-text-gray-700">
        Login
      </div>
      <div className="tw-px-5">
        <form className="tw-flex tw-flex-col tw-gap-2 tw-items-center">
          <div className="tw-rounded-md tw-bg-white tw-outline tw-outline-1 -tw-outline-offset-1 tw-outline-gray-300 focus-within:tw-outline focus-within:tw-outline-2 focus-within:-tw-outline-offset-2 focus-within:tw-outline-indigo-600">
            <input
              id="username"
              name="username"
              type="text"
              placeholder="Username"
              onChange={(e) =>
                setLoginForm({ ...loginForm, username: e.target.value })
              }
              className="tw-py-1.5 tw-pl-1 tw-pr-3 tw-text-base tw-text-gray-900 placeholder:tw-text-gray-400 focus:tw-outline focus:tw-outline-0 sm:tw-text-sm/6"
            />
          </div>
          <div className="tw-rounded-md tw-bg-white tw-outline tw-outline-1 -tw-outline-offset-1 tw-outline-gray-300 focus-within:tw-outline focus-within:tw-outline-2 focus-within:-tw-outline-offset-2 focus-within:tw-outline-indigo-600">
            <input
              id="username"
              name="username"
              type="text"
              placeholder="Username"
              onChange={(e) =>
                setLoginForm({ ...loginForm, password: e.target.value })
              }
              className="tw-block tw-min-w-0 tw-grow tw-py-1.5 tw-pl-1 tw-pr-3 tw-text-base tw-text-gray-900 placeholder:tw-text-gray-400 focus:tw-outline focus:tw-outline-0 sm:tw-text-sm/6"
            />
          </div>
          <div className="tw-my-3">
            <input type="checkbox" id="keepLog" />
            <label className="tw-text-gray-700">&nbsp;Keep me log in</label>
          </div>
          <div className="tw-flex-1 tw-text-center">
            <button
              className="tw-bg-sky-500 hover:tw-bg-sky-700 tw-text-white tw-rounded-xl tw-my-1 tw-py-1 tw-px-10"
              type="submit"
            >
              Login
            </button>
          </div>
        </form>
      </div>
      <div className="tw-px-2 tw-text-xs">
        By clicking login,you agree to our terms and have read and acknowledge
        our privacy statement.
      </div>
      <div className="tw-my-2 tw-text-gray-700">Forget password?</div>
      <div className="tw-text-xs tw-mt-10 tw-pb-3 tw-px-2">
        System Status | Privacy Policy | Refund and Cancellation | Terms and
        Conditions | Copyright @ 2022-2024 Business by Software Pvt Ltd All
        rights reserved.
      </div>
    </div>
  );
};

export default Login;
