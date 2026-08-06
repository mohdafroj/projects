import React, { Suspense } from "react";

const RemoteHeader = React.lazy(() => import("remoteHeader/Header"));

export const Layout = ({ children }: { children: React.ReactNode }) => {
  return (
    <div className="flex flex-col h-screen bg-[#0A0E1A] text-slate-100 font-sans">
      <Suspense fallback={<div className="px-5 py-2 text-red-800">Loading Header...</div>}>
        <RemoteHeader />
      </Suspense>

      <main className="flex-1 overflow-y-auto text-slate-100 px-4">
        {children}
      </main>
    </div>
  );
};

export default Layout;
