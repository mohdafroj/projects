import React, { useState } from "react";

interface User {
  id: string;
  name: string;
  email: string;
  role: string;
  status: "active" | "inactive";
}

interface Service {
  id: string;
  name: string;
  description: string;
  features: string[];
  icon: string;
}

interface Feature {
  id: string;
  name: string;
  description: string;
  service: string;
  module: string;
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

  const [services, setServices] = useState<Service[]>([
    {
      id: "1",
      name: "Student Management",
      description: "Manage student records, enrollment, and progress tracking",
      features: ["Add Students", "View Records", "Update Progress", "Generate Reports"],
      icon: "👥",
    },
    {
      id: "2",
      name: "Teacher Management",
      description: "Manage teacher profiles, assignments, and schedules",
      features: ["Manage Profiles", "Assign Classes", "Create Schedules", "Track Performance"],
      icon: "👨‍🏫",
    },
    {
      id: "3",
      name: "Class Management",
      description: "Manage class sections, timetables, and curricula",
      features: ["Create Classes", "Manage Timetable", "Set Curriculum", "Assign Teachers"],
      icon: "📚",
    },
    {
      id: "4",
      name: "Attendance System",
      description: "Track student and teacher attendance",
      features: ["Mark Attendance", "Generate Reports", "Set Rules", "Send Alerts"],
      icon: "✓",
    },
  ]);

  const [features, setFeatures] = useState<Feature[]>([
    {
      id: "1",
      name: "Add Students",
      description: "Create and add new student records",
      service: "Student Management",
      module: "remoteStudent",
      status: "active",
    },
    {
      id: "2",
      name: "View Records",
      description: "View and search student records",
      service: "Student Management",
      module: "remoteStudent",
      status: "active",
    },
    {
      id: "3",
      name: "Update Progress",
      description: "Update student progress and grades",
      service: "Student Management",
      module: "remoteStudent",
      status: "active",
    },
    {
      id: "4",
      name: "Manage Profiles",
      description: "Manage teacher profile information",
      service: "Teacher Management",
      module: "remoteTeacher",
      status: "active",
    },
    {
      id: "5",
      name: "Assign Classes",
      description: "Assign teachers to classes",
      service: "Teacher Management",
      module: "remoteTeacher",
      status: "active",
    },
    {
      id: "6",
      name: "Create Classes",
      description: "Create and configure class sections",
      service: "Class Management",
      module: "remoteClass",
      status: "active",
    },
    {
      id: "7",
      name: "Mark Attendance",
      description: "Record daily attendance",
      service: "Attendance System",
      module: "remoteAttendance",
      status: "active",
    },
    {
      id: "8",
      name: "Generate Reports",
      description: "Generate attendance reports",
      service: "Attendance System",
      module: "remoteAttendance",
      status: "inactive",
    },
  ]);

  const [selectedTab, setSelectedTab] = useState<"users" | "roles" | "permissions" | "services" | "features">("users");
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
    overflowX: "auto",
  };

  const tabStyle = (isActive: boolean): React.CSSProperties => ({
    padding: "12px 20px",
    background: isActive ? "#667eea" : "white",
    color: isActive ? "white" : "#333",
    border: "none",
    cursor: "pointer",
    borderRadius: "4px 4px 0 0",
    fontWeight: isActive ? "bold" : "normal",
    whiteSpace: "nowrap",
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

  const cardStyle: React.CSSProperties = {
    background: "white",
    padding: "20px",
    borderRadius: "8px",
    marginBottom: "15px",
    boxShadow: "0 2px 4px rgba(0,0,0,0.1)",
    border: "1px solid #e0e0e0",
  };

  const cardHeaderStyle: React.CSSProperties = {
    display: "flex",
    alignItems: "center",
    gap: "15px",
    marginBottom: "10px",
  };

  const cardIconStyle: React.CSSProperties = {
    fontSize: "32px",
  };

  const cardTitleStyle: React.CSSProperties = {
    fontSize: "18px",
    fontWeight: "bold",
    color: "#333",
  };

  const featureListStyle: React.CSSProperties = {
    display: "grid",
    gridTemplateColumns: "repeat(2, 1fr)",
    gap: "10px",
    marginTop: "10px",
  };

  const featureItemStyle: React.CSSProperties = {
    background: "#f0f0f0",
    padding: "8px 12px",
    borderRadius: "4px",
    fontSize: "13px",
    color: "#555",
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
        {selectedTab === "users" && (
          <button style={buttonStyle} onClick={() => setShowForm(!showForm)}>
            {showForm ? "Cancel" : "+ Add User"}
          </button>
        )}
      </div>

      {showForm && selectedTab === "users" && (
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
        <button
          style={tabStyle(selectedTab === "services")}
          onClick={() => setSelectedTab("services")}
        >
          🔧 Services ({services.length})
        </button>
        <button
          style={tabStyle(selectedTab === "features")}
          onClick={() => setSelectedTab("features")}
        >
          ✨ Features ({features.length})
        </button>
      </div>

      {/* Users Tab */}
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

      {/* Roles Tab */}
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

      {/* Permissions Tab */}
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

      {/* Services Tab */}
      {selectedTab === "services" && (
        <div>
          <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(300px, 1fr))", gap: "20px" }}>
            {services.map((service) => (
              <div key={service.id} style={cardStyle}>
                <div style={cardHeaderStyle}>
                  <div style={cardIconStyle}>{service.icon}</div>
                  <div>
                    <div style={cardTitleStyle}>{service.name}</div>
                    <div style={{ fontSize: "13px", color: "#777", marginTop: "4px" }}>
                      {service.features.length} features
                    </div>
                  </div>
                </div>
                <p style={{ fontSize: "14px", color: "#666", marginBottom: "12px" }}>
                  {service.description}
                </p>
                <div>
                  <div style={{ fontSize: "12px", fontWeight: "bold", color: "#667eea", marginBottom: "8px" }}>
                    Features:
                  </div>
                  <div style={featureListStyle}>
                    {service.features.map((feature, index) => (
                      <div key={index} style={featureItemStyle}>
                        ✓ {feature}
                      </div>
                    ))}
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Features Tab */}
      {selectedTab === "features" && (
        <div>
          <table style={tableStyle}>
            <thead>
              <tr>
                <th style={headerCellStyle}>Feature Name</th>
                <th style={headerCellStyle}>Description</th>
                <th style={headerCellStyle}>Service</th>
                <th style={headerCellStyle}>Module</th>
                <th style={headerCellStyle}>Status</th>
              </tr>
            </thead>
            <tbody>
              {features.map((feature) => (
                <tr key={feature.id}>
                  <td style={cellStyle}>
                    <strong>{feature.name}</strong>
                  </td>
                  <td style={cellStyle}>{feature.description}</td>
                  <td style={cellStyle}>{feature.service}</td>
                  <td style={cellStyle}>
                    <code style={{ background: "#f0f0f0", padding: "4px 8px", borderRadius: "4px" }}>
                      {feature.module}
                    </code>
                  </td>
                  <td style={cellStyle}>
                    <span style={statusStyle(feature.status)}>
                      {feature.status.charAt(0).toUpperCase() + feature.status.slice(1)}
                    </span>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
};

export default IAM;
