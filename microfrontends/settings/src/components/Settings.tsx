import React, { useState } from 'react';

const Settings: React.FC = () => {
    const [darkMode, setDarkMode] = useState(false);
    const [notifications, setNotifications] = useState(true);

    return (
        <div style={{
            padding: '24px',
            backgroundColor: '#fef3c7',
            borderRadius: '8px',
            border: '2px solid #d97706'
        }}>
            <h2 style={{ color: '#b45309', marginBottom: '16px' }}>
                ⚙️ Settings Microfrontend
            </h2>
            <div style={{ display: 'flex', flexDirection: 'column', gap: '12px' }}>
                <label style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                    <input
                        type="checkbox"
                        checked={darkMode}
                        onChange={(e) => setDarkMode(e.target.checked)}
                    />
                    Dark Mode
                </label>
                <label style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                    <input
                        type="checkbox"
                        checked={notifications}
                        onChange={(e) => setNotifications(e.target.checked)}
                    />
                    Enable Notifications
                </label>
            </div>
        </div>
    );
};

export default Settings;
