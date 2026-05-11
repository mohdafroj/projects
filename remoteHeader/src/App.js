import React from 'react';

function App() {
    return (
        <div id="header-app">
            <div className="mx-6 my-6 rounded-3xl border-2 border-blue-500 bg-blue-100 p-8 shadow-lg">
                <h2 className="text-2xl font-semibold text-slate-900">Remote Header</h2>
                <p className='TestName' style={{ color: '#a8142c' }}>Afroj</p>
                <button
                    className="mt-6 rounded-full bg-blue-600 px-5 py-2 text-white transition hover:bg-blue-700"
                    onClick={() => alert('Hello from Remote App!')}
                >
                    Click Me
                </button>
            </div>
        </div>
    );
}

export default App;
