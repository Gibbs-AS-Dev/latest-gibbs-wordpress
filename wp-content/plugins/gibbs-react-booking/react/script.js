(function() {
    const version = Date.now();
    const widgetClass = 'rmp-slot-booking';
    let widgetsInitialized = false;
    let loadedSiteUrl = '';

    // Function to get all widget elements
    function getWidgetElements() {
        return document.querySelectorAll('.' + widgetClass);
    }

    // Function to generate unique widget ID if not exists
    function ensureWidgetId(element, index) {
        if (!element.id) {
            element.id = 'rmp-slot-booking-' + Date.now() + '-' + index;
        }
        return element.id;
    }

    // Function to extract the site URL from the data-url attribute
    function getSiteUrl(widgetElement) {
        return widgetElement ? widgetElement.getAttribute('data-url') : '';
    }

    function appendWidget(widgetId, dataId, siteUrl, hideBorder) {
        var pluginUrl = siteUrl + "/wp-content/plugins/gibbs-react-booking";
        (function() {
            function initSlotBooking() {
                if (typeof window.rmpSlotBookingInit === "function") {
                    window.rmpSlotBookingInit(widgetId, dataId, siteUrl + "/wp-content/plugins/gibbs-react-booking/server/slots/slot-booking-endpoint.php", siteUrl, pluginUrl, "",hideBorder);
                } else {
                    // If function is not available yet, wait a bit and try again
                    setTimeout(initSlotBooking, 100);
                }
            }

            if (document.readyState === "loading") {
                document.addEventListener("DOMContentLoaded", initSlotBooking);
            } else {
                initSlotBooking();
            }
        })();
    }

    // Function to initialize all widgets
    function initializeAllWidgets() {
        const widgetElements = getWidgetElements();
        const siteUrl = loadedSiteUrl;

        if (!siteUrl) {
            console.error('Site URL not found!');
            return;
        }

        widgetElements.forEach(function(widgetElement, index) {
            const widgetId = ensureWidgetId(widgetElement, index);
            const dataId = widgetElement.getAttribute('data-page-id');
            const hideBorder = widgetElement.getAttribute('data-hide-border');
            const widgetSiteUrl = getSiteUrl(widgetElement) || siteUrl;

            console.log(hideBorder);

            if (dataId) {
                appendWidget(widgetId, dataId, widgetSiteUrl, hideBorder);
            }
        });
    }

    // Function to dynamically append the CSS file
    function appendCss(siteUrl) {
        // Check if CSS is already added
        if (document.querySelector('link[href*="components.css"]')) {
            return;
        }

        const cssLink = document.createElement('link');
        cssLink.rel = 'stylesheet';
        cssLink.type = 'text/css';
        cssLink.href = `${siteUrl}/wp-content/plugins/gibbs-react-booking/assets/css/components.css?v=${version}`;
        document.head.appendChild(cssLink);
    }

    // Append React and ReactDOM dynamically if not already loaded
    function loadReact() {
        const widgetElements = getWidgetElements();
        
        if (widgetElements.length === 0) {
            console.warn('No widget elements found with class: ' + widgetClass);
            return;
        }

        // Get site URL from first widget
        const firstWidget = widgetElements[0];
        const siteUrl = getSiteUrl(firstWidget);
        
        if (!siteUrl) {
            console.error('Site URL not found!');
            return;
        }

        loadedSiteUrl = siteUrl;

        // Append CSS before loading the React scripts (only once)
        appendCss(siteUrl);

        if (typeof React === 'undefined' || typeof ReactDOM === 'undefined') {
            // Check if React scripts are already being loaded
            if (document.querySelector('script[src*="react.production.min.js"]')) {
                // Scripts are already loading, wait for them
                const checkReady = setInterval(function() {
                    if (typeof React !== 'undefined' && typeof ReactDOM !== 'undefined') {
                        clearInterval(checkReady);
                        loadWidgetScript();
                    }
                }, 100);
                return;
            }

            // Create React script element
            const reactScript = document.createElement('script');
            reactScript.src =  `${siteUrl}/wp-content/plugins/gibbs-react-booking/react/react.production.min.js?v=${version}`;
            
            // Create ReactDOM script element
            const reactDomScript = document.createElement('script');
            reactDomScript.src = `${siteUrl}/wp-content/plugins/gibbs-react-booking/react/react-dom.production.min.js?v=${version}`;
            
            // Append the scripts to the head
            document.head.appendChild(reactScript);
            document.head.appendChild(reactDomScript);

            // Add onload callback to ensure both React and ReactDOM are loaded
            reactScript.onload = reactDomScript.onload = function() {
                loadWidgetScript();
            };

            // Add error handling in case the scripts fail to load
            reactScript.onerror = reactDomScript.onerror = function() {
                console.error('Error loading React or ReactDOM scripts.');
            };
        } else {
            // If React and ReactDOM are already loaded, load the widget script directly
            loadWidgetScript();
        }
    }

    // Function to load widget script
    function loadWidgetScript() {
        const siteUrl = loadedSiteUrl;
        
        // Check if components.js is already loaded
        if (document.querySelector('script[src*="components.js"]')) {
            initializeAllWidgets();
            return;
        }

        // Load widget script
        const widgetScript = document.createElement('script');
        widgetScript.src = `${siteUrl}/wp-content/plugins/gibbs-react-booking/assets/js/components.js?v=${version}`;
        widgetScript.onload = function() {
            initializeAllWidgets();
        };
        document.head.appendChild(widgetScript);
    }

    // Initialize on load
    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", loadReact);
    } else {
        loadReact();
    }
})();
