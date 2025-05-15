import React from 'react';
import ReactDOM from 'react-dom';
import App from './App';
import './scss/admin.scss';
import { createRoot } from 'react-dom/client';

// Render the app
const rootElement = document.getElementById('wpdf-root');
if (rootElement) {
    const reportsRoot = createRoot(rootElement);
	reportsRoot.render( <App />);
}