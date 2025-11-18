import React, { useEffect, useRef, useState } from 'react';
import axios from 'axios';
import {
    embed,
    SessionLoaded,
    SessionUpdated,
    SessionPayment,
    SessionPaymentError,
    SessionCancel,
    SessionNotFound,
    SessionLocked,
    SessionLockFailed,
    ActivePaymentProductType,
    ValidateSession,
} from "@dintero/checkout-web-sdk";

const DinteroPayment = ({ 
    sid, 
    language = "no", 
    popOut = false,
    onPaymentComplete, 
    onPaymentError,
    onSessionLoaded,
    onSessionUpdated,
    onSessionCancel,
    onSessionNotFound,
    onSessionLocked,
    onSessionLockFailed,
    onActivePaymentType,
    onValidateSession,
    title = "Secure Payment",
    subtitle = "Complete your purchase securely"
}) => {
    const containerRef = useRef(null);
    const checkoutRef = useRef(null);
    const [isLoading, setIsLoading] = useState(true);
    const [error, setError] = useState(null);
    const [paymentStatus, setPaymentStatus] = useState(null); // 'success', 'failed', 'cancelled', 'processing'
    const [statusMessage, setStatusMessage] = useState('');
    const [showErrorOverlay, setShowErrorOverlay] = useState(false);
    const [paymentCompleted, setPaymentCompleted] = useState(false);
    const initializeCheckout = async () => {
        if (containerRef.current && sid) {
            try {
                setIsLoading(true);
                setError(null);
                setPaymentStatus(null);
                setStatusMessage('');
                setShowErrorOverlay(false);
                
                const checkout = await embed({
                    container: containerRef.current,
                    sid: sid,
                    popOut: popOut,
                    language: language,
                    onSession: (event) => {
                        //console.log("session", event.session);
                        if (event.type === 'session_loaded' && onSessionLoaded) {
                            onSessionLoaded(event);
                        } else if (event.type === 'session_updated' && onSessionUpdated) {
                            onSessionUpdated(event);
                        }
                    },
                    onPayment: async (event, checkout) => {

                        setPaymentStatus('processing');
                        setStatusMessage('Processing your payment...');
                        setShowErrorOverlay(false);
                        setPaymentCompleted(true);

                        if(event.transaction_id){
                            
                            try {
                                var apiUrl = "/dintero-webhook";
                                
                                const response = await axios.get(`${apiUrl}?transaction_id=${event.transaction_id}&session_id=${event.sid}&merchant_reference=${event.merchant_reference}`);

                                if(response.data.success && response.data.data.redirect_url){
                                    setPaymentStatus('success');
                                    setStatusMessage('Payment completed successfully!');
                                    setShowErrorOverlay(false);
                                    // setTimeout(() => {
                                    //     window.location.href = "/thank-you/?booking_id=" + event.transaction_id;
                                    // }, 2000);
                                }else{
                                    setPaymentStatus('failed');
                                    setStatusMessage('Payment successfuly completed. But we could not verify it. Please contact us.');
                                    setShowErrorOverlay(true);
                                }
                                
                            } catch (err) {
                                console.error('Error submitting booking:', err);
                                setPaymentStatus('failed');
                                setStatusMessage(err?.response?.data?.message || 'Payment verification error. Please try again.');
                                setShowErrorOverlay(true);
                            } finally {
                            
                            }
                        }else{
                            setPaymentStatus('failed');
                            setStatusMessage('Transaction id not found.');
                            setShowErrorOverlay(true);
                        }
                        checkout.destroy();
                    },
                    onPaymentError: (event, checkout) => {
                        console.log("Payment error:", event);
                        setPaymentStatus('failed');
                        setStatusMessage(getErrorMessage(event));
                        setShowErrorOverlay(true);
                        if (onPaymentError) {
                            onPaymentError(event);
                        }
                        checkout.destroy();
                    },
                    onSessionCancel: (event, checkout) => {
                        console.log("Session cancelled:", event);
                        setPaymentStatus('cancelled');
                        setStatusMessage('Payment was cancelled. You can try again.');
                        setShowErrorOverlay(true);
                        checkout.destroy();
                    },
                    onSessionNotFound: (event, checkout) => {
                        console.log("session not found (expired)", event.type);
                        setPaymentStatus('failed');
                        setStatusMessage('Payment session expired. Please try again.');
                        setShowErrorOverlay(true);
                        checkout.destroy();
                    },
                    onSessionLocked: (event, checkout, callback) => {
                        console.log("pay_lock_id", event.pay_lock_id);
                        if (onSessionLocked) {
                            onSessionLocked(event, callback);
                        } else {
                            callback(); // refresh session
                        }
                    },
                    onSessionLockFailed: (event, checkout) => {
                        console.log("session lock failed");
                        setPaymentStatus('failed');
                        setStatusMessage('Unable to secure payment session. Please try again.');
                        setShowErrorOverlay(true);
                        if (onSessionLockFailed) {
                            onSessionLockFailed(event);
                        }
                    },
                    onActivePaymentType: (event, checkout) => {
                        console.log("payment product type selected", event.payment_product_type);
                        if (onActivePaymentType) {
                            onActivePaymentType(event);
                        }
                    },
                    onValidateSession: (event, checkout, callback) => {

                        // setPaymentStatus('failed');
                        // setStatusMessage('Payment verification error. Please try again.');
                        // setShowErrorOverlay(true);

                       // console.log("validating session", event.session);
                        if (onValidateSession) {
                            onValidateSession(event, callback);
                        } else {
                            callback({
                                success: true,
                                clientValidationError: undefined,
                            });
                        }
                    },
                });

                checkoutRef.current = checkout;
                setIsLoading(false);

            } catch (error) {
                console.error('Error initializing Dintero payment:', error);
                setError(error);
                setPaymentStatus('failed');
                setStatusMessage('Failed to initialize payment system. Please try again.');
                setShowErrorOverlay(true);
                setIsLoading(false);
                if (onPaymentError) {
                    onPaymentError(error);
                }
            }
        }
    };

    useEffect(() => {
        initializeCheckout();

        // Cleanup function
        return () => {
            if (checkoutRef.current && typeof checkoutRef.current.destroy === 'function') {
                checkoutRef.current.destroy();
            }
        };
    }, [sid, language, popOut, onPaymentComplete, onPaymentError, onSessionLoaded, onSessionUpdated, onSessionCancel, onSessionNotFound, onSessionLocked, onSessionLockFailed, onActivePaymentType, onValidateSession]);

    const handleRetry = () => {
        setShowErrorOverlay(false);
        setPaymentStatus(null);
        setStatusMessage('');
        setError(null);
        initializeCheckout();
    };

    // Helper function to get user-friendly error messages
    const getErrorMessage = (event) => {
        if (event.error) {
            switch (event.error.type) {
                case 'card_declined':
                    return 'Your card was declined. Please try a different payment method.';
                case 'insufficient_funds':
                    return 'Insufficient funds. Please try a different card or payment method.';
                case 'expired_card':
                    return 'Your card has expired. Please use a different card.';
                case 'invalid_card':
                    return 'Invalid card details. Please check and try again.';
                case 'network_error':
                    return 'Network error. Please check your connection and try again.';
                case 'timeout':
                    return 'Payment timeout. Please try again.';
                default:
                    return event.error.message || 'Payment failed. Please try again.';
            }
        }
        return 'Payment failed. Please try again.';
    };

    // Helper function to get status icon and color
    const getStatusDisplay = () => {
        switch (paymentStatus) {
            case 'success':
                return {
                    icon: '✅',
                    color: '#059669',
                    bgColor: '#ecfdf5',
                    title: 'Payment Successful'
                };
            case 'failed':
                return {
                    icon: '❌',
                    color: '#dc2626',
                    bgColor: '#fef2f2',
                    title: 'Payment Failed'
                };
            case 'cancelled':
                return {
                    icon: '⚠️',
                    color: '#d97706',
                    bgColor: '#fffbeb',
                    title: 'Payment Cancelled'
                };
            case 'processing':
                return {
                    icon: '⏳',
                    color: '#2563eb',
                    bgColor: '#eff6ff',
                    title: 'Processing Payment'
                };
            default:
                return null;
        }
    };

    const containerStyles = {
        maxWidth: '600px',
        margin: '0 auto',
        padding: '20px',
        fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif',
        lineHeight: '1.6',
        color: '#333'
    };

    const cardStyles = {
        background: '#ffffff',
        borderRadius: '12px',
        boxShadow: '0 4px 6px rgba(0, 0, 0, 0.1), 0 1px 3px rgba(0, 0, 0, 0.08)',
        border: '1px solid #e1e5e9',
        overflow: 'hidden',
        transition: 'all 0.3s ease'
    };

    const headerStyles = {
        background: 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
        color: 'white',
        padding: '24px 32px',
        textAlign: 'center'
    };

    const contentStyles = {
        padding: '5px',
        minHeight: '400px',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        backgroundColor: '#f8fafc',
        position: 'relative'
    };

    const loadingStyles = {
        textAlign: 'center',
        color: '#64748b'
    };

    const spinnerStyles = {
        width: '48px',
        height: '48px',
        border: '4px solid #e2e8f0',
        borderTop: '4px solid #667eea',
        borderRadius: '50%',
        animation: 'spin 1s linear infinite',
        margin: '0 auto 20px'
    };

    const errorStyles = {
        textAlign: 'center',
        color: '#dc2626',
        padding: '20px'
    };

    const errorOverlayStyles = {
        position: 'absolute',
        top: '0',
        left: '0',
        right: '0',
        bottom: '0',
        background: 'rgba(255, 255, 255, 0.95)',
        display: 'flex',
        justifyContent: 'center',
        zIndex: 20,
        paddingTop: '10%'
    };

    const errorCardStyles = {
        background: '#ffffff',
        borderRadius: '12px',
        boxShadow: '0 10px 25px rgba(0, 0, 0, 0.15)',
        padding: '32px',
        textAlign: 'center',
        maxWidth: '400px',
        border: '1px solid #e1e5e9',
        height: 'max-content'
    };

    const securityBadgeStyles = {
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        gap: '8px',
        fontSize: '14px',
        color: '#059669',
        marginTop: '16px',
        fontWeight: '500'
    };

    // Show success status (this will redirect)
    if (paymentStatus === 'success' || paymentStatus === 'processing') {
        const statusDisplay = getStatusDisplay();
        return (
            <div style={containerStyles}>
                <div style={cardStyles}>
                    {/* <div style={headerStyles}>
                        <h2 style={{ margin: '0 0 8px 0', fontSize: '24px', fontWeight: '600' }}>
                            {statusDisplay.title}
                        </h2>
                        <p style={{ margin: '0', opacity: '0.9', fontSize: '16px' }}>
                            {subtitle}
                        </p>
                    </div> */}
                    <div style={{
                        ...contentStyles,
                        backgroundColor: statusDisplay.bgColor
                    }}>
                        <div style={{
                            textAlign: 'center',
                            padding: '40px 20px',
                            color: statusDisplay.color
                        }}>
                            <div style={{ fontSize: '64px', marginBottom: '24px' }}>
                                {statusDisplay.icon}
                            </div>
                            {/* <h3 style={{ 
                                margin: '0 0 16px 0', 
                                fontSize: '20px', 
                                fontWeight: '600',
                                color: statusDisplay.color
                            }}>
                                {statusDisplay.title}
                            </h3> */}
                            <p style={{ 
                                margin: '0', 
                                fontSize: '16px', 
                                color: statusDisplay.color,
                                opacity: 0.8
                            }}>
                                {statusMessage}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        );
    }

    if (error && !showErrorOverlay) {
        return (
            <div style={containerStyles}>
                <div style={cardStyles}>
                    <div style={headerStyles}>
                        <h2 style={{ margin: '0 0 8px 0', fontSize: '24px', fontWeight: '600' }}>
                            Payment Error
                        </h2>
                        <p style={{ margin: '0', opacity: '0.9', fontSize: '16px' }}>
                            Unable to load payment system
                        </p>
                    </div>
                    <div style={contentStyles}>
                        <div style={errorStyles}>
                            <div style={{ fontSize: '48px', marginBottom: '16px' }}>⚠️</div>
                            <h3 style={{ margin: '0 0 12px 0', fontSize: '18px' }}>
                                Failed to load payment system
                            </h3>
                            <p style={{ margin: '0', fontSize: '14px', color: '#6b7280' }}>
                                {error.message || 'An unexpected error occurred. Please try again.'}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        );
    }

    return (
        <div style={containerStyles}>
            <div style={cardStyles}>
                <div style={headerStyles}>
                    <h2 style={{ margin: '0 0 8px 0', fontSize: '24px', fontWeight: '600' }}>
                        {title}
                    </h2>
                    <p style={{ margin: '0', opacity: '0.9', fontSize: '16px' }}>
                        {subtitle}
                    </p>
                    <div style={securityBadgeStyles}>
                        <span>🔒</span>
                        <span>SSL Secured Payment</span>
                    </div>
                </div>
                
                <div style={contentStyles}>
                    <div 
                        id="checkout-container" 
                        ref={containerRef}
                        style={{
                            width: '100%',
                            minHeight: '300px',
                            position: 'relative'
                        }}
                    />
                    
                    {isLoading && (
                        <div style={{
                            position: 'absolute',
                            top: '0',
                            left: '0',
                            right: '0',
                            bottom: '0',
                            background: 'rgba(255, 255, 255, 0.95)',
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            zIndex: 10
                        }}>
                            <div style={loadingStyles}>
                                <div style={spinnerStyles} />
                                <h3 style={{ margin: '0 0 8px 0', fontSize: '18px', fontWeight: '500' }}>
                                    Loading...
                                </h3>
                                <p style={{ margin: '0', fontSize: '14px', color: '#64748b' }}>
                                    Please wait while we securely connect to our payment provider...
                                </p>
                            </div>
                        </div>
                    )}

                    {showErrorOverlay && paymentStatus && (
                        <div style={errorOverlayStyles}>
                            <div style={errorCardStyles}>
                                <div style={{ fontSize: '48px', marginBottom: '16px' }}>
                                    {getStatusDisplay()?.icon}
                                </div>
                                <h3 style={{ 
                                    margin: '0 0 12px 0', 
                                    fontSize: '18px',
                                    color: getStatusDisplay()?.color
                                }}>
                                    {getStatusDisplay()?.title}
                                </h3>
                                <p style={{ 
                                    margin: '0', 
                                    fontSize: '14px', 
                                    color: '#6b7280',
                                    lineHeight: '1.5'
                                }}>
                                    {statusMessage}
                                </p>
                                {!paymentCompleted && (
                                    <button onClick={handleRetry} style={{ marginTop: '20px', padding: '10px 20px', background: '#667eea', color: 'white', border: 'none', borderRadius: '5px', cursor: 'pointer' }}>Close</button>
                                )}
                            </div>
                        </div>
                    )}
                </div>
            </div>

            <style>
                {`
                    @keyframes spin {
                        0% { transform: rotate(0deg); }
                        100% { transform: rotate(360deg); }
                    }
                    
                    @media (max-width: 768px) {
                        .payment-container {
                            padding: 16px;
                        }
                        .payment-header {
                            padding: 20px 24px;
                        }
                        .payment-content {
                            padding: 24px;
                        }
                    }
                    
                    @media (max-width: 480px) {
                        .payment-container {
                            padding: 12px;
                        }
                        .payment-header {
                            padding: 16px 20px;
                        }
                        .payment-content {
                            padding: 20px;
                        }
                    }
                `}
            </style>
        </div>
    );
};

export default DinteroPayment;
