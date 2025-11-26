import React, { useState, useRef, useEffect } from 'react';
import DatePicker, { registerLocale } from 'react-datepicker';
import 'react-datepicker/dist/react-datepicker.css';
import nb from 'date-fns/locale/nb';
import en from 'date-fns/locale/en-US';
import axios from 'axios';
import { Ltext, __dynamicText, __extraText,getLanguage } from './utils/translations';
import LanguageSwitcher from './components/LanguageSwitcher';
import styles from './assets/scss/SlotBooking.module.scss';
import './assets/scss/SlotBooking.scss';

// ============================================================================
// CONSTANTS & CONFIGURATION
// ============================================================================

// Day names mapping
const DAY_NAMES = {
  1: Ltext("Monday"),
  2: Ltext("Tuesday"), 
  3: Ltext("Wednesday"),
  4: Ltext("Thursday"),
  5: Ltext("Friday"),
  6: Ltext("Saturday"),
  7: Ltext("Sunday")
};

// Initialize locale based on current language
const initializeLocale = () => {
  const currentLang = getLanguage();
  let locale = 'en';
  
  if (currentLang === 'no' || currentLang === 'nb' || currentLang === 'nn' || 
      currentLang === 'nn-NO' || currentLang === 'nb-NO') {
    registerLocale('nb', nb);
    locale = 'nb';
  } else {
    registerLocale('en', en);
    locale = 'en';
  }
  
  return locale;
};

const locale = initializeLocale();


function getLanguageCode(locale){
  if(locale === 'nb'){
    return '';
  }
  return 'en';
}

// ============================================================================
// UTILITY FUNCTIONS
// ============================================================================

/**
 * Calculate time difference between two times in minutes
 * @param {string} startTime - Start time in HH:MM format
 * @param {string} endTime - End time in HH:MM format
 * @returns {number} Duration in minutes
 */
const calculateTimeDifference = (startTime, endTime) => {
  // Handle special case where end time is 23:59 (convert to 24:00)
  if (endTime === "23:59") {
    endTime = "24:00";
  }
  
  const [startHour, startMinute] = startTime.split(':').map(Number);
  const [endHour, endMinute] = endTime.split(':').map(Number);
  
  // Convert to total minutes
  const startTotalMinutes = startHour * 60 + startMinute;
  let endTotalMinutes = endHour * 60 + endMinute;
  
  // Handle overnight slots (end time is next day)
  if (endTotalMinutes < startTotalMinutes) {
    endTotalMinutes += 24 * 60; // Add 24 hours
  }
  
  const diff = Math.abs(endTotalMinutes - startTotalMinutes);
  const hours = Math.floor(diff / 60);
  const minutes = diff % 60;
  
  return (hours * 60) + minutes;
};

/**
 * Format duration label for display
 * @param {number} duration - Duration in minutes
 * @returns {string} Formatted duration label
 */
const formatDurationLabel = (duration) => {
  if (duration < 60) {
    return `${duration} ${Ltext("Minutes")}`;
  } else if (duration === 60) {
    return `1 ${Ltext("Hour")}`;
  } else {
    const hours = Math.floor(duration / 60);
    const minutes = duration % 60;

    let hourLabel = Ltext("Hour");
    if(hours > 1){
      hourLabel = Ltext("Hours");
    }
    
    if (minutes === 0) {
      return `${hours} ${hourLabel}`;
    } else {
      return `${hours} ${hourLabel} ${minutes} ${Ltext("Minutes")}`;
    }
  }
};

/**
 * Convert date to YYYY-MM-DD format
 * @param {Date} date - Date object
 * @returns {string} Formatted date string
 */
const formatDateKey = (date) => {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
};

/**
 * Get adjusted day of week (Sunday = 7, Monday = 1, etc.)
 * @param {Date} date - Date object
 * @returns {number} Adjusted day of week
 */
const getAdjustedDayOfWeek = (date) => {
  const dayOfWeek = date.getDay();
  return dayOfWeek === 0 ? 7 : dayOfWeek;
};


// ============================================================================
// MAIN COMPONENT
// ============================================================================

function SlotBooking({ listing_id, apiUrl, homeUrl, setPrevBookingData, prevBookingData, handleBookingSuccess, bookingToken, onViewDatesChange }) {
  // ============================================================================
  // STATE MANAGEMENT
  // ============================================================================

  const API_BASE_URL = apiUrl;
  
  // Date and calendar state
  const [selected, setSelected] = useState(null);
  const [isOpen, setIsOpen] = useState(false);
  const [currentViewDates, setCurrentViewDates] = useState([]);
  const [currentMonth, setCurrentMonth] = useState(new Date());
  
  // Booking data state
  const [bookingSlots, setBookingSlots] = useState([]);
  const [bookingData, setBookingData] = useState([]);
  const [processedBookings, setProcessedBookings] = useState(null);
  const [processedBookingsFixed, setProcessedBookingsFixed] = useState(null);
  const [dateChanged, setDateChanged] = useState(false);
  const [selectedDateSlots, setSelectedDateSlots] = useState([]);
  const [selectedDate, setSelectedDate] = useState(null);
  const [enableSlotPrice, setEnableSlotPrice] = useState(false);
  const [slotPriceLabel, setSlotPriceLabel] = useState("");
  const [allSlotPriceLabel, setAllSlotPriceLabel] = useState("");
  const [showSlotPriceRadio, setShowSlotPriceRadio] = useState(false);
  const [slotPriceType, setSlotPriceType] = useState(null);
  const [selectedSlot, setSelectedSlot] = useState(null);
  
  // Duration and filtering state
  const [enableSlotDuration, setEnableSlotDuration] = useState(false);
  const [selectedDuration, setSelectedDuration] = useState(null);
  const [durationDropdownOpen, setDurationDropdownOpen] = useState(false);
  const [filteredSlots, setFilteredSlots] = useState([]);
  const [dynamicDurationOptions, setDynamicDurationOptions] = useState([]);
  
  // UI state
  const [timeSlotsOpen, setTimeSlotsOpen] = useState(true);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  const [bookingSuccess, setBookingSuccess] = useState(false);
  const [services, setServices] = useState([]);
  const [additionalServiceLabelName, setAdditionalServiceLabelName] = useState("");
  const [countPerGuest, setCountPerGuest] = useState(0);
  const [bookingSystemService, setBookingSystemService] = useState(false);
  
  // Services state
  const [selectedServices, setSelectedServices] = useState({});
  const [servicesOpen, setServicesOpen] = useState(false);
  
  // Quantity state
  const [quantity, setQuantity] = useState(1);
  const [quantityOpen, setQuantityOpen] = useState(false);
  
  // Refs
  const containerRef = useRef(null);
  const durationDropdownRef = useRef(null);
  const timeSlotsDropdownRef = useRef(null);
  const servicesDropdownRef = useRef(null);
  const quantityDropdownRef = useRef(null);

  const [filterRun, setFilterRun] = useState("");

  const [priceCalculationRun, setPriceCalculationRun] = useState(false);
  const [priceData, setPriceData] = useState(null);
  const [priceDataLoading, setPriceDataLoading] = useState(false);
  
  // Booking state
  const [bookingLoading, setBookingLoading] = useState(false);
  
  // Coupon state
  const [couponCode, setCouponCode] = useState("");
  const [couponApplied, setCouponApplied] = useState(null);
  const [couponLoading, setCouponLoading] = useState(false);
  const [couponError, setCouponError] = useState(null);

  const [confirmationData, setConfirmationData] = useState(null);

  const [minAmountGuests, setMinAmountGuests] = useState(1);
  const [maxAmountGuests, setMaxAmountGuests] = useState(null);
  const [firstBookingMinimumGuests, setFirstBookingMinimumGuests] = useState(null);

  const [taxPercentage, setTaxPercentage] = useState(0);

  const [seasonStatus, setSeasonStatus] = useState(false);
  const [seasonDiscountData, setSeasonDiscountData] = useState([]);

  const [guestSlot, setGuestSlot] = useState(false);

  const [maxBookDays, setMaxBookDays] = useState(null);
  const [minBookDays, setMinBookDays] = useState(null);

  const [hideQuantity, setHideQuantity] = useState(false);
  const [hidePriceDiv, setHidePriceDiv] = useState(false);

  // ============================================================================
  // CALENDAR UTILITIES
  // ============================================================================

  /**
   * Get all dates in the current calendar view
   */
  const getCurrentViewDates = (date) => {
    const year = date.getFullYear();
    const month = date.getMonth();
    
    // Get first and last day of the month
    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    
    // Calculate calendar boundaries
    const firstDayOfWeek = firstDay.getDay();
    const adjustedFirstDayOfWeek = firstDayOfWeek === 0 ? 6 : firstDayOfWeek - 1;
    
    const startDate = new Date(firstDay);
    startDate.setDate(startDate.getDate() - adjustedFirstDayOfWeek);
    
    const endDate = new Date(lastDay);
    const daysToAdd = 42 - (lastDay.getDate() + adjustedFirstDayOfWeek);
    endDate.setDate(endDate.getDate() + daysToAdd);
    
    // Generate date range
    const dates = [];
    const current = new Date(startDate);
    const yesterday = new Date();
    yesterday.setDate(yesterday.getDate() - 1);
    yesterday.setHours(23, 59, 59, 999);
    
    while (current <= endDate) {
      if (current >= yesterday) {
        dates.push(new Date(current));
      }
      current.setDate(current.getDate() + 1);
    }
    
    return dates;
  };

  /**
   * Check if a date has available slots
   */
  const isDateEnabled = (date) => {
    const dateKey = formatDateKey(date);
    return processedBookings[dateKey] && processedBookings[dateKey].length > 0;
  };

  const getSlotsDates = (date, fromDayNum, toDayNum) => {

    const slotsDates = {};
    if(fromDayNum === toDayNum){
      slotsDates["from_date"] = formatDateKey(date);
      slotsDates["to_date"] = formatDateKey(date);
    }else{
      slotsDates["from_date"] = formatDateKey(date);
      // Calculate to_date based on from_date and toDayNum
      const fromDate = date;
      const toDate = date;

      let fromDayOfWeek = fromDate.getDay();

      if (fromDayOfWeek === 0) fromDayOfWeek = 7; // Adjust Sunday to 7
      let daysToAdd = toDayNum - fromDayNum;
      if (daysToAdd < 0) daysToAdd += 7;
      toDate.setDate(toDate.getDate() + daysToAdd);
      slotsDates["to_date"] = formatDateKey(toDate);
    }
    return slotsDates;

  };

  // ============================================================================
  // BOOKING LOGIC
  // ============================================================================

  /**
   * Check if a time slot is booked
   */
  const checkBookedDates = (fromDateTime, toDateTime) => {
    const slotStart = new Date(fromDateTime);
    const slotEnd = new Date(toDateTime);
    const now = new Date();
    
    // Check if slot is in the past
    if (slotStart < now && slotEnd < now) {
      return "empty";
    }

    let bookingCount = 0;

    let book_data = {};

    // Check for overlapping bookings
    for (const booking of bookingData) {
      const bookingStart = new Date(booking.date_start);
      const bookingEnd = new Date(booking.date_end);
      
      if (slotStart < bookingEnd && bookingStart < slotEnd) {

        bookingCount += parseInt(booking.count_slot);

        book_data.count_slot = bookingCount;
        
      }
    }

    if(book_data && book_data.count_slot){
      return book_data;
    }
    
    return false;
  };

  /**
   * Process slot data for a specific date range
   */
  const processSlotData = (slot, bookedDates) => {
    const slotParts = slot.split("|");
    const [fromDay, fromTime, toDay, toTime, slotPrice, slots, slotId, closed = "0", allSlotPrice = "0", slotLabel = "", startDate = "", endDate = ""] = slotParts;
    
    const fromDayNum = parseInt(fromDay);
    const toDayNum = parseInt(toDay);
    const slotsNum = parseInt(slots);

    currentViewDates.forEach(date => {
      
      const calendarDayIndex = getAdjustedDayOfWeek(date);
      
      if (calendarDayIndex === fromDayNum) {

        const dateKey = formatDateKey(date);

        if(startDate && startDate !== "" && !isNaN(Date.parse(startDate))){
          const startDateKey = formatDateKey(new Date(startDate));
          if(dateKey < startDateKey){
            return;
          }
        }

        if(endDate && endDate !== "" && !isNaN(Date.parse(endDate))){
          const endDateKey = formatDateKey(new Date(endDate));
          //console.log('endDateKey', endDateKey);
          //console.log('dateKey', dateKey);
          if(dateKey > endDateKey){
            return;
          }
        }

        const slotDates = getSlotsDates(date, fromDayNum, toDayNum);

        const isBooked = checkBookedDates(`${slotDates.from_date} ${fromTime}`, `${slotDates.to_date} ${toTime}`);
        
        let remainingSlots = slotsNum;

        if (isBooked && isBooked.count_slot && parseInt(isBooked.count_slot) > 0) {
          //console.log('isBooked', isBooked);
          remainingSlots = slotsNum - parseInt(isBooked.count_slot);

          if(remainingSlots < 0){
            remainingSlots = 0;
          }
        }

        
        if (remainingSlots > 0 && isBooked !== "empty") {
          if (!bookedDates[dateKey]) {
            bookedDates[dateKey] = [];
          }
          
          bookedDates[dateKey].push({
            slot_text: slot,
            from_time: fromTime,
            to_time: toTime,
            from_date: `${slotDates.from_date}`,
            to_date: `${slotDates.to_date}`,
            slot_price: slotPrice,
            all_slot_price: allSlotPrice,
            slots: slotsNum,
            from_day: fromDayNum,
            to_day: toDayNum,
            remaining_slots: remainingSlots,
            slot_id: slotId,
            slot_label: slotLabel
          });
        }
      }
    });
  };

  

// ============================================================================
// SIMPLE FILTERING FUNCTIONS
// ============================================================================

/**
 * Filter slots by price type
 * @param {Array} slots - Array of slot objects
 * @param {string} priceType - Price type to filter by
 * @returns {Array} - Filtered slots
 */
const filterByPriceType = async (slots, priceType) => {
  if (!priceType) return slots;
  
  return slots.filter(slot => {
    if (priceType === "slot_price") {
      return slot.slot_price && slot.slot_price !== "";
    } else if (priceType === "all_slot_price") {
      return slot.all_slot_price && 
             slot.all_slot_price !== "" && 
             slot.slots === slot.remaining_slots;
    }
    return true;
  });
};

/**
 * Filter slots by duration
 * @param {Array} slots - Array of slot objects
 * @param {number} duration - Duration in minutes
 * @returns {Array} - Filtered slots
 */
const filterByDuration = async (slots, duration) => {
  if (!duration) return slots;
  
  return slots.filter(slot => {
    const slotDuration = calculateTimeDifference(slot.from_time, slot.to_time);
    return slotDuration === duration;
  });
};

/**
 * Get available duration options for given slots
 * @param {Array} slots - Array of slot objects
 * @returns {Array} - Array of duration options with value and label
 */
const getAvailableDurations = async (slots) => {
  if (!slots || slots.length === 0) return [];
  
  const slotDurations = slots.map(slot => 
    calculateTimeDifference(slot.from_time, slot.to_time)
  );
  
  const uniqueDurations = [...new Set(slotDurations)].sort((a, b) => a - b);
  
  return uniqueDurations.map(duration => ({
    value: duration,
    label: formatDurationLabel(duration)
  }));
};

/**
 * Apply all filters to slots
 * @param {Array} slots - Array of slot objects
 * @param {Object} filters - Filter options { priceType, duration }
 * @returns {Object} - Filtered results with slots and available durations
 */
  const applyFilters = async (slots) => {
    setError(null);
    
    
    let filteredSlotsData = [...slots];
    
    
    // Apply price type filter
    if (slotPriceType) {
      filteredSlotsData = await filterByPriceType(filteredSlotsData, slotPriceType);
    }

    //console.log('filteredSlotsData', filteredSlotsData);
    

    const availableDurations = await getAvailableDurations(filteredSlotsData);

    let defaultDuration = selectedDuration;

    if(availableDurations.length > 0 && !selectedDuration){
      defaultDuration = availableDurations[0].value;
      setSelectedDuration(defaultDuration);
    }
    // Apply duration filter
    if (defaultDuration) {
      filteredSlotsData = await filterByDuration(filteredSlotsData, defaultDuration);
    }
    

    
    
    setFilteredSlots(filteredSlotsData);
    setDynamicDurationOptions(availableDurations);

    if(filteredSlotsData.length  === 0){
      setError(Ltext("No available slots for the selected date"));
      return false;
    }

    return true;
  };

  // ============================================================================
  // EVENT HANDLERS
  // ============================================================================

  /**
   * Handle service quantity change
   */
  const handleServiceQuantityChange = (serviceName, newQuantity) => {
    if (newQuantity < 0) return;
    
    setSelectedServices(prev => {
      const currentService = prev[serviceName];
      
      if (!currentService || !currentService.selected) {
        return prev;
      }
      
      if (newQuantity === 0) {
        // Remove service if quantity is 0
        const newState = { ...prev };
        delete newState[serviceName];
        return newState;
      }
      
      return {
        ...prev,
        [serviceName]: {
          ...currentService,
          quantity: newQuantity
        }
      };
    });
  };

  /**
   * Handle service selection
   */
  const handleServiceSelect = (service) => {
    setSelectedServices(prev => {
      const currentService = prev[service.name];
      const isCurrentlySelected = currentService?.selected;
      
      if (isCurrentlySelected) {
        // If already selected, deselect it
        const newState = { ...prev };
        delete newState[service.name];
        return newState;
      } else {
        // If not selected, select it with quantity 1
        return {
          ...prev,
          [service.name]: {
            service: service,
            quantity: 1,
            selected: true
          }
        };
      }
    });
    setPriceCalculationRun("service_selection");
  };

  /**
   * Get total selected services count
   */
  const getSelectedServicesCount = () => {
    return Object.values(selectedServices).reduce((total, serviceData) => {
      return total + (serviceData.selected ? serviceData.quantity : 0);
    }, 0);
  };

  /**
   * Handle month change in calendar
   */
  const handleMonthChange = (date) => {
    setCurrentMonth(date);
    const viewDates = getCurrentViewDates(date);
    setCurrentViewDates(viewDates);
    //resetFilters();
  };

  /**
   * Handle slot price type change
   */
  const handleSlotPriceTypeChange = async (priceType, direct = false) => {
    setSlotPriceType(priceType);
    setError(null);
    // setSelectedDuration(null);
    // setDynamicDurationOptions([]);
    // if(!direct){
    //   setFilterRun("slot_price_type_change");
    // }
    await resetFilters(priceType); 
  };



  /**
   * Handle duration selection
   */
  const handleDurationSelect = async (duration, direct = false) => {
    setSelectedDuration(duration);
    setDurationDropdownOpen(false);
    setError(null);
    if(!direct){
      setFilterRun("duration_selection");
    }
  };

  const dateSelectionFunction = async (date, direct = false) => {

    setSelected(date);

    const dateKey = formatDateKey(date);
    
    if (processedBookings[dateKey] && processedBookings[dateKey].length > 0) {

      const adjustedDayOfWeek = getAdjustedDayOfWeek(date);
      
      // Map slots with day information
      // const availableSlots = processedBookings[dateKey].map(slot => ({
      //   ...slot,
      //   from_day: adjustedDayOfWeek,
      //   to_day: adjustedDayOfWeek
      // }));
      const availableSlots = processedBookings[dateKey];     

      // Sort slots by time
      const sortedSlots = [...availableSlots].sort((a, b) => {
        const timeA = a.from_time.split(':').map(Number);
        const timeB = b.from_time.split(':').map(Number);
        return timeA[0] !== timeB[0] ? timeA[0] - timeB[0] : timeA[1] - timeB[1];
      });

      setSelectedDateSlots(sortedSlots);
      setSelectedDate(date);

      if(!direct){
        setFilterRun("date_selection");
      }
      
    } else {
      setSelectedDateSlots([]);
      setSelectedDate(date);
      setError(Ltext("No available slots for the selected date"));
    }

    return true;
    
  }
  

  /**
   * Handle date selection
   */
  const handleDateSelection = async (date) => {

    
    await resetFilters(); 

    await dateSelectionFunction(date);
    
  };

  const getSlotPrice = async () => {

    setError(null);

    setPriceDataLoading(true);
    setPriceData(null);
    setConfirmationData(null);

    if(!selectedSlot){
      setError(Ltext("No slot selected"));
      return false;
    }

    if(!selected){
      setError(Ltext("No date selected"));
      return false;
    }
    setTimeSlotsOpen(false);
    
    

    const priceData = {
      action: 'getSlotPrice',
      listing_id: listing_id,
      slot_text: selectedSlot.slot_text,
      slot_id: selectedSlot.slot_id,
      price_type: slotPriceType,
      start_date: selectedSlot.from_date,
      end_date: selectedSlot.to_date,
      adults: quantity,
      services: selectedServices,
      coupon: couponApplied
    }
    

   

    try {
      const bookingResponse = await axios.post(`${API_BASE_URL}`, priceData);
      if (bookingResponse.data.success && bookingResponse.data.data) {
        setPriceData(bookingResponse.data.data);
        let formData = priceData;
        formData.services = bookingResponse.data.data.services;
        formData.adults = bookingResponse.data.data.adults;
        formData.remaining_slots = bookingResponse.data.data.remaining_slots;
        formData.total_slots = bookingResponse.data.data.total_slots;
        setConfirmationData(formData);
      } else {
        setError(bookingResponse.data.message || 'Booking failed');
      }
    } catch (error) {

      console.log('error', error);
      if(error?.response?.data?.message){
        if(error?.response?.data?.message == "slot_max_guests_error"){
          setError(__dynamicText("Minimum {minAmountGuests} and Maximum {maxAmountGuests} guests allowed", {minAmountGuests: minAmountGuests, maxAmountGuests: error?.response?.data?.errors?.remaining_slots}));
        }else{
          setError(Ltext(error?.response?.data?.message));
        }
      }else if(error?.message){
        setError(Ltext(error?.message));
      }else {
        setError(Ltext("Failed to book slot. Please try again."));
      }

    } finally{
      setPriceDataLoading(false);
    }
    
    

  };

  /**
   * Handle slot selection and booking
   */
  const handleSlotSelection = async (slot, direct = false) => {
    try {
      setError(null);
      setBookingSuccess(false);
      
      // Set the selected slot
      setSelectedSlot(slot);

      if(!direct){
        setPriceCalculationRun("price_slot_selection");
      }
      
     
    } catch (err) {
      setSelectedSlot(null);
      console.error('Error booking slot:', err);
      setError(err.response?.data?.message || Ltext("Failed to book slot. Please try again."));
    }
    return true;
  };

  /**
   * Handle quantity change
   */
  const handleQuantityChange = (newQuantity) => {
    if (newQuantity >= 1) {
      setQuantity(newQuantity);
    }
    
  };

  /**
   * Handle quantity selection
   */
  const handleQuantitySelect = () => {
    setQuantityOpen(false);
    setPriceCalculationRun("quantity_selection");
    // You can add additional logic here when quantity is selected
  };

  /**
   * Handle coupon code change
   */
  const handleCouponCodeChange = (e) => {
    setCouponCode(e.target.value);
    setCouponError(null);
  };

  /**
   * Apply coupon code
   */
  const handleApplyCoupon = async () => {
    if (!couponCode.trim()) {
      setCouponError(Ltext("Please enter a coupon code"));
      return;
    }

    if (!selectedSlot || !selected) {
      setCouponError(Ltext("Please select a slot first"));
      return;
    }

    setCouponLoading(true);
    setCouponError(null);

    const data = {
      action: 'applyCoupon',
      listing_id: listing_id,
      couponCode: couponCode.trim(),
      price: (priceData && priceData.org_total_price) ? priceData.org_total_price : 0,
    };

    try {
      const response = await axios.post(`${API_BASE_URL}`, data);
      if (response.data.success && response.data?.data?.coupon != "") {
        setCouponApplied(response.data.data.coupon);
        setPriceCalculationRun("coupon_applied");
      } else {
        setCouponError(response.data.message || Ltext("Invalid coupon code"));
        setCouponApplied(null);
      }
    } catch (error) {
      setCouponError(error.response?.data?.message || Ltext("Failed to apply coupon. Please try again."));
      setCouponApplied(null);
    } finally {
      setCouponLoading(false);
    }
  };

  /**
   * Remove coupon
   */
  const handleRemoveCoupon = () => {
    setCouponCode("");
    setCouponApplied(null);
    setCouponError(null);
    setPriceCalculationRun("coupon_removed");
  };

  

  /**
   * Handle booking submission
   */
  const handleBooking = async (isDirectCheckout = false) => {
    if (!selectedSlot || !selected) {
      setError(Ltext("Please select a slot first"));
      return;
    }
    if(!confirmationData){
      setError(Ltext("You don't have any booking data. Please try again."));
      return;
    }


    if(firstBookingMinimumGuests && (quantity < firstBookingMinimumGuests) && confirmationData.remaining_slots == confirmationData.total_slots && slotPriceType != "all_slot_price"){
      setError(__dynamicText("Please select at least {guests} guests", {guests: firstBookingMinimumGuests}));
      return;
    }


    if(minAmountGuests && (quantity < minAmountGuests) && slotPriceType != "all_slot_price"){
      setError(__dynamicText("Please select at least {guests} guests", {guests: minAmountGuests}));
      return;
    }
    if(maxAmountGuests && (quantity > maxAmountGuests) && slotPriceType != "all_slot_price"){
      setError(__dynamicText("Please select at most {guests} guests", {guests: maxAmountGuests}));
      return;
    }

    

    setBookingLoading(true);
    setError(null);

    let formData = confirmationData;
    formData.action = 'bookConfirmation';
    formData.current_page_url = window.location.href;
    formData.isDirectCheckout = isDirectCheckout?"true":"false";
    formData.booking_token = bookingToken;

    try {
      const response = await axios.post(`${API_BASE_URL}`, formData);
      if (response.data.success && response.data.data.booking_token) {

        setBookingLoading(false);

        // Show timer banner and set start time
        // setShowTimerBanner(true);
        // setBookingStartTime(new Date());

        handleBookingSuccess(response.data.data.booking_token, {selectedSlot: selectedSlot, quantity: quantity, couponCode: couponCode, couponApplied: couponApplied,  slotPriceType: slotPriceType, selectedDuration: selectedDuration, selectedDate: selectedDate, selectedServices: selectedServices});

        setTimeout(() => {
          setBookingLoading(false);
        }, 1000);

        //setBookingSuccess(true);
      }else if(response.data.success && response.data.data.checkout_url){
        //window.location.href = response.data.data.checkout_url;
      }else {
        setBookingLoading(false);
        setError(response.data.message || Ltext("Failed to book slot. Please try again."));
      }
    } catch (error) {
      setBookingLoading(false);
      setError(error.response?.data?.message || Ltext("Failed to book slot. Please try again."));
    } finally {
      
    }
  };

  const resetFilters = async (priceType = "") => {
    setSelected(null);
    setIsOpen(false);
    setSelectedDuration(null);
    setDynamicDurationOptions([]);
    setSelectedDateSlots([]);
    setFilteredSlots([]);
    if(priceType != ""){
      setSlotPriceType(priceType);
    }else{
      setSlotPriceType(slotPriceType);
    }
    
    setSelectedSlot(null);
    setQuantity(1);
    setQuantityOpen(false);
    setError(null);
    setBookingSuccess(false);
    
    // Reset coupon state
    setCouponCode("");
    setCouponApplied(null);
    setCouponError(null);

    return true;
  }

  // ============================================================================
  // EFFECTS
  // ============================================================================

  // Initialize calendar view dates
  useEffect(() => {
    const initialViewDates = getCurrentViewDates(new Date());
    setCurrentViewDates(initialViewDates);
  }, []);
  
  useEffect(() => {
    if(slotPriceType && processedBookingsFixed){
      const filteredProcessedBookings = filterProcessedBookings(processedBookingsFixed);

     // console.log('filteredProcessedBookings', filteredProcessedBookings);
      setProcessedBookings(filteredProcessedBookings);
      setDateChanged(false);
    }
  }, [slotPriceType, dateChanged]);

  const filterProcessedBookings = (processedBookings) => {
    if (!processedBookings) return processedBookings;
    
    const filteredBookings = {};
    
    Object.keys(processedBookings).forEach(dateKey => {
      const slotsForDate = processedBookings[dateKey];
      
      if (slotPriceType === "slot_price") {
        const filteredSlots = slotsForDate.filter(booking => booking.slot_price && booking.slot_price !== "");
        if (filteredSlots.length > 0) {
          filteredBookings[dateKey] = filteredSlots;
        }
      } else if (slotPriceType === "all_slot_price") {
        const filteredSlots = slotsForDate.filter(booking => booking.all_slot_price && booking.all_slot_price !== "");
        if (filteredSlots.length > 0) {
          filteredBookings[dateKey] = filteredSlots;
        }
      } else {
        filteredBookings[dateKey] = slotsForDate;
      }
    });
    
    return filteredBookings;
  }

  // Notify parent component of view date changes
  useEffect(() => {
    if (currentViewDates.length > 0 && onViewDatesChange && typeof onViewDatesChange === 'function') {
      onViewDatesChange(currentViewDates, currentMonth);
    }
  }, [currentViewDates, currentMonth, onViewDatesChange]);

  // Fetch booking data from API
  useEffect(() => {
    const fetchAvailableDates = async () => {
      try {
        setLoading(true);
        setError(null);
        
        const response = await axios.get(`${API_BASE_URL}?action=get_available_dates&listing_id=${listing_id}`);
        
        if (response.data.success) {
          setBookingSlots(response.data.data.booking_slots);
          setBookingData(response.data.data.booking_data);
          setEnableSlotDuration(response.data.data.enable_slot_duration === "on");
          setEnableSlotPrice(response.data.data.enable_slot_price === "on");
          setSlotPriceLabel(response.data.data.slot_price_label);
          setAllSlotPriceLabel(response.data.data.all_slot_price_label);
          if(response.data.data.services && response.data.data.services.length > 0){
            setServices(response.data.data.services);
          }
          if(response.data.data.additional_service_label_name){
            setAdditionalServiceLabelName(response.data.data.additional_service_label_name);
          }
          if(response.data.data.count_per_guest){
            setCountPerGuest(response.data.data.count_per_guest);
          }
          if(response.data.data.booking_system_service){
            setBookingSystemService(response.data.data.booking_system_service === "on");
          }
          if(response.data.data.min_amount_guests && response.data.data.min_amount_guests != ""){
            setMinAmountGuests(parseInt(response.data.data.min_amount_guests));
          }
          if(response.data.data.max_amount_guests && response.data.data.max_amount_guests != ""){
            setMaxAmountGuests(parseInt(response.data.data.max_amount_guests));
          }
          if(response.data.data.first_booking_minimum_guests && response.data.data.first_booking_minimum_guests != ""){
            setFirstBookingMinimumGuests(parseInt(response.data.data.first_booking_minimum_guests));
          }
          if(response.data.data.taxPercentage && response.data.data.taxPercentage != ""){
            setTaxPercentage(parseInt(response.data.data.taxPercentage));
          }
          if(response.data.data.season_status && response.data.data.season_status == "on"){
            setSeasonStatus(true);
          }
          if(response.data.data.season_discount_data && response.data.data.season_discount_data.length > 0){
            setSeasonDiscountData(response.data.data.season_discount_data);
          }
          if(response.data.data.guest_slot && response.data.data.guest_slot == "yes"){
            setGuestSlot(true);
          }
          if(response.data.data.max_book_days && response.data.data.max_book_days != ""){
            setMaxBookDays(parseInt(response.data.data.max_book_days));
          }
          if(response.data.data.min_book_days && response.data.data.min_book_days != ""){
            setMinBookDays(parseInt(response.data.data.min_book_days));
          }
          if(response.data.data.hide_quantity && response.data.data.hide_quantity == "on"){
            setHideQuantity(true);
          }
          if(response.data.data.hide_price_div && response.data.data.hide_price_div == "on"){
            setHidePriceDiv(true);
          }
        } else {
          throw new Error(response.data.message || 'Failed to fetch available dates');
        }
      } catch (err) {
        setError(Ltext("Failed to load available dates. Please try again."));
      } finally {
        setLoading(false);
      }
    };

    fetchAvailableDates();
  }, [listing_id]);

  // Process booking data when dependencies change
  useEffect(() => {
    if (bookingSlots.length > 0 && currentViewDates.length > 0) {
      const bookedDates = {};
      let has_slot_price = false;
      let has_all_slot_price = false;
      bookingSlots.forEach(slot => {
        processSlotData(slot, bookedDates);
        const slotParts = slot.split("|");
        const [fromDay, fromTime, toDay, toTime, slotPrice, slots, slotId, closed = "0", all_slot_price = "0", slotLabel = "", startDate = "", endDate = ""] = slotParts;

        if(slotPrice != ""){
          has_slot_price = true;
        }
        if(all_slot_price != ""){
          has_all_slot_price = true;
        }
      });
      if(enableSlotPrice && !slotPriceType){
        setShowSlotPriceRadio(has_slot_price && has_all_slot_price);
        setSlotPriceType(has_all_slot_price ? "all_slot_price" : "slot_price");
      }else if(has_all_slot_price && !has_slot_price && !slotPriceType){
        setSlotPriceType("all_slot_price");
      }else if(has_slot_price && !has_all_slot_price && !slotPriceType){
        setSlotPriceType("slot_price");
      }
      
      
      setProcessedBookingsFixed(bookedDates);
      setProcessedBookings(bookedDates);
      setDateChanged(true);
    }
  }, [bookingSlots, bookingData, currentViewDates]);

  useEffect(() => {
    if(prevBookingData && processedBookings){

      const prevBookingFilterRun = async () => {
        if(prevBookingData.slotPriceType){
          await handleSlotPriceTypeChange(prevBookingData.slotPriceType, true);
        }
        
        if(prevBookingData.selectedDate){
          await dateSelectionFunction(prevBookingData.selectedDate, true);
        }
        if(prevBookingData.selectedDuration){
          await handleDurationSelect(prevBookingData.selectedDuration, true);
        }
        if(prevBookingData.selectedSlot){
          await handleSlotSelection(prevBookingData.selectedSlot);
        }
        if(prevBookingData.quantity){
          setQuantity(prevBookingData.quantity);
        }
        if(prevBookingData.couponCode){
          setCouponCode(prevBookingData.couponCode);
        }
        if(prevBookingData.couponApplied){
          setCouponApplied(prevBookingData.couponApplied);
        }

        //console.log('prevBookingData', prevBookingData);

        if(prevBookingData.selectedServices){
         //console.log('prevBookingData.selectedServices', prevBookingData.selectedServices);
          setSelectedServices(prevBookingData.selectedServices);
        }
        setPrevBookingData(null);
        setFilterRun("prev_booking_data");
        
      }
      
      prevBookingFilterRun();
    }
  }, [prevBookingData, processedBookings]);

  // Handle outside clicks
  useEffect(() => {
    const handleClickOutside = (event) => {

      
      
      if (containerRef.current && !containerRef.current.contains(event.target)) {
        setIsOpen(false);
      }
      if (durationDropdownRef.current && !durationDropdownRef.current.contains(event.target)) {
        setDurationDropdownOpen(false);
      }
      if (timeSlotsDropdownRef.current && !timeSlotsDropdownRef.current.contains(event.target)) {
        setTimeSlotsOpen(false);
      }
      if (servicesDropdownRef.current && !servicesDropdownRef.current.contains(event.target)) {
        if(servicesOpen){
          setPriceCalculationRun("service_selection");
        }
        setServicesOpen(false);
      }
      if (quantityDropdownRef.current && !quantityDropdownRef.current.contains(event.target)) {
        if(quantityOpen){
          setPriceCalculationRun("quantity_selection");
        }
        setQuantityOpen(false);
      }
    };

    if (isOpen || durationDropdownOpen || timeSlotsOpen || servicesOpen || quantityOpen) {
      document.addEventListener('mousedown', handleClickOutside);
      document.addEventListener('touchstart', handleClickOutside);
    }

    return () => {
      document.removeEventListener('mousedown', handleClickOutside);
      document.removeEventListener('touchstart', handleClickOutside);
    };
  }, [isOpen, durationDropdownOpen, timeSlotsOpen, servicesOpen, quantityOpen]);

  const filterSlots = async (slots) => {
    if(slots.length > 0){
      const filterResult = await applyFilters(slots);
      return filterResult;
    }else{
      return false;
    }
  }

  // Handle language changes
  useEffect(() => {

    if(selectedDateSlots.length > 0){
      
      //filterSlots(selectedDateSlots);

    }
    const handleLanguageChange = () => {
      setSelectedDateSlots([...selectedDateSlots]);
      
      const newLang = getLanguage();
      if (newLang === 'no' || newLang === 'nb' || newLang === 'nn') {
        registerLocale('nb', nb);
      } else {
        registerLocale('en', en);
      }
    };

    window.addEventListener('rmpLanguageChanged', handleLanguageChange);
    return () => {
      window.removeEventListener('rmpLanguageChanged', handleLanguageChange);
    };
  }, [selectedDateSlots]);


  useEffect(() => {
    if(filterRun && selectedDateSlots.length > 0){
      const runFilter = async () => {
        const hasResult = await filterSlots(selectedDateSlots);
        if(hasResult && filterRun != "prev_booking_data"){
          setTimeSlotsOpen(true);
        }
        if(filterRun != "prev_booking_data"){
          setSelectedSlot(null);
        }
        console.log('filterRun', filterRun);
        setFilterRun("");
      }
      runFilter();
    }
  }, [filterRun, prevBookingData]);

  useEffect(() => {
    if(priceCalculationRun && selectedSlot){
      const runPriceCalculation = async () => {
        await getSlotPrice();
      }
      runPriceCalculation();
      setPriceCalculationRun(false);
    }
  }, [priceCalculationRun]);
 



  // ============================================================================
  // RENDER
  // ============================================================================

  if (loading) {
    return (
      <div className={styles.slotBooking} style={{ maxWidth: 350, margin: '0 auto', position: 'relative' }}>
        <div className={`${styles.header} rmp-slot-booking-header`}>
          <div className='header_top'>
            <strong>{Ltext("Booking")}</strong>
            <hr />
          </div>
          <div style={{ textAlign: 'center', padding: '20px' }}>
            {Ltext("Loading available dates...")}
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className={styles.slotBooking} style={{ maxWidth: 350, margin: '0 auto' }}>
      <div className={`${styles.header} rmp-slot-booking-header`}>
        <div className='header_top'>
          <strong>{Ltext("Booking")}</strong>
          <hr />
        </div>
        
        {/* Error Message */}
        {error && (
          <div style={{ 
            color: '#d32f2f', 
            backgroundColor: '#ffebee', 
            padding: '10px', 
            borderRadius: '4px', 
            marginBottom: '10px',
            fontSize: '13px'
          }}>
            {error}
          </div>
        )}
        
        {/* Success Message */}
        {bookingSuccess && (
          <div style={{ 
            color: '#2e7d32', 
            backgroundColor: '#e8f5e8', 
            padding: '10px', 
            borderRadius: '4px', 
            marginBottom: '10px',
            fontSize: '13px'
          }}>
            {Ltext("Booking successful! You will receive a confirmation email shortly.")}
          </div>
        )}

        {/* Slot Price Type Radio Buttons */}
        {showSlotPriceRadio && (
          <div>
            <SlotPriceTypeRadio
              slotPriceType={slotPriceType}
              onSlotPriceTypeChange={handleSlotPriceTypeChange}
              slotPriceLabel={slotPriceLabel}
              allSlotPriceLabel={allSlotPriceLabel}
              duration={selectedDuration}
            />
          </div>
        )}
        {/* Date Picker */}
        <div className={`${styles.cardInner} rmp-slot-booking`} ref={containerRef}>
          <div className={styles.inputContainer}>
            <input
              className={`${styles.slotBookingInput}`}
              placeholder={Ltext("Select available time")}
              readOnly
              onClick={() => {
                if(selectedDate && selectedDate.getMonth() != currentMonth){
                  handleMonthChange(selectedDate);
                }else{
                  handleMonthChange(minBookDays ? new Date(Date.now() + minBookDays * 24 * 60 * 60 * 1000) : new Date());
                }
                setIsOpen(true);
              }}
              style={{
                width: '100%',
                padding: '13px 16px',
                fontFamily: "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif",
                borderRadius: '8px',
                border: '1px solid #e0e0e0',
                fontWeight: 600,
                cursor: 'pointer',
                outline: 'none',
                boxShadow: 'none',
                transition: 'border 0.2s',
                fontSize: '13px',
                color: '#333',
                boxShadow: '0 2px 8px rgba(0, 0, 0, 0.1)',
              }}
              value={selected ? selected.toLocaleDateString('nb-NO', { 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
              }) : ''}
            />
          </div>
          
          {/* Calendar */}
          {isOpen && (
            <div className={styles.calendarContainer}>
              <DatePicker
                selected={selected}
                onChange={handleDateSelection}
                locale={locale}
                minDate={minBookDays ? new Date(Date.now() + minBookDays * 24 * 60 * 60 * 1000) : new Date()}
                maxDate={maxBookDays ? new Date(Date.now() + maxBookDays * 24 * 60 * 60 * 1000) : new Date(Date.now() + 24 * 30 * 24 * 60 * 60 * 1000)}
                filterDate={isDateEnabled}
                calendarStartDay={1}
                dateFormat="dd.MM.yyyy"
                inline
                popperPlacement="bottom"
                popperClassName="rmp-slot-booking-popper"
                onMonthChange={handleMonthChange}
                renderCustomHeader={({ date, decreaseMonth, increaseMonth, prevMonthButtonDisabled, nextMonthButtonDisabled }) => {
                  const calendarLocale = locale === 'nb' ? 'nb-NO' : 'en-US';
                  const monthLabel = new Date(date).toLocaleDateString(calendarLocale, { month: 'long' });
                  return (
                    <div className={styles.calendarHeader}>
                      <button
                        type="button"
                        className={styles.calendarNavBtn+` ${styles.calendarNavBtnPrevious}`}
                        onClick={decreaseMonth}
                        disabled={prevMonthButtonDisabled}
                        aria-label={Ltext("Previous month")}
                      >
                        {/* <span aria-hidden="true">&lt;</span> */}
                      </button>
                      <div className={styles.calendarMonthYear}>
                        <div className={styles.calendarMonth}>{monthLabel}</div>
                        <div className={styles.calendarYear}>{date.getFullYear()}</div>
                      </div>
                      <button
                        type="button"
                        className={styles.calendarNavBtn+` ${styles.calendarNavBtnNext}`}
                        onClick={increaseMonth}
                        disabled={nextMonthButtonDisabled}
                        aria-label={Ltext("Next month")}
                      >
                        {/* <span aria-hidden="true">&gt;</span> */}
                      </button>
                    </div>
                  );
                }}
                renderDayContents={(day, date) => {
                  const isEnabled = isDateEnabled(date);
                  return (
                    <div style={{
                      position: 'relative',
                      width: '70%',
                      height: '70%',
                      display: 'flex',
                      alignItems: 'center',
                      justifyContent: 'center'
                    }}>
                      {day}
                      {!isEnabled && (
                        <div style={{
                          position: 'absolute',
                          top: '50%',
                          left: '0',
                          right: '0',
                          height: '2px',
                          backgroundColor: '#b3b3b3',
                          transform: 'rotate(-45deg)',
                          opacity: 0.7
                        }} />
                      )}
                    </div>
                  );
                }}
              />
            </div>
          )}
        </div>
        
        
        {/* Duration Dropdown */}
        {enableSlotDuration && selected && dynamicDurationOptions.length > 0 && (
          <div>
            <DurationDropdown
              selectedDuration={selectedDuration}
              onDurationSelect={handleDurationSelect}
              isOpen={durationDropdownOpen}
              onToggle={() => setDurationDropdownOpen(!durationDropdownOpen)}
              durationOptions={dynamicDurationOptions}
              durationDropdownRef={durationDropdownRef}
            />
          </div>
        )}
        
        {/* Time Slots Dropdown */}
        {selected && filteredSlots.length > 0 && (
          <TimeSlotsDropdown
            slots={filteredSlots}
            onSlotSelect={handleSlotSelection}
            isOpen={timeSlotsOpen}
            onToggle={() => setTimeSlotsOpen(!timeSlotsOpen)}
            timeSlotsDropdownRef={timeSlotsDropdownRef}
            slotPriceType={slotPriceType}
            selectedSlot={selectedSlot}
            taxPercentage={taxPercentage}
            hideQuantity={hideQuantity}
            hidePriceDiv={hidePriceDiv}
          />
        )}

        {selected && services.length > 0 && selectedSlot && (
          <ServicesDropdown 
            services={services}
            selectedServices={selectedServices}
            onServiceSelect={handleServiceSelect}
            onQuantityChange={handleServiceQuantityChange}
            isOpen={servicesOpen}
            servicesDropdownRef={servicesDropdownRef}
            onToggle={() => {
              if(servicesOpen){
                setPriceCalculationRun("service_selection");
              }
              setServicesOpen(!servicesOpen)
            }}
            additionalServiceLabelName={additionalServiceLabelName}
          />
        )}

        {/* Quantity Dropdown */}
        {selected && selectedSlot && slotPriceType != "all_slot_price" && (
          <QuantityDropdown
            quantity={quantity}
            onQuantityChange={handleQuantityChange}
            onQuantitySelect={handleQuantitySelect}
            isOpen={quantityOpen}
            onToggle={() => {
              if(quantityOpen){
                setPriceCalculationRun("quantity_selection");
              }
              setQuantityOpen(!quantityOpen)
            }}
            quantityDropdownRef={quantityDropdownRef}
            hideQuantity={hideQuantity}
          />
        )}

        {/* Coupon Field */}
        {selected && selectedSlot && (
          <CouponField
            couponCode={couponCode}
            onCouponCodeChange={handleCouponCodeChange}
            onApplyCoupon={handleApplyCoupon}
            onRemoveCoupon={handleRemoveCoupon}
            couponApplied={couponApplied}
            couponLoading={couponLoading}
            couponError={couponError}
          />
        )}

        {priceData && selectedSlot && (
          <>
            {/* Direct Checkout with Dintero Button */}
            {!bookingLoading && (
              <div className={styles.bookingButtonContainer} style={{ marginBottom: '15px' }}>
                <button
                  className={styles.directCheckoutButton}
                  onClick={() => handleBooking(true)}
                  disabled={bookingLoading}
                  style={{
                    width: '100%',
                    padding: '18px 20px',
                    fontSize: '16px',
                    fontFamily: "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif",
                    borderRadius: '12px',
                    border: 'none',
                    background: 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
                    color: 'white',
                    fontWeight: 700,
                    cursor: bookingLoading ? 'not-allowed' : 'pointer',
                    outline: 'none',
                    transition: 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)',
                    opacity: bookingLoading ? 0.7 : 1,
                    marginBottom: '15px',
                    display: 'none',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                    gap: '12px',
                    boxShadow: '0 8px 25px rgba(102, 126, 234, 0.3)',
                    position: 'relative',
                    overflow: 'hidden'
                  }}
                  onMouseEnter={(e) => {
                    if (!bookingLoading) {
                      e.target.style.transform = 'translateY(-2px)';
                      e.target.style.boxShadow = '0 12px 35px rgba(102, 126, 234, 0.4)';
                    }
                  }}
                  onMouseLeave={(e) => {
                    if (!bookingLoading) {
                      e.target.style.transform = 'translateY(0)';
                      e.target.style.boxShadow = '0 8px 25px rgba(102, 126, 234, 0.3)';
                    }
                  }}
                >
                  {/* Background pattern overlay */}
                  <div style={{
                    position: 'absolute',
                    top: 0,
                    left: 0,
                    right: 0,
                    bottom: 0,
                    background: 'linear-gradient(45deg, rgba(255,255,255,0.1) 25%, transparent 25%), linear-gradient(-45deg, rgba(255,255,255,0.1) 25%, transparent 25%), linear-gradient(45deg, transparent 75%, rgba(255,255,255,0.1) 75%), linear-gradient(-45deg, transparent 75%, rgba(255,255,255,0.1) 75%)',
                    backgroundSize: '20px 20px',
                    backgroundPosition: '0 0, 0 10px, 10px -10px, -10px 0px',
                    opacity: 0.3
                  }} />
                  
                  {/* Left side content */}
                  <div style={{ display: 'flex', alignItems: 'center', gap: '12px', zIndex: 1 }}>
                    {/* Payment icon */}
                    <div style={{
                      background: 'rgba(255, 255, 255, 0.2)',
                      borderRadius: '50%',
                      padding: '8px',
                      display: 'flex',
                      alignItems: 'center',
                      justifyContent: 'center',
                      backdropFilter: 'blur(10px)'
                    }}>
                      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M20 4H4C2.89 4 2.01 4.89 2.01 6L2 18C2 19.11 2.89 20 4 20H20C21.11 20 22 19.11 22 18V6C22 4.89 21.11 4 20 4ZM20 18H4V12H20V18ZM20 8H4V6H20V8Z" fill="currentColor"/>
                        <path d="M6 14H8V16H6V14Z" fill="currentColor"/>
                        <path d="M10 14H12V16H10V14Z" fill="currentColor"/>
                        <path d="M14 14H16V16H14V14Z" fill="currentColor"/>
                      </svg>
                    </div>
                    {/* Button text */}
                    <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'flex-start', lineHeight: '1.2' }}>
                      {/* <span style={{ fontSize: '14px', opacity: 0.9, fontWeight: 500 }}>
                        {Ltext("Secure Payment")}
                      </span> */}
                      <span style={{ fontSize: '18px', fontWeight: 700 }}>
                        {Ltext("Checkout Now")} - {priceData.org_total_price} kr
                      </span>
                    </div>
                  </div>
                  {/* Arrow icon */}
                  <div style={{
                    background: 'rgba(255, 255, 255, 0.2)',
                    borderRadius: '50%',
                    padding: '6px',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    backdropFilter: 'blur(10px)',
                    zIndex: 1
                  }}>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                      <path d="M5 12H19M19 12L12 5M19 12L12 19" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
                    </svg>
                  </div>
                </button>
              </div>
            )}

            {/* Regular Booking Button */}
            <div className={styles.bookingButtonContainer} style={{ marginBottom: '15px' }}>
              <button
                className={styles.bookingButton}
                onClick={() => handleBooking(false)}
                disabled={bookingLoading}
                style={{
                  width: '100%',
                  padding: '14px 16px',
                  fontSize: '16px',
                  fontFamily: "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif",
                  borderRadius: '8px',
                  border: 'none',
                  background: '#1a9a94',
                  color: 'white',
                  fontWeight: 600,
                  cursor: bookingLoading ? 'not-allowed' : 'pointer',
                  outline: 'none',
                  transition: 'background-color 0.2s',
                  opacity: bookingLoading ? 0.7 : 1,
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                  gap: '8px'
                }}
                onMouseEnter={(e) => {
                  if (!bookingLoading) {
                    e.target.style.backgroundColor = '#1a9a94';
                  }
                }}
                onMouseLeave={(e) => {
                  if (!bookingLoading) {
                    e.target.style.backgroundColor = '#1a9a94';
                  }
                }}
              >
                {bookingLoading && (
                  <div 
                    className={styles.loadingSpinner} 
                    style={{
                      width: '16px',
                      height: '16px',
                      border: '2px solid rgba(255, 255, 255, 0.3)',
                      borderTop: '2px solid white',
                      borderRadius: '50%',
                      animation: 'spin 1s linear infinite'
                    }}
                  />
                )}
                {bookingLoading ? Ltext("Booking...") : Ltext("Book Now")}
              </button>
            </div>

            {/* Price Data */}
            {!hidePriceDiv && (
              <div className={styles.priceData}>
                {/* {priceData.totalPrice && priceData.totalPrice > 0 ? (
                  <div className={`${styles.priceDataItem} ${styles.dFlex} ${styles.justifyContentBetween} ${styles.alignItemsCenter}`}>
                    <span className={styles.priceDataItemLabel}>{Ltext("Slot price")} {priceData.adults > 1 && priceData.price_type != "all_slot_price" && "("+priceData.adults+" "+Ltext("Adults")+")"}</span>
                    <span className={styles.priceDataItemValue}>{priceData.totalPrice} kr</span>
                  </div>
                ):''}
                {priceData?.tax > 0 ? (
                  <div className={`${styles.priceDataItem} ${styles.dFlex} ${styles.justifyContentBetween} ${styles.alignItemsCenter}`}>
                    <span className={styles.priceDataItemLabel}>{Ltext("Tax")} ({Ltext("Included")})</span>
                    <span className={styles.priceDataItemValue}>{priceData.tax} kr</span>
                  </div>
                ):''} */}
                {priceData.services.length > 0 ? (
                  <>
                    {priceData.services.map((srv, idx) => (
                      <div 
                        key={idx} 
                        className={`${styles.priceDataItem} ${styles.dFlex} ${styles.justifyContentBetween} ${styles.alignItemsCenter}`}
                      >
                        <span className={styles.priceDataItemLabel}>
                          {/* Show service name and, if countable and quantity > 1, "(xN)" */}
                          {srv.name}
                          {srv.quantity > 1 ? ` (x${srv.quantity})` : ''}
                        </span>
                        <span className={styles.priceDataItemValue}>
                          {srv.price ? Math.round(srv.price) : Math.round(srv.service_price)} kr 
                          {/* {srv.tax > 0  ? "("+Ltext("Included Tax")+")" : ""} */}
                        </span>
                      </div>
                    ))}
                  </>
                ):''}
                
                {/* {priceData.services.length > 0 && priceData.total_service_price && priceData.total_service_price > 0 ? (
                  <div className={`${styles.priceDataItem} ${styles.dFlex} ${styles.justifyContentBetween} ${styles.alignItemsCenter}`}>
                    <span className={styles.priceDataItemLabel}>{Ltext("Service Price")}</span>
                    <span className={styles.priceDataItemValue}>{priceData.total_service_price} kr ({Ltext("Included Tax")})</span>
                  </div>
                ):''} */}
                {couponApplied && priceData.coupon_discount && priceData.coupon_discount > 0 ? (
                  <div className={`${styles.priceDataItem} ${styles.dFlex} ${styles.justifyContentBetween} ${styles.alignItemsCenter}`} style={{ color: '#2e7d32' }}>
                    <span className={styles.priceDataItemLabel}>{Ltext("Coupon discount")}</span>
                    <span className={styles.priceDataItemValue}>-{priceData.coupon_discount} kr</span>
                  </div>
                ):''}

                {priceData.season_discount && priceData.season_discount > 0 ? (
                  <div className={`${styles.priceDataItem} ${styles.dFlex} ${styles.justifyContentBetween} ${styles.alignItemsCenter}`}>
                    <span className={styles.priceDataItemLabel}>{Ltext("Extra discount")}</span>
                    <span className={styles.priceDataItemValue}>-{priceData.season_discount} kr</span>
                  </div>
                ):''}

                {priceData.org_total_price && priceData.org_total_price > 0 ? (
                  <div className={`${styles.priceDataItem} ${styles.dFlex} ${styles.justifyContentBetween} ${styles.alignItemsCenter}`}>
                    <span className={styles.priceDataItemLabel}>{Ltext("Total price")}</span>
                    <span className={styles.priceDataItemValue}>{priceData.org_total_price} kr</span>
                  </div>
                ):(priceData && priceData.org_total_price === 0 && (
                  <div className={`${styles.priceDataItem} ${styles.dFlex} ${styles.justifyContentBetween} ${styles.alignItemsCenter}`}>
                    <span className={styles.priceDataItemLabel}>{Ltext("Total price")}</span>
                    <span className={styles.priceDataItemValue}>{Ltext("Free")}</span>
                  </div>
                ))}
              </div> 
            )}
          </>
        )}
        {priceDataLoading && (
          <div className={styles.priceData}>
            <div className={`${styles.priceDataItem} ${styles.loadingItem} ${styles.dFlex} ${styles.justifyContentCenter} ${styles.alignItemsCenter}`}>
              <span className={styles.priceDataItemValue}>
                <div className={styles.loadingSpinner}></div>
                {Ltext("Loading...")}
              </span>
            </div>
          </div>
        )}

        {/* Season Discount Banner - Bottom */}
        {seasonDiscountData && seasonDiscountData.length > 0 && (
          <div style={{
            background: 'linear-gradient(135deg, #008474 0%, #009688 100%)',
            color: 'white',
            padding: '15px',
            borderRadius: '8px',
            marginTop: '20px',
            marginBottom: '15px',
            boxShadow: '0 4px 15px rgba(0,132,116,0.2)',
            position: 'relative',
            overflow: 'hidden'
          }}>
            <div style={{
              position: 'absolute',
              top: '-10px',
              right: '-10px',
              width: '40px',
              height: '40px',
              background: 'rgba(255,255,255,0.2)',
              borderRadius: '50%',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center'
            }}>
              <span style={{ fontSize: '20px' }}>🎉</span>
            </div>
            
            <div style={{ marginBottom: '10px' }}>
              <strong style={{ fontSize: '16px' }}>
                {Ltext("Special Season Offers!")}
              </strong>
            </div>
            
            {seasonDiscountData.map((season, index) => (
              <div key={index} style={{
                backgroundColor: 'rgba(255,255,255,0.15)',
                padding: '8px 12px',
                borderRadius: '6px',
                marginBottom: '8px',
                fontSize: '14px'
              }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                  <span>
                    <strong>{season.season_name}</strong>
                  </span>
                  <span style={{
                    backgroundColor: 'rgba(255,255,255,0.2)',
                    padding: '4px 8px',
                    borderRadius: '4px',
                    fontSize: '12px',
                    fontWeight: 'bold'
                  }}>
                    {season.season_price_percent}% {Ltext("OFF")}
                  </span>
                </div>
                <div style={{ fontSize: '12px', opacity: 0.9, marginTop: '4px' }}>
                  {Ltext("Valid")}: {season.season_price_from} - {season.season_price_to}
                </div>
              </div>
            ))}
            
            <div style={{
              fontSize: '12px',
              opacity: 0.8,
              fontStyle: 'italic',
              marginTop: '8px'
            }}>
              {Ltext("Book now and save big!")}
            </div>
          </div>
        )}
      </div>
    </div>
  );
}


// ============================================================================
// COMPONENTS
// ============================================================================

/**
 * Slot Price Type Radio Buttons Component
 */
const SlotPriceTypeRadio = ({ 
  slotPriceType, 
  onSlotPriceTypeChange, 
  slotPriceLabel, 
  allSlotPriceLabel,
  duration
}) => {
  return (
    <div className={styles.slotPriceTypeContainer}>
      <div className={styles.radioGroup}>
        <label className={styles.radioOption}>
          <input
            type="radio"
            name="slotPriceType"
            value="slot_price"
            checked={slotPriceType === "slot_price"}
            onChange={(e) => onSlotPriceTypeChange(e.target.value)}
          />
          <span>{slotPriceLabel || Ltext("Drop in")}</span>
        </label>
        
        <label className={styles.radioOption}>
          <input
            type="radio"
            name="slotPriceType"
            value="all_slot_price"
            checked={slotPriceType === "all_slot_price"}
            onChange={(e) => onSlotPriceTypeChange(e.target.value)}
          />
          <span>{allSlotPriceLabel || Ltext("Private")}</span>
        </label>
      </div>
    </div>
  );
};

/**
 * Duration Dropdown Component
 */
const DurationDropdown = ({ 
  selectedDuration, 
  onDurationSelect, 
  isOpen, 
  onToggle, 
  durationOptions = [], 
  durationDropdownRef 
}) => {

  if(durationOptions.length <= 1){
    return null;
  }

  return (
    <div className={styles.durationDropdownContainer} ref={durationDropdownRef}>
      <div
        className={
          `${styles.durationDropdown} ${isOpen ? styles.activeDurationDropdown : ''}`.trim()
        }
      >
        <div className={styles.durationHeader} onClick={onToggle}>
          <span>
            {selectedDuration 
              ? durationOptions.find(opt => opt.value === selectedDuration)?.label 
              : Ltext("Select duration")
            }
          </span>
          <span className={isOpen ? styles.open : ''}>▼</span>
        </div>
        
        {isOpen && (
          <div className={styles.durationContent}>
            {durationOptions.length > 0 ? (
              durationOptions.map((option) => (
                <div 
                  key={option.value}
                  className={`${styles.durationItem} ${selectedDuration === option.value ? styles.selected : ''}`}
                  onClick={() => onDurationSelect(option.value)}
                  style={{ cursor: 'pointer' }}
                >
                  <span>{option.label}</span>
                  {selectedDuration === option.value && (
                    <span className={styles.checkmark}>✓</span>
                  )}
                </div>
              ))
            ) : (
              <div className={styles.durationItem} style={{ color: '#999', cursor: 'default' }}>
                {Ltext("No durations available")}
              </div>
            )}
          </div>
        )}
      </div>
    </div>
  );
};

/**
 * Time Slots Dropdown Component
 */
const TimeSlotsDropdown = ({ 
  slots, 
  onSlotSelect, 
  isOpen, 
  onToggle, 
  timeSlotsDropdownRef,
  slotPriceType = "slot_price",
  selectedSlot = null,
  taxPercentage = 0,
  hideQuantity = false,
  hidePriceDiv = false
}) => {
  if (!slots || slots.length === 0) {
    return null;
  }

  return (
    <div className={styles.slotMainGibbs}> 
      <div
        className={
          `${styles.timeSlotsDropdown} ${isOpen ? styles.activeTimeSlotsDropdown : ''}`.trim()
        }
        ref={timeSlotsDropdownRef}
      >
        <div className={styles.timeSlotsHeader} onClick={onToggle}>
            <span>
            {selectedSlot
              ? selectedSlot.from_day === selectedSlot.to_day
                ? DAY_NAMES[selectedSlot.from_day] + " " + selectedSlot.from_time + " - " + selectedSlot.to_time
                : DAY_NAMES[selectedSlot.from_day] + " " + selectedSlot.from_time + " - " + DAY_NAMES[selectedSlot.to_day] + " " + selectedSlot.to_time
              : Ltext("Time Slots")}
            </span>
            <span className={isOpen ? styles.open : ''}>▼</span>
        </div>
        
        {isOpen && (
          <div className={styles.timeSlotsContent}>
            {slots.map((slot, index) => {
              const displayPrice = slotPriceType === "all_slot_price" ? slot.all_slot_price : slot.slot_price;

              let displayPriceTax = displayPrice;

              if(taxPercentage > 0){
                displayPriceTax = Number(displayPriceTax);
                const tax = (displayPriceTax * taxPercentage) / 100;
                displayPriceTax = displayPriceTax + tax;

              }
              displayPriceTax = Math.round(displayPriceTax);

              return (
                <div 
                  key={index}
                  className={styles.timeSlotItem}
                  onClick={() => onSlotSelect(slot)}
                >
                  {slot.slot_label && slot.slot_label !== "" ? (
                    <div>{slot.slot_label}</div>
                  ) : (
                    <>
                    </>
                  )}
                  <div>
                    {slot.from_day === slot.to_day
                      ? `${DAY_NAMES[slot.from_day]} ${slot.from_time} - ${slot.to_time}`
                      : `${DAY_NAMES[slot.from_day]} ${slot.from_time} - ${DAY_NAMES[slot.to_day]} ${slot.to_time}`}
                  </div>
                  {/* <div>{displayPriceTax} kr {taxPercentage && taxPercentage > 0 ? "("+Ltext("Inc. Tax")+")" : ""}</div> */}
                  {!hidePriceDiv ? (
                    <div>{displayPriceTax} kr</div>
                  ) : (
                    <div></div>
                  )}
                  {!hideQuantity && (
                    <div>
                      {slot.slots && slot.slots > 1 ? <>
                        {(slotPriceType !== "all_slot_price") ? slot.remaining_slots + "/" + slot.slots + " " + Ltext("Available") : ""}
                      </> : ""}
                    </div>
                  )}
                </div>
              );
            })}
          </div>
        )}
      </div>
    </div>
  );
};

/**
 * Services Component
 */
const ServicesDropdown = ({ 
  services, 
  selectedServices, 
  onServiceSelect, 
  onQuantityChange, 
  isOpen, 
  servicesDropdownRef,
  onToggle,
  additionalServiceLabelName
}) => {
  if (!services || services.length === 0) {
    return null;
  }

  const selectedCount = Object.values(selectedServices).length;

  return (
    <div className={`${styles.servicesContainer} ${isOpen ? styles.activeServicesDropdown : ''}`.trim()} ref={servicesDropdownRef}>
      <div className={`${styles.servicesHeader} ${styles.dFlex} ${styles.justifyContentBetween} ${styles.alignItemsCenter}`} onClick={onToggle}>
        <div className={`${styles.servicesHeaderLeft} ${styles.dFlex} ${styles.alignItemsCenter}`}>
          <span>{Ltext(additionalServiceLabelName) || Ltext("Services")}</span>
          <span className={styles.servicesCounter}>{selectedCount}</span>
        </div>
        <div className={`${styles.servicesHeaderRight} ${styles.dFlex} ${styles.alignItemsCenter}`}>
          <span className={isOpen ? styles.open : ''}>▼</span>
        </div>
      </div>
      
      {isOpen && (
        <div className={styles.servicesContent}>
          {services.map((service, index) => {
            const isSelected = selectedServices[service.name]?.selected;
            const quantity = selectedServices[service.name]?.quantity || 0;

            let service_tax = parseInt(service.tax);
            let service_price = parseFloat(service.price);

            if(service_tax > 0){
              service_price = service_price + (service_price * (service_tax / 100));
            }
            service_price = Math.round(service_price);
            
            return (
              <div key={index} className={styles.serviceItem}>
                <div className={styles.serviceInfo}>
                  <div className={styles.serviceName}>{service.name}</div>
                  <div className={styles.servicePrice}>{service_price} kr</div>
                </div>
                
                {service.bookable_quantity === "on" && isSelected && (
                  <div className={styles.quantitySelector}>
                    <button 
                      className={`${styles.quantityBtn} ${styles.minus}`}
                      onClick={() => onQuantityChange(service.name, quantity - 1)}
                      disabled={quantity <= 0}
                    >
                      -
                    </button>
                    <span className={styles.quantityDisplay}>{quantity}</span>
                    <button 
                      className={`${styles.quantityBtn} ${styles.plus}`}
                      onClick={() => onQuantityChange(service.name, quantity + 1)}
                    >
                      +
                    </button>
                  </div>
                )}
                
                <button 
                  className={`${styles.selectBtn} ${isSelected ? styles.selected : ''}`}
                  onClick={() => onServiceSelect(service)}
                >
                  {isSelected ? Ltext("Remove") : Ltext("Select")}
                </button>
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
};

/**
 * Quantity Dropdown Component
 */
const QuantityDropdown = ({ 
  quantity, 
  onQuantityChange, 
  onQuantitySelect, 
  isOpen, 
  onToggle, 
  quantityDropdownRef,
  hideQuantity = false
}) => {
  return (
    <div className={styles.quantityDropdownContainer} ref={quantityDropdownRef} style={{ display: hideQuantity ? 'none' : 'block' }}>
      <div className={`${styles.quantityDropdown} ${isOpen ? styles.activeQuantityDropdown : ''}`.trim()}>
        <div className={styles.quantityHeader} onClick={onToggle}>
          <div className={styles.quantityHeaderLeft}>
            <span>{Ltext("Quantity")}</span>
            <span className={styles.quantityCounter}>{quantity}</span>
          </div>
          <div className={styles.quantityHeaderRight}>
            <span className={isOpen ? styles.open : ''}>▼</span>
          </div>
        </div>
        
        {isOpen && (
          <div className={styles.quantityContent}>
            <div className={styles.quantitySelectorMain}>
              <button 
                className={`${styles.quantityBtn} ${styles.minus}`}
                onClick={() => onQuantityChange(quantity - 1)}
                disabled={quantity <= 1}
              >
                -
              </button>
              <input
                type="number"
                className={styles.quantityInput}
                value={quantity}
                onChange={(e) => {
                  const newQuantity = parseInt(e.target.value) || 1;
                  if (newQuantity >= 1) {
                    onQuantityChange(newQuantity);
                  }
                }}
                min="1"
                style={{
                  width: '60px',
                  textAlign: 'center',
                  padding: '8px',
                  border: '1px solid #ddd',
                  borderRadius: '4px',
                  fontSize: '14px',
                  margin: '0 4px'
                }}
              />
              <button 
                className={`${styles.quantityBtn} ${styles.plus}`}
                onClick={() => onQuantityChange(quantity + 1)}
              >
                +
              </button>
              <button 
                className={styles.selectQuantityBtn}
                onClick={onQuantitySelect}
              >
                {Ltext("Select")}
              </button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
};

/**
 * Coupon Field Component
 */
const CouponField = ({
  couponCode,
  onCouponCodeChange,
  onApplyCoupon,
  onRemoveCoupon,
  couponApplied,
  couponLoading,
  couponError
}) => {
  return (
    <div className={styles.couponFieldContainer}>
      <div className={styles.couponQuestion}>
        {Ltext("Do you have a coupon or gift card?")}
      </div>
      
      <div className={styles.couponInputWrapper}>
        <div className={styles.couponInputContainer}>
          <input
            type="text"
            className={styles.couponInput}
            placeholder={Ltext("Enter your code here")}
            value={couponCode}
            onChange={onCouponCodeChange}
            onKeyDown={(e) => {
              if (e.key === 'Enter' && !couponApplied && !couponLoading && couponCode.trim()) {
                onApplyCoupon();
              }
            }}
            disabled={couponApplied || couponLoading}
          />
          
          {couponApplied ? (
            <button
              className={styles.couponRemoveBtn}
              onClick={onRemoveCoupon}
            >
              {Ltext("Remove")}
            </button>
          ) : (
            <button
              className={styles.couponApplyBtn}
              onClick={onApplyCoupon}
              disabled={couponLoading || !couponCode.trim()}
            >
              {couponLoading ? Ltext("Applying...") : Ltext("Apply")}
            </button>
          )}
        </div>
      </div>
      
      {couponError && (
        <div className={styles.couponError}>
          {Ltext(couponError)}
        </div>
      )}
      
      {couponApplied && (
        <div className={styles.couponSuccess}>
          {Ltext(couponApplied)}
        </div>
      )}
    </div>
  );
};

export default SlotBooking; 