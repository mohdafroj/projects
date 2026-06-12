import React, { useState } from "react";
import "./IAM.css";

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

interface Role {
  id: string;
  name: string;
  description: string;
  permissions: number;
}

interface Permission {
  id: string;
  name: string;
  admin: boolean;
  teacher: boolean;
  student: boolean;
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

  const [roles, setRoles] = useState<Role[]>([
    {
      id: "1",
      name: "Admin",
      description: "Full access to all features and settings",
      permissions: 25,
    },
    {
      id: "2",
      name: "Teacher",
      description: "Access to teaching, grading, and classroom management",
      permissions: 15,
    },
    {
      id: "3",
      name: "Student",
      description: "Limited access to course content and grades",
      permissions: 5,
    },
  ]);

  const [permissions, setPermissions] = useState<Permission[]>([
    { id: "1", name: "View Users", admin: true, teacher: false, student: false },
    { id: "2", name: "Create Users", admin: true, teacher: false, student: false },
    { id: "3", name: "Manage Roles", admin: true, teacher: false, student: false },
    { id: "4", name: "Create Classes", admin: true, teacher: true, student: false },
    { id: "5", name: "View Grades", admin: true, teacher: true, student: true },
    { id: "6", name: "Edit Attendance", admin: true, teacher: true, student: false },
    { id: "7", name: "Generate Reports", admin: true, teacher: true, student: false },
    { id: "8", name: "View Dashboard", admin: true, teacher: true, student: true },
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
  const [showRoleForm, setShowRoleForm] = useState(false);
  const [newRole, setNewRole] = useState({ name: "", description: "", permissions: 0 });
  const [showServiceForm, setShowServiceForm] = useState(false);
  const [showFeatureForm, setShowFeatureForm] = useState(false);
  const [showPermissionForm, setShowPermissionForm] = useState(false);
  const [newPermission, setNewPermission] = useState({ name: "", admin: false, teacher: false, student: false });

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

  const addRole = () => {
    if (newRole.name && newRole.description) {
      const role: Role = {
        id: String(roles.length + 1),
        ...newRole,
      };
      setRoles([...roles, role]);
      setNewRole({ name: "", description: "", permissions: 0 });
      setShowRoleForm(false);
    }
  };

  const addPermission = () => {
    if (newPermission.name) {
      const permission: Permission = {
        id: String(permissions.length + 1),
        ...newPermission,
      };
      setPermissions([...permissions, permission]);
      setNewPermission({ name: "", admin: false, teacher: false, student: false });
      setShowPermissionForm(false);
    }
  };

  const deleteRole = (id: string) => {
    setRoles(roles.filter((role) => role.id !== id));
  };

  const deletePermission = (id: string) => {
    setPermissions(permissions.filter((perm) => perm.id !== id));
  };

  const deleteService = (id: string) => {
    setServices(services.filter((service) => service.id !== id));
  };

  const deleteFeature = (id: string) => {
    setFeatures(features.filter((feature) => feature.id !== id));
  };

  return (
    <div className="iam-container">
      <div className="iam-header">
        <div className="iam-title">Identity & Access Management</div>
        {selectedTab === "users" && (
          <button className="btn btn-primary" onClick={() => setShowForm(!showForm)}>
            {showForm ? "Cancel" : "+ Add User"}
          </button>
        )}
        {selectedTab === "roles" && (
          <button className="btn btn-secondary" onClick={() => setShowRoleForm(!showRoleForm)}>
            {showRoleForm ? "Cancel" : "+ Add Role"}
          </button>
        )}
        {selectedTab === "permissions" && (
          <button className="btn btn-secondary" onClick={() => setShowPermissionForm(!showPermissionForm)}>
            {showPermissionForm ? "Cancel" : "+ Add Permission"}
          </button>
        )}
        {selectedTab === "services" && (
          <button className="btn btn-secondary" onClick={() => setShowServiceForm(!showServiceForm)}>
            {showServiceForm ? "Cancel" : "+ Add Service"}
          </button>
        )}
        {selectedTab === "features" && (
          <button className="btn btn-secondary" onClick={() => setShowFeatureForm(!showFeatureForm)}>
            {showFeatureForm ? "Cancel" : "+ Add Feature"}
          </button>
        )}
      </div>

      {showForm && selectedTab === "users" && (
        <div className="iam-form">
          <h3>Create New User</h3>
          <input
            type="text"
            placeholder="Full Name"
            className="form-input"
            value={newUser.name}
            onChange={(e) => setNewUser({ ...newUser, name: e.target.value })}
          />
          <input
            type="email"
            placeholder="Email"
            className="form-input"
            value={newUser.email}
            onChange={(e) => setNewUser({ ...newUser, email: e.target.value })}
          />
          <select
            className="form-input"
            value={newUser.role}
            onChange={(e) => setNewUser({ ...newUser, role: e.target.value })}
          >
            <option>Admin</option>
            <option>Teacher</option>
            <option>Student</option>
          </select>
          <div className="form-buttons">
            <button className="btn btn-primary" onClick={addUser}>
              Create User
            </button>
          </div>
        </div>
      )}

      {showRoleForm && selectedTab === "roles" && (
        <div className="iam-form">
          <h3>Create New Role</h3>
          <input
            type="text"
            placeholder="Role Name"
            className="form-input"
            value={newRole.name}
            onChange={(e) => setNewRole({ ...newRole, name: e.target.value })}
          />
          <textarea
            placeholder="Role Description"
            className="form-input form-textarea"
            value={newRole.description}
            onChange={(e) => setNewRole({ ...newRole, description: e.target.value })}
          />
          <input
            type="number"
            placeholder="Number of Permissions"
            className="form-input"
            value={newRole.permissions}
            onChange={(e) => setNewRole({ ...newRole, permissions: parseInt(e.target.value) || 0 })}
          />
          <div className="form-buttons">
            <button className="btn btn-primary" onClick={addRole}>
              Create Role
            </button>
          </div>
        </div>
      )}

      {showPermissionForm && selectedTab === "permissions" && (
        <div className="iam-form">
          <h3>Create New Permission</h3>
          <input
            type="text"
            placeholder="Permission Name"
            className="form-input"
            value={newPermission.name}
            onChange={(e) => setNewPermission({ ...newPermission, name: e.target.value })}
          />
          <div className="form-checkbox">
            <label>
              <input
                type="checkbox"
                checked={newPermission.admin}
                onChange={(e) => setNewPermission({ ...newPermission, admin: e.target.checked })}
              />
              Admin Access
            </label>
          </div>
          <div className="form-checkbox">
            <label>
              <input
                type="checkbox"
                checked={newPermission.teacher}
                onChange={(e) => setNewPermission({ ...newPermission, teacher: e.target.checked })}
              />
              Teacher Access
            </label>
          </div>
          <div className="form-checkbox">
            <label>
              <input
                type="checkbox"
                checked={newPermission.student}
                onChange={(e) => setNewPermission({ ...newPermission, student: e.target.checked })}
              />
              Student Access
            </label>
          </div>
          <div className="form-buttons">
            <button className="btn btn-primary" onClick={addPermission}>
              Create Permission
            </button>
          </div>
        </div>
      )}

      <div className="iam-tabs">
        <button
          className={`tab ${selectedTab === "users" ? "active" : ""}`}
          onClick={() => setSelectedTab("users")}
        >
          👥 Users ({users.length})
        </button>
        <button
          className={`tab ${selectedTab === "roles" ? "active" : ""}`}
          onClick={() => setSelectedTab("roles")}
        >
          🔐 Roles ({roles.length})
        </button>
        <button
          className={`tab ${selectedTab === "permissions" ? "active" : ""}`}
          onClick={() => setSelectedTab("permissions")}
        >
          ⚙️ Permissions ({permissions.length})
        </button>
        <button
          className={`tab ${selectedTab === "services" ? "active" : ""}`}
          onClick={() => setSelectedTab("services")}
        >
          🔧 Services ({services.length})
        </button>
        <button
          className={`tab ${selectedTab === "features" ? "active" : ""}`}
          onClick={() => setSelectedTab("features")}
        >
          ✨ Features ({features.length})
        </button>
      </div>

      {/* Users Tab */}
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
                    {user.status.charAt(0).toUpperCase() + user.status.slice(1)}
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

      {/* Roles Tab */}
      {selectedTab === "roles" && (
        <div>
          <table className="iam-table">
            <thead>
              <tr>
                <th>Role Name</th>
                <th>Description</th>
                <th>Permissions</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              {roles.map((role) => (
                <tr key={role.id}>
                  <td>
                    <strong>{role.name}</strong>
                  </td>
                  <td>{role.description}</td>
                  <td>
                    <span className="permission-badge">{role.permissions} permissions</span>
                  </td>
                  <td>
                    <div className="action-buttons">
                      <button className="btn btn-edit">Edit</button>
                      <button className="btn btn-danger" onClick={() => deleteRole(role.id)}>
                        Delete
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {/* Permissions Tab */}
      {selectedTab === "permissions" && (
        <div>
          <table className="iam-table">
            <thead>
              <tr>
                <th>Permission</th>
                <th>Admin</th>
                <th>Teacher</th>
                <th>Student</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              {permissions.map((permission) => (
                <tr key={permission.id}>
                  <td>
                    <strong>{permission.name}</strong>
                  </td>
                  <td className="checkmark">{permission.admin ? "✓" : "✗"}</td>
                  <td className="checkmark">{permission.teacher ? "✓" : "✗"}</td>
                  <td className="checkmark">{permission.student ? "✓" : "✗"}</td>
                  <td>
                    <div className="action-buttons">
                      <button className="btn btn-edit">Edit</button>
                      <button className="btn btn-danger" onClick={() => deletePermission(permission.id)}>
                        Delete
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {/* Services Tab */}
      {selectedTab === "services" && (
        <div className="services-grid">
          {services.map((service) => (
            <div key={service.id} className="service-card">
              <div className="card-header">
                <div className="card-title-section">
                  <div className="card-icon">{service.icon}</div>
                  <div>
                    <div className="card-title">{service.name}</div>
                    <div className="card-subtitle">{service.features.length} features</div>
                  </div>
                </div>
                <div className="action-buttons">
                  <button className="btn btn-edit">Edit</button>
                  <button className="btn btn-danger" onClick={() => deleteService(service.id)}>
                    Delete
                  </button>
                </div>
              </div>
              <p className="card-description">{service.description}</p>
              <div className="card-features">
                <div className="features-label">Features:</div>
                <div className="features-list">
                  {service.features.map((feature, index) => (
                    <div key={index} className="feature-item">
                      ✓ {feature}
                    </div>
                  ))}
                </div>
              </div>
            </div>
          ))}
        </div>
      )}

      {/* Features Tab */}
      {selectedTab === "features" && (
        <div>
          <table className="iam-table">
            <thead>
              <tr>
                <th>Feature Name</th>
                <th>Description</th>
                <th>Service</th>
                <th>Module</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              {features.map((feature) => (
                <tr key={feature.id}>
                  <td>
                    <strong>{feature.name}</strong>
                  </td>
                  <td>{feature.description}</td>
                  <td>{feature.service}</td>
                  <td>
                    <code className="module-code">{feature.module}</code>
                  </td>
                  <td>
                    <span className={`status status-${feature.status}`}>
                      {feature.status.charAt(0).toUpperCase() + feature.status.slice(1)}
                    </span>
                  </td>
                  <td>
                    <div className="action-buttons">
                      <button className="btn btn-edit">Edit</button>
                      <button className="btn btn-danger" onClick={() => deleteFeature(feature.id)}>
                        Delete
                      </button>
                    </div>
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
