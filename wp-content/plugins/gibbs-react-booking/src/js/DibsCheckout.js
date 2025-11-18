import React, { useState, useEffect, useRef } from 'react';
import axios from 'axios';
import { Ltext, getLanguage } from './utils/translations';

const DibsCheckout = ({ 
    paymentId, 
    checkoutKey, 
    mode, 
    apiUrl, 
    cr_user_id, 
    pluginUrl, 
    homeUrl, 
    onBack, 
    onSuccess, 
    onError,
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
    const [currentHeight, setCurrentHeight] = useState(800);

    // Determine SDK URL based on mode
    const SDK_URL = mode === 'live' 
        ? "https://checkout.dibspayment.eu/v1/checkout.js?v=1"
        : "https://test.checkout.dibspayment.eu/v1/checkout.js?v=1";

    function loadScript(src) {
        return new Promise((resolve, reject) => {
            const s = document.createElement("script");
            s.src = src; 
            s.async = true;
            s.onload = resolve; 
            s.onerror = reject;
            document.head.appendChild(s);
        });
    }

    // Function to detect and send height updates
    const sendHeightUpdate = () => {
        if (containerRef.current) {
            const height = containerRef.current.scrollHeight;
            if (height !== currentHeight && height > 0) {
                setCurrentHeight(height);
                
                // Send height update to parent iframe
                if (window.parent && window.parent !== window) {
                    window.parent.postMessage({
                        action: 'resize',
                        height: height,
                        source: 'dibs-checkout'
                    }, '*');
                }
            }
        }
    };

    const initializeCheckout = async () => {
        if (containerRef.current && paymentId && checkoutKey) {
            try {
                setIsLoading(true);
                setError(null);
                setPaymentStatus(null);
                setStatusMessage('');
                setShowErrorOverlay(false);
                
                // Load DIBS SDK
                await loadScript(SDK_URL);

                // Create DIBS checkout instance
                const checkout = new window.Dibs.Checkout({
                    checkoutKey: checkoutKey,
                    paymentId: paymentId,
                    containerId: "dibs-checkout-container",
                    language: getLanguage() || "nb-NO"
                });

                checkoutRef.current = checkout;

                // Set up event listeners
                checkout.on("payment-completed", (response) => {
                    console.log("Payment completed:", response);
                    setPaymentStatus('success');
                    setStatusMessage('Payment completed successfully!');
                    setShowErrorOverlay(false);
                    setPaymentCompleted(true);

                    // Send height update after status change
                    setTimeout(() => sendHeightUpdate(), 100);

                    // Send message to parent iframe
                    if (window.parent && window.parent !== window) {
                        window.parent.postMessage({
                            action: 'payment-completed',
                            paymentId: paymentId,
                            response: response,
                            status: 'success',
                            message: 'Payment completed successfully!'
                        }, '*');
                    }

                    // Update payment status on server
                    //updatePaymentStatus('completed', response);
                    
                    // Call success callback
                    if (onSuccess) {
                        onSuccess(response);
                    }
                });

                checkout.on("payment-cancelled", (response) => {
                    console.log("Payment cancelled:", response);
                    setPaymentStatus('cancelled');
                    setStatusMessage('Payment was cancelled. You can try again.');
                    setShowErrorOverlay(true);

                    // Send height update after status change
                    setTimeout(() => sendHeightUpdate(), 100);

                    // Send message to parent iframe
                    if (window.parent && window.parent !== window) {
                        window.parent.postMessage({
                            action: 'payment-cancelled',
                            paymentId: paymentId,
                            response: response,
                            status: 'cancelled',
                            message: 'Payment was cancelled. You can try again.'
                        }, '*');
                    }

                    if (onError) {
                        onError('Payment was cancelled');
                    }
                });

                checkout.on("payment-failed", (response) => {
                    console.log("Payment failed:", response);
                    setPaymentStatus('failed');
                    setStatusMessage(getErrorMessage(response));
                    setShowErrorOverlay(true);

                    // Send height update after status change
                    setTimeout(() => sendHeightUpdate(), 100);

                    // Send message to parent iframe
                    if (window.parent && window.parent !== window) {
                        window.parent.postMessage({
                            action: 'payment-failed',
                            paymentId: paymentId,
                            response: response,
                            status: 'failed',
                            message: getErrorMessage(response)
                        }, '*');
                    }

                    if (onError) {
                        onError('Payment failed');
                    }
                });

                setIsLoading(false);
                
                // Start monitoring height changes after checkout is loaded
                setTimeout(() => {
                    sendHeightUpdate();
                    
                    // Set up periodic height checking
                    const heightInterval = setInterval(() => {
                        sendHeightUpdate();
                    }, 1000);
                    
                    // Store interval reference for cleanup
                    checkoutRef.current.heightInterval = heightInterval;
                }, 500);

            } catch (error) {
                console.error('Error initializing DIBS payment:', error);
                setError(error);
                setPaymentStatus('failed');
                setStatusMessage('Failed to initialize payment system. Please try again.');
                setShowErrorOverlay(true);
                setIsLoading(false);
                if (onError) {
                    onError(error);
                }
            }
        }
    };

    // const updatePaymentStatus = async (status, paymentData) => {
    //     try {
    //         await axios.post(apiUrl, {
    //             action: 'updateDibsPaymentStatus',
    //             payment_id: paymentId,
    //             status: status,
    //             payment_data: paymentData
    //         });
    //     } catch (err) {
    //         console.error('Error updating payment status:', err);
    //     }
    // };

    // Helper function to get user-friendly error messages
    const getErrorMessage = (event) => {
        if (event && event.error) {
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

    useEffect(() => {
        initializeCheckout();

        // Cleanup function
        return () => {
            if (checkoutRef.current) {
                if (typeof checkoutRef.current.cleanup === 'function') {
                    checkoutRef.current.cleanup();
                }
                if (checkoutRef.current.heightInterval) {
                    clearInterval(checkoutRef.current.heightInterval);
                }
            }
        };
    }, [paymentId, checkoutKey, mode]);

    const handleRetry = () => {
        setShowErrorOverlay(false);
        setPaymentStatus(null);
        setStatusMessage('');
        setError(null);
        initializeCheckout();
    };

    const handleBack = () => {
        if (onBack) {
            onBack();
        } else {
            window.history.back();
        }
    };

    // Container styles matching DinteroPayment
    const containerStyles = {
        maxWidth: '800px',
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
        background: 'linear-gradient(135deg, #1a9a94 0%, #158a84 100%)',
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
        borderTop: '4px solid #1a9a94',
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

    // Show success status
    if (paymentStatus === 'success' || paymentStatus === 'processing') {
        const statusDisplay = getStatusDisplay();
        return (
            <div style={containerStyles}>
                <div style={cardStyles}>
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
                            <button 
                                onClick={handleRetry}
                                style={{ 
                                    marginTop: '20px', 
                                    padding: '10px 20px', 
                                    background: '#1a9a94', 
                                    color: 'white', 
                                    border: 'none', 
                                    borderRadius: '5px', 
                                    cursor: 'pointer' 
                                }}
                            >
                                Try Again
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        );
    }

    return (
        <div style={containerStyles}>
            <div style={cardStyles}>
                {/* <div style={headerStyles}>
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
                </div> */}
                
                <div style={contentStyles}>
                    <div 
                        id="dibs-checkout-container" 
                        ref={containerRef}
                        style={{
                            width: '100%',
                            minHeight: '300px',
                            height: `${currentHeight}px`,
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
                                    <div style={{ marginTop: '20px', display: 'flex', gap: '10px', justifyContent: 'center' }}>
                                        <button 
                                            onClick={handleRetry} 
                                            style={{ 
                                                padding: '10px 20px', 
                                                background: '#1a9a94', 
                                                color: 'white', 
                                                border: 'none', 
                                                borderRadius: '5px', 
                                                cursor: 'pointer' 
                                            }}
                                        >
                                            Try Again
                                        </button>
                                        <button 
                                            onClick={handleBack} 
                                            style={{ 
                                                padding: '10px 20px', 
                                                background: '#6b7280', 
                                                color: 'white', 
                                                border: 'none', 
                                                borderRadius: '5px', 
                                                cursor: 'pointer' 
                                            }}
                                        >
                                            Go Back
                                        </button>
                                    </div>
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

export default DibsCheckout;