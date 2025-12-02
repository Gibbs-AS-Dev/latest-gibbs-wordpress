import React, { useState } from 'react';
import ReactDOM from 'react-dom/client';
import SubscriptionDiscount from '../SubscriptionDiscount/SubscriptionDiscount.js';
import ComponentGallery from './ComponentGallery.js';
import GibbsCustomer from './GibbsCustomer.js';

// React Modules container responsible for rendering requested component
function ReactModulesContainer({ component, page_id, apiUrl, homeUrl, user_token = null, owner_id = null }) {
    const [currentView, setCurrentView] = useState(component);

    React.useEffect(() => {
        setCurrentView(component);
    }, [component]);

    const renderCurrentView = () => {
        switch (currentView) {
            case 'subscription_discount':
                return (
                    <SubscriptionDiscount 
                        page_id={page_id} 
                        apiUrl={apiUrl} 
                        homeUrl={homeUrl}
                        user_token={user_token}
                        owner_id={owner_id}
                    />
                );
            case 'gibbs_customer':
                return (
                    <GibbsCustomer 
                        page_id={page_id} 
                        apiUrl={apiUrl} 
                        homeUrl={homeUrl}
                        user_token={user_token}
                        owner_id={owner_id}
                    />
                );
            case 'component_gallery':
            case 'components':
                return <ComponentGallery />;
            
            default:
                return <div className="rmp-error">Invalid view state: {currentView}</div>;
        }
    };

    return (
        <div className="rmp-react-modules-container">
            {renderCurrentView()}
        </div>
    );
}

window.rmpReactModulesInit = function (component, containerId, page_id, apiUrl, homeUrl, user_token, owner_id) {
    const container = document.getElementById(containerId);
    if (container) {
        const root = ReactDOM.createRoot(container);
        root.render(
            <ReactModulesContainer 
                component={component}
                page_id={page_id} 
                apiUrl={apiUrl} 
                homeUrl={homeUrl}
                user_token={user_token} 
                owner_id={owner_id}
            />
        );
    }
};