import React, { Suspense } from "react";
import { BrowserRouter as Router, Routes, Route } from "react-router-dom";
import Layout from "./layouts/Layout";
import ChatbotDashboard from "./pages/aichatbot/ChatbotDashboard";
import ChatappDashboard from "./pages/chatapp/ChatappDashboard";
import ResumeDashboard from "./pages/resume/ResumeDashboard";
import FinanceDashboard from "./pages/financetracker/FinanceDashboard";
import WebscrapDashboard from "./pages/webscrap/WebscrapDashboard";
import ToolsDashboard from "./pages/tools/ToolsDashboard";

const RemoteIAM = React.lazy(() => import("remoteIAM/IAM"));
const iamPath = process.env.IAM_BASE_PATH || "/iam";

export const App = () => {
  return (
    <Router future={{ v7_startTransition: true, v7_relativeSplatPath: true }}>

      <Layout>
        <Routes>
          <Route path="/" element={<ChatbotDashboard />} />
          <Route path="/chatapp" element={<ChatappDashboard />} />
          <Route path="/resume-analyzer" element={<ResumeDashboard />} />
          <Route path="/web-scraping" element={<WebscrapDashboard />} />
          <Route path="/finance-tracker" element={<FinanceDashboard />} />
          <Route path="/tools" element={<ToolsDashboard />} />
          <Route
            path={`${iamPath}/*`}
            element={
              <Suspense fallback={<div className="px-5 py-2 text-red-800">Loading IAM Module...</div>}>
                <RemoteIAM />
              </Suspense>
            }
          />
        </Routes>
      </Layout>

    </Router>
  );
};

export default App;
