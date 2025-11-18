import React, { useState, useEffect, useRef } from 'react';
import { Ltext } from './utils/translations';
import styles from './assets/scss/IframePage.module.scss';

/**
 * IframePage Component
 * A React component for handling iframe content with loading states and error handling
 */
const IframePage = ({ 
    src, 
    title = "Loading...", 
    width = "100%", 
    height = "auto", 
    onLoad = null, 
    onError = null,
    showLoader = true,
    loaderText = "Loading content...",
    className = "",
    allowTransparency = true,
    frameBorder = "0",
    sandbox = null,
    allow = null,
    autoResize = true,
    minHeight = "400px",
    maxHeight = "none",
    setShowTimerBanner = null,
    handleContinueBooking = null,
    thankYouPage = null
}) => {
    const [isLoading, setIsLoading] = useState(showLoader);
    const [hasError, setHasError] = useState(false);
    const [errorMessage, setErrorMessage] = useState("");
    const [iframeHeight, setIframeHeight] = useState("800px");
    const [paymentSuccess, setPaymentSuccess] = useState(false);
    const [paymentData, setPaymentData] = useState(null);
    const iframeRef = useRef(null);
    const timeoutRef = useRef(null);
    const resizeObserverRef = useRef(null);

    // Calculate iframe content height
    const calculateContentHeight = () => {
        if (!iframeRef.current || !autoResize) return;
        
        try {
            const iframe = iframeRef.current;
            const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
            
            if (iframeDoc) {
                const body = iframeDoc.body;
                const html = iframeDoc.documentElement;
                
                if (body && html) {
                    const height = Math.max(
                        body.scrollHeight,
                        body.offsetHeight,
                        html.clientHeight,
                        html.scrollHeight,
                        html.offsetHeight
                    );
                    
                    // Apply min/max height constraints
                    let finalHeight = height;
                    if (minHeight && typeof minHeight === 'string' && minHeight.includes('px')) {
                        const minHeightPx = parseInt(minHeight);
                        finalHeight = Math.max(finalHeight, minHeightPx);
                    }
                    if (maxHeight && typeof maxHeight === 'string' && maxHeight.includes('px')) {
                        const maxHeightPx = parseInt(maxHeight);
                        finalHeight = Math.min(finalHeight, maxHeightPx);
                    }
                    
                    setIframeHeight(`${finalHeight}px`);
                }
            }
        } catch (error) {
            console.warn('Cannot access iframe content for height calculation:', error);
        }
    };

    // Handle iframe load event
    const handleLoad = () => {
        setIsLoading(false);
        setHasError(false);
        
        // Calculate initial height after load
        // if (autoResize && height === "auto") {
        //     setTimeout(() => {
        //         calculateContentHeight();
        //     }, 100);
        // }
        
        if (onLoad) {
            onLoad();
        }
        
        // Clear any existing timeout
        if (timeoutRef.current) {
            clearTimeout(timeoutRef.current);
        }
    };

    // Handle iframe error event
    const handleError = () => {
        setIsLoading(false);
        setHasError(true);
        setErrorMessage("Failed to load content");
        if (onError) {
            onError("Failed to load iframe content");
        }
    };

    // Set up timeout for loading
    useEffect(() => {
        if (showLoader && isLoading) {
            // Set a timeout to show error if iframe doesn't load within 30 seconds
            timeoutRef.current = setTimeout(() => {
                setIsLoading(false);
                setHasError(true);
                setErrorMessage("Content took too long to load");
            }, 30000);
        }

        return () => {
            if (timeoutRef.current) {
                clearTimeout(timeoutRef.current);
            }
        };
    }, [showLoader, isLoading]);

    // Listen for messages from iframe
    useEffect(() => {
        const handleMessage = (event) => {
            // Handle different message types from iframe
            if (event.data && typeof event.data === 'object') {
                switch (event.data.action) {
                    case 'redirect':
                        if (event.data.url) {
                            window.location.href = event.data.url;
                        }
                        break;
                    case 'resize':
                        if (event.data.height && iframeRef.current) {
                            const newHeight = event.data.height;
                            // Apply min/max height constraints
                            let finalHeight = newHeight;
                            if (minHeight && typeof minHeight === 'string' && minHeight.includes('px')) {
                                const minHeightPx = parseInt(minHeight);
                                finalHeight = Math.max(finalHeight, minHeightPx);
                            }
                            if (maxHeight && typeof maxHeight === 'string' && maxHeight.includes('px')) {
                                const maxHeightPx = parseInt(maxHeight);
                                finalHeight = Math.min(finalHeight, maxHeightPx);
                            }
                            
                            // Special handling for DibsCheckout resize messages
                            if (event.data.source === 'dibs-checkout') {
                                console.log('DibsCheckout height update:', finalHeight);
                                // Add some padding for DibsCheckout content
                                finalHeight = Math.max(finalHeight + 50, 400);
                            }

                            finalHeight = finalHeight + 50;
                            
                            setIframeHeight(`${finalHeight}px`);
                        }
                        break;
                    case 'close':
                        // Handle iframe close action
                        if (event.data.url) {
                            window.location.href = event.data.url;
                        }
                        break;
                    case 'error':
                        setHasError(true);
                        setErrorMessage(event.data.message || "An error occurred");
                        break;
                    case 'payment-completed':
                        
                        if (thankYouPage && thankYouPage != "") {
                            window.location.href = thankYouPage;
                        }else{
                            setPaymentSuccess(true);
                            setPaymentData(event.data);
                            setIsLoading(false);
                            setHasError(false);
                            if (setShowTimerBanner) {
                                setShowTimerBanner(false);
                            }
                        }
                        break;
                    case 'payment-cancelled':
                    case 'payment-failed':
                        setHasError(true);
                        setErrorMessage(event.data.message || "Payment failed");
                        setIsLoading(false);
                        break;
                    default:
                        console.log('Unknown message from iframe:', event.data);
                }
            }
        };

        window.addEventListener('message', handleMessage);
        return () => window.removeEventListener('message', handleMessage);
    }, [minHeight, maxHeight]);

    // Set up periodic height checking for auto-resize
    useEffect(() => {
        if (autoResize && height === "auto") {
            const interval = setInterval(() => {
                //calculateContentHeight();
            }, 1000); // Check every second

            return () => clearInterval(interval);
        }
    }, [autoResize, height]);

    // Handle initial height setup for DibsCheckout
    // useEffect(() => {
    //     if (src && src.includes('dibs-checkout')) {
    //         // Set initial height for DibsCheckout
    //         setIframeHeight('800px');
    //     }
    // }, [src]);

    // Handle retry
    const handleRetry = () => {
        setHasError(false);
        setIsLoading(true);
        setErrorMessage("");
        
        // Force iframe reload
        if (iframeRef.current) {
            iframeRef.current.src = iframeRef.current.src;
        }
    };

    // Thank You Module Component
    const ThankYouModule = () => (
        <div className={styles.thankYouOverlay}>
            <div className={styles.thankYouCard}>
                {/* Success Animation */}
                <div className={styles.successAnimation}>
                    <div className={styles.checkmark}>
                        <svg width="80" height="80" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="12" cy="12" r="10" stroke="#059669" strokeWidth="2" fill="rgba(5, 150, 105, 0.1)"/>
                            <path d="M9 12L11 14L15 10" stroke="#059669" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round"/>
                        </svg>
                    </div>
                </div>
                
                {/* Thank You Title */}
                <h2 className={styles.thankYouTitle}>
                    {Ltext("Payment Successful!")}
                </h2>
                
                {/* Thank You Message */}
                <p className={styles.thankYouMessage}>
                    {Ltext("Thank you for your payment. Check your email for receipt.")}
                </p>
                
                {/* Payment Details */}
                {/* {paymentData && (
                    <div className={styles.paymentDetails}>
                        <div className={styles.paymentDetailItem}>
                            <span className={styles.paymentDetailLabel}>{Ltext("Payment ID")}:</span>
                            <span className={styles.paymentDetailValue}>{paymentData.paymentId}</span>
                        </div>
                        <div className={styles.paymentDetailItem}>
                            <span className={styles.paymentDetailLabel}>{Ltext("Status")}:</span>
                            <span className={styles.paymentDetailValue}>{Ltext("Completed")}</span>
                        </div>
                    </div>
                )} */}
                
                {/* Action Buttons */}
                {/* <div className={styles.thankYouActions}>
                    <button 
                        className={styles.continueButton}
                        onClick={() => {
                            handleContinueBooking();
                        }}
                    >
                        {Ltext("Continue")}
                    </button>
                </div> */}
                
                {/* Security Badge */}
                {/* <div className={styles.securityBadge}>
                    <span className={styles.securityIcon}>🔒</span>
                    <span className={styles.securityText}>{Ltext("Secure Payment Processed")}</span>
                </div> */}
            </div>
        </div>
    );

    return (
        <div className={`${styles.container} ${className}`} style={{ width, height }}>
            {/* Loading Overlay */}
            {isLoading && (
                <div className={styles.loaderOverlay}>
                    <div className={styles.loader}>
                        {/* Modern Spinner */}
                        <div className={styles.spinner}></div>
                        
                        {/* Loading Text */}
                        <p className={styles.loaderText}>
                            {loaderText}
                        </p>
                        
                        {/* Loading Dots Animation */}
                        <div className={styles.loadingDots}>
                            <div className={styles.dot}></div>
                            <div className={styles.dot}></div>
                            <div className={styles.dot}></div>
                        </div>
                    </div>
                </div>
            )}

            {/* Payment Success State */}
            {paymentSuccess && <ThankYouModule />}

            {/* Error State */}
            {hasError && !paymentSuccess && (
                <div className={styles.errorOverlay}>
                    <div className={styles.error}>
                        {/* Enhanced Error Icon */}
                        <div className={styles.errorIcon}>
                            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="12" cy="12" r="10" stroke="#e74c3c" strokeWidth="2" fill="rgba(231, 76, 60, 0.1)"/>
                                <path d="M15 9L9 15M9 9L15 15" stroke="#e74c3c" strokeWidth="2" strokeLinecap="round"/>
                                <circle cx="12" cy="12" r="2" fill="#e74c3c"/>
                            </svg>
                        </div>
                        
                        {/* Error Title */}
                        <h3 className={styles.errorTitle}>
                            {Ltext("Error Loading Content")}
                        </h3>
                        
                        {/* Error Message */}
                        <p className={styles.errorMessage}>
                            {errorMessage}
                        </p>
                        
                        {/* Retry Button */}
                        <button 
                            className={styles.retryButton}
                            onClick={handleRetry}
                        >
                            {Ltext("Retry")}
                        </button>
                    </div>
                </div>
            )}

            {/* Iframe */}
            <iframe
                ref={iframeRef}
                src={src}
                title={title}
                width={width}
                height={iframeHeight}
                frameBorder={frameBorder}
                allowTransparency={allowTransparency}
                sandbox={sandbox}
                allow={allow}
                onLoad={handleLoad}
                onError={handleError}
                className={styles.iframe}
                style={{
                    display: (hasError || paymentSuccess) ? 'none' : 'block',
                    height: iframeHeight,
                    backgroundColor: "#fff"
                }}
            />
        </div>
    );
};

/**
 * IframePageManager - Higher order component for managing iframe pages
 */
export const IframePageManager = ({ 
    children, 
    onPageChange = null,
    currentPage = null 
}) => {
    const [pageHistory, setPageHistory] = useState([]);
    const [currentPageState, setCurrentPageState] = useState(currentPage);

    const navigateToPage = (pageData) => {
        setPageHistory(prev => [...prev, currentPageState]);
        setCurrentPageState(pageData);
        if (onPageChange) {
            onPageChange(pageData);
        }
    };

    const goBack = () => {
        if (pageHistory.length > 0) {
            const previousPage = pageHistory[pageHistory.length - 1];
            setPageHistory(prev => prev.slice(0, -1));
            setCurrentPageState(previousPage);
            if (onPageChange) {
                onPageChange(previousPage);
            }
        }
    };

    return (
        <div className={styles.pageManager}>
            {children({
                currentPage: currentPageState,
                navigateToPage,
                goBack,
                canGoBack: pageHistory.length > 0
            })}
        </div>
    );
};

export default IframePage;
