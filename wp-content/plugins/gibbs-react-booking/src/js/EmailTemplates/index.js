import React, { useState, useEffect, useRef } from 'react';
import ReactDOM from 'react-dom/client';
import EmailTemplate from './EmailTemplate';
import EmailLog from './EmailLog';
import { Layout } from '../layouts';






// Booking Container Component - handles both booking and confirmation states
function EmailTemplateContainer({ page_id, apiUrl, homeUrl, user_token = null, owner_id = null }) {
    const [currentView, setCurrentView] = useState('email_template');

   

    const renderCurrentView = () => {
        switch (currentView) {
            case 'email_template':
                return (
                    <EmailTemplate 
                        page_id={page_id} 
                        apiUrl={apiUrl} 
                        homeUrl={homeUrl}
                        user_token={user_token}
                        owner_id={owner_id}
                    />
                );
            
            default:
                return <div className="rmp-error">Invalid view state</div>;
        }
    };

    return (
        <div className="rmp-wallet-container">
            {renderCurrentView()}
        </div>
    );
}

window.rmpEmailTemplateInit = function (containerId, page_id, apiUrl, homeUrl, user_token, owner_id) {
    const container = document.getElementById(containerId);
    if (container) {
        const root = ReactDOM.createRoot(container);
        root.render(
            <Layout>
                <EmailTemplateContainer 
                    page_id={page_id} 
                    apiUrl={apiUrl} 
                    homeUrl={homeUrl}
                    user_token={user_token} 
                    owner_id={owner_id}
                />
            </Layout>
        );
    }
};
window.rmpEmailLogInit = function (containerId, page_id, apiUrl, homeUrl, user_token, owner_id) {
    const container = document.getElementById(containerId);
    if (container) {
        const root = ReactDOM.createRoot(container);
        root.render(
            <Layout>
                <EmailLog 
                    page_id={page_id} 
                    apiUrl={apiUrl} 
                    homeUrl={homeUrl}
                    user_token={user_token} 
                    owner_id={owner_id}
                />
            </Layout>
        );
    }
};