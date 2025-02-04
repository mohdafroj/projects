import React, { useRef } from "react";

const Test = () => {
  const inputRef = useRef("Hii");
  console.log(inputRef);
  return (
    <div className="text-white px-4 sm:px-8 py-2 sm:py-3 bg-sky-700 hover:bg-sky-800">
      <div className="tw-flex tw-justify-between">
        <input
          type="text"
          ref={inputRef}
          value=""
          placeholder="Focus me on mount"
        />
        <a href="https://vite.dev" target="_blank">
          <img src={viteLogo} className="logo" alt="Vite logo" />
        </a>
        <a href="https://react.dev" target="_blank">
          <img src={reactLogo} className="logo react" alt="React logo" />
        </a>
      </div>
      <h1 className="tw-text-red-400">Vite + React</h1>
      <h4>{message}</h4>
      <div className="card">
        <button onClick={() => setCount((count) => count + 1)}>
          count is {count}
        </button>
        <p>
          Edit <code>src/App.jsx</code> and save to test HMR
        </p>
      </div>
      <p className="read-the-docs">
        Click on the Vite and React logos to learn more
      </p>
      <table className="tw-table-auto tw-w-full tw-border-collapse tw-border tw-border-slate-400">
        <caption>This is caption of table!</caption>
        <thead>
          <tr>
            <th className="tw-border tw-border-slate-300">State</th>
            <th className="tw-border tw-border-slate-300">City</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td className="tw-border tw-border-slate-300">Indiana</td>
            <td className="tw-border tw-border-slate-300">Indianapolis</td>
          </tr>
          <tr>
            <td className="tw-border tw-border-slate-300">Ohio</td>
            <td className="tw-border tw-border-slate-300">Columbus</td>
          </tr>
          <tr>
            <td className="tw-border tw-border-slate-300">Michigan</td>
            <td className="tw-border tw-border-slate-300">Detroit</td>
          </tr>
        </tbody>
      </table>
      <button className="tw-bg-sky-500 hover:tw-bg-sky-700 tw-text-white tw-rounded-3xl tw-my-1 tw-py-1 tw-px-4">
        Save changes
      </button>
      <div class="tw-max-w-full mx-auto p-8">
        <details
          class="open:bg-white dark:open:bg-slate-900 open:ring-1 open:ring-black/5 dark:open:ring-white/10 open:shadow-lg p-6 rounded-lg"
          close
        >
          <summary class="text-sm leading-6 text-slate-900 dark:text-white font-semibold select-none">
            Why do they call it Ovaltine?
          </summary>
          <div class="mt-3 text-sm leading-6 text-slate-600 dark:text-slate-400">
            The mug is round. The jar is round. They should call it Roundtine.
          </div>
        </details>
      </div>
    </div>
  );
};

export default Test;
