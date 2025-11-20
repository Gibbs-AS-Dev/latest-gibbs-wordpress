import React, { useRef, useState, useEffect, useLayoutEffect, useCallback } from 'react';
import axios from 'axios';
import PhoneInput from 'react-phone-input-2'
import 'react-phone-input-2/lib/style.css'
import { Ltext, getLanguage } from './utils/translations';
import styles from './assets/scss/SlotBookingConfirmation.module.scss';
import './assets/scss/SlotBookingConfirmation.scss';

// ============================================================================
// SLOT BOOKING CONFIRMATION COMPONENT
// ============================================================================

function SlotBookingConfirmation({ userLoggedIn, bookingToken, apiUrl, cr_user_id, setCrUserId, pluginUrl, homeUrl, prevBookingData, handleBackToBooking, handleDinteroPayment, handleNetsEasyPayment, handleWaitingPayment}) {
  const [bookingData, setBookingData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [contactType, setContactType] = useState('personal');
  const [contactInfo, setContactInfo] = useState();
  const [listingTitle, setListingTitle] = useState('');
  const [listingImage, setListingImage] = useState('');
  const [message, setMessage] = useState('');
  const [termsAccepted, setTermsAccepted] = useState(false);
  const [userDataLoading, setUserDataLoading] = useState(true);

  const [phoneNumber, setPhoneNumber] = useState('');
  const [countryCode, setCountryCode] = useState('+47');

  const [email, setEmail] = useState('');

  const [firstName, setFirstName] = useState('');
  const [lastName, setLastName] = useState('');

  const [companyNumber, setCompanyNumber] = useState('');
  const [billingCity, setBillingCity] = useState('');
  const [billingPostcode, setBillingPostcode] = useState('');
  const [billingAddress1, setBillingAddress1] = useState(''); 
  const [billingCountry, setBillingCountry] = useState('no');

  const [postMeta, setPostMeta] = useState({});
  const [pdfMeta, setPdfMeta] = useState(null);

  // Add state for email loading
  const [emailLoading, setEmailLoading] = useState(false);
  
  // Add state for form submission
  const [submitting, setSubmitting] = useState(false);
  const [paymentMethods, setPaymentMethods] = useState([]);
  const [paymentMethodsLoading, setPaymentMethodsLoading] = useState(false);
  
  // Add state for selected payment method
  const [selectedPaymentMethod, setSelectedPaymentMethod] = useState('');
  
  // Add state for validation errors
  const [validationErrors, setValidationErrors] = useState({});

  const [hideBookingMessage, setHideBookingMessage] = useState(false);
  const [listingMetaLoading, setListingMetaLoading] = useState(false);
  
  // Add state for initial email/phone lookup
  const [initialEmailPhone, setInitialEmailPhone] = useState('');
  const [showInitialForm, setShowInitialForm] = useState(false);
  const [initialFormSubmitted, setInitialFormSubmitted] = useState(false);
  const [initialFormLoading, setInitialFormLoading] = useState(false);


  const [isEditForm, setIsEditForm] = useState(false);
  const [initialUserView, setInitialUserView] = useState(false);

  const [previousBookingId, setPreviousBookingId] = useState(null);

  const confirmationContainerRef = useRef(null);
  const [confirmationContainerWidth, setConfirmationContainerWidth] = useState(0);

  const [socialLoginLoading, setSocialLoginLoading] = useState(false);

  const [popup, setPopup] = useState(null);
  const [popupProvider, setPopupProvider] = useState(null);

  useLayoutEffect(() => {
    if(!confirmationContainerRef.current){
      return;
    }

    const container = confirmationContainerRef.current;
    
    // Set initial width
    const updateWidth = () => {
      if(container){
        const width = container.offsetWidth;
        setConfirmationContainerWidth(width);
      }
    };

    // Initial width measurement
    updateWidth();

    // Set up ResizeObserver to track width changes
    const resizeObserver = new ResizeObserver((entries) => {
      for (let entry of entries) {
        const width = entry.contentRect.width;
        setConfirmationContainerWidth(width);
      }
    });

    resizeObserver.observe(container);

    // Cleanup
    return () => {
      resizeObserver.disconnect();
    };
  }, [bookingData]);

  useEffect(() => {
    if (contactInfo?.user_email) {

      //setCrUserId(contactInfo.ID);

      //console.log(contactInfo);
      const phonenumberr = contactInfo.phone?contactInfo.phone.replace(contactInfo.country_code, ""):"";
      setPhoneNumber(phonenumberr);
      setCountryCode(contactInfo.country_code || "+47");

      if(contactInfo.user_email){
        setEmail(contactInfo.user_email);
      }
      if(contactInfo.profile_type !== ""){
        setContactType(contactInfo.profile_type);
      }
      if(contactInfo.first_name){
        setFirstName(contactInfo.first_name);
      }
      if(contactInfo.last_name){
        setLastName(contactInfo.last_name);
      }
      if(contactInfo.company_number){
        setCompanyNumber(contactInfo.company_number);
      }
      if(contactInfo.billing_city){
        setBillingCity(contactInfo.billing_city);
      }
      if(contactInfo.billing_postcode){
        setBillingPostcode(contactInfo.billing_postcode);
      } 
      if(contactInfo.billing_address_1){
        setBillingAddress1(contactInfo.billing_address_1);
      }
      if(contactInfo.billing_country){
        setBillingCountry(contactInfo.billing_country);
      }
      if(postMeta?._manual_invoice_payment && postMeta?._manual_invoice_payment == "dont_show_invoice"){
        if(contactInfo.billing_postcode == ""){
           setBillingPostcode("0000");
        }
        if(contactInfo.billing_city == ""){
          setBillingCity("Ikke valgt");
        }
        if(contactInfo.billing_address_1 == ""){
          setBillingAddress1("Ikke valgt");
        }
      }
    }
  }, [contactInfo, postMeta]);

  // Add function to fetch user data by email or phone
  const fetchUserDataByEmailOrPhone = async (input) => {
    if (!input || input.trim() === '') {
      return;
    }

    if(cr_user_id && cr_user_id !== ''){
      return true;
    }

    try {
      setInitialFormLoading(true);
      const data = {
        action: 'fetch_user_data_by_email_or_phone',
        input: input
      };
      
      const response = await axios.post(`${apiUrl}`, data);
      
      if (response.data.success && response.data.data && response.data.data.user_email) {
        const userData = response.data.data;

        if(!userLoggedIn && userData.ID){
          setCrUserId(userData.ID);
        }
        // Auto-fill fields with user data
        if (userData.user_email) {
          setEmail(userData.user_email);
        }
        if (userData.first_name) {
          setFirstName(userData.first_name);
        }
        if (userData.last_name) {
          setLastName(userData.last_name);
        }
        const phonenumberr = userData.phone?userData.phone.replace(userData.country_code, ""):"";
        setPhoneNumber(phonenumberr);
        setCountryCode(userData.country_code || "+47");

        if (userData.company_number) {
          setCompanyNumber(userData.company_number);
        }
        if (userData.billing_city) {
          setBillingCity(userData.billing_city);
        }
        if (userData.billing_postcode) {
          setBillingPostcode(userData.billing_postcode);
        }
        if (userData.billing_address_1) {
          setBillingAddress1(userData.billing_address_1);
        }
        if (userData.billing_country) {
          setBillingCountry(userData.billing_country);
        }
        if (userData.profile_type) {
          setContactType(userData.profile_type);
        }
        setValidationErrors({});
        return true; // Success
      }else{

        setEmail(initialEmailPhone);
        // If no user found, check if input is phone number or email
        // const isPhoneNumber = /^\+?\d+$/.test(initialEmailPhone.replace(/\s/g, ''));
        
        // if (isPhoneNumber) {
        //   // Input is phone number - set phone field
        //   setPhoneNumber(initialEmailPhone);
        // } else {
        //   // Input is email - set email field
        //   setEmail(initialEmailPhone);
        // }
      }
      return false; // No user found
    } catch (err) {
      console.error('Error fetching user data by email/phone:', err);
      return false;
    } finally {
      setInitialFormLoading(false);
    }
  };

  // Add function to fetch user data by email (existing functionality)
  const fetchUserDataByEmail = async (email) => {
    if (!email || email.trim() === '') {
      return;
    }

    try {
      setEmailLoading(true);
      const data = {
        action: 'email_user_data',
        email: email
      };
      
      const response = await axios.post(`${apiUrl}`, data);
      
      if (response.data.success && response.data.data && response.data.data.user_email) {
        const userData = response.data.data;

        if(!userLoggedIn && userData.ID){
          setCrUserId(userData.ID);
        }
        // Auto-fill fields with user data
        if (userData.first_name) {
          setFirstName(userData.first_name);
        }
        if (userData.last_name) {
          setLastName(userData.last_name);
        }
        const phonenumberr = userData.phone?userData.phone.replace(userData.country_code, ""):"";
        setPhoneNumber(phonenumberr);
        setCountryCode(userData.country_code || "+47");

        if (userData.company_number) {
          setCompanyNumber(userData.company_number);
        }
        if (userData.billing_city) {
          setBillingCity(userData.billing_city);
        }
        if (userData.billing_postcode) {
          setBillingPostcode(userData.billing_postcode);
        }
        if (userData.billing_address_1) {
          setBillingAddress1(userData.billing_address_1);
        }
        if (userData.billing_country) {
          setBillingCountry(userData.billing_country);
        }
        if (userData.profile_type) {
          setContactType(userData.profile_type);
        }
        setValidationErrors({});
      }else{
        if(!userLoggedIn){
          setContactInfo(null);
          setEmail(email);
          setInitialEmailPhone(email);
          setCrUserId(null);
        }
      }
    } catch (err) {
      console.error('Error fetching user data by email:', err);
    } finally {
      setEmailLoading(false);
    }
  };

  // Handle email change
  const handleEmailChange = useCallback((e) => {
    const newEmail = e.target.value;
    setEmail(newEmail);
    // Clear email error when user starts typing
    if (validationErrors.email) {
      setValidationErrors(prev => {
        const newErrors = { ...prev };
        delete newErrors.email;
        return newErrors;
      });
    }
  }, [validationErrors.email]);

  // Handle email blur (when user clicks outside)
  const handleEmailBlur = useCallback((e) => {
    const email = e.target.value;
    
    // Only fetch if email is not empty
    if (email && email.trim() !== '') {
      fetchUserDataByEmail(email);
    }
  }, []);

  // Handle initial email/phone form submission
  const handleInitialFormSubmit = async (e) => {
    e.preventDefault();
    
    if (!initialEmailPhone || initialEmailPhone.trim() === '') {
      return;
    }

    const success = await fetchUserDataByEmailOrPhone(initialEmailPhone);
    if (success) {
      setInitialUserView(true);
      setIsEditForm(false);
      //setEmail(initialEmailPhone);
      setInitialFormSubmitted(true);
      setShowInitialForm(false);
    } else {
      setInitialUserView(false);
      setIsEditForm(true);
      // If no user found, still proceed but show the form
     // setEmail(initialEmailPhone);
      setInitialFormSubmitted(true);
      setShowInitialForm(false);
    }
  };

  // Handle social login
  const handleSocialLogin = async (provider) => {
    console.log(`Social login with ${provider}`);
    setSocialLoginLoading(true);
  
    // IMPORTANT: Open popup synchronously BEFORE any async calls
    const width = 500;
    const height = 600;
    const popup = window.open("", "socialLogin", `width=${width},height=${height}`);
  
    if (!popup) {
      console.error("Popup blocked by Safari");
      return;
    }
  
    // Show a styled loading message centered in the popup window
    popup.document.write(`
      <html>
        <head>
          <title>Loading...</title>
          <style>
            html, body {
              height: 100%;
              margin: 0;
              padding: 0;
              background: #fafcff;
            }
            body {
              display: flex;
              align-items: center;
              justify-content: center;
              height: 100vh;
              font-family: "Segoe UI", Arial, sans-serif;
            }
            .loader-container {
              text-align: center;
            }
            .spinner {
              margin: 0 auto 16px auto;
              width: 48px;
              height: 48px;
              border: 5px solid #e0e7ef;
              border-top: 5px solid #1a9a94;
              border-radius: 50%;
              animation: spin 1s linear infinite;
            }
            @keyframes spin {
              0% { transform: rotate(0deg);}
              100% { transform: rotate(360deg);}
            }
            .loading-text {
              font-size: 18px;
              color: #2b4170;
              font-weight: 500;
            }
          </style>
        </head>
        <body>
          <div class="loader-container">
            <div class="spinner"></div>
            <div class="loading-text">Loading...</div>
          </div>
        </body>
      </html>
    `);

    setPopup(popup);
    setPopupProvider(provider);
  
    try {
      const redirectUrl = `${apiUrl}?action=social_login&provider=${provider}&booking_token=${bookingToken}&apiUrl=${apiUrl}`;
  
      // async call AFTER popup is opened
      const response = await axios.get(redirectUrl);
  
      if (response.data.success && response.data.data?.url) {
        const authUrl = response.data.data.url;
        popup.location.href = authUrl;  // 🔥 Safari-friendly
      } else {
        popup.close();
        console.error("Error social login:", response.data.message);
        return;
      }
  
      // Polling logic (same as before)
      const checkPopupResponse = setInterval(() => {
        try {
          if (popup.closed) {
            clearInterval(checkPopupResponse);
            console.log("Popup closed without response.");
            return;
          }
  
          let href;
          try {
            href = popup.location.href; // Only works when same-origin
          } catch (err) {
            return; // Still on Google/Facebook/etc.
          }
  
          const url = new URL(href);
          const code = url.searchParams.get("code");
          const error = url.searchParams.get("error");
  
          if (code) {
            clearInterval(checkPopupResponse);
            popup.close();
            exchangeCodeForToken(code, provider);
          }
          if (error) {
            clearInterval(checkPopupResponse);
            popup.close();
            console.error("Error logging in:", error);
          }
        } catch (err) {}
      }, 2000);
  
    } catch (error) {
      //popup.close();
      console.error("Error exchanging code for token:", error);
    } finally {
      setSocialLoginLoading(false);
    }
  };

  useEffect(() => {
    const listener = (event) => {
      if (event.origin !== window.location.origin) return; // security check
      if (event.data?.error) {
        if(popup){
          console.log('Popup closed due to error:', event.data.error);
          popup.close();
        }
       // exchangeCodeForToken(event.data.code, providerRef.current);
      }
      if(event.data?.code){
        if(popup){
          console.log('Popup closed due to code:', event.data.code);
          popup.close();
          exchangeCodeForToken(event.data.code, popupProvider);
        }
        
      }
    };
  
    window.addEventListener("message", listener);
    return () => window.removeEventListener("message", listener);
  }, [popup, popupProvider]);

  // Exchange authorization code for access token
  const exchangeCodeForToken = async (code, provider) => {
    try {
      setEmailLoading(true);
      console.log('Exchanging code for access token...', { code, provider });

      // Call API to exchange code for access token
      const response = await axios.post(apiUrl, {
        action: 'exchange_social_token',
        code: code,
        provider: provider,
        booking_token: bookingToken,
        apiUrl: apiUrl
      }, {
        headers: {
          'Content-Type': 'application/json'
        }
      });

      console.log('Token exchange response:', response.data);

      if (response.data.success && response.data.data && response.data.data.user_id) {
        const userData = response.data.data;

        setCrUserId(userData.user_id);

        // cr_user_id = response.data.data.user_id;

        // fetchUserListingInfo(bookingToken);

        // setInitialUserView(true);
        // setIsEditForm(false);
        // setShowInitialForm(false);
        // setInitialFormSubmitted(true);
        
        // Populate the form with user data
        // if (userData.email) setEmail(userData.email);
        // if (userData.first_name) setFirstName(userData.first_name);
        // if (userData.last_name) setLastName(userData.last_name);
        // if (userData.phone) setPhoneNumber(userData.phone);
        
        // // Store access token if needed
        // if (userData.access_token) {
        //   console.log('Access token received:', userData.access_token);
        //   // You can store it in state or localStorage if needed
        //   // localStorage.setItem('social_access_token', userData.access_token);
        // }

        // // Show success message
        // console.log('Social login successful!');
        
        // // Hide initial form and show confirmation form
        // setShowInitialForm(false);
        // setInitialFormSubmitted(true);
        // setIsEditForm(true);
      } else {
        console.error('Token exchange failed:', response.data.message || 'Unknown error');
        alert('Social login failed. Please try again or use email/phone.');
      }
    } catch (error) {
      console.error('Error exchanging code for token:', error);
      alert('An error occurred during social login. Please try again.');
    } finally {
      setEmailLoading(false);
    }
  };

  // Handle continue button click
  const handleContinue = () => {
    setShowInitialForm(false);
    setInitialFormSubmitted(true);
  };

  useEffect(() => {
    fetchBookingData(bookingToken);
    fetchUserListingInfo(bookingToken);
  }, [bookingToken, cr_user_id]);

  // Show initial form when cr_user_id is empty
  useEffect(() => {
    if (!cr_user_id || cr_user_id === '') {
      setShowInitialForm(true);
      setInitialUserView(false);
    } else {
      setInitialUserView(true);
      setIsEditForm(false);
      setShowInitialForm(false);
      setInitialFormSubmitted(true);
    }
  }, [cr_user_id]);

  useEffect(() => {
    if(bookingData && bookingData.listing_id){
      fetchPaymentMethods(bookingData.listing_id);
    }
  }, [bookingData]);
  
  // Set default selected payment method when methods are loaded
  useEffect(() => {
    if (!selectedPaymentMethod && Array.isArray(paymentMethods) && paymentMethods.length > 0) {
      const defaultMethod = paymentMethods.find((m) => m.enabled) || paymentMethods[0];
      if (defaultMethod && defaultMethod.id) {
        setSelectedPaymentMethod(defaultMethod.id);
      }
    }
  }, [paymentMethods, selectedPaymentMethod]);

  const fetchPaymentMethods = async (listing_id) => {
    try {
      setPaymentMethodsLoading(true);
      const response = await axios.get(`${apiUrl}?action=getPaymentMethods&listing_id=${listing_id}`);
      if (response.data.success) {
        setPaymentMethods(response.data.data);
      } else {
        setError(Ltext(response.data.message) || Ltext("Failed to load payment methods"));
      }

    } catch (err) {
      setError(Ltext("Error loading payment methods"));
    } finally {
      setPaymentMethodsLoading(false);
    }
  }

  const fetchBookingData = async (bookingToken) => {
    try {
      setLoading(true);
      const data = {
        action: 'getSlotBookingConfirmation',
        bookingToken: bookingToken,
        cr_user_id: cr_user_id
      }
      const response = await axios.post(`${apiUrl}`, data);
      if (response.data.success) {
        setBookingData(response.data.data);

        if(response.data.data.listing_id){
          fetchListingImage(response.data.data.listing_id);
          fetchListingMeta(response.data.data.listing_id);
        }
      } else {
        setError(Ltext(response.data.message) || Ltext("Failed to load booking confirmation"));
      }

    } catch (err) {
      setError(Ltext("Error loading booking confirmation"));
    } finally {
      setLoading(false);
    }
  };

  const fetchUserListingInfo = async (bookingToken) => {
    try {
      setUserDataLoading(true);
      const response = await axios.get(`${apiUrl}?action=UserListingInfo&bookingToken=${bookingToken}&cr_user_id=${cr_user_id}`);
      if (response.data.success) {
        if(response.data.data.listing_title){
          setListingTitle(response.data.data.listing_title);
        }
        if(response.data.data.user_data){
          setContactInfo(response.data.data.user_data);
        }
        if(response.data.data.post_meta){
          setPostMeta(response.data.data.post_meta);
        }
        if(response.data.data.pdf_meta){
          if (response.data.data.pdf_meta && Object.keys(response.data.data.pdf_meta).length > 0) {
            // Filter each entry in pdf_meta and set value in an array
            const filteredPdfMeta = Object.entries(response.data.data.pdf_meta)
              .filter(([key, value]) => value && value.trim() !== '')
              .map(([key, value]) => ({ key, value }));

            setPdfMeta(filteredPdfMeta);
          } else {
            setPdfMeta(null);
          }
        }
      } else {
        setError(response.data.message || Ltext("Failed to load booking confirmation"));
      }

    } catch (err) {
      setError(Ltext("Error loading booking confirmation"));
    } finally {
      setUserDataLoading(false);
    }
  };

  const fetchListingImage = async (listing_id) => {

    try{
      const response = await axios.get(`${apiUrl}?action=getListingImage&listing_id=${listing_id}`);
      if (response.data.success && response.data.data.listing_image) {
        setListingImage(response.data.data.listing_image);
      }
    }catch(err){
      console.error('Error fetching listing image:', err);
    }
  };

  const fetchListingMeta = async (listing_id) => {
    try{
      setListingMetaLoading(true);
      const response = await axios.get(`${apiUrl}?action=getListingMeta&listing_id=${listing_id}`);
      if(response.data.success){
        if(response.data?.data?.hide_booking_message == "hide"){
          setHideBookingMessage(true);
          console.log("hide booking message");
        }else{
          setHideBookingMessage(false);
          console.log("show booking message");
        }
      }
    }catch(err){
      console.error('Error fetching listing meta:', err);
    }finally{
      setListingMetaLoading(false);
    }
  };

  const formatDate = (dateString) => {
    const date = new Date(dateString);
    const currentLang = getLanguage();
    
    
    if (currentLang === 'no' || currentLang === 'nb' || currentLang === 'nn' || currentLang === 'nn-NO' || currentLang === 'nb-NO') {
      return date.toLocaleDateString('nb-NO', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
      });
    } else {
      return date.toLocaleDateString('en-US', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
      });
    }
  };

  const parseBookingSlot = (bookingSlot) => {
    if (!bookingSlot) return { startTime: '', endTime: '' };
    
    const parts = bookingSlot.split('|');
    if (parts.length >= 4) {
      return {
        startTime: parts[1] || '',
        endTime: parts[3] || ''
      };
    }
    return { startTime: '', endTime: '' };
  };

  const formatTime = (timeString) => {
    const currentLang = getLanguage();

    if (currentLang === 'no' || currentLang === 'nb' || currentLang === 'nn' || currentLang === 'nn-NO' || currentLang === 'nb-NO') {
      return timeString; // Keep 24-hour format for Norwegian
    } else {
      // Convert to 12-hour format for English
      const [hours, minutes] = timeString.split(':');
      const hour = parseInt(hours);
      const ampm = hour >= 12 ? 'PM' : 'AM';
      const displayHour = hour % 12 || 12;
      return `${displayHour}:${minutes} ${ampm}`;
    }
  };

  const handleContactTypeChange = (type) => {
    setContactType(type);
    // Clear name-related errors when contact type changes
    if (validationErrors.firstName || validationErrors.lastName || validationErrors.companyNumber) {
      setValidationErrors(prev => {
        const newErrors = { ...prev };
        delete newErrors.firstName;
        delete newErrors.lastName;
        delete newErrors.companyNumber;
        return newErrors;
      });
    }
  };

  const handleInputChange = (field, value) => {
    setContactInfo(prev => ({
      ...prev,
      [field]: value
    }));
  };

  const submitBooking = async (submissionData) => {
    try {
      setSubmitting(true);
      setValidationErrors({}); // Clear any previous errors
      
      const data = {
        action: 'submitSlotBooking',
        previousBookingId: previousBookingId,
        ...submissionData
      };
      
      const response = await axios.post(`${apiUrl}`, data);
      
      if (response.data.success && response.data?.data?.payment_method && response.data?.data?.payment_method == "free") {
        
        window.location.href = response.data.data.redirect_url;

      }else if(response.data.success && response.data?.data?.payment_method && response.data?.data?.payment_method == "dintero" && response.data?.data?.access_token && response.data?.data?.order_id){

        const url = "https://checkout.dintero.com/v1/sessions-profile";

        const accessToken = response.data.data.access_token;
        const sessionData = response.data.data.session;

        const responseDintero = await axios.post(url, sessionData, {
          headers: {
            "Authorization": `Bearer ${accessToken}`,
            "Content-Type": "application/json"
          }
        });
        const dataDintero = responseDintero.data;

        console.log("dataDintero", dataDintero);

        if(dataDintero?.id && dataDintero?.url){

          const savePaymentId = await axios.post(`${apiUrl}`, {
            action: 'savePaymentId',
            order_id: response.data.data.order_id,
            payment_id: dataDintero.id,
            checkout_url: dataDintero.url
          });

          console.log("savePaymentId", savePaymentId);

          if(savePaymentId?.data?.success && savePaymentId?.data?.data?.payment_id){

            handleDinteroPayment(savePaymentId.data.data.payment_id);

          }else{
            setError(Ltext("Dintero payment failed. Please try again."));
            setSubmitting(false);
          }


          //handleDinteroPayment(dataDintero.id);
        }else{
          setError(Ltext("Dintero payment failed. Please try again."));
          setSubmitting(false);
        }

        

        
        //console.log(response.data.data);
        //handleDinteroPayment(response.data.data.id);
        //window.location.href = response.data.data.checkout_url;
      }else if(response.data.success && response.data?.data?.payment_method && response.data?.data?.payment_method == "dibs_easy"){

        //handleDibsEasyPayment(response.data.data.id);

      }else if(response.data.success && response.data?.data?.payment_method && response.data?.data?.payment_method == "cod"){
        //console.log(response.data.data);
        window.location.href = response.data.data.redirect_url;
      }else if(response.data.success && response.data?.data?.payment_method && response.data?.data?.payment_method == "nets_easy"){
        handleNetsEasyPayment(response.data.data.paymentId, response.data.data.token, response.data.data.mode, response.data.data?.thank_you_page || "");
      }else if(response.data.success && response.data?.data?.payment_method && response.data?.data?.payment_method == "waiting"){
        if(handleWaitingPayment){
          handleWaitingPayment();
        } else {
          window.location.reload();
        }
      }else {
        setSubmitting(false);
        // Handle API error
        setValidationErrors({ general: response.data.message || Ltext("Failed to submit booking. Please try again.") });
      }
      console.log("response", response.data);
      
    } catch (err) {

      if(err.response?.data?.errors?.booking_id){
        setPreviousBookingId(err.response?.data?.errors?.booking_id);
      }

      setSubmitting(false);
      console.error('Error submitting booking:', err);
      setValidationErrors({ general: err.response.data.message || Ltext("An error occurred while submitting your booking. Please try again.") });


    } finally {
      
    }
  };

  const handleProceed = () => {
    // Clear previous errors
    setValidationErrors({});

    // Validation for required fields
    const errors = {};

    // Email validation
    if (!email || email.trim() === '') {
      errors.email = Ltext("Email is required");
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      errors.email = Ltext("Please enter a valid email address");
    }

    // Phone validation
    if (!phoneNumber || phoneNumber.trim() === '') {
      errors.phone = Ltext("Phone number is required");
    } else if (phoneNumber.length < 8) {
      errors.phone = Ltext("Please enter a valid phone number");
    }

    // Name validation based on contact type
    if (contactType === 'company') {
      if (!firstName || firstName.trim() === '') {
        errors.firstName = Ltext("Company name is required");
      }
      if (!companyNumber || companyNumber.trim() === '') {
        errors.companyNumber = Ltext("Organization number is required");
      }
    } else {
      if (!firstName || firstName.trim() === '') {
        errors.firstName = Ltext("First name is required");
      }
      if (!lastName || lastName.trim() === '') {
        errors.lastName = Ltext("Last name is required");
      }
    }

    // Billing address validation (only if invoice payment is enabled)
    if (postMeta?._manual_invoice_payment && postMeta?._manual_invoice_payment !== "dont_show_invoice" && isEditForm) {
      if (!billingCity || billingCity.trim() === '') {
        errors.billingCity = Ltext("City is required");
      }
      if (!billingPostcode || billingPostcode.trim() === '') {
        errors.billingPostcode = Ltext("Postal code is required");
      }
      if (!billingAddress1 || billingAddress1.trim() === '') {
        errors.billingAddress1 = Ltext("Billing address is required");
      }
    }

    // Terms validation
    if (!termsAccepted) {
      errors.terms = Ltext("Please accept the terms and conditions");
    }

    // Payment method validation (if any methods are available)
    if (!selectedPaymentMethod) {
      errors.paymentMethod = Ltext("Please select a payment method");
    }

    // Set errors if any
    if (Object.keys(errors).length > 0) {
      setValidationErrors(errors);
      return;
    }

    let country_code = countryCode;
    if(country_code){
      country_code = country_code.replace("+", "");
      country_code = "+" + country_code;
    }


    // let phone_number = phoneNumber;

    // if(phone_number){
    //   phone_number = phone_number.replace("+", "");
    // }

    // // Remove dial code (country code) from phoneNumber before submission
    // let phoneWithoutDialCode = phone_number;
    // if (country_code && phone_number.startsWith(country_code)) {
    //   phoneWithoutDialCode = phone_number.slice(country_code.length);
    // }

    // if(country_code != ""){
    //   country_code = "+" + country_code;
    // }

    // Prepare data for submission
    const submissionData = {
      bookingToken,
      contactType,
      email: email.trim(),
      phone: phoneNumber, // Remove country code for phone
      countryCode: country_code,
      firstName: firstName.trim(),
      lastName: contactType === 'company' ? '' : lastName.trim(),
      companyName: contactType === 'company' ? firstName.trim() : '',
      companyNumber: contactType === 'company' ? companyNumber.trim() : '',
      billingCity: billingCity.trim(),
      billingPostcode: billingPostcode.trim(),
      billingAddress1: billingAddress1.trim(),
      billingCountry,
      message: message.trim(),
      termsAccepted,
      // Include chosen payment method for backend
      paymentMethod: selectedPaymentMethod,
    };

    submitBooking(submissionData);
    // console.log(submissionData);
  };

  const handlePrint = () => {
    window.print();
  };

  // const handleBackToBooking = () => {
  //   localStorage.removeItem('rmp_last_booking');
    
  //   // Try to go back to the previous page with booking token
  //   if (window.history.length > 1) {
  //     window.history.back();
  //   } else {
  //     window.location.href = '/';
  //   }
  // };

  if (loading) {
    return (
      <div className={styles.confirmationContainer}>
        <div className={styles.loading}>
          <div className={styles.spinner}></div>
          <p>{Ltext("Loading booking confirmation...")}</p>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className={styles.confirmationContainer}>
        <div className={styles.error}>
          <h2>{Ltext("Error")}</h2>
          <p>{error}</p>
          <button 
            className={styles.btn} 
            onClick={handleBackToBooking}
          >
            {Ltext("Back to Booking")}
          </button>
        </div>
      </div>
    );
  }

  if (!bookingData) {
    return (
      <div className={styles.confirmationContainer}>
        <div className={styles.error}>
          <h2>{Ltext("No Booking Data")}</h2>
          <p>{Ltext("No booking data found. Please try booking again.")}</p>
          <button 
            className={styles.btn} 
            onClick={handleBackToBooking}
          >
            {Ltext("Back to Booking")}
          </button>
        </div>
      </div>
    );
  }

  return (
    <div
      className={
        `${styles.confirmationContainer} bk_confirmation_container` +
        (confirmationContainerWidth < 800 ? ' min_confirmation_container' : '')
      }
      ref={confirmationContainerRef}
    >
     

      

      {/* Initial Email/Phone Form - Only show when cr_user_id is empty */}
      
      

      <div
        className={
          `${styles.confirmationLayout}` +
          (confirmationContainerWidth < 800 ? ' min_confirmation_layout' : '')
        }
      >
        
        {/* Left Column - Contact Information */}

          {(showInitialForm && !initialUserView) && (
            <div className={styles.contactSection}>

              {/* Back Button */}
              <div className={styles.backButtonContainer}>
                <button 
                  className={styles.backBtn}
                  onClick={handleBackToBooking}
                >
                  ← {Ltext("Back to Booking")}
                </button>
              </div>
              {userDataLoading && (
                <div className={styles.contactOverlay}>
                  <div className={styles.overlaySpinnerContainer}>
                    <div className={styles.overlaySpinner}></div>
                    <p>{Ltext("Loading contact information...")}</p>
                  </div>
                </div>
              )}
              <h2 className={styles.sectionTitle}>{Ltext("Contact Information")}</h2>
              <form onSubmit={handleInitialFormSubmit}>
                <div className={styles.formGroup}>
                  <label htmlFor="initialEmailPhone" className="required">
                    {Ltext("Email")}
                  </label>
                  <div className={styles.emailInputContainer}>
                    <input
                      type="text"
                      id="initialEmailPhone"
                      value={initialEmailPhone}
                      onChange={(e) => setInitialEmailPhone(e.target.value)}
                      placeholder={Ltext("Enter email")}
                      className={`${emailLoading ? styles.emailLoading : ''} ${validationErrors.email ? styles.inputError : ''}`}
                      required
                    />
                    {/* {initialFormLoading && (
                      <div className={styles.initialFormSpinner}>
                        <div className={styles.spinnerSmall}></div>
                      </div>
                    )} */}
                  </div>
                </div>
                <div className={styles.initialFormActions}>
                  <button 
                    type="submit" 
                    className={styles.proceedBtn}
                    disabled={!initialEmailPhone || initialFormLoading}
                  >
                    {initialFormLoading ? (
                      <>
                        <div className={styles.spinnerSmall}></div>
                        {Ltext("Looking up...")}
                      </>
                    ) : (
                      Ltext("Continue")
                    )}
                  </button>
                </div>
              </form>
              
              {/* Social Login Separator */}
              <div className={styles.socialLoginSeparator} style={{ display:  'none' }}>
                <span className={styles.separatorLine}></span>
                <span className={styles.separatorText}>{Ltext("or log in with")}</span>
                <span className={styles.separatorLine}></span>
              </div>

              {/* Social Login Buttons */}
              <div className={styles.socialLoginButtons} style={{ display:  'none' }}>
                <button 
                  type="button" 
                  className={`${styles.socialBtn} ${styles.socialBtnVipps}`}
                  onClick={() => handleSocialLogin('vipps')}
                >
                  <div className={styles.socialIconBox}>
                    <svg className={styles.socialIcon} viewBox="0 0 24 24" fill="none">
                      <rect x="2" y="2" width="20" height="20" rx="4" fill="#FF5B24"/>
                      <circle cx="16" cy="9" r="1.5" fill="white"/>
                      <path d="M7 14.5C7 14.5 9 17 12 17C15 17 17 14.5 17 14.5" stroke="white" strokeWidth="2" strokeLinecap="round"/>
                    </svg>
                  </div>
                  <span className={styles.socialBtnText}>
                    {socialLoginLoading ? (
                      <>
                        {Ltext("Connecting to Vipps...")}
                      </>
                    ) : (
                      Ltext("Vipps")
                    )}
                  </span>
                </button>
                
                {/* <button 
                  type="button" 
                  className={`${styles.socialBtn} ${styles.socialBtnFacebook}`}
                  onClick={() => handleSocialLogin('facebook')}
                >
                  <div className={styles.socialIconBox}>
                    <svg className={styles.socialIcon} viewBox="0 0 24 24" fill="none">
                      <rect x="2" y="2" width="20" height="20" rx="4" fill="#1877F2"/>
                      <path d="M16 13.5L16.5 10.5H13.5V8.5C13.5 7.5 14 6.5 15.5 6.5H16.5V4C16.5 4 15 3.5 13.5 3.5C11 3.5 9.5 5 9.5 8V10.5H7V13.5H9.5V21H13.5V13.5H16Z" fill="white"/>
                    </svg>
                  </div>
                  <span className={styles.socialBtnText}>{Ltext("Facebook")}</span>
                </button>
                
                <button 
                  type="button" 
                  className={`${styles.socialBtn} ${styles.socialBtnGoogle}`}
                  onClick={() => handleSocialLogin('google')}
                >
                  <div className={styles.socialIconBox}>
                    <svg className={styles.socialIcon} viewBox="0 0 24 24" fill="none">
                      <rect x="2" y="2" width="20" height="20" rx="4" fill="white"/>
                      <path d="M20.5 12.2c0-.8-.07-1.5-.2-2.2h-8.3v4.2h4.8c-.2 1.3-1 2.4-2.1 3.1v2.5h3.4c2-1.8 3.1-4.5 3.4-7.6z" fill="#4285F4"/>
                      <path d="M12 21.5c2.9 0 5.3-1 7-2.6l-3.4-2.6c-.9.6-2.1 1-3.6 1-2.8 0-5.2-1.9-6-4.4H2.5v2.7C4.3 19.2 7.9 21.5 12 21.5z" fill="#34A853"/>
                      <path d="M6 12.9c-.2-.6-.3-1.3-.3-1.9s.1-1.3.3-1.9V6.4H2.5c-.9 1.2-1.5 2.6-1.5 4.5s.6 3.3 1.5 4.5L6 12.9z" fill="#FBBC05"/>
                      <path d="M12 6.5c1.6 0 3 .5 4.1 1.5l3.1-3.1C17.3 3.2 14.8 2 12 2c-4.1 0-7.7 2.3-9.5 5.9L6 10.6C6.8 8.4 9.2 6.5 12 6.5z" fill="#EA4335"/>
                    </svg>
                  </div>
                  <span className={styles.socialBtnText}>{Ltext("Google")}</span>
                </button>
                
                <button 
                  type="button" 
                  className={`${styles.socialBtn} ${styles.socialBtnMicrosoft}`}
                  onClick={() => handleSocialLogin('microsoft')}
                >
                  <div className={styles.socialIconBox}>
                    <svg className={styles.socialIconMicrosoft} viewBox="0 0 24 24" fill="none">
                      <rect x="2" y="2" width="20" height="20" rx="4" fill="white"/>
                      <rect x="5" y="5" width="6.5" height="6.5" rx="0.5" fill="#FF8C00"/>
                      <rect x="12.5" y="5" width="6.5" height="6.5" rx="0.5" fill="#7FBA00"/>
                      <rect x="5" y="12.5" width="6.5" height="6.5" rx="0.5" fill="#00A4EF"/>
                      <rect x="12.5" y="12.5" width="6.5" height="6.5" rx="0.5" fill="#FFB900"/>
                    </svg>
                  </div>
                  <span className={styles.socialBtnText}>{Ltext("Microsoft")}</span>
                </button> */}
              </div>
            </div>
        )}
        {!showInitialForm && initialFormSubmitted && (
        <>
          <div className={styles.contactSection}>
              {/* Back Button */}
              <div className={styles.backButtonContainer}>
                <button 
                  className={styles.backBtn}
                  onClick={handleBackToBooking}
                >
                  ← {Ltext("Back to Booking")}
                </button>
              </div>
              {(isEditForm) ? (
                <div className={styles.contactSectionInner}>
                  {userDataLoading && (
                    <div className={styles.contactOverlay}>
                      <div className={styles.overlaySpinnerContainer}>
                        <div className={styles.overlaySpinner}></div>
                        <p>{Ltext("Loading contact information...")}</p>
                      </div>
                    </div>
                  )}
                  <h2 className={styles.sectionTitle}>{Ltext("Contact Information")}</h2>
                  
                  {/* Contact Type Selection */}
                  <div className={styles.contactType}>
                    <div className={styles.radioGroup}>
                      <div className={styles.radioItem}>
                        <input
                          type="radio"
                          id="personal"
                          name="contactType"
                          value="personal"
                          checked={contactType === 'personal'}
                          onChange={() => handleContactTypeChange('personal')}
                        />
                        <label htmlFor="personal">{Ltext("Private")}</label>
                      </div>
                      <div className={styles.radioItem}>
                        <input
                          type="radio"
                          id="company"
                          name="contactType"
                          value="company"
                          checked={contactType === 'company'}
                          onChange={() => handleContactTypeChange('company')}
                        />
                        <label htmlFor="company">{Ltext("Company")}</label>
                      </div>
                    </div>
                  </div>

                  {/* Contact Form Fields */}
                  <div className={styles.formFieldsGrid}>
                    <div className={styles.formGroup}>
                      <label htmlFor="email" className="required">{Ltext("Email")}</label>
                      <div className={styles.emailInputContainer}>
                        <input
                          type="email"
                          id="email"
                          value={email}
                          onChange={handleEmailChange}
                          onBlur={handleEmailBlur}
                          placeholder={Ltext("Email")}
                          className={`${emailLoading ? styles.emailLoading : ''} ${validationErrors.email ? styles.inputError : ''}`}
                        />
                        {emailLoading && (
                          <div className={styles.emailSpinner}>
                            <div className={styles.spinnerSmall}></div>
                          </div>
                        )}
                      </div>
                      {validationErrors.email && (
                        <div className={styles.fieldError}>{validationErrors.email}</div>
                      )}
                    </div>

                    <div className={styles.formGroup}>
                      <label htmlFor="phone" className="required">{Ltext("Phone")}</label>
                      <div className={styles.phoneInputContainer}>
                        <PhoneInput
                          country={'no'}
                          value={countryCode}
                          inputProps={{
                            readOnly: true,
                            disabled: true
                          }}
                          onChange={(value, country) => {
                            //setPhoneNumber(value);
                            setCountryCode(country.dialCode || "+47");
                            // Clear phone error when user starts typing
                            // if (validationErrors.phone) {
                            //   setValidationErrors(prev => {
                            //     const newErrors = { ...prev };
                            //     delete newErrors.phone;
                            //     return newErrors;
                            //   });
                            // }
                          }}
                          enableSearch={false}
                          inputStyle={{ 
                            width: '0%',
                            border: validationErrors.phone ? '2px solid #dc3545' : '2px solid #e9ecef',
                            borderLeft: 'none',
                            borderRadius: '0 8px 8px 0',
                            padding: '12px 16px',
                            fontSize: '16px'
                          }}
                          containerStyle={{ width: '120px' }}
                          buttonStyle={{ 
                            border: validationErrors.phone ? '2px solid #dc3545' : '2px solid #e9ecef',
                            borderRight: 'none',
                            borderRadius: '8px 0 0 8px',
                            backgroundColor: '#f8f9fa'
                          }}
                          countryCodeEditable={false}
                        />
                        <input
                          type="text"
                          id="phone"
                          value={phoneNumber}
                          className={`${styles.phoneInput} ${validationErrors.phone ? styles.inputError : ''}`}
                          onChange={(e) => {
                            setPhoneNumber(e.target.value);
                            if (validationErrors.phone) {
                              setValidationErrors(prev => {
                                const newErrors = { ...prev };
                                delete newErrors.phone;
                                return newErrors;
                              });
                            }
                          }}
                        />
                      </div>
                      {validationErrors.phone && (
                        <div className={styles.fieldError}>{validationErrors.phone}</div>
                      )}
                    </div>

                    <div className={styles.formGroup}>
                      {contactType === 'company' ? (
                        <label htmlFor="firstName" className="required">{Ltext("Name")}</label>
                      ) : (
                        <label htmlFor="firstName" className="required">{Ltext("First Name")}</label>
                      )}
                      <input
                        type="text"
                        id="firstName"
                        value={firstName}
                        onChange={(e) => {
                          setFirstName(e.target.value);
                          // Clear firstName error when user starts typing
                          if (validationErrors.firstName) {
                            setValidationErrors(prev => {
                              const newErrors = { ...prev };
                              delete newErrors.firstName;
                              return newErrors;
                            });
                          }
                        }}
                        placeholder={contactType === 'company' ? (Ltext("Name")) : ( Ltext("First name") )}
                        className={validationErrors.firstName ? styles.inputError : ''}
                      />
                      {validationErrors.firstName && (
                        <div className={styles.fieldError}>{validationErrors.firstName}</div>
                      )}
                    </div>

                    {contactType === 'company' ? (
                      <div className={styles.formGroup}>
                        <label htmlFor="organizationNumber" className="required">{Ltext("Organization Number")}</label>
                        <input
                          type="text"
                          id="organizationNumber"
                          value={companyNumber}
                          onChange={(e) => {
                            setCompanyNumber(e.target.value);
                            // Clear companyNumber error when user starts typing
                            if (validationErrors.companyNumber) {
                              setValidationErrors(prev => {
                                const newErrors = { ...prev };
                                delete newErrors.companyNumber;
                                return newErrors;
                              });
                            }
                          }}
                          placeholder="123456789"
                          className={validationErrors.companyNumber ? styles.inputError : ''}
                        />
                        {validationErrors.companyNumber && (
                          <div className={styles.fieldError}>{validationErrors.companyNumber}</div>
                        )}
                      </div>
                    
                    ) : (
                      <div className={styles.formGroup}>
                        <label htmlFor="lastName" className="required">{Ltext("Last Name")}</label>
                        <input
                          type="text"
                          id="lastName"
                          value={lastName}
                          onChange={(e) => {
                            setLastName(e.target.value);
                            // Clear lastName error when user starts typing
                            if (validationErrors.lastName) {
                              setValidationErrors(prev => {
                                const newErrors = { ...prev };
                                delete newErrors.lastName;
                                return newErrors;
                              });
                            }
                          }}
                          placeholder={Ltext("Last name")}
                          className={validationErrors.lastName ? styles.inputError : ''}
                        />
                        {validationErrors.lastName && (
                          <div className={styles.fieldError}>{validationErrors.lastName}</div>
                        )}
                      </div>
                    )}

                    {postMeta?._manual_invoice_payment && postMeta?._manual_invoice_payment != "dont_show_invoice" && (
                      <div className={styles.formGroup}>
                        <label htmlFor="billingCity" className="required">{Ltext("City")}</label>
                        <input
                          type="text"
                          id="billingCity"
                          value={billingCity}
                          onChange={(e) => {
                            setBillingCity(e.target.value);
                            // Clear billingCity error when user starts typing
                            if (validationErrors.billingCity) {
                              setValidationErrors(prev => {
                                const newErrors = { ...prev };
                                delete newErrors.billingCity;
                                return newErrors;
                              });
                            }
                          }}
                          placeholder={Ltext("City")}
                          className={validationErrors.billingCity ? styles.inputError : ''}
                        />
                        {validationErrors.billingCity && (
                          <div className={styles.fieldError}>{validationErrors.billingCity}</div>
                        )}
                      </div>
                    )}   
                    {postMeta?._manual_invoice_payment && postMeta?._manual_invoice_payment != "dont_show_invoice" && (
                      <div className={styles.formGroup}>
                        <label htmlFor="billingPostcode" className="required">{Ltext("Postal Code")}</label>
                        <input
                          type="text"
                          id="billingPostcode"
                          value={billingPostcode}
                          onChange={(e) => {
                            setBillingPostcode(e.target.value);
                            // Clear billingPostcode error when user starts typing
                            if (validationErrors.billingPostcode) {
                              setValidationErrors(prev => {
                                const newErrors = { ...prev };
                                delete newErrors.billingPostcode;
                                return newErrors;
                              });
                            }
                          }}
                          placeholder="0000"
                          className={validationErrors.billingPostcode ? styles.inputError : ''}
                        />
                        {validationErrors.billingPostcode && (
                          <div className={styles.fieldError}>{validationErrors.billingPostcode}</div>
                        )}
                      </div>
                    )}
                  </div>

                  {/* Full-width fields outside the grid */}
                  {postMeta?._manual_invoice_payment && postMeta?._manual_invoice_payment != "dont_show_invoice" && (
                    <div className={styles.formGroup}>
                      <label htmlFor="billingAddress1" className="required">{Ltext("Billing Address")}</label>
                      <input
                        type="text"
                        id="billingAddress1"
                        value={billingAddress1}
                        onChange={(e) => {
                          setBillingAddress1(e.target.value);
                          // Clear billingAddress1 error when user starts typing
                          if (validationErrors.billingAddress1) {
                            setValidationErrors(prev => {
                              const newErrors = { ...prev };
                              delete newErrors.billingAddress1;
                              return newErrors;
                            });
                          }
                        }}
                        placeholder={Ltext("Street address")}
                        className={validationErrors.billingAddress1 ? styles.inputError : ''}
                      />
                      {validationErrors.billingAddress1 && (
                        <div className={styles.fieldError}>{validationErrors.billingAddress1}</div>
                      )}
                    </div>
                  )}

                  {!hideBookingMessage && (
                    <>
                      <h3 className={styles.sectionTitle}>{Ltext("Message")}</h3>
                      <div className={styles.formGroup}>
                        <label htmlFor="message">{Ltext("(Optional)")}</label>
                        <textarea
                          id="message"
                          value={message}
                          onChange={(e) => setMessage(e.target.value)}
                          placeholder={Ltext("Write your message here...")}
                        />
                      </div>
                    </>
                  )}
                </div>
              ):(
                <>
                  {initialUserView && firstName && (
                    <div className={styles.bookingUserInfo}>
                      <h1 className={styles.bookingTitle}>{Ltext("Complete booking")}</h1>
                      <div className={styles.bookingAsUser}>
                        <span className={styles.bookingAsText}>
                          {Ltext("Booking as")} <strong>{firstName || ''} {lastName || ''}</strong>
                        </span>
                        <button 
                          className={styles.changeBtn}
                          onClick={() => setIsEditForm(true)}
                        >
                          {Ltext("Change")}
                        </button>
                      </div>
                    </div>
                  )}
                </>
              )}
              <div className={styles.contactSectionInnerBottom}>
                {/* Payment Methods */}
                <div className={styles.paymentMethodsSection}>
                      
                  {paymentMethodsLoading ? (
                    <div className={styles.loadingRow}>
                      <div className={styles.spinnerSmall}></div>
                      <span>{Ltext("Loading payment methods...")}</span>
                    </div>
                  ) : Array.isArray(paymentMethods) && paymentMethods.length > 1 ? (
                    <>
                      <h3 className={styles.sectionTitlePayment}>{Ltext("Payment method")}</h3>
                      <div role="radiogroup" aria-label="payment-methods" className={styles.paymentMethodList}>
                        {paymentMethods.map((method) => {
                          const isSelected = selectedPaymentMethod === method.id;
                          const isDisabled = method.enabled === false;
                          return (
                            <label
                              key={method.id}
                              className={`${styles.paymentMethodCard} ${isSelected ? styles.selected : ''} ${isDisabled ? styles.disabled : ''}`}
                              aria-selected={isSelected}
                            >
                              <input
                                type="radio"
                                className={styles.methodRadio}
                                name="paymentMethod"
                                value={method.id}
                                checked={isSelected}
                                onChange={() => setSelectedPaymentMethod(method.id)}
                                disabled={isDisabled}
                                aria-label={method.title}
                              />
                              <div className={styles.methodContent}>
                                <div className={styles.methodHeader}>
                                  <div className={styles.methodTitle}>{Ltext(method.title)}</div>
                                  <div className={styles.methodBadges}>
                                    {method.id === 'dintero' && (
                                      <span className={styles.methodBadge}>Vipps / Card</span>
                                    )}
                                    {method.id === 'dibs_easy' && (
                                      <span className={styles.methodBadge}>Visa/Mastercard/Vipps</span>
                                    )}
                                    {method.id === 'cod' && (
                                      <span className={styles.methodBadge}>{Ltext('Invoice')}</span>
                                    )}
                                  </div>
                                </div>
                                {method.description ? (
                                  <div className={styles.methodDescription}>{Ltext(method.description)}</div>
                                ) : null}
                              </div>
                            </label>
                          );
                        })}
                      </div>
                    </>
                  ) : Array.isArray(paymentMethods) && paymentMethods.length === 1 ? (
                    <></>
                  ) : (
                    <div className={styles.emptyState}>{Ltext("No payment methods available")}</div>
                  )}
                  {validationErrors.paymentMethod && (
                    <div className={styles.fieldError}>{validationErrors.paymentMethod}</div>
                  )}
                </div>

                {/* Terms and Conditions */}
                <div className={styles.termsSection}>
                  <p>{Ltext("The landlord requires that you have read and approved the terms")}</p>
                  <div className={`${styles.termsCheckbox} ${validationErrors.terms ? styles.checkboxError : ''}`}>
                    <input
                      type="checkbox"
                      id="terms"
                      checked={termsAccepted}
                      onChange={(e) => {
                        setTermsAccepted(e.target.checked);
                        // Clear terms error when user checks the checkbox
                        if (validationErrors.terms) {
                          setValidationErrors(prev => {
                            const newErrors = { ...prev };
                            delete newErrors.terms;
                            return newErrors;
                          });
                        }
                      }}
                    />
                    <label htmlFor="terms">
                      {pdfMeta && pdfMeta.length > 0 ? (
                        <>
                          {pdfMeta.map((pdfData, index) => {
                            if (pdfData.value && pdfData.value.trim() !== '') {
                              const fileName = pdfData.value.split('/').pop() || `Document ${index + 1}`;
                              return (
                                <a 
                                  key={index}
                                  href={pdfData.value} 
                                  className={styles.termsLink} 
                                  target="_blank" 
                                  rel="noopener noreferrer"
                                >
                                  {fileName}
                                </a>
                              );
                            }
                            return null;
                          })}
                        </>
                      ):(
                        <span>{Ltext("By proceeding, you agree to the terms and conditions for the website.")}</span>
                      )}
                    </label>
                  </div>
                  {validationErrors.terms && (
                    <div className={styles.fieldError}>{validationErrors.terms}</div>
                  )}
                  
                  {pdfMeta && pdfMeta.length > 0 && (
                    <p className={styles.termsNote}>
                      {Ltext("By proceeding, you agree to the terms and conditions for the website.")}
                    </p>
                  )}
                </div>

                

                {/* General Error Display for API errors */}
                {validationErrors.general && (
                  <div className={styles.generalError}>
                    <div className={styles.errorMessage}>{Ltext(validationErrors.general)}</div>
                  </div>
                )}
                
                {!isEditForm && Object.keys(validationErrors).length > 0 && (
                  <div className={styles.allValidationErrors}>
                    <div className={styles.errorTitle}>
                      {Ltext("Please fix the following errors:")}
                    </div>
                    {Object.entries(validationErrors).map(([field, message]) => {
                      // Don't show 'general' error here (it's already rendered above)
                      if (field === "general") return null;
                      return (
                        <div className={styles.errorItem} key={field}>
                          {Ltext(message)}
                        </div>
                      );
                    })}
                  </div>
                )}

                

                <button 
                  className={styles.proceedBtn}
                  onClick={handleProceed}
                  disabled={!termsAccepted || submitting}
                >
                  {submitting ? (
                    <>
                      <div className={styles.spinnerSmall}></div>
                      {Ltext("Submitting...")}
                    </>
                  ) : (
                    Ltext("Proceed")
                  )}
                </button>
                {!termsAccepted && (
                  <div className={styles.termsValidation}>
                    <span className={styles.warningIcon}>⚠️</span>
                    <span>{Ltext("Please accept the terms and conditions to continue.")}</span>
                  </div>
                )}
              </div>
          </div>  
        </>
        )}
        {/* Right Column - Booking Summary */}
        <div className={styles.summarySection}>
          <div className={styles.bookingImage}>
            {listingImage && (
              <img src={listingImage} alt={listingTitle} />
            )}
          </div>
          <h3 className={styles.bookingTitle}>{listingTitle}</h3>
          
          <h3 className={styles.summaryTitle}>{Ltext("Summary")}</h3>
          
          <div className={styles.summaryItem}>
            <span className={styles.summaryLabel}>{Ltext("Date")}</span>
            <span className={styles.summaryValue}>
              {bookingData.start_date && bookingData.end_date && bookingData.booking_slot
                ? (() => {
                    return `${formatDate(bookingData.start_date)}`;
                  })()
                : 'N/A'
              }
            </span>
          </div>
          <div className={styles.summaryItem}>
            <span className={styles.summaryLabel}>{Ltext("Time")}</span>
            <span className={styles.summaryValue}>
              {bookingData.start_date && bookingData.end_date && bookingData.booking_slot
                ? (() => {
                    const { startTime, endTime } = parseBookingSlot(bookingData.booking_slot);
                    return `${formatTime(startTime)} - ${formatTime(endTime)}`;
                  })()
                : 'N/A'
              }
            </span>
          </div>

          <div className={styles.summaryItem}>
            <span className={styles.summaryLabel}>{Ltext("Quantity")}</span>
            <span className={styles.summaryValue}>{bookingData.adults || 1}</span>
          </div>

          <div className={styles.summaryItem}>
            {bookingData.slot_label && bookingData.slot_label !== "" ? (
              <span className={styles.summaryLabel}>{bookingData.slot_label}</span>
            ) : (
              <span className={styles.summaryLabel} style={{width: "70%"}}>{Ltext("Price (excl. VAT)")}</span>
            )}
            <span className={styles.summaryValue}>{bookingData.totalPrice} kr</span>
          </div>

          {/* {bookingData.tax && bookingData.tax > 0 ? (
            <div className={styles.summaryItem}>
              <span className={styles.summaryLabel} style={{width: "70%"}}>{Ltext("Tax")} ({Ltext("Included")})</span>
              <span className={styles.summaryValue}>{bookingData.tax} kr</span>
            </div>
          ):(
            <></>
          )} */}

          {/* Services */}
          {bookingData.services && bookingData.services.length > 0 ? (
            <div className={styles.servicesList}>
              {bookingData.services.map((service, index) => (
                <div key={index} className={styles.serviceItem}>
                  <span className={styles.serviceName}>
                    {service.name}
                    {service.quantity > 1 && ` (*${service.quantity})`}
                  </span>
                    <span className={styles.servicePrice}>
                    {Math.round(service.price)} kr 
                    {/* {Ltext("(incl. VAT)")} */}
                  </span>
                </div>
              ))}
            </div>
          ):(
            <></>
          )}


          {bookingData.coupon_discount > 0 ? (
            <>
              
              <div className={styles.summaryItem}>
                <span className={styles.summaryLabel}>{Ltext("Total amount")} {Ltext("(incl. VAT)")}</span>
                <span className={styles.summaryValue}>{Number(bookingData.org_total_price) + Number(bookingData.coupon_discount)} kr</span>
              </div>
              <div className={styles.summaryItem}>
                <span className={styles.summaryLabel}>{Ltext("Discount")}</span>
                <span className={styles.summaryValue}>-{bookingData.coupon_discount} kr</span>
              </div>
              {bookingData.season_discount && bookingData.season_discount > 0 ? (
                <div className={styles.summaryItem}>
                  <span className={styles.summaryLabel}>{Ltext("Extra discount")}</span>
                  <span className={styles.summaryValue}>-{bookingData.season_discount} kr</span>
                </div>
              ):''}
              {bookingData.subscription_discount && bookingData.subscription_discount > 0 ? (
                <div className={styles.summaryItem}>
                  <span className={styles.summaryLabel}>{Ltext("Subscription discount")}</span>
                  <span className={styles.summaryValue}>-{bookingData.subscription_discount} kr</span>
                </div>
              ):''}
              <div className={`${styles.summaryItem} ${styles.total}`}>
                <span className={styles.summaryLabel} style={{width: "70%"}}>{Ltext("Total Price")} {Ltext("(incl. VAT)")}</span>
                <span className={styles.summaryValue} style={{width: "30%"}}>{bookingData.org_total_price} kr</span>
              </div>
            </>
          ):(
            <>  
            {bookingData.season_discount && bookingData.season_discount > 0 ? (
                <div className={styles.summaryItem}>
                  <span className={styles.summaryLabel}>{Ltext("Extra discount")}</span>
                  <span className={styles.summaryValue}>-{bookingData.season_discount} kr</span>
                </div>
              ):''}
            {bookingData.subscription_discount && bookingData.subscription_discount > 0 ? (
              <div className={styles.summaryItem}>
                <span className={styles.summaryLabel}>{Ltext("Subscription discount")}</span>
                <span className={styles.summaryValue}>-{bookingData.subscription_discount} kr</span>
              </div>
            ):''}
            <div className={`${styles.summaryItem} ${styles.total}`}>
              <span className={styles.summaryLabel} style={{width: "70%"}}>{Ltext("Total Price")} {Ltext("(incl. VAT)")}</span>
              <span className={styles.summaryValue} style={{width: "30%"}}>{bookingData.org_total_price} kr</span>
            </div>
            </>
          )}
        </div>
      </div>
    </div>
  );
}

export default SlotBookingConfirmation; 