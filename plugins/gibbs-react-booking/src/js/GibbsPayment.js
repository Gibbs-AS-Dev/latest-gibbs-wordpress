import React, { useEffect, useRef, useState } from 'react';
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

const GibbsPayment = ({ 
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

    useEffect(() => {
        const initializeCheckout = async () => {
            if (containerRef.current && sid) {
                try {
                    setIsLoading(true);
                    setError(null);
                    
                    const checkout = await embed({
                        container: containerRef.current,
                        sid: sid,
                        popOut: popOut,
                        language: language,
                        onSession: (event) => {
                            console.log("session", event.session);
                            if (event.type === 'session_loaded' && onSessionLoaded) {
                                onSessionLoaded(event);
                            } else if (event.type === 'session_updated' && onSessionUpdated) {
                                onSessionUpdated(event);
                            }
                        },
                        onPayment: (event, checkout) => {
                            console.log("transaction_id", event.transaction_id);
                            console.log("href", event.href);
                            if (onPaymentComplete) {
                                onPaymentComplete(event);
                            }
                            checkout.destroy();
                        },
                        onPaymentError: (event, checkout) => {
                            console.log("href", event.href);
                            if (onPaymentError) {
                                onPaymentError(event);
                            }
                            checkout.destroy();
                        },
                        onSessionCancel: (event, checkout) => {
                            console.log("href", event.href);
                            checkout.destroy();
                        },
                        onSessionNotFound: (event, checkout) => {
                            console.log("session not found (expired)", event.type);
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
                            console.log("validating session", event.session);
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
                    setIsLoading(false);
                    if (onPaymentError) {
                        onPaymentError(error);
                    }
                }
            }
        };

        initializeCheckout();

        // Cleanup function
        return () => {
            if (checkoutRef.current && typeof checkoutRef.current.destroy === 'function') {
                checkoutRef.current.destroy();
            }
        };
    }, [sid, language, popOut, onPaymentComplete, onPaymentError, onSessionLoaded, onSessionUpdated, onSessionCancel, onSessionNotFound, onSessionLocked, onSessionLockFailed, onActivePaymentType, onValidateSession]);

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
        backgroundColor: '#f8fafc'
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

    if (error) {
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
                                    Loading Payment System
                                </h3>
                                <p style={{ margin: '0', fontSize: '14px', color: '#64748b' }}>
                                    Please wait while we securely connect to our payment provider...
                                </p>
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

export default GibbsPayment;
