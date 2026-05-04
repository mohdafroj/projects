import React, { Suspense, useState } from 'react';

const RemoteDashboard = React.lazy(() => import('dashboard/Dashboard'));
const RemoteSettings = React.lazy(() => import('settings/Settings'));

const LoadingSpinner = () => (
  <div style={{ padding: '20px', textAlign: 'center' }}>
    <div style={{
      width: '40px',
      height: '40px',
      border: '4px solid #f3f3f3',
      borderTop: '4px solid #3498db',
      borderRadius: '50%',
      animation: 'spin 1s linear infinite',
      margin: '0 auto'
    }} />
    <p>Loading microfrontend...</p>
  </div>
);

type Tab = 'dashboard' | 'settings';

function App() {
  const [activeTab, setActiveTab] = useState<Tab>('dashboard');

  return (
    <div style={{ fontFamily: 'system-ui, sans-serif', padding: '20px' }}>
      <header style={{
        marginBottom: '24px',
        paddingBottom: '16px',
        borderBottom: '2px solid #e5e7eb'
      }}>
        <h1 style={{ marginBottom: '16px' }}>🏠 Host Application</h1>
        <nav style={{ display: 'flex', gap: '12px' }}>
          <button
            onClick={() => setActiveTab('dashboard')}
            style={{
              padding: '8px 16px',
              backgroundColor: activeTab === 'dashboard' ? '#3b82f6' : '#e5e7eb',
              color: activeTab === 'dashboard' ? 'white' : 'black',
              border: 'none',
              borderRadius: '6px',
              cursor: 'pointer'
            }}
          >
            Dashboard
          </button>
          <button
            onClick={() => setActiveTab('settings')}
            style={{
              padding: '8px 16px',
              backgroundColor: activeTab === 'settings' ? '#3b82f6' : '#e5e7eb',
              color: activeTab === 'settings' ? 'white' : 'black',
              border: 'none',
              borderRadius: '6px',
              cursor: 'pointer'
            }}
          >
            Settings
          </button>
        </nav>
      </header>

      <main>
        <Suspense fallback={<LoadingSpinner />}>
          {activeTab === 'dashboard' && <RemoteDashboard username="John" />}
          {activeTab === 'settings' && <RemoteSettings />}
        </Suspense>
      </main>

      <style>{`
        @keyframes spin {
          0% { transform: rotate(0deg); }
          100% { transform: rotate(360deg); }
        }
      `}</style>
    </div>
  );
}

export default App;
