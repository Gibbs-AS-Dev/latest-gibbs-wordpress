import React, { useState, useEffect, useRef } from 'react';
import ReactDOM from 'react-dom/client';
import Wallet from './Wallet';
import SmsLogContainer from './SmsLog';
import { Layout } from '../layouts';



// Booking Container Component - handles both booking and confirmation states
function WalletContainer({ page_id, apiUrl, homeUrl, user_token = null }) {
    const [currentView, setCurrentView] = useState('wallet');

   

    const renderCurrentView = () => {
        switch (currentView) {
            case 'wallet':
                return (
                    <Wallet 
                        page_id={page_id} 
                        apiUrl={apiUrl} 
                        homeUrl={homeUrl}
                        user_token={user_token}
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


// Updated SlotBooking shortcode initializer
window.rmpWalletInit = function (containerId, page_id, apiUrl, homeUrl, user_token) {
    const container = document.getElementById(containerId);
    if (container) {
        const root = ReactDOM.createRoot(container);
        root.render(
            <Layout>
                <WalletContainer 
                    page_id={page_id} 
                    apiUrl={apiUrl} 
                    homeUrl={homeUrl}
                    user_token={user_token}
                />
            </Layout>
        );
    }
};


window.rmpSmsLogInit = function (containerId, page_id, apiUrl, homeUrl, user_token, owner_id) {
    const container = document.getElementById(containerId);
    if (container) {
        const root = ReactDOM.createRoot(container);
        root.render(
            <Layout>
                <SmsLogContainer 
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