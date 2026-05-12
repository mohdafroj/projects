import React, { useState } from "react";

interface User {
  id: string;
  name: string;
  email: string;
  role: string;
  status: "active" | "inactive";
}

const IAM = () => {
  const [users, setUsers] = useState<User[]>([
    {
      id: "1",
      name: "John Admin",
      email: "john@school.com",
      role: "Admin",
      status: "active",
    },
    {
      id: "2",
      name: "Jane Teacher",
      email: "jane@school.com",
      role: "Teacher",
      status: "active",
    },
    {
      id: "3",
      name: "Bob Student",
      email: "bob@school.com",
      role: "Student",
      status: "inactive",
    },
  ]);

  const [selectedTab, setSelectedTab] = useState<"users" | "roles" | "permissions">("users");
  const [showForm, setShowForm] = useState(false);
  const [newUser, setNewUser] = useState({ name: "", email: "", role: "Student" });

  const containerStyle: React.CSSProperties = {
    padding: "20px",
    background: "#f5f5f5",
    minHeight: "calc(100vh - 100px)",
  };

  const headerStyle: React.CSSProperties = {
    display: "flex",
    justifyContent: "space-between",
    alignItems: "center",
    marginBottom: "30px",
    background: "white",
    padding: "20px",
    borderRadius: "8px",
    boxShadow: "0 2px 4px rgba(0,0,0,0.1)",
  };

  const titleStyle: React.CSSProperties = {
    fontSize: "24px",
    fontWeight: "bold",
    color: "#333",
  };

  const buttonStyle: React.CSSProperties = {
    background: "#667eea",
    color: "white",
    border: "none",
    padding: "10px 20px",
    borderRadius: "4px",
    cursor: "pointer",
    fontSize: "14px",
    fontWeight: "500",
  };

  const tabsStyle: React.CSSProperties = {
    display: "flex",
    gap: "10px",
    marginBottom: "20px",
    borderBottom: "2px solid #ddd",
  };

  const tabStyle = (isActive: boolean): React.CSSProperties => ({
    padding: "12px 20px",
    background: isActive ? "#667eea" : "white",
    color: isActive ? "white" : "#333",
    border: "none",
    cursor: "pointer",
    borderRadius: "4px 4px 0 0",
    fontWeight: isActive ? "bold" : "normal",
  });

  const tableStyle: React.CSSProperties = {
    width: "100%",
    borderCollapse: "collapse",
    background: "white",
    borderRadius: "8px",
    overflow: "hidden",
    boxShadow: "0 2px 4px rgba(0,0,0,0.1)",
  };

  const cellStyle: React.CSSProperties = {
    padding: "12px",
    textAlign: "left",
    borderBottom: "1px solid #ddd",
  };

  const headerCellStyle: React.CSSProperties = {
    ...cellStyle,
    background: "#667eea",
    color: "white",
    fontWeight: "bold",
  };

  const statusStyle = (status: string): React.CSSProperties => ({
    padding: "4px 12px",
    borderRadius: "12px",
    fontSize: "12px",
    fontWeight: "bold",
    background: status === "active" ? "#d4edda" : "#f8d7da",
    color: status === "active" ? "#155724" : "#721c24",
  });

  const formStyle: React.CSSProperties = {
    background: "white",
    padding: "20px",
    borderRadius: "8px",
    marginBottom: "20px",
    boxShadow: "0 2px 4px rgba(0,0,0,0.1)",
  };

  const inputStyle: React.CSSProperties = {
    width: "100%",
    padding: "10px",
    marginBottom: "10px",
    border: "1px solid #ddd",
    borderRadius: "4px",
    fontSize: "14px",
  };

  const formButtonsStyle: React.CSSProperties = {
    display: "flex",
    gap: "10px",
  };

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
    <div style={containerStyle}>
      <div style={headerStyle}>
        <div style={titleStyle}>Identity & Access Management</div>
        <button style={buttonStyle} onClick={() => setShowForm(!showForm)}>
          {showForm ? "Cancel" : "+ Add User"}
        </button>
      </div>

      {showForm && (
        <div style={formStyle}>
          <h3 style={{ marginBottom: "15px" }}>Create New User</h3>
          <input
            type="text"
            placeholder="Full Name"
            style={inputStyle}
            value={newUser.name}
            onChange={(e) => setNewUser({ ...newUser, name: e.target.value })}
          />
          <input
            type="email"
            placeholder="Email"
            style={inputStyle}
            value={newUser.email}
            onChange={(e) => setNewUser({ ...newUser, email: e.target.value })}
          />
          <select
            style={inputStyle}
            value={newUser.role}
            onChange={(e) => setNewUser({ ...newUser, role: e.target.value })}
          >
            <option>Admin</option>
            <option>Teacher</option>
            <option>Student</option>
          </select>
          <div style={formButtonsStyle}>
            <button style={buttonStyle} onClick={addUser}>
              Create User
            </button>
          </div>
        </div>
      )}

      <div style={tabsStyle}>
        <button
          style={tabStyle(selectedTab === "users")}
          onClick={() => setSelectedTab("users")}
        >
          👥 Users ({users.length})
        </button>
        <button
          style={tabStyle(selectedTab === "roles")}
          onClick={() => setSelectedTab("roles")}
        >
          🔐 Roles
        </button>
        <button
          style={tabStyle(selectedTab === "permissions")}
          onClick={() => setSelectedTab("permissions")}
        >
          ⚙️ Permissions
        </button>
      </div>

      {selectedTab === "users" && (
        <table style={tableStyle}>
          <thead>
            <tr>
              <th style={headerCellStyle}>Name</th>
              <th style={headerCellStyle}>Email</th>
              <th style={headerCellStyle}>Role</th>
              <th style={headerCellStyle}>Status</th>
              <th style={headerCellStyle}>Actions</th>
            </tr>
          </thead>
          <tbody>
            {users.map((user) => (
              <tr key={user.id}>
                <td style={cellStyle}>{user.name}</td>
                <td style={cellStyle}>{user.email}</td>
                <td style={cellStyle}>{user.role}</td>
                <td style={cellStyle}>
                  <span style={statusStyle(user.status)}>
                    {user.status.charAt(0).toUpperCase() + user.status.slice(1)}
                  </span>
                </td>
                <td style={cellStyle}>
                  <button
                    style={{
                      ...buttonStyle,
                      background: "#e74c3c",
                      padding: "6px 12px",
                      fontSize: "12px",
                    }}
                  >
                    Delete
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}

      {selectedTab === "roles" && (
        <div style={{ background: "white", padding: "20px", borderRadius: "8px" }}>
          <h3>Available Roles</h3>
          <div style={{ marginTop: "15px" }}>
            <div style={{ marginBottom: "15px", padding: "10px", border: "1px solid #ddd", borderRadius: "4px" }}>
              <strong>Admin</strong> - Full access to all features and settings
            </div>
            <div style={{ marginBottom: "15px", padding: "10px", border: "1px solid #ddd", borderRadius: "4px" }}>
              <strong>Teacher</strong> - Access to teaching, grading, and classroom management
            </div>
            <div style={{ marginBottom: "15px", padding: "10px", border: "1px solid #ddd", borderRadius: "4px" }}>
              <strong>Student</strong> - Limited access to course content and grades
            </div>
          </div>
        </div>
      )}

      {selectedTab === "permissions" && (
        <div style={{ background: "white", padding: "20px", borderRadius: "8px" }}>
          <h3>Permission Matrix</h3>
          <table style={{ ...tableStyle, marginTop: "15px" }}>
            <thead>
              <tr>
                <th style={headerCellStyle}>Permission</th>
                <th style={headerCellStyle}>Admin</th>
                <th style={headerCellStyle}>Teacher</th>
                <th style={headerCellStyle}>Student</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td style={cellStyle}>View Users</td>
                <td style={cellStyle}>✓</td>
                <td style={cellStyle}>✗</td>
                <td style={cellStyle}>✗</td>
              </tr>
              <tr>
                <td style={cellStyle}>Create Users</td>
                <td style={cellStyle}>✓</td>
                <td style={cellStyle}>✗</td>
                <td style={cellStyle}>✗</td>
              </tr>
              <tr>
                <td style={cellStyle}>Manage Roles</td>
                <td style={cellStyle}>✓</td>
                <td style={cellStyle}>✗</td>
                <td style={cellStyle}>✗</td>
              </tr>
              <tr>
                <td style={cellStyle}>Create Classes</td>
                <td style={cellStyle}>✓</td>
                <td style={cellStyle}>✓</td>
                <td style={cellStyle}>✗</td>
              </tr>
              <tr>
                <td style={cellStyle}>View Grades</td>
                <td style={cellStyle}>✓</td>
                <td style={cellStyle}>✓</td>
                <td style={cellStyle}>✓</td>
              </tr>
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
};

export default IAM;
