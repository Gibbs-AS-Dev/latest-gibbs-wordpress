import React, { useState, useEffect, useCallback, useMemo, useRef } from 'react';
import { createPortal } from 'react-dom';
import axios from 'axios';
import Table from '../components/Table';
import Tabs from '../components/Tabs';
import Button from '../components/Button';
import Pagination from '../components/Pagination';
import CreateCustomer from './CreateCustomer';
import EditCustomer from './EditCustomer';
import EditUsergroup from './EditUsergroup';
import styles from '../assets/scss/GibbsCustomer.module.scss';
import '../assets/scss/GibbsCustomer.scss';
import Select from 'react-select';
import DatePicker from 'react-datepicker';
import 'react-datepicker/dist/react-datepicker.css';
import { Ltext } from '../utils/customer-translations';

function GibbsCustomer({ apiUrl, user_token, owner_id }) {
  const [customers, setCustomers] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [activeTab, setActiveTab] = useState('active');
  const [searchQuery, setSearchQuery] = useState('');
  const [expandedRows, setExpandedRows] = useState(new Set());
  const [page, setPage] = useState(1);
  const [paginationMeta, setPaginationMeta] = useState({ total: 0, totalPages: 1 });
  const [sortConfig, setSortConfig] = useState({ key: 'created_at', direction: 'desc' });
  const [filterCount, setFilterCount] = useState(0);
  const [isFilterOpen, setIsFilterOpen] = useState(false);
  const [isSettingsOpen, setIsSettingsOpen] = useState(false);
  const [isShowRowsOpen, setIsShowRowsOpen] = useState(false);
  const [isColumnsOpen, setIsColumnsOpen] = useState(false);
  const [hoveredRowIndex, setHoveredRowIndex] = useState(null);
  const [selectedHideColumns, setSelectedHideColumns] = useState([]);
  const [rowsPerPage, setRowsPerPage] = useState(10);
  // Applied status filter (used in API params)
  const [statusFilter, setStatusFilter] = useState('all');
  const [countryFilter, setCountryFilter] = useState('all');
  const [industryFilter, setIndustryFilter] = useState('all');
  // Draft values in the dropdown before clicking "Filter"
  //const [statusFilterDraft, setStatusFilterDraft] = useState('all');
  const [countryFilterDraft, setCountryFilterDraft] = useState('all');
  const [industryFilterDraft, setIndustryFilterDraft] = useState('all');
  const preferencesLoadedRef = useRef(false);

  const [groupLicenses, setGroupLicenses] = useState([]);
  const [groupLicenseSelections, setGroupLicenseSelections] = useState({});
  const [savingGroupLicenses, setSavingGroupLicenses] = useState({});
  const groupLicenseSaveTimeouts = useRef({});

  const [nextInvoiceDates, setNextInvoiceDates] = useState({});
  const [savingNextInvoice, setSavingNextInvoice] = useState({});
  const nextInvoiceSaveTimeouts = useRef({});
  const [openDatePicker, setOpenDatePicker] = useState(null); // Track which DatePicker is open (by superadmin ID)
  const [datePickerPosition, setDatePickerPosition] = useState(null); // Position for DatePicker portal
  const [datePickerData, setDatePickerData] = useState(null); // Data for the open DatePicker (superadmin, date, etc.)
  const [revenueValues, setRevenueValues] = useState({});
  const [savingRevenue, setSavingRevenue] = useState({});
  const revenueSaveTimeouts = useRef({});
  const [revenueEditMode, setRevenueEditMode] = useState({});
  const [switchingUser, setSwitchingUser] = useState({});

  const [callSavePreferences, setCallSavePreferences] = useState(false);
  const [isCreateCustomerOpen, setIsCreateCustomerOpen] = useState(false);
  const [isEditCustomerOpen, setIsEditCustomerOpen] = useState(false);
  const [editingCustomer, setEditingCustomer] = useState(null);
  const [isEditUsergroupOpen, setIsEditUsergroupOpen] = useState(false);
  const [editingUsergroup, setEditingUsergroup] = useState(null);
  const [countries, setCountries] = useState([]);
  const [industries, setIndustries] = useState([]);

  const [packages, setPackages] = useState([]);

  const [customerColumns, setCustomerColumns] = useState(window.customerListData.columns);
  const [customerActions, setCustomerActions] = useState(window.customerListData.actions);
  const [salesRepRole, setSalesRepRole] = useState(window.customerListData.sales_rep_role);
  const [selectedCountries, setSelectedCountries] = useState(window.customerListData.selected_countries);


  const tabs = [
    { id: 'active', label: Ltext('Active'), count: paginationMeta.total },
    { id: 'invoice', label: Ltext('Invoice'), count: paginationMeta.total },
    { id: 'stripe', label: Ltext('Stripe'), count: paginationMeta.total },
    { id: 'inactive', label: Ltext('Inactive'), count: paginationMeta.total },
    { id: 'all', label: Ltext('All'), count: paginationMeta.total },
  ];

  // Add custom CSS to head
  useEffect(() => {
    const style = document.createElement('style');
    style.textContent = `
      #wrapper, html {
        background-color: #F2F3F7 !important;
      }
    `;
    document.head.appendChild(style);

    // Cleanup function to remove the style when component unmounts
    return () => {
      if (document.head.contains(style)) {
        document.head.removeChild(style);
      }
    };
  }, []);

  const fetchCustomers = useCallback(async (requestedPageParam) => {
    const targetPage = requestedPageParam ?? page;
    if (!apiUrl) {
      setError('Missing API URL');
      setLoading(false);
      return;
    }

    setLoading(true);
    setError(null);
    setPaginationMeta({ total: 0, totalPages: 1 });

    try {
      const params = {
        action: 'getGibbsCustomers',
        owner_id,
        page: targetPage,
        per_page: rowsPerPage,
        tab: activeTab,
        search: searchQuery,
        status: statusFilter,
        sort_by: sortConfig.key,
        sort_direction: sortConfig.direction,
        country: countryFilter,
        industry: industryFilter
      };

      if(salesRepRole){
        params.selected_countries = selectedCountries || [];
        params.sales_rep = true;
      }

      const headers = user_token ? { Authorization: `Bearer ${user_token}` } : {};
      const response = await axios.get(apiUrl, { params, headers });
      
      if (response?.data?.success) {
        const payload = response.data.data || {};
        const customersData = payload.customers || [];
        const pagination = payload.pagination || {};
        const total = Number(pagination.total ?? customersData.length ?? 0);
        const totalPages = Math.max(1, Number(pagination.total_pages ?? Math.ceil(total / rowsPerPage)));

        setGroupLicenses(payload.group_licenses || []);

        setCustomers(customersData);
        setPaginationMeta({ total, totalPages });
        setPage((prev) => (targetPage !== prev ? targetPage : prev));
      } else {
        setError(response?.data?.message || Ltext('Failed to load customers'));
        setCustomers([]);
        setPaginationMeta({ total: 0, totalPages: 1 });
      }
    } catch (err) {
      const message = err?.response?.data?.message || err?.message || Ltext('Failed to load customers');
      setError(message);
      setCustomers([]);
      setPaginationMeta({ total: 0, totalPages: 1 });
    } finally {
      setLoading(false);
    }
  }, [apiUrl, owner_id, page, rowsPerPage, activeTab, searchQuery, sortConfig, statusFilter, countryFilter, industryFilter, user_token, salesRepRole, selectedCountries]);

  // Load user preferences on mount
  useEffect(() => {
    const loadPreferences = async () => {
      if (!apiUrl || !owner_id) return;

      try {
        const headers = user_token ? { Authorization: `Bearer ${user_token}` } : {};
        const response = await axios.get(apiUrl, {
          params: {
            action: 'getCustomerPreferences',
            owner_id
          },
          headers
        });

        if (response?.data?.success && response.data.data?.preferences) {
          const prefs = response.data.data.preferences;
          if (prefs?.selectedHideColumns && Array.isArray(prefs?.selectedHideColumns)) {
            setSelectedHideColumns(prefs.selectedHideColumns);
          }
          if (prefs?.rowsPerPage && prefs?.rowsPerPage > 0) {
            setRowsPerPage(prefs.rowsPerPage);
          }
        }
        preferencesLoadedRef.current = true;
      } catch (err) {
        // Silently fail - use defaults if preferences can't be loaded
        console.warn('Failed to load preferences:', err);
        preferencesLoadedRef.current = true; // Mark as loaded even on error
      }
    };

    const loadFilterPreferences = async () => {
      if (!apiUrl || !owner_id) return;

      try {
        const headers = user_token ? { Authorization: `Bearer ${user_token}` } : {};
        const response = await axios.get(apiUrl, {
          params: {
            action: 'getFilterPreferences',
            owner_id
          },
          headers
        });

        if (response?.data?.success && response.data.data?.countries && response.data.data?.industries) {
          const countries = response.data.data?.countries || [];
          const industries = response.data.data?.industries || [];
          setCountries(countries);
          setIndustries(industries);
        }
      } catch (err) {
        // Silently fail - use defaults if preferences can't be loaded
        console.warn('Failed to load preferences:', err);
        preferencesLoadedRef.current = true; // Mark as loaded even on error
      }
    };
    const getPackages = async () => {
      if (!apiUrl || !owner_id) return;

      try {
        const headers = user_token ? { Authorization: `Bearer ${user_token}` } : {};
        const response = await axios.get(apiUrl, {
          params: {
            action: 'getPackages',
            owner_id
          },
          headers
        });

        if (response?.data?.success && response.data.data?.packages) {
          const packages = response.data.data?.packages || [];
          setPackages(packages);
        }
      } catch (err) {
        // Silently fail - use defaults if preferences can't be loaded
        console.warn('Failed to load preferences:', err);
        preferencesLoadedRef.current = true; // Mark as loaded even on error
      }
    };
    loadFilterPreferences();
    loadPreferences();
    getPackages();
  }, [apiUrl, owner_id, user_token]);

  useEffect(() => {
    fetchCustomers(page);
  }, [fetchCustomers, page, activeTab, searchQuery, sortConfig, statusFilter]);

  // Cleanup timeouts on unmount
  useEffect(() => {
    return () => {
      Object.values(groupLicenseSaveTimeouts.current).forEach(timeoutId => {
        clearTimeout(timeoutId);
      });
      Object.values(nextInvoiceSaveTimeouts.current).forEach(timeoutId => {
        clearTimeout(timeoutId);
      });
      Object.values(revenueSaveTimeouts.current).forEach(timeoutId => {
        clearTimeout(timeoutId);
      });
    };
  }, []);

  // Close dropdowns when clicking outside
  useEffect(() => {
    const handleClickOutside = (event) => {
      if (!event.target.closest(`.${styles.filterWrapper}`) && 
          !event.target.closest(`.${styles.settingsWrapper}`) &&
          !event.target.closest(`.${styles.showRowsSection}`) &&
          !event.target.closest(`.${styles.columnsSection}`)) {
        setIsFilterOpen(false);
        setIsSettingsOpen(false);
        setIsShowRowsOpen(false);
      }
    };

    if (isFilterOpen || isSettingsOpen || isShowRowsOpen) {
      document.addEventListener('mousedown', handleClickOutside);
    }

    return () => {
      document.removeEventListener('mousedown', handleClickOutside);
    };
  }, [isFilterOpen, isSettingsOpen, isShowRowsOpen]);

  // Close DatePicker when clicking outside
  useEffect(() => {
    const handleClickOutside = (event) => {
      if (openDatePicker && 
          !event.target.closest('.react-datepicker') &&
          !event.target.closest('input[readonly]')) {
        setOpenDatePicker(null);
        setDatePickerPosition(null);
        setDatePickerData(null);
      }
    };

    if (openDatePicker) {
      document.addEventListener('mousedown', handleClickOutside);
    }

    return () => {
      document.removeEventListener('mousedown', handleClickOutside);
    };
  }, [openDatePicker]);

  // Save preferences to API
  const savePreferences = useCallback(async (preferences) => {
    if (!apiUrl || !owner_id) return;

    try {
      const headers = user_token ? { Authorization: `Bearer ${user_token}` } : {};
      await axios.post(apiUrl, {
        action: 'saveCustomerPreferences',
        owner_id,
        preferences
      }, { headers });
    } catch (err) {
      // Silently fail - preferences save is not critical
      console.warn('Failed to save preferences:', err);
    }
  }, [apiUrl, owner_id, user_token]);

  // Save group licenses to API
  const saveGroupLicenses = useCallback(async (superadminId, groupId, licenseIds) => {
    if (!apiUrl || !groupId) return;

    setSavingGroupLicenses((prev) => ({ ...prev, [groupId]: true }));

    try {
      const headers = user_token ? { Authorization: `Bearer ${user_token}` } : {};
      const response = await axios.post(apiUrl, {
        action: 'updateGroupLicenses',
        superadmin_id: superadminId,
        group_id: groupId,
        license_ids: licenseIds
      }, { headers });

      if (response?.data?.success) {
        // Success - licenses are saved
        console.log('Group licenses saved successfully');
      } else {
        throw new Error(response?.data?.message || 'Failed to save group licenses');
      }
    } catch (err) {
      console.error('Failed to save group licenses:', err);
      // Optionally show an error message to the user
      alert('Failed to save group licenses. Please try again.');
    } finally {
      setSavingGroupLicenses((prev) => ({ ...prev, [groupId]: false }));
    }
  }, [apiUrl, user_token]);

  // Save next invoice date to API
  const saveNextInvoice = useCallback(async (superadminId, nextInvoiceDate) => {
    if (!apiUrl || !superadminId) return;

    setSavingNextInvoice((prev) => ({ ...prev, [superadminId]: true }));

    try {
      const headers = user_token ? { Authorization: `Bearer ${user_token}` } : {};
      const response = await axios.post(apiUrl, {
        action: 'updateNextInvoice',
        superadmin_id: superadminId,
        next_invoice: nextInvoiceDate || ''
      }, { headers });

      if (response?.data?.success) {
        // Success - next invoice date is saved
        console.log('Next invoice date saved successfully');
      } else {
        throw new Error(response?.data?.message || 'Failed to save next invoice date');
      }
    } catch (err) {
      console.error('Failed to save next invoice date:', err);
      // Optionally show an error message to the user
      alert('Failed to save next invoice date. Please try again.');
    } finally {
      setSavingNextInvoice((prev) => ({ ...prev, [superadminId]: false }));
    }
  }, [apiUrl, user_token]);
  
  // Save MRR/ARR to API
  const saveRevenue = useCallback(async (superadminId, mrrValue, arrValue) => {
    if (!apiUrl || !superadminId) return;

    setSavingRevenue((prev) => ({ ...prev, [superadminId]: true }));

    try {
      const headers = user_token ? { Authorization: `Bearer ${user_token}` } : {};
      const response = await axios.post(apiUrl, {
        action: 'updateMrrArr',
        owner_id,
        superadmin_id: superadminId,
        mrr: mrrValue,
        arr: arrValue
      }, { headers });

      if (!response?.data?.success) {
        throw new Error(response?.data?.message || 'Failed to save MRR/ARR');
      }
    } catch (err) {
      console.error('Failed to save MRR/ARR:', err);
      alert('Failed to save MRR/ARR. Please try again.');
    } finally {
      setSavingRevenue((prev) => ({ ...prev, [superadminId]: false }));
    }
  }, [apiUrl, owner_id, user_token]);
  

  // Save preferences when selectedHideColumns changes
  useEffect(() => {
    console.log('callSavePreferences', callSavePreferences);
    // Only save if preferences have been loaded (avoid saving on initial mount)
    if (callSavePreferences) {
      
      const timeoutId = setTimeout(() => {
        savePreferences({
          selectedHideColumns,
          rowsPerPage
        });
        setCallSavePreferences(false);
      }, 500); // Debounce by 500ms to avoid too many API calls

      return () => clearTimeout(timeoutId);
    }
  }, [savePreferences, callSavePreferences]);

  const handleRowsPerPageChange = (newRowsPerPage) => {
    setCallSavePreferences(true);
    setRowsPerPage(newRowsPerPage);
    setPage(1);
    setIsShowRowsOpen(false);
    setIsColumnsOpen(false);
    setIsFilterOpen(false);
    setIsSettingsOpen(false);
  };

  const handleTabChange = (tabId) => {
    setActiveTab(tabId);
    setPage(1);
  };

  const handleSearchChange = (e) => {
    setSearchQuery(e.target.value);
    setPage(1);
  };

  const toggleRowExpansion = (companyId) => {
    setExpandedRows((prev) => {
      const next = new Set(prev);
      if (next.has(companyId)) {
        next.delete(companyId);
      } else {
        next.add(companyId);
      }
      return next;
    });
  };

  const handleSort = (columnKey) => {
    setSortConfig((prev) => ({
      key: columnKey,
      direction: prev.key === columnKey && prev.direction === 'asc' ? 'desc' : 'asc'
    }));
  };

  const getLicenseBadgeClass = (licenseType) => {
    if (!licenseType || typeof licenseType !== 'string') {
      return styles.badgeDefault;
    }
    switch (licenseType.toLowerCase()) {
      case 'custom plan':
        return styles.badgeCustomPlan;
      case 'trial':
        return styles.badgeTrial;
      case 'no license':
        return styles.badgeNoLicense;
      case 'standard':
        return styles.badgeStandard;
      default:
        return styles.badgeDefault;
    }
  };

  const formatDate = (dateString) => {
    if (!dateString) return '—';
    try {
      const date = new Date(dateString);
      if (isNaN(date.getTime())) return '—';
      return date.toLocaleDateString('en-GB', { year: 'numeric', month: '2-digit', day: '2-digit' });
    } catch {
      return '—';
    }
  };

  // Helper function to format Date object to YYYY-MM-DD string (timezone-safe)
  const formatDateToString = (date) => {
    if (!date) return '';
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
  };

  // Helper function to parse YYYY-MM-DD string to Date object (timezone-safe)
  const parseDateString = (dateString) => {
    if (!dateString) return null;
    const parts = dateString.split('-');
    if (parts.length !== 3) return null;
    const year = parseInt(parts[0], 10);
    const month = parseInt(parts[1], 10) - 1; // Month is 0-indexed
    const day = parseInt(parts[2], 10);
    const date = new Date(year, month, day);
    // Validate the date
    if (date.getFullYear() !== year || date.getMonth() !== month || date.getDate() !== day) {
      return null;
    }
    return date;
  };

  const formatCurrency = (value) => {
    if (value === '' || value === null || value === undefined || Number.isNaN(Number(value))) {
      return '0';
    }
    return Number(value).toLocaleString('en-US');
  };

  const handleRevenueInputChange = (row, field, rawValue) => {
    if (!row || !row.superadmin) return;
    const superadminId = row.superadmin;
    const parsedValue = rawValue === '' ? '' : parseFloat(rawValue);
    if (rawValue !== '' && Number.isNaN(parsedValue)) return;

    const existing = revenueValues[superadminId] ?? {
      mrr: row.mrr ?? '',
      arr: row.arr ?? ''
    };

    const updated = {
      ...existing,
      [field]: rawValue === '' ? '' : parsedValue
    };

    // Keep ARR in sync with MRR unless ARR is actively being edited
    const arrIsBeingEdited = revenueEditMode[superadminId]?.arr;
    if (field === 'mrr' && !arrIsBeingEdited) {
      updated.arr = rawValue === '' ? '' : parsedValue * 12;
    }

    setRevenueValues((prev) => ({
      ...prev,
      [superadminId]: updated
    }));
  };

  const startRevenueEdit = (superadminId, row, field) => {
    if (!superadminId) return;
    setRevenueValues((prev) => {
      const current = prev[superadminId] ?? {
        mrr: row.mrr ?? '',
        arr: row.arr ?? ''
      };
      return { ...prev, [superadminId]: current };
    });
    setRevenueEditMode((prev) => ({
      ...prev,
      [superadminId]: { ...(prev[superadminId] || {}), [field]: true }
    }));
  };

  const handleRevenueSave = async (superadminId) => {
    const current = revenueValues[superadminId];
    if (!current) return;
    await saveRevenue(
      superadminId,
      current.mrr === '' || current.mrr === undefined || current.mrr === null ? 0 : current.mrr,
      current.arr === '' || current.arr === undefined || current.arr === null ? 0 : current.arr
    );
    // Keep UI in sync after save
    setCustomers((prev) =>
      prev.map((company) =>
        company.superadmin === superadminId
          ? {
              ...company,
              mrr: current.mrr === '' || current.mrr === undefined || current.mrr === null ? 0 : current.mrr,
              arr: current.arr === '' || current.arr === undefined || current.arr === null ? 0 : current.arr
            }
          : company
      )
    );
    setRevenueEditMode((prev) => ({
      ...prev,
      [superadminId]: { mrr: false, arr: false }
    }));
  };

  const cancelAllRevenueEdits = useCallback(() => {
    setRevenueEditMode({});
    setRevenueValues({});
  }, []);

  const isAnyRevenueEditing = useMemo(
    () => Object.values(revenueEditMode).some((mode) => (mode?.mrr || mode?.arr)),
    [revenueEditMode]
  );

  useEffect(() => {
    const handleClickOutside = (event) => {
      const target = event.target;
      if (target && target.closest && target.closest('[data-revenue-editor]')) return;
      cancelAllRevenueEdits();
    };
    if (isAnyRevenueEditing) {
      document.addEventListener('mousedown', handleClickOutside);
    }
    return () => {
      document.removeEventListener('mousedown', handleClickOutside);
    };
  }, [isAnyRevenueEditing, cancelAllRevenueEdits]);

  const decodeHtml = (input) => {
    if (!input || typeof input !== 'string') return input;
    const textarea = document.createElement('textarea');
    textarea.innerHTML = input;
    return textarea.value;
  };

  const handleSwitchToUser = useCallback(async (row) => {
    if (!row || row.isUsergroup || !row.superadmin) return;
    const superadminId = row.superadmin;

    setSwitchingUser((prev) => ({ ...prev, [superadminId]: true }));

    try {
      const headers = user_token ? { Authorization: `Bearer ${user_token}` } : {};
      const response = await axios.get(apiUrl, {
        params: { action: 'getSwitchUserUrl', superadmin_id: superadminId },
        headers
      });

      const switchUrl = decodeHtml(response?.data?.data?.switch_url);

      if (switchUrl) {
        window.open(switchUrl, '_blank', 'noopener');
      } else {
        alert(response?.data?.message || Ltext('Unable to switch to this user'));
      }
    } catch (err) {
      const message = err?.response?.data?.message || err?.message || Ltext('Failed to switch user');
      alert(message);
    } finally {
      setSwitchingUser((prev) => ({ ...prev, [superadminId]: false }));
    }
  }, [apiUrl, user_token]);

  // Flatten customers and usergroups for table display
  const tableData = useMemo(() => {
    const rows = [];
    customers.forEach((company) => {
      // Add company row
      rows.push({
        ...company,
        isCompany: true,
        rowId: `company-${company.id}`
      });

      // Add usergroup rows if expanded
      if (expandedRows.has(company.id) && company.usergroups && company.usergroups.length > 0) {
        company.usergroups.forEach((usergroup) => {
          rows.push({
            ...usergroup,
            isUsergroup: true,
            parentCompanyId: company.id,
            rowId: `usergroup-${usergroup.id}`
          });
        });
      }
    });
    return rows;
  }, [customers, expandedRows]);

  const appliedFiltersCount = useMemo(() => {
    let count = 0;
    if (countryFilter !== 'all') count += 1;
    if (industryFilter !== 'all') count += 1;
    return count;
  }, [countryFilter, industryFilter]);

  // Filter countries based on salesRepRole and selectedCountries
  const availableCountries = useMemo(() => {
    if (salesRepRole && selectedCountries && selectedCountries.length > 0) {
      return countries.filter(country => selectedCountries.includes(country.code));
    }else if(salesRepRole){
      return [];
    }
    return countries;
  }, [countries, salesRepRole, selectedCountries]);

  const allColumns = [
    {
      key: 'company_name',
      header: Ltext('COMPANY NAME'),
      sortable: true,
      thStyle: { width: '20%', minWidth: '200px' },
      tdStyle: { minWidth: '200px' },
      render: (row) => {
        if (row.isUsergroup) {
          return (
            <div className={styles.usergroupCell}>
              <span className={styles.usergroupName}>{row.name || '—'}</span>
              <span className={styles.usergroupLabel}>{Ltext('Usergroup')}</span>
            </div>
          );
        }
        const hasUsergroups = row.usergroups && row.usergroups.length > 0;
        return (
          <div className={styles.companyCell}>
            {hasUsergroups && (
              <Button
                variant="ghost"
                size="small"
                onClick={() => toggleRowExpansion(row.id)}
                aria-label={expandedRows.has(row.id) ? 'Collapse' : 'Expand'}
                className={styles.expandButton}
                key={`expand-btn-${row.id}-${expandedRows.has(row.id)}`}
              >
                <i key={`chevron-icon-${row.id}-${expandedRows.has(row.id)}`} className={expandedRows.has(row.id) ? 'fa fa-chevron-down' : 'fa fa-chevron-right'}></i>
              </Button>
            )}
            <div>
              <div className={styles.companyName}>{row.company_name || row.name || '—'}</div>
              <div className={styles.usergroupCount}>
                {hasUsergroups ? `${row.usergroups.length} ${Ltext('Usergroups')}` : Ltext('0 Usergroups')}
              </div>
            </div>
          </div>
        );
      }
    },
    {
      key: 'superadmin',
      header: Ltext('SUPERADMIN'),
      sortable: true,
      thStyle: { width: '12%' },
      render: (row) => (row.first_name || row.last_name ? row.first_name + ' ' + row.last_name : '—')
    },
    {
      key: 'email',
      header: Ltext('EMAIL'),
      sortable: true,
      thStyle: { width: '12%' },
      render: (row) => row.email || '—'
    },
    {
      key: 'phone',
      header: Ltext('PHONE'),
      sortable: true,
      thStyle: { width: '8%' },
      render: (row) => (row.country_code && row.phone != '' ? row.country_code + ' ' + row.phone : row.phone || '—')
    },
    {
      key: 'company_country',
      header: Ltext('COUNTRY'),
      sortable: true,
      thStyle: { width: '10%' },
      render: (row) => {
        if (row.isUsergroup) return '—';
        return row.company_country || '—';
      }
    },
    {
      key: 'company_industry',
      header: Ltext('INDUSTRY'),
      sortable: true,
      thStyle: { width: '10%' },
      render: (row) => {
        if (row.isUsergroup) return '—';
        return Ltext(row.company_industry) || '—';
      }
    },
    {
      key: 'mrr',
      sortable: true,
      header: Ltext('MRR'),
      thStyle: { width: '10%' },
      render: (row) => {
        if (row.isUsergroup) return '—';
        const superadminId = row.superadmin;
        const revenue = revenueValues[superadminId] ?? { mrr: row.mrr ?? '', arr: row.arr ?? '' };
        const value = revenue.mrr ?? '';
        const isEditing = revenueEditMode[superadminId]?.mrr;
        const company_country = row.company_country;
        const company_country_data = countries.find((country) => country.code === company_country);

        if (!isEditing || row.payment !== 'Invoice' || !customerActions.includes('update_mrr_arr')) {
          return (
            <div
              style={{ display: 'inline-flex', alignItems: 'center', gap: 6, cursor: 'pointer' }}
              onClick={() => startRevenueEdit(superadminId, row, 'mrr')}
            >
              {row.payment === 'Invoice' || (value !== '' && value !== null && value !== undefined && value !== 0) ? (
                <>
                  <span style={{ fontSize: 12, color: '#6b7280' }}>{company_country_data?.currency || 'NOK'}</span> 
                  <span>{formatCurrency(value)}</span>
                </>
              ): (
                <span style={{ fontSize: 12, color: '#6b7280' }}>-</span> 
              )}
              
            </div>
          );
        }

        return (
          <div data-revenue-editor style={{ display: 'flex', gap: 6, alignItems: 'center' }}>
            <input
              type="number"
              min="0"
              step="1"
              autoFocus
              value={value === '' || value === null || value === undefined ? '' : value}
              onChange={(e) => handleRevenueInputChange(row, 'mrr', e.target.value)}
              disabled={savingRevenue[superadminId]}
              style={{
                width: 120,
                padding: '4px 6px',
                border: '1px solid #d1d5db',
                borderRadius: 4,
                fontSize: 13,
                background: savingRevenue[superadminId] ? '#f9fafb' : '#fff'
              }}
            />
            <Button
              variant="primary"
              size="small"
              disabled={savingRevenue[superadminId]}
              onClick={() => handleRevenueSave(superadminId)}
            >
              {savingRevenue[superadminId] ? Ltext('Saving...') : Ltext('Save')}
            </Button>
          </div>
        );
      }
    },
    {
      key: 'arr',
      sortable: true,
      header: Ltext('ARR'),
      thStyle: { width: '10%' },
      render: (row) => {
        if (row.isUsergroup) return '—';
        const superadminId = row.superadmin;
        const revenue = revenueValues[superadminId] ?? { mrr: row.mrr ?? '', arr: row.arr ?? '' };
        const value = revenue.arr ?? '';
        const isEditing = revenueEditMode[superadminId]?.arr;
        const company_country = row.company_country;
        const company_country_data = countries.find((country) => country.code === company_country);

        if (!isEditing || row.payment !== 'Invoice' || !customerActions.includes('update_mrr_arr')) {
          return (
            <div
              style={{ display: 'inline-flex', alignItems: 'center', gap: 6, cursor: 'pointer' }}
              onClick={() => startRevenueEdit(superadminId, row, 'arr')}
            >
              {row.payment === 'Invoice' || (value !== '' && value !== null && value !== undefined && value !== 0) ? (
                <>
                  <span style={{ fontSize: 12, color: '#6b7280' }}>{company_country_data?.currency || 'NOK'}</span> 
                  <span>{formatCurrency(value)}</span>
                </>
              ): (
                <span style={{ fontSize: 12, color: '#6b7280' }}>—</span> 
              )}
            </div>
          );
        }

        return (
          <div data-revenue-editor style={{ display: 'flex', gap: 6, alignItems: 'center' }}>
            <input
              type="number"
              min="0"
              step="1"
              autoFocus
              value={value === '' || value === null || value === undefined ? '' : value}
              onChange={(e) => handleRevenueInputChange(row, 'arr', e.target.value)}
              disabled={savingRevenue[superadminId]}
              style={{
                width: 120,
                padding: '4px 6px',
                border: '1px solid #d1d5db',
                borderRadius: 4,
                fontSize: 13,
                background: savingRevenue[superadminId] ? '#f9fafb' : '#fff'
              }}
            />
            <Button
              variant="primary"
              size="small"
              disabled={savingRevenue[superadminId]}
              onClick={() => handleRevenueSave(superadminId)}
            >
              {savingRevenue[superadminId] ? Ltext('Saving...') : Ltext('Save')}
            </Button>
          </div>
        );
      }
    },
    {
      key: 'gibbs_licenses',
      header: Ltext('GIBBS LICENSES'),
      thStyle: { width: '26%', minWidth: '260px' },
      tdStyle: { minWidth: '260px' },
      render: (row) => {
        // For usergroup rows, show multi-select based on global `groupLicenses` list
        if (row.isUsergroup) {
          const options = groupLicenses.map((license) => ({
            value: license.id,
            label: license.licence_name,
          }));
          // Selected IDs come from local state override (if user changed it),
          // otherwise from row.group_license (initial data from API)
          const selectedIds =
            groupLicenseSelections[row.id] ||
            (row.group_license || []).map((license) => license.licence_id);
          const selectedOptions = options.filter((opt) => selectedIds.includes(opt.value));

          return (
            <div className={styles.licenseDropdown}>
              <Select
                isMulti
                options={options}
                value={selectedOptions}
                onChange={(selected) => {
                  if (!customerActions.includes('update_group_licenses')) {
                    return;
                  }
                  const ids = (selected || []).map((opt) => opt.value);
                  setGroupLicenseSelections((prev) => ({
                    ...prev,
                    [row.id]: ids,
                  }));
                  // Clear existing timeout for this group
                  if (groupLicenseSaveTimeouts.current[row.id]) {
                    clearTimeout(groupLicenseSaveTimeouts.current[row.id]);
                  }
                  // Save to API with debounce (500ms)
                  groupLicenseSaveTimeouts.current[row.id] = setTimeout(() => {
                    saveGroupLicenses(row.superadmin, row.id, ids);
                    delete groupLicenseSaveTimeouts.current[row.id];
                  }, 500);
                }}
                isClearable={false}
                placeholder={Ltext('Select licenses')}
                classNamePrefix="gibbs-license-select"
                isDisabled={savingGroupLicenses[row.id]}
                menuPortalTarget={typeof document !== 'undefined' ? document.body : null}
                styles={{
                  control: (base) => ({
                    ...base,
                    minHeight: 34,
                    borderRadius: 4,
                    borderColor: '#d1d5db',
                    boxShadow: 'none',
                    '&:hover': { borderColor: '#9ca3af' },
                  }),
                  valueContainer: (base) => ({
                    ...base,
                    padding: '0 8px',
                    gap: 4,
                    flexWrap: 'wrap',
                  }),
                  multiValue: (base) => ({
                    ...base,
                    backgroundColor: '#e5edff',
                    borderRadius: 4,
                  }),
                  multiValueLabel: (base) => ({
                    ...base,
                    fontSize: 12,
                    color: '#1d4ed8',
                    padding: '2px 6px',
                  }),
                  multiValueRemove: (base) => ({
                    ...base,
                    paddingLeft: 0,
                    paddingRight: 4,
                    '&:hover': {
                      backgroundColor: 'transparent',
                      color: '#1d4ed8',
                    },
                  }),
                  indicatorsContainer: (base) => ({
                    ...base,
                    alignItems: 'center',
                  }),
                  dropdownIndicator: (base) => ({
                    ...base,
                    paddingTop: 4,
                    paddingBottom: 4,
                  }),
                  clearIndicator: () => ({
                    display: 'none',
                  }),
                  indicatorSeparator: () => ({
                    display: 'none',
                  }),
                  menuPortal: (base) => ({ ...base, zIndex: 9999 }),
                }}
              />
            </div>
          );
        }
        return '—';
      }
    },
    {
      key: 'created_at',
      header: Ltext('CREATED'),
      thStyle: { width: '10%' },
      sortable: true,
      render: (row) => formatDate(row.created_at || row.created)
    },
    {
      key: 'stripe_license',
      header: Ltext('STRIPE LICENSE'),
      thStyle: { width: '12%' },
      sortable: true,
      render: (row) => {
        if (row.isUsergroup) return '—';
        const stripeLicense = row.stripe_license || row.license_type || row.plan_type || '';
        if (stripeLicense) {
          return (
            <span className={`${styles.licenseBadge} ${getLicenseBadgeClass(stripeLicense)}`}>
              {stripeLicense}
            </span>
          );
        }
        return '—';
      }
    },
    {
      key: 'payment',
      header: Ltext('PAYMENT'),
      sortable: true,
      thStyle: { width: '10%' },
      render: (row) => Ltext(row.payment) || '—'
    },
    {
      key: 'next_invoice',
      header: Ltext('NEXT INVOICE'),
      sortable: true,
      thStyle: { width: '12%' },
      render: (row) => {
        if (row.payment === 'Invoice') {
          // Use local state if available, otherwise use row data
          let currentDateString = '';
          if (nextInvoiceDates[row.superadmin] !== undefined) {
            currentDateString = nextInvoiceDates[row.superadmin];
          } else if (row.next_invoice) {
            // If it's already in YYYY-MM-DD format, use it directly
            if (typeof row.next_invoice === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(row.next_invoice)) {
              currentDateString = row.next_invoice;
            } else {
              // Otherwise, parse and format it
              const date = new Date(row.next_invoice);
              if (!isNaN(date.getTime())) {
                currentDateString = formatDateToString(date);
              }
            }
          }
          
          // Convert string to Date object for DatePicker (timezone-safe)
          const currentDate = parseDateString(currentDateString);
          
          return (
            <div style={{ display: 'inline-block' }}>
              <input
                readOnly
                value={currentDateString || ''}
                onClick={(e) => {
                  if (!customerActions.includes('update_next_invoice')) {
                    return;
                  }
                  const rect = e.target.getBoundingClientRect();
                  setDatePickerPosition({
                    top: rect.bottom + window.scrollY + 4,
                    left: rect.left + window.scrollX
                  });
                  setDatePickerData({
                    superadmin: row.superadmin,
                    currentDate: currentDate,
                    currentDateString: currentDateString
                  });
                  setOpenDatePicker(row.superadmin);
                }}
                disabled={savingNextInvoice[row.superadmin]}
                style={{
                  border: "none",
                  borderBottom: "1px dotted #d1d5db",
                  boxShadow: "none",
                  outline: "none",
                  background: "transparent",
                  padding: "2px 4px",
                  width: "100px",
                  minWidth: "100px",
                  color: "inherit",
                  font: "inherit",
                  cursor: savingNextInvoice[row.superadmin] ? "not-allowed" : "pointer",
                  transition: "border-color 0.2s"
                }}
                onMouseEnter={(e) => {
                  if (!savingNextInvoice[row.superadmin]) {
                    e.target.style.borderBottomColor = "#10b981";
                  }
                }}
                onMouseLeave={(e) => {
                  e.target.style.borderBottomColor = "#d1d5db";
                }}
              />
            </div>
          );
        }else if (row.payment === 'Card') {
          return Ltext("Auto-bill");
        }
        return '—';
      }
    },
    {
      key: 'canceled_at',
      header: Ltext('CANCEL DATE'),
      sortable: true,
      thStyle: { width: '12%' },
      render: (row) => {
        if (row.isUsergroup) return '—';
        return row.canceled_at ? formatDate(row.canceled_at) : '—';
      }
    },
    {
      key: 'edit',
      header: Ltext('EDIT'),
      thStyle: { width: '11%' , textAlign: 'center'},
      render: (row) => {
        return (
          <div className={styles.actionButtons}>
            {(customerActions.includes('edit_usergroup') && row.isUsergroup) ? (
              <Button
                variant="ghost"
                size="small"
                key={`edit-btn-${row.id}-${row.superadmin}`}
                onClick={() => {
                  setEditingUsergroup({id: row.id, name: row.name, email: row.email, email_cc: row.email_cc});
                  setIsEditUsergroupOpen(true);
                }}
                aria-label="Edit"
                className={styles.actionButton}
              >
                <i className="fa fa-edit"></i>
              </Button>
            ) : (customerActions.includes('edit_customer') && !row.isUsergroup) ? (
              <Button
                variant="ghost"
                size="small"
                key={`edit-btn-${row.id}-${row.superadmin}`}
                onClick={() => {
                  setEditingCustomer({id: row.id, superadmin: row.superadmin});
                  setIsEditCustomerOpen(true);
                }}
                aria-label="Edit"
                className={styles.actionButton}
              >
                <i className="fa fa-edit"></i>
              </Button>
            ) : null}
            {!row.isUsergroup && customerActions.includes('switch_customer') && (
              <Button
                variant="ghost"
                size="small"
                key={`switch-btn-${row.id}-${row.superadmin}`}
                onClick={() => handleSwitchToUser(row)}
                aria-label="Switch user"
                className={styles.actionButton}
                disabled={switchingUser[row.superadmin]}
                title={Ltext('Switch to this user')}
                loading={switchingUser[row.superadmin]}
              >
              <svg
                width="16"
                height="16"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                strokeWidth="2"
                strokeLinecap="round"
                strokeLinejoin="round"
                aria-hidden="true"
                focusable="false"
              >
                <path d="M17 14l5-5-5-5" />
                <path d="M12 9h10" />
                <path d="M7 10l-5 5 5 5" />
                <path d="M2 15h10" />
              </svg>
              </Button>
            )}
            {/* <Button
              variant="ghost"
              size="small"
              aria-label="Delete"
              className={styles.actionButton}
            >
              <i className="fa fa-trash"></i>
            </Button> */}
          </div>
        );
      }
    }
  ];

  // Filter columns based on user permissions and selectedHideColumns
  // First, get allowed column keys from customerColumns (permissions from backend)
  const allowedColumnKeys = useMemo(() => {
    if (!customerColumns || !Array.isArray(customerColumns)) {
      return [];
    }
    return customerColumns.map(col => col.key || col);
  }, [customerColumns]);

  // Filter allColumns to only include columns user has permission to see
  // Note: 'edit' column is always shown regardless of permissions
  const permittedColumns = useMemo(() => {
    return allColumns.filter(col => {
      // Always include edit column
      if (col.key === 'edit' && (customerActions.includes('edit_customer') || customerActions.includes('switch_customer') || customerActions.includes('edit_usergroup'))) {
        return true;
      }
      // Check permission for other columns
      return allowedColumnKeys.includes(col.key);
    });
  }, [allColumns, allowedColumnKeys]);

  // Then filter based on selectedHideColumns (user preferences)
  // Note: 'edit' column is always shown and cannot be hidden
  const columns = useMemo(() => {
    return permittedColumns.filter(col => {
      // Always show edit column
      // if (col.key === 'edit') {
      //   return true;
      // }
      // Apply hide/show preference for other columns
      return !selectedHideColumns.includes(col.key);
    });
  }, [permittedColumns, selectedHideColumns]);

  return (
    <div className={styles.wrapper}>
      <div className={styles.container}>
        <div className={styles.header}>
          <div className={styles.headerLeft}>
            <Tabs tabs={tabs} activeTab={activeTab} onTabChange={handleTabChange} />
          </div>
          <div className={styles.headerRight}>
            <div className={styles.filterWrapper}>
                <div style={{ position: 'relative', display: 'inline-flex', alignItems: 'center' }}>
                  <Button
                    variant="ghost"
                    size="small"
                    className={`${styles.iconButton} ${styles.filterButton} ${isFilterOpen ? styles.active : ''}`}
                    aria-label="Filter"
                    onClick={() => {
                      setIsFilterOpen(!isFilterOpen);
                      setIsSettingsOpen(false);
                    }}
                  >
                      <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="lucide lucide-funnel w-5 h-5" aria-hidden="true"><path d="M10 20a1 1 0 0 0 .553.895l2 1A1 1 0 0 0 14 21v-7a2 2 0 0 1 .517-1.341L21.74 4.67A1 1 0 0 0 21 3H3a1 1 0 0 0-.742 1.67l7.225 7.989A2 2 0 0 1 10 14z"></path></svg> <span className={styles.filterButtonText}>{Ltext('Filter')}</span>
                  </Button>
                  {appliedFiltersCount > 0 && (
                        <span
                          style={{
                            position: 'absolute',
                            top: -6,
                            right: -10,
                            minWidth: 18,
                            height: 18,
                            borderRadius: 9,
                            background: '#ef4444',
                            color: '#fff',
                            fontSize: 11,
                            lineHeight: '18px',
                            textAlign: 'center',
                            padding: '0 5px',
                            boxShadow: '0 1px 3px rgba(0,0,0,0.25)'
                          }}
                        >
                          {appliedFiltersCount}
                        </span>
                  )}
                </div>
                {isFilterOpen && (
                  <div className={`${styles.filterDropdownMenu} ${styles.filterDropdownMenuFilter}`}>
                    <div className={styles.filterDropdownHeader}>
                      <h3>{Ltext('Filter')}</h3>
                    </div>
                    <div className={styles.filterDropdownBody}>
                      <div className={styles.filterSection}>
                        <div className={styles.filterSelectWrapper}>
                          <label className={styles.filterLabel}>{Ltext('Country')}</label>
                          <select
                            className={styles.filterSelect}
                            value={countryFilterDraft}
                            onChange={(e) => {
                              setCountryFilterDraft(e.target.value);
                            }}
                          >
                            <option value="all">{Ltext('All Countries')}</option>
                            {availableCountries.map((country) => (
                              <option key={country.code} value={country.code}>{country.name}</option>
                            ))}
                          </select>
                        </div>
                        <div className={styles.filterSelectWrapper}>
                          <label className={styles.filterLabel}>{Ltext('Industry')}</label>
                          <select
                            className={styles.filterSelect}
                            value={industryFilterDraft}
                            onChange={(e) => {
                              setIndustryFilterDraft(e.target.value);
                            }}
                          >
                            <option value="all">{Ltext('All Industries')}</option>
                            {industries && Object.entries(industries).map(([key, Industryvalue]) => (
                              <option value={Industryvalue}>{Industryvalue}</option>
                            ))}
                          </select>
                        </div>
                      </div>
                      <Button
                        variant="primary"
                        onClick={() => {
                          //setStatusFilter(statusFilterDraft);
                          setCountryFilter(countryFilterDraft);
                          setIndustryFilter(industryFilterDraft);
                          const applied = (countryFilterDraft !== 'all' ? 1 : 0) + (industryFilterDraft !== 'all' ? 1 : 0);
                          setFilterCount(applied);
                          setPage(1);
                          setIsFilterOpen(false);
                        }}
                        className={styles.filterApplyButton}
                      >
                        {Ltext('Filter')}
                      </Button>
                      <Button
                        variant="ghost"
                        onClick={() => {
                          setCountryFilterDraft('all');
                          setIndustryFilterDraft('all');
                          setCountryFilter('all');
                          setIndustryFilter('all');
                          setFilterCount(0);
                          setPage(1);
                          setIsFilterOpen(false);
                        }}
                        className={styles.filterApplyButton}
                        style={{ marginTop: 6 }}
                      >
                        {Ltext('Clear')}
                      </Button>
                    </div>
                  </div>
                )}
            </div>

            {customerActions.includes('create_customer') && (
              <Button
                variant="primary"
                onClick={() => setIsCreateCustomerOpen(true)}
                leftIcon={<span>+</span>}
                className={styles.newCustomerButton}
              >
                {Ltext('New Customer')}
              </Button>
            )}
            <div className={styles.searchBox}>
              <i className={`fa fa-search ${styles.searchIcon}`}></i>
              <input
                type="text"
                className={styles.searchInput}
                placeholder={Ltext('Search by name, email...')}
                value={searchQuery}
                onChange={handleSearchChange}
              />
            </div>

            {customerActions.includes('manage_preferences') && (
              <div className={styles.settingsWrapper}>
                <Button
                  variant="ghost"
                  size="small"
                  className={`${styles.iconButton} ${isSettingsOpen ? styles.active : ''}`}
                  aria-label="Settings"
                  onClick={() => {
                    setIsSettingsOpen(!isSettingsOpen);
                    setIsFilterOpen(false);
                  }}
                >
                  <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-settings w-5 h-5" aria-hidden="true"><path d="M9.671 4.136a2.34 2.34 0 0 1 4.659 0 2.34 2.34 0 0 0 3.319 1.915 2.34 2.34 0 0 1 2.33 4.033 2.34 2.34 0 0 0 0 3.831 2.34 2.34 0 0 1-2.33 4.033 2.34 2.34 0 0 0-3.319 1.915 2.34 2.34 0 0 1-4.659 0 2.34 2.34 0 0 0-3.32-1.915 2.34 2.34 0 0 1-2.33-4.033 2.34 2.34 0 0 0 0-3.831A2.34 2.34 0 0 1 6.35 6.051a2.34 2.34 0 0 0 3.319-1.915"></path><circle cx="12" cy="12" r="3"></circle></svg>
                </Button>
                {isSettingsOpen && (
                  <div className={`${styles.settingsDropdownMenu} ${styles.settingsDropdownMenuSettings}`}>
                    <div className={styles.settingsDropdownHeader}>
                      <h3>{Ltext('Settings')}</h3>
                    </div>
                    <div className={styles.settingsDropdownBody}>
                      <div className={`${styles.settingsSection} ${styles.columnsSection}`}>
                        <div 
                          className={styles.settingsSectionHeader}
                          onClick={() => 
                            {
                              setIsColumnsOpen(!isColumnsOpen);
                              setIsShowRowsOpen(false);
                            }
                          }
                        >
                          <div className={styles.showRowsHeaderLeft}>
                            <i className={`fa fa-columns ${styles.icon}`}></i>
                            <span>{Ltext('Select columns')}</span>
                          </div>
                          <i className={`fa ${isColumnsOpen ? 'fa-chevron-up' : 'fa-chevron-down'}`}></i>
                        </div>
                        {isColumnsOpen && (
                          <div className={styles.settingsCheckboxes}>
                            
                            {/* {[
                              { key: 'company_name', label: Ltext('COMPANY NAME') },
                              { key: 'superadmin', label: Ltext('SUPERADMIN') },
                              { key: 'email', label: Ltext('EMAIL') },
                              { key: 'phone', label: Ltext('PHONE') },
                              { key: 'company_country', label: Ltext('COUNTRY') },
                              { key: 'company_industry', label: Ltext('INDUSTRY') },
                                { key: 'mrr', label: Ltext('MRR') },
                                { key: 'arr', label: Ltext('ARR') },
                              { key: 'gibbs_licenses', label: Ltext('GIBBS LICENSES') },
                              { key: 'created_at', label: Ltext('CREATED') },
                              { key: 'stripe_license', label: Ltext('STRIPE LICENSE') },
                              { key: 'payment', label: Ltext('PAYMENT') },
                              { key: 'next_invoice', label: Ltext('NEXT INVOICE') },
                              { key: 'canceled_at', label: Ltext('CANCEL DATE') }
                            ] */}
                            {permittedColumns.map(col => {
                              // Skip edit column in selection UI - it's always visible
                              if (col.key === 'edit') {
                                return null;
                              }
                              
                              const isChecked = !selectedHideColumns.includes(col.key);
                              // Get label from customerColumns if available, otherwise use column header
                              const columnLabel = customerColumns?.find(c => (c.key || c) === col.key)?.label || col.header || col.key;
                              return (
                                <label key={col.key} className={styles.settingsCheckbox+` ${!isChecked ? styles.hideColumnChecked : ''}`}>
                                  <input
                                    type="checkbox"
                                    checked={isChecked}
                                    onChange={(e) => {
                                      setCallSavePreferences(true);
                                      // Checkbox checked = column is visible
                                      if (e.target.checked) {
                                        // Remove from hidden columns
                                        setSelectedHideColumns((prev) =>
                                          prev.filter((c) => c !== col.key)
                                        );
                                      } else {
                                        // Add to hidden columns
                                        setSelectedHideColumns((prev) =>
                                          prev.includes(col.key) ? prev : [...prev, col.key]
                                        );
                                      }
                                    }}
                                    style={{ display: 'none' }}
                                  />
                                  <span>{typeof columnLabel === 'string' ? Ltext(columnLabel) : columnLabel}</span>
                                  <i className={`fa fa-check ${styles.checkboxCheckIcon}`} style={{ color: '#10b981' }}></i>
                                </label>
                              );
                            })}
                          </div>
                        )}
                      </div>
                      <div className={`${styles.settingsSection} ${styles.showRowsSection}`}>
                        <div 
                          className={styles.settingsSectionHeader}
                          onClick={() => 
                              {
                                setIsShowRowsOpen(!isShowRowsOpen);
                                setIsColumnsOpen(false);
                              }
                            }
                        >
                          <div className={styles.showRowsHeaderLeft}>
                            <i className={`fa fa-list ${styles.icon}`}></i>
                            <span>{Ltext('Show rows')}</span>
                          </div>
                          <i className={`fa ${isShowRowsOpen ? 'fa-chevron-up' : 'fa-chevron-down'}`}></i>
                        </div>
                        {isShowRowsOpen && (
                          <div className={styles.showRowsOptions}>
                            {[10, 25, 50, 100].map((rows) => (
                              <div
                                key={rows}
                                className={`${styles.showRowsOption} ${rowsPerPage === rows ? styles.selected : ''}`}
                                onClick={() => handleRowsPerPageChange(rows)}
                              >
                                <span>{rows} {Ltext('rows')}</span>
                                {rowsPerPage === rows && (
                                  <i className="fa fa-check" style={{ color: '#10b981' }}></i>
                                )}
                              </div>
                            ))}
                          </div>
                        )}
                      </div>
                    </div>
                  </div>
                )}
              </div>
            )}
            {/* <Button
              variant="ghost"
              size="small"
              className={styles.iconButton}
              aria-label="Download"
            >
              <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                <path d="M10 13V3M10 13L6 9M10 13L14 9M3 16H17" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
              </svg>
            </Button> */}
          </div>
        </div>

        {loading ? (
          <div className={styles.loadingState}>
            <div className={styles.loadingSpinner}></div>
            <div>{Ltext('Loading customers...')}</div>
          </div>
        ) : error ? (
          <div className={styles.errorState}>
            <p>{error}</p>
            <Button variant="primary" onClick={() => fetchCustomers(page)}>
              {Ltext('Retry')}
            </Button>
          </div>
        ) : tableData.length === 0 ? (
          <div className={styles.emptyState}>{Ltext('No customers found')}</div>
        ) : (
          <div className={styles.tableSection}>
            <Table
              data={tableData}
              getRowKey={(row) => row.rowId}
              wrapperClassName="gibbs-customer-root"
              enableDragScroll
              sortConfig={sortConfig}
              onSort={handleSort}
              onRowMouseEnter={(row, rowIndex) => {
                const index = tableData.indexOf(row);
                setHoveredRowIndex(index);
              }}
              onRowMouseLeave={() => setHoveredRowIndex(null)}
              columns={columns.map((col, colIndex) => ({
                ...col,
                tdClassName: `${col.tdClassName || ''} ${styles.tableCell} ${colIndex === 0 ? styles.stickyColumn : ''}`,
                tdStyle: (row, rowIndex) => {
                  const rowIndexInTable = tableData.indexOf(row);
                  const isHovered = hoveredRowIndex === rowIndexInTable;
                  const bgColor = isHovered ? '#ffffff' : (row.isUsergroup ? '#ffffff' : (rowIndexInTable % 2 === 0 ? '#ffffff' : '#ffffff'));
                  const baseStyle = col.tdStyle || {};
                  if (colIndex === 0) {
                    return {
                      ...baseStyle,
                      position: 'sticky',
                      left: 0,
                      zIndex: 5,
                      backgroundColor: bgColor,
                      boxShadow: '2px 0 4px rgba(0, 0, 0, 0.05)'
                    };
                  }
                  return baseStyle;
                },
                render: (row, rowIndex) => {
                  const content = col.render ? col.render(row, rowIndex) : (col.key ? row[col.key] : null);
                  if (row.isUsergroup && colIndex === 0) {
                    return <div className={styles.usergroupIndent}>{content}</div>;
                  }
                  return content;
                }
              }))}
              rowStyle={(row) => {
                const rowIndex = tableData.indexOf(row);
                const isHovered = hoveredRowIndex === rowIndex;
                const bgColor = isHovered ? '#ffffff' : (row.isUsergroup ? '#ffffff' : (rowIndex % 2 === 0 ? '#ffffff' : '#ffffff'));
                return { backgroundColor: bgColor };
              }}
            />
            <Pagination
              currentPage={page}
              totalPages={paginationMeta.totalPages}
              totalItems={paginationMeta.total}
              itemsPerPage={rowsPerPage}
              onPageChange={setPage}
              showInfo
            />
          </div>
        )}
      </div>
      
      {customerActions.includes('create_customer') && (
        <CreateCustomer
          isOpen={isCreateCustomerOpen}
          onClose={() => setIsCreateCustomerOpen(false)}
          apiUrl={apiUrl}
          user_token={user_token}
          owner_id={owner_id}
          onSuccess={() => {
            // After successful creation, reset sorting to company name (ascending) and go to first page.
            // The effect watching sortConfig/page will then refetch customers.
            setSortConfig({ key: 'company_name', direction: 'desc' });
            setPage(1);
          }}
          industries={industries}
          countries={availableCountries}
          packages={packages}
        />
      )}

      {customerActions.includes('edit_customer') && (
          <EditCustomer
            isOpen={isEditCustomerOpen}
            onClose={() => {
              setIsEditCustomerOpen(false);
              setEditingCustomer(null);
            }}
            apiUrl={apiUrl}
            user_token={user_token}
            owner_id={owner_id}
            customer={editingCustomer}
            onSuccess={() => {
              // Refresh current page after successful edit
              setSortConfig({ key: 'group_updated_at', direction: 'desc' });
              setPage(1);
            }}
            industries={industries}
            countries={availableCountries}
            customerActions={customerActions}
          />
      )}
      {customerActions.includes('edit_usergroup') && (
        <EditUsergroup
          isOpen={isEditUsergroupOpen}
          onClose={() => {
            setIsEditUsergroupOpen(false);
            setEditingUsergroup(null);
          }}
          apiUrl={apiUrl}
          user_token={user_token}
          owner_id={owner_id}
          usergroup={editingUsergroup}
          onSuccess={() => {
            // Refresh current page after successful edit
            fetchCustomers(page);
          }}
        />
      )}

      {/* DatePicker Portal - Renders outside the table */}
      {openDatePicker && datePickerPosition && datePickerData && typeof document !== 'undefined' && createPortal(
        <div
          style={{
            position: 'absolute',
            top: `${datePickerPosition.top}px`,
            left: `${datePickerPosition.left}px`,
            zIndex: 10000
          }}
        >
          <DatePicker
            selected={datePickerData.currentDate}
            onChange={(date) => {
              // Only proceed if a date was actually selected
              if (!date) {
                setOpenDatePicker(null);
                setDatePickerPosition(null);
                setDatePickerData(null);
                return;
              }
              
              // Convert Date to YYYY-MM-DD string format (timezone-safe)
              const newDate = formatDateToString(date);
              
              setNextInvoiceDates((prev) => ({
                ...prev,
                [datePickerData.superadmin]: newDate
              }));
              
              // Clear existing timeout for this superadmin
              if (nextInvoiceSaveTimeouts.current[datePickerData.superadmin]) {
                clearTimeout(nextInvoiceSaveTimeouts.current[datePickerData.superadmin]);
              }
              
              // Save to API with debounce (500ms)
              nextInvoiceSaveTimeouts.current[datePickerData.superadmin] = setTimeout(() => {
                saveNextInvoice(datePickerData.superadmin, newDate);
                delete nextInvoiceSaveTimeouts.current[datePickerData.superadmin];
              }, 500);
              
              // Close the DatePicker after selection
              setOpenDatePicker(null);
              setDatePickerPosition(null);
              setDatePickerData(null);
            }}
            inline
            dateFormat="yyyy-MM-dd"
            isClearable={false}
            calendarClassName={styles.datePickerCalendar}
          />
        </div>,
        document.body
      )}
    </div>
  );
}

export default GibbsCustomer;

