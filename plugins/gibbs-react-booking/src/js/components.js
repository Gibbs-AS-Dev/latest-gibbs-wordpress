import React, { useState, useEffect, useRef } from 'react';
import ReactDOM from 'react-dom/client';
import SlotBooking from './SlotBooking';
import SlotBookingConfirmation from './SlotBookingConfirmation';
import DinteroPayment from './DinteroPayment';
import DibsCheckout from './DibsCheckout';
import IframePage from './IframePage';
import { Ltext } from './utils/translations';

// Full Screen Timer Banner Component
const FullScreenTimerBanner = ({ startTime, onClose }) => {
  const [timeLeft, setTimeLeft] = useState(0);
  const [isVisible, setIsVisible] = useState(true);

  useEffect(() => {
    const calculateTimeLeft = () => {
      const now = new Date();
      const elapsed = now - startTime;
      const totalTime = 15 * 60 * 1000; // 15 minutes in milliseconds
      const remaining = Math.max(0, totalTime - elapsed);
      setTimeLeft(remaining);
    };

    // Calculate immediately
    calculateTimeLeft();

    // Update every second
    const timer = setInterval(calculateTimeLeft, 1000);

    return () => clearInterval(timer);
  }, [startTime]);

  useEffect(() => {
    if (timeLeft === 0) {
      // Auto-hide after 15 minutes
      const hideTimer = setTimeout(() => {
        setIsVisible(false);
        onClose();
        
        // Refresh the window when timer ends
        window.location.reload();
      }, 1000);
      return () => clearTimeout(hideTimer);
    }
  }, [timeLeft, onClose]);

  const formatTime = (milliseconds) => {
    const minutes = Math.floor(milliseconds / (1000 * 60));
    const seconds = Math.floor((milliseconds % (1000 * 60)) / 1000);

    return `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
  };

  if (!isVisible) return null;

  // Create and append banner to body
  useEffect(() => {
    // Create banner element
    const bannerElement = document.createElement('div');
    bannerElement.id = 'rmp-timer-banner';
    bannerElement.style.cssText = `
      position: fixed;
      bottom: 20px;
      left: 50%;
      transform: translateX(-50%);
      background-color: #1a9a94;
      color: white;
      padding: 12px 20px;
      border-radius: 25px;
      box-shadow: 0 8px 25px rgba(26, 154, 148, 0.4);
      z-index: 9999;
      display: flex;
      align-items: center;
      gap: 12px;
      backdrop-filter: blur(10px);
      border: 2px solid rgba(255, 255, 255, 0.2);
      animation: slideUp 0.5s ease-out;
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    `;

    // Add CSS animation
    const style = document.createElement('style');
    style.textContent = `
      @keyframes slideUp {
        from { 
          opacity: 0; 
          transform: translateX(-50%) translateY(100px); 
        }
        to { 
          opacity: 1; 
          transform: translateX(-50%) translateY(0); 
        }
      }
    `;
    document.head.appendChild(style);

    // Banner content
    bannerElement.innerHTML = `
      <div style="
        // background: rgba(31, 112, 208, 0.25);
        border-radius: 50%;
        padding: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        backdrop-filter: blur(10px);
      ">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <circle cx="12" cy="12" r="10" stroke="#fff" stroke-width="3"/>
          <polyline points="12,6 12,12 17,15" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </div>
      
      <div style="display: flex; flex-direction: column; line-height: 1.2;">
        <span style="font-size: 12px; opacity: 0.9; font-weight: 500;display: none;">
          ${Ltext("Booking Confirmed!")}
        </span>
        <span style="font-size: 14px; font-weight: 700;">
          ${Ltext("Time remaining")}: <span id="rmp-timer-display">${formatTime(timeLeft)}</span>
        </span>
      </div>
      
      <button id="rmp-timer-close" style="
        background: rgba(31, 112, 208, 0.18);
        border: none;
        border-radius: 50%;
        padding: 6px;
        display: none;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        backdrop-filter: blur(10px);
        transition: all 0.2s;
        min-width: 24px;
        min-height: 24px;
        color: #fff;
      ">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <line x1="18" y1="6" x2="6" y2="18" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
          <line x1="6" y1="6" x2="18" y2="18" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>
    `;

    // Add event listeners
    const closeButton = bannerElement.querySelector('#rmp-timer-close');
    closeButton.addEventListener('click', () => {
      onClose();
    });

    // Hover effects for close button
    closeButton.addEventListener('mouseenter', () => {
      closeButton.style.background = 'rgba(255, 255, 255, 0.3)';
    });
    closeButton.addEventListener('mouseleave', () => {
      closeButton.style.background = 'rgba(255, 255, 255, 0.2)';
    });

    // Append to body
    document.body.appendChild(bannerElement);

    // Cleanup function
    return () => {
      if (bannerElement && bannerElement.parentNode) {
        bannerElement.parentNode.removeChild(bannerElement);
      }
      if (style && style.parentNode) {
        style.parentNode.removeChild(style);
      }
    };
  }, []); // Empty dependency array - only run once when component mounts

  // Update timer display when timeLeft changes
  useEffect(() => {
    const timerDisplay = document.getElementById('rmp-timer-display');
    if (timerDisplay) {
      timerDisplay.textContent = formatTime(timeLeft);
    }
  }, [timeLeft]);

  // Don't render anything in React - we're appending to body
  return null;
};

// Booking Container Component - handles both booking and confirmation states
function BookingContainer({ listing_id, apiUrl, homeUrl, pluginUrl, initialBookingToken = null, current_user_id = null, hideBorder = false }) {
    const [currentView, setCurrentView] = useState(initialBookingToken ? 'confirmation' : 'booking');
    const [bookingToken, setBookingToken] = useState(initialBookingToken);
    const [prevBookingData, setPrevBookingData] = useState(null);
    const [paymentId, setPaymentId] = useState(null);
    const [checkoutKey, setCheckoutKey] = useState(null);
    const [mode, setMode] = useState(null);
    const [cr_user_id, setCrUserId] = useState(current_user_id);
    const [userLoggedIn, setUserLoggedIn] = useState(current_user_id ? true : false);
    const [iframeUrl, setIframeUrl] = useState(null);


    const [hideSlotBorder, setHideSlotBorder] = useState(hideBorder == "1" ? true : false);
    
    // Timer banner state
    const [showTimerBanner, setShowTimerBanner] = useState(false);
    const [bookingStartTime, setBookingStartTime] = useState(null);
    const [thankYouPage, setThankYouPage] = useState(null);

    const handleBookingSuccess = (token, data) => {
        setBookingToken(token);
        setPrevBookingData(data);
        setCurrentView('confirmation');
        
        // Show timer banner
        setShowTimerBanner(true);
        setBookingStartTime(new Date());
    };

    const handleBackToBooking = () => {
        setCurrentView('booking');
        setShowTimerBanner(false);
        //setBookingToken(null);
        //setBookingData(null);
    };

    // Handle continue booking
    const handleContinueBooking = () => {
        setCurrentView('booking');
        setShowTimerBanner(false);
        setPrevBookingData(null);
        setBookingToken(null);
    };
    
    // Handle Dintero payment
    const handleDinteroPayment = (paymentId) => {
        setCurrentView('dinteroPaymentView');
        setPaymentId(paymentId);
    };
    
    const handleNetsEasyPayment = (paymentId, checkoutKey = null, mode = null, thank_you_page = null) => {
        setCurrentView('dibsCheckout');
        setThankYouPage(thank_you_page);
        // setPaymentId(paymentId);
        // setCheckoutKey(checkoutKey);
        // setMode(mode);
        setIframeUrl(homeUrl + `/gibbs-pay/?payment_id=${paymentId}&checkout_key=${checkoutKey}&mode=${mode}`);
    };
    
    // Handle waiting payment
    const handleWaitingPayment = () => {
        setCurrentView('waitingPayment');
    };
    useEffect(() => {
        // setCurrentView('dibsCheckout');
        // setPaymentId("955833b8e572450792d843694c1bf601");
        // setCheckoutKey("d5a12b4dbc2347e2b98aeaf160a20d8f");
        // setMode("test");
        // setIframeUrl(homeUrl + `/gibbs-payment/?payment_id=${paymentId}&checkout_key=${checkoutKey}&mode=${mode}`);
    }, [paymentId, checkoutKey, mode]);

    const renderCurrentView = () => {
        switch (currentView) {
            case 'booking':
                return (
                    <SlotBooking 
                        listing_id={listing_id} 
                        apiUrl={apiUrl} 
                        homeUrl={homeUrl}
                        prevBookingData={prevBookingData}
                        setPrevBookingData={setPrevBookingData}
                        handleBookingSuccess={handleBookingSuccess}
                        bookingToken={bookingToken}
                    />
                );
            case 'confirmation':
                return (
                    <SlotBookingConfirmation 
                        userLoggedIn={userLoggedIn}
                        bookingToken={bookingToken}
                        apiUrl={apiUrl}
                        cr_user_id={cr_user_id}
                        setCrUserId={setCrUserId}
                        pluginUrl={pluginUrl}
                        homeUrl={homeUrl}
                        prevBookingData={prevBookingData}
                        setPrevBookingData={setPrevBookingData}
                        handleBackToBooking={handleBackToBooking}
                        handleDinteroPayment={handleDinteroPayment}
                        handleNetsEasyPayment={handleNetsEasyPayment}
                        handleWaitingPayment={handleWaitingPayment}
                    />
                );
            case 'dinteroPaymentView':
                return (
                    <DinteroPayment 
                        sid={paymentId}
                        apiUrl={apiUrl}
                        cr_user_id={cr_user_id}
                        pluginUrl={pluginUrl}
                        homeUrl={homeUrl}
                    />
                );
            case 'dibsCheckout':
                return (
                    <IframePage 
                        src={iframeUrl}
                        title="Nets Easy Payment"
                        width="100%"
                        height="100%"
                        showLoader={true}
                        loaderText="Loading payment form..."
                        onLoad={() => console.log('Payment form loaded')}
                        onError={(error) => console.error('Payment form error:', error)}
                        setShowTimerBanner={setShowTimerBanner}
                        handleContinueBooking={handleContinueBooking}
                        thankYouPage={thankYouPage}
                    />
                );
            case 'waitingPayment':
                return (
                    <div style={{
                        display: 'flex',
                        flexDirection: 'column',
                        alignItems: 'center',
                        justifyContent: 'center',
                        minHeight: '400px',
                        padding: '40px 20px',
                        textAlign: 'center'
                    }}>
                        <div style={{
                            fontSize: '64px',
                            marginBottom: '24px',
                            color: '#1a9a94'
                        }}>
                            ⏳
                        </div>
                        <h2 style={{
                            fontSize: '24px',
                            fontWeight: '600',
                            marginBottom: '16px',
                            color: '#333'
                        }}>
                            {Ltext("Your booking is waiting for confirmation.")}
                        </h2>
                        <p style={{
                            fontSize: '16px',
                            color: '#666',
                            maxWidth: '500px',
                            lineHeight: '1.6'
                        }}>
                            {Ltext("Your booking has been submitted and is waiting for confirmation. You will receive an email once it is confirmed.")}
                        </p>
                    </div>
                );
            default:
                return <div className="rmp-error">Invalid view state</div>;
        }
    };

    return (
        <div className={`rmp-booking-container${hideSlotBorder ? ' hide-slot-border' : ''}`}>
            {renderCurrentView()}
            
            {/* Full Screen Timer Banner */}
            {showTimerBanner && bookingStartTime && (
                <FullScreenTimerBanner 
                    startTime={bookingStartTime}
                    onClose={() => setShowTimerBanner(false)}
                />
            )}
        </div>
    );
}


// Updated SlotBooking shortcode initializer
window.rmpSlotBookingInit = function (containerId, listing_id, apiUrl, homeUrl, pluginUrl, cr_user_id, hideBorder = false) {
    const container = document.getElementById(containerId);
    if (container) {
        const root = ReactDOM.createRoot(container);
        root.render(
            <BookingContainer 
                listing_id={listing_id} 
                apiUrl={apiUrl} 
                homeUrl={homeUrl}
                pluginUrl={pluginUrl}
                current_user_id={cr_user_id}
                hideBorder={hideBorder}
            />
        );
    }
};

// Updated Booking Confirmation initializer
window.rmpBookingConfirmationInit = function (containerId, pluginUrl, bookingToken, apiUrl, cr_user_id, homeUrl) {
    const container = document.getElementById(containerId);
    if (container) {
        const root = ReactDOM.createRoot(container);
        root.render(
            <BookingContainer 
                listing_id={null}
                apiUrl={apiUrl}
                homeUrl={homeUrl}
                pluginUrl={pluginUrl}
                initialBookingToken={bookingToken}
                cr_user_id={cr_user_id}
            />
        );
    }
};

window.rmpNetsEasyPaymentInit = function (containerId, pluginUrl, paymentId, checkoutKey, mode, apiUrl, cr_user_id, homeUrl) {
    const container = document.getElementById(containerId);
    if (container) {
        const root = ReactDOM.createRoot(container);
        root.render(
            <DibsCheckout 
                paymentId={paymentId}
                checkoutKey={checkoutKey} 
                mode={mode}
                apiUrl={apiUrl}
                cr_user_id={cr_user_id}
                pluginUrl={pluginUrl}
                homeUrl={homeUrl}
                onBack={() => {}}
                onSuccess={(data) => {
                    // console.log('Payment successful:', data);
                    // Handle successful payment
                    // window.location.href = homeUrl;
                }}
                onError={(error) => {
                    console.error('Payment error:', error);
                }} 
            />
        );
    }
};


// Initialize React components when DOM is ready
function initializeReactModules() {
    const containers = document.querySelectorAll('[data-module]');
    
    containers.forEach(container => {
        const module = container.getAttribute('data-module');
        const containerId = container.id;
        
        const root = ReactDOM.createRoot(container);
        root.render(<ReactModule module={module} containerId={containerId} />);
    });
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeReactModules);
} else {
    initializeReactModules();
}

// Also initialize for dynamically loaded content
if (typeof jQuery !== 'undefined') {
    jQuery(document).on('ajaxComplete', initializeReactModules);
} 