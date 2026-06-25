import React, { useState } from "react";
import { mockUsers, mockRoles, mockPermissions, mockServices, mockFeatures } from "../constants/mockData";
import { User, Role, Permission, Service, Feature } from "../types/iam";

const IAMDashboard = () => {
  const [users, setUsers] = useState<User[]>(mockUsers);
  const [roles, setRoles] = useState<Role[]>(mockRoles);
  const [permissions, setPermissions] = useState<Permission[]>(mockPermissions);
  const [services, setServices] = useState<Service[]>(mockServices);
  const [features, setFeatures] = useState<Feature[]>(mockFeatures);

  const [selectedTab, setSelectedTab] = useState<"users" | "roles" | "permissions" | "services" | "features">("users");
  const [showForm, setShowForm] = useState(false);
  const [newUser, setNewUser] = useState({ name: "", email: "", role: "Student" });

  const addUser = () => {
    if (newUser.name && newUser.email) {
      const user: User = {
        id: String(users.length + 1),
        ...newUser,
        status: "active",
      };
      setUsers([...users, user]);
      setNewUser({ name: "", email: "", role: "Student" });
      setShowForm(false);
    }
  };

  return (
    <div className="iam-container">
      <div className="iam-header">
        <div className="iam-title">Identity & Access Management</div>
        <div className="iam-actions">
           {selectedTab === "users" && (
            <button className="btn btn-primary" onClick={() => setShowForm(!showForm)}>
              {showForm ? "Cancel" : "+ Add User"}
            </button>
          )}
        </div>
      </div>

      <div className="iam-tabs">
        {["users", "roles", "permissions", "services", "features"].map((tab) => (
          <button
            key={tab}
            className={`tab ${selectedTab === tab ? "active" : ""}`}
            onClick={() => setSelectedTab(tab as any)}
          >
            {tab.charAt(0).toUpperCase() + tab.slice(1)}
          </button>
        ))}
      </div>

      <div className="tab-content">
        {selectedTab === "users" && (
            <table className="iam-table">
            <thead>
                <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Status</th>
                <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                {users.map((user) => (
                <tr key={user.id}>
                    <td>{user.name}</td>
                    <td>{user.email}</td>
                    <td>{user.role}</td>
                    <td>
                    <span className={`status status-${user.status}`}>
                        {user.status}
                    </span>
                    </td>
                    <td>
                    <div className="action-buttons">
                        <button className="btn btn-edit">Edit</button>
                        <button className="btn btn-danger">Delete</button>
                    </div>
                    </td>
                </tr>
                ))}
            </tbody>
            </table>
        )}
        
        {/* Placeholder for other tabs to keep this example concise during refactor */}
        {selectedTab !== "users" && (
            <div style={{ padding: "40px", textAlign: "center", color: "#666" }}>
                {selectedTab.charAt(0).toUpperCase() + selectedTab.slice(1)} management coming soon...
            </div>
        )}
      </div>
    </div>
  );
};

export default IAMDashboard;
