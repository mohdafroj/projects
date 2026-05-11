import React, { Suspense } from 'react';

// Lazy load remote components
const RemoteHeader = React.lazy(() => import('remoteHeader/App'));

function App() {
    return (
        <div style={{ padding: '20px', fontFamily: 'Arial, sans-serif' }}>
            <h1>Host Micro Frontend App</h1>
            <p>This is the host application consuming remote microfrontends.</p>
            <div style={{ marginTop: '20px' }}>
                <Suspense fallback={<div style={{ color: 'orange' }}>Loading Remote Header...</div>}>
                    <RemoteHeader />
                </Suspense>
            </div>
        </div>
    );
}

export default App;