import React, { Suspense } from "react";
import { BrowserRouter as Router, Routes, Route, Link } from "react-router-dom";

const RemoteHeader = React.lazy(() => import("remoteHeader/Header"));
const RemoteIAM = React.lazy(() => import("remoteIAM/IAM"));

const Dashboard = () => (
  <div style={{ padding: "20px" }}>
    <h2>Software Management Dashboard</h2>
    <p>Welcome to the Software Management CRM System</p>
    <div style={{ marginTop: "20px" }}>
      <h3>Quick Links:</h3>
      <ul>
        <li>
          <Link to="/students">Students Management</Link>
        </li>
        <li>
          <Link to="/teachers">Teachers Management</Link>
        </li>
        <li>
          <Link to="/classes">Classes Management</Link>
        </li>
        <li>
          <Link to="/attendance">Attendance</Link>
        </li>
        <li>
          <Link to="/iam">IAM Management</Link>
        </li>
      </ul>
    </div>
  </div>
);

const Students = () => (
  <div style={{ padding: "20px" }}>
    <h2>Students Management</h2>
    <p>Manage student records, enrollment, and progress tracking.</p>
  </div>
);

const Teachers = () => (
  <div style={{ padding: "20px" }}>
    <h2>Teachers Management</h2>
    <p>Manage teacher profiles, assignments, and schedules.</p>
  </div>
);

const Classes = () => (
  <div style={{ padding: "20px" }}>
    <h2>Classes Management</h2>
    <p>Manage class sections, timetables, and curricula.</p>
  </div>
);

const Attendance = () => (
  <div style={{ padding: "20px" }}>
    <h2>Attendance</h2>
    <p>Track student and teacher attendance.</p>
  </div>
);

export const App = () => {
  return (
    <Router future={{ v7_startTransition: true, v7_relativeSplatPath: true }}>
      <div
        style={{
          fontFamily: "Arial, sans-serif",
          background: "#f5f5f5",
          minHeight: "100vh",
        }}
      >
        <Suspense fallback={<div style={{ padding: "20px" }}>Loading Header...</div>}>
          <RemoteHeader />
        </Suspense>

        <Routes>
          <Route path="/" element={<Dashboard />} />
          <Route path="/students" element={<Students />} />
          <Route path="/teachers" element={<Teachers />} />
          <Route path="/classes" element={<Classes />} />
          <Route path="/attendance" element={<Attendance />} />
          <Route
            path="/iam"
            element={
              <Suspense fallback={<div style={{ padding: "20px" }}>Loading IAM Module...</div>}>
                <RemoteIAM />
              </Suspense>
            }
          />
        </Routes>
      </div>
    </Router>
  );
};

export default App;
