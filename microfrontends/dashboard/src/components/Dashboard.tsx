import React from 'react';

interface DashboardProps {
    username?: string;
}

const Dashboard: React.FC<DashboardProps> = ({ username = 'User' }) => {
    return (
        <div style={{
            padding: '24px',
            backgroundColor: '#e0f2fe',
            borderRadius: '8px',
            border: '2px solid #0284c7'
        }}>
            <h2 style={{ color: '#0369a1', marginBottom: '16px' }}>
                📊 Dashboard Microfrontend
            </h2>
            <p>Welcome, <strong>{username}</strong>!</p>
            <div style={{ marginTop: '16px' }}>
                <p>Stats: 150 Active Users | 42 New Signups Today</p>
            </div>
        </div>
    );
};

export default Dashboard;
