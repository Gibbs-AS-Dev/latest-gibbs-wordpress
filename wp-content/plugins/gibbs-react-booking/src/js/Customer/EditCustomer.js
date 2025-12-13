import React, { useEffect, useState, useRef } from 'react';
import axios from 'axios';
import PhoneInput from 'react-phone-input-2';
import 'react-phone-input-2/lib/style.css';
import Button from '../components/Button';
import Modal from '../components/Modal';
import styles from '../assets/scss/CreateCustomer.module.scss';
import { Ltext } from '../utils/customer-translations';

/**
 * Edit existing customer (company + superadmin) in a modal.
 * Layout is similar to the "Add New Customer" modal, but fields are pre‑filled.
 */
function EditCustomer({ isOpen, onClose, apiUrl, user_token, owner_id, customer, onSuccess, industries, countries }) {
  const [formData, setFormData] = useState({
    company_company_name: '',
    company_email: '',
    company_industry: '',
    company_country: 'NO',
    company_country_code: '+47',
    company_phone: '',
    company_organization_number: '',
    company_street_address: '',
    company_zip_code: '',
    company_city: '',
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    country_code: '+47'
  });
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [confirmError, setConfirmError] = useState(null);
  const [superadminSearch, setSuperadminSearch] = useState('');
  const [availableSuperadmins, setAvailableSuperadmins] = useState([]);
  const [selectedSuperadminId, setSelectedSuperadminId] = useState(null);
  const [searchingSuperadmins, setSearchingSuperadmins] = useState(false);
  const [fetchingCustomer, setFetchingCustomer] = useState(false);
  const [fullCustomerData, setFullCustomerData] = useState(null);
  const [superadminId, setSuperadminId] = useState(null);
  const [showConfirmDialog, setShowConfirmDialog] = useState(false);
  const [pendingSuperadmin, setPendingSuperadmin] = useState(null);
  const [checkingEmail, setCheckingEmail] = useState(false);
  const superadminSectionRef = useRef(null);

  // Fetch full customer data if only minimal data is provided
  useEffect(() => {
    if (!customer || !isOpen) return;
    if (customer?.superadmin) {
      const fetchCustomerData = async () => {
        setFetchingCustomer(true);
        setError(null);
        setConfirmError(null);
        
        try {
          const headers = user_token ? { Authorization: `Bearer ${user_token}` } : {};
          const response = await axios.get(apiUrl, {
            params: {
              action: 'getGibbsCustomer',
              owner_id,
              superadmin_id: customer.superadmin
            },
            headers
          });

          if (response?.data?.success && response.data.data?.customer) {
            console.log(response.data.data.customer);
            setFullCustomerData(response.data.data.customer);
          } else {
            setError(response?.data?.message || Ltext('Failed to fetch customer data'));
          }
        } catch (err) {
          const message = err?.response?.data?.message || err?.message || Ltext('Failed to fetch customer data');
          setError(message);
        } finally {
          setFetchingCustomer(false);
        }
      };

      fetchCustomerData();
    }
  }, [customer]);

  // Reset full customer data when modal closes
  useEffect(() => {
    if (!isOpen) {
      setFullCustomerData(null);
      setFetchingCustomer(false);
      setAvailableSuperadmins([]);
      setSuperadminSearch('');
    }
  }, [isOpen]);

  // Populate form when customer changes / modal opens
  useEffect(() => {
    if (fullCustomerData?.ID){
      setSuperadminId(fullCustomerData.ID);
      const customerData = fullCustomerData;
      const metaData = customerData.meta_data || {};
      
      setFormData({
        company_company_name: metaData.company_company_name || '',
        company_organization_number: metaData.company_organization_number || '',
        company_street_address: metaData.company_street_address || '',
        company_zip_code: metaData.company_zip_code || '',
        company_city: metaData.company_city || '',
        company_industry: metaData.company_industry || '',
        company_country: metaData.company_country || 'NO',
        company_country_code: metaData.company_country_code || '+47',
        company_phone: metaData.company_phone || '',
        company_email: metaData.company_email || '',
        first_name: metaData.first_name || '',
        last_name: metaData.last_name || '',
        email: customerData.user_email || '',
        phone: metaData.phone || '',
        country_code: metaData.country_code || '+47',
      });
      setError(null);
    }
  }, [fullCustomerData]);

  // Search for superadmins
  useEffect(() => {
    const searchSuperadmins = async () => {
      if (!apiUrl || !superadminSearch.trim()) {
        setAvailableSuperadmins([]);
        return;
      }

      setSearchingSuperadmins(true);
      try {
        const headers = user_token ? { Authorization: `Bearer ${user_token}` } : {};
        const response = await axios.get(apiUrl, {
          params: {
            action: 'getUsers',
            owner_id,
            search: superadminSearch,
            per_page: 20,
            page: 1
          },
          headers
        });

        if (response?.data?.success) {
          const users = response.data.data?.users || [];
          // Extract unique superadmins from customers
          const superadminsMap = new Map();
          users.forEach(user => {
            if (user.id) {
              const id = user.id;
              if (!superadminsMap.has(id)) {
                superadminsMap.set(id, {
                  id,
                  name: `${user.first_name || ''} ${user.last_name || ''}`.trim() || user.display_name,
                  email: user.user_email
                });
              }
            }
          });
          setAvailableSuperadmins(Array.from(superadminsMap.values()));
        }
      } catch (err) {
        console.warn('Failed to search superadmins:', err);
        setAvailableSuperadmins([]);
      } finally {
        setSearchingSuperadmins(false);
      }
    };

    const timeoutId = setTimeout(searchSuperadmins, 300);
    return () => clearTimeout(timeoutId);
  }, [superadminSearch, apiUrl, user_token, owner_id]);

  // Scroll modal body to the "Change Superadmin" section when results are loaded
  useEffect(() => {
    if (availableSuperadmins.length > 0 && superadminSectionRef.current) {
      superadminSectionRef.current.scrollIntoView({
        behavior: 'smooth',
        block: 'nearest'
      });
    }
  }, [availableSuperadmins]);

  const handleSuperadminSelect = (superadmin) => {
    setConfirmError(null);
    // Show confirmation dialog
    setPendingSuperadmin(superadmin);
    setShowConfirmDialog(true);
  };

  const handleConfirmSuperadminSelect = async () => {
    if (!pendingSuperadmin) return;

    setCheckingEmail(true);
    setConfirmError(null);

    try {
      // Check if email already exists
      const headers = user_token ? { Authorization: `Bearer ${user_token}` } : {};
      const payload = {
        action: 'changeSuperAdmin',
        old_superadmin_id: superadminId,
        new_superadmin_id: pendingSuperadmin.id
      };
      const response = await axios.post(apiUrl, payload, { headers });

      // If email already exists (error response)
      if (response?.data?.success) {
        setShowConfirmDialog(false);
        setPendingSuperadmin(null);
        setCheckingEmail(false);
        onClose();
        if (onSuccess) {
          onSuccess();
        }
      }else{
        setConfirmError(response?.data?.message || 'Failed to change superadmin');
      }
    } catch (err) {
      const message = err?.response?.data?.message || err?.message || 'Failed to change superadmin';
      setConfirmError(message);
    } finally {
      setCheckingEmail(false);
    }
  };

  const handleCancelSuperadminSelect = () => {
    setShowConfirmDialog(false);
    setPendingSuperadmin(null);
    setCheckingEmail(false);
  };

  const handleChange = (e) => {
    const { name, value } = e.target;
    setFormData((prev) => ({
      ...prev,
      [name]: value
    }));
    if (error) setError(null);
  };

  const handleCountryCodeChange = (value, country) => {
    const countryCode = country.dialCode ? `+${country.dialCode}` : '+47';
    setFormData((prev) => ({
      ...prev,
      country_code: countryCode
    }));
    if (error) setError(null);
  };
  const handleCompanyCountryCodeChange = (value, country) => {
    const countryCode = country.dialCode ? `+${country.dialCode}` : '+47';
    setFormData((prev) => ({
      ...prev,
      company_country_code: countryCode
    }));
    if (error) setError(null);
  };

  const getCountryCodeDigits = (country_code) => {
    return country_code.replace('+', '') || '47';
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!customer) return;
    if (!superadminId) return;

    setError(null);
    setLoading(true);

    try {
      const headers = user_token ? { Authorization: `Bearer ${user_token}` } : {};

      // Use PUT for updating existing customer
      const payload = {
        action: 'updateGibbsSuperadmin',
        superadmin_id: superadminId,
        ...formData
      };

      const response = await axios.put(apiUrl, payload, { headers });

      if (response?.data?.success) {
        if (onSuccess) {
          onSuccess();
        }
        onClose();
      } else {
        setError(response?.data?.message || Ltext('Failed to update customer'));
      }
    } catch (err) {
      const message = err?.response?.data?.message || err?.message || Ltext('Failed to update customer');
      setError(message);
    } finally {
      setLoading(false);
    }
  };

  const handleClose = () => {
    if (!loading && !fetchingCustomer) {
      onClose();
    }
  };

  const renderFooter = () => {
    return (
      <div className={styles.footer}>
        <Button
          type="button"
          variant="cancel"
          onClick={handleClose}
          disabled={loading || fetchingCustomer}
        >
          {Ltext('Cancel')}
        </Button>
        <Button
          type="submit"
          variant="primary"
          loading={loading}
          disabled={loading || fetchingCustomer}
          form="editCustomerForm"
        >
          {Ltext('Save')}
        </Button>
      </div>
    );
  };

  return (
    <Modal
      isOpen={isOpen}
      onClose={handleClose}
      title={Ltext('Company & Superadmin')}
      size="medium"
      footer={renderFooter()}
     // closeOnOverlayClick={!loading && !fetchingCustomer}
      closeOnOverlayClick={false}
      className={styles.createCustomer}
    >
      <form id="editCustomerForm" onSubmit={handleSubmit} className={styles.form}>
        {fetchingCustomer && (
          <div className={styles.errorMessage} style={{ backgroundColor: '#f0f9ff', color: '#0369a1', borderColor: '#bae6fd' }}>
            {Ltext('Loading customer data...')}
          </div>
        )}
        {error && (
          <div className={styles.errorMessage}>
            {error}
          </div>
        )}

        {/* Confirmation Dialog */}
        {showConfirmDialog && (
          <div style={{
            position: 'fixed',
            top: 0,
            left: 0,
            right: 0,
            bottom: 0,
            backgroundColor: 'rgba(0, 0, 0, 0.5)',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            zIndex: 10000
          }}>
            <div style={{
              backgroundColor: '#ffffff',
              borderRadius: '8px',
              padding: '24px',
              maxWidth: '500px',
              width: '90%',
              boxShadow: '0 4px 6px rgba(0, 0, 0, 0.1)'
            }}>
              {confirmError && (
                <div className={styles.errorMessage}>
                  {confirmError}
                </div>
              )}
              <div style={{ marginBottom: '16px' }}>
                <h3 style={{ margin: 0, fontSize: '18px', fontWeight: 600 }}>{Ltext('Confirm Selection')}</h3>
              </div>
              <div style={{ marginBottom: '20px' }}>
                <p style={{ margin: '0 0 16px 0', color: '#374151' }}>
                  {Ltext('Are you sure you want to proceed with selecting this superadmin?')}
                </p>
                {pendingSuperadmin && (
                  <div style={{
                    backgroundColor: '#f9fafb',
                    padding: '12px',
                    borderRadius: '6px',
                    fontSize: '14px',
                    color: '#374151'
                  }}>
                    <div style={{ marginBottom: '8px' }}>
                      <strong>{Ltext('Name:')}</strong> {pendingSuperadmin.name}
                    </div>
                    <div>
                      <strong>{Ltext('Email:')}</strong> {pendingSuperadmin.email}
                    </div>
                  </div>
                )}
              </div>
              <div style={{
                display: 'flex',
                gap: '12px',
                justifyContent: 'flex-end'
              }}>
                <Button
                  type="button"
                  variant="cancel"
                  onClick={handleCancelSuperadminSelect}
                  disabled={checkingEmail}
                >
                  {Ltext('Cancel')}
                </Button>
                <Button
                  type="button"
                  variant="primary"
                  onClick={handleConfirmSuperadminSelect}
                  loading={checkingEmail}
                  disabled={checkingEmail}
                >
                  {Ltext('Confirm')}
                </Button>
              </div>
            </div>
          </div>
        )}

        {/* Company information */}
        <div className={styles.sectionTitle}>{Ltext('Company Information')}</div>
        <div className={`${styles.formRow}`}>
          <div className={styles.formGroup}>
            <label htmlFor="company_company_name" className={styles.label}>
              {Ltext('Company Name')}
            </label>
            <input
              type="text"
              id="company_company_name"
              name="company_company_name"
              value={formData.company_company_name}
              onChange={handleChange}
              className={styles.input}
              disabled={loading || fetchingCustomer}
              required={true}
            />
          </div>
          <div className={styles.formGroup}>
                <label htmlFor="company_email" className={styles.label}>
                {Ltext('Company Email')}
                </label>
                <input
                type="email"
                id="company_email"
                name="company_email"
                value={formData.company_email}
                onChange={handleChange}
                className={styles.input}
                disabled={loading || fetchingCustomer}
                required={true}
                />
            </div>
        </div>

        <div className={`${styles.formRow}`}>
          <div className={styles.formGroup}>
            <label htmlFor="company_industry" className={styles.label}>
              {Ltext('Organization type')}
            </label>
            <select
              id="company_industry"
              name="company_industry"
              value={formData.company_industry}
              onChange={handleChange}
              className={styles.input}
              disabled={loading || fetchingCustomer}
              required={true}
            >
              <option value="">{Ltext('Select Industry')}</option>
              {industries && Object.entries(industries).map(([key, Industryvalue]) => (
                <option value={Industryvalue}>{Ltext(Industryvalue)}</option>
              ))}
            </select>
          </div>
          <div className={styles.formGroup}>
                <label htmlFor="company_country" className={styles.label}>
                {Ltext('Country')}
                </label>
                <select
                id="company_country"
                name="company_country"
                value={formData.company_country}
                onChange={handleChange}
                className={styles.input}
                disabled={loading || fetchingCustomer}
                required={true}>
                <option value="">{Ltext('Select Country')}</option>
                {countries.map((country) => (
                  <option value={country.code}>{country.name}</option>
                ))}
                </select>
            </div>
        </div>

        <div className={`${styles.formRow} ${styles.formRowflexWidth} react_phone_input_container`}>
          <div className={styles.formGroup}>
            <label htmlFor="country_code" className={styles.label}>
              {Ltext('Country Code')}
            </label>
            <PhoneInput
              country={'no'}
              value={getCountryCodeDigits(formData.company_country_code)}
              onChange={handleCompanyCountryCodeChange}
              inputProps={{
                readOnly: true,
                disabled: loading
              }}
              readonly={true}
              enableSearch={false}
              inputStyle={{
                width: '0%',
                border: '1px solid #d1d5db',
                borderLeft: 'none',
                borderRadius: '0 6px 6px 0',
                padding: '10px 12px',
                fontSize: '14px',
                transition: 'all 0.2s'
              }}
              containerStyle={{ width: '100%' }}
              buttonStyle={{
                border: '1px solid #d1d5db',
                borderRight: 'none',
                borderRadius: '6px 0 0 6px',
                backgroundColor: '#ffffff'
              }}
              countryCodeEditable={false}
              disabled={loading || fetchingCustomer}
            />
          </div>
          <div className={styles.formGroup}>
              <label htmlFor="company_phone" className={styles.label}>
                {Ltext('Phone Number')}
              </label>
              <input
                type="tel"
                id="company_phone"
                name="company_phone"
                value={formData.company_phone}
                onChange={handleChange}
                className={styles.input}
                disabled={loading || fetchingCustomer}
                required={true}
              />
          </div>
          <div className={styles.formGroup}>
            <label htmlFor="company_organization_number" className={styles.label}>
              {Ltext('Organization Number')}
            </label>
            <input
              type="text"
              id="company_organization_number"
              name="company_organization_number"
              value={formData.company_organization_number}
              onChange={handleChange}
              className={styles.input}
              disabled={loading || fetchingCustomer}
            />
          </div>
        </div>

        <div className={`${styles.formRow} ${styles.formRowFullWidth}`}>
         
          <div className={styles.formGroup}>
            <label htmlFor="company_street_address" className={styles.label}>
              {Ltext('Street Address')}
            </label>
            <input
              type="text"
              id="company_street_address"
              name="company_street_address"
              value={formData.company_street_address}
              onChange={handleChange}
              className={styles.input}
              disabled={loading || fetchingCustomer}
            />
          </div>
        </div>

        <div className={styles.formRow}>
          <div className={styles.formGroup}>
            <label htmlFor="company_zip_code" className={styles.label}>
              {Ltext('Zip')}
            </label>
            <input
              type="text"
              id="company_zip_code"
              name="company_zip_code"
              value={formData.company_zip_code}
              onChange={handleChange}
              className={styles.input}
              disabled={loading || fetchingCustomer}
            />
          </div>
          <div className={styles.formGroup}>
            <label htmlFor="company_city" className={styles.label}>
              {Ltext('City')}
            </label>
            <input
              type="text"
              id="company_city"
              name="company_city"
              value={formData.company_city}
              onChange={handleChange}
              className={styles.input}
              disabled={loading || fetchingCustomer}
            />
          </div>
        </div>

        {/* Superadmin information */}
        <div className={styles.sectionTitle}>{Ltext('Superadmin Information')}</div>
        {/* Superadmin information */}
        <div className={styles.formRow}>
          <div className={styles.formGroup}>
            <label htmlFor="first_name" className={styles.label}>
              {Ltext('First Name')}
            </label>
            <input
              type="text"
              id="first_name"
              name="first_name"
              value={formData.first_name}
              onChange={handleChange}
              className={styles.input}
              disabled={loading || fetchingCustomer}
              required={true}
            />
          </div>
          <div className={styles.formGroup}>
            <label htmlFor="last_name" className={styles.label}>
              {Ltext('Last Name')}
            </label>
            <input
              type="text"
              id="last_name"
              name="last_name"
              value={formData.last_name}
              onChange={handleChange}
              className={styles.input}
              disabled={loading || fetchingCustomer}
            />
          </div>
        </div>

        <div className={`${styles.formRow} ${styles.formRowFullWidth}`}>
          <div className={styles.formGroup}>
            <label htmlFor="email" className={styles.label}>
              {Ltext('Email')}
            </label>
            <input
              type="email"
              id="email"
              name="email"
              value={formData.email}
              onChange={handleChange}
              className={styles.input}
              disabled={loading || fetchingCustomer}
              required={true}
              readOnly={true}
            />
          </div>
        </div>

        <div className={`${styles.formRow} ${styles.formRowPhone} react_phone_input_container`}>
          <div className={styles.formGroup}>
            <label htmlFor="country_code" className={styles.label}>
              {Ltext('Country Code')}
            </label>
            <PhoneInput
              country={'no'}
              value={getCountryCodeDigits(formData.country_code)}
              onChange={handleCountryCodeChange}
              inputProps={{
                readOnly: true,
                disabled: loading
              }}
              readonly={true}
              enableSearch={false}
              inputStyle={{
                width: '0%',
                border: '1px solid #d1d5db',
                borderLeft: 'none',
                borderRadius: '0 6px 6px 0',
                padding: '10px 12px',
                fontSize: '14px',
                transition: 'all 0.2s'
              }}
              containerStyle={{ width: '100%' }}
              buttonStyle={{
                border: '1px solid #d1d5db',
                borderRight: 'none',
                borderRadius: '6px 0 0 6px',
                backgroundColor: '#ffffff'
              }}
              countryCodeEditable={false}
              disabled={loading || fetchingCustomer}
            />
          </div>
          <div className={styles.formGroup}>
            <label htmlFor="phone" className={styles.label}>
              {Ltext('Phone')}
            </label>
            <input
              type="tel"
              id="phone"
              name="phone"
              value={formData.phone}
              onChange={handleChange}
              className={styles.input}
              disabled={loading || fetchingCustomer}
              required={true}
            />
          </div>
        </div>

        {/* Change Superadmin */}
        <div className={styles.sectionTitle}>{Ltext('Change Superadmin')}</div>
        <div className={styles.superadminSearchWrapper} ref={superadminSectionRef}>
          <div className={styles.superadminSearchInput}>
            <i className={`fa fa-search ${styles.searchIcon}`}></i>
            <input
              type="text"
              placeholder={Ltext('Search for superadmin...')}
              value={superadminSearch}
              onChange={(e) => setSuperadminSearch(e.target.value)}
              className={styles.superadminSearch}
              disabled={loading || fetchingCustomer}
            />
          </div>
          {availableSuperadmins.length > 0 && (
            <div className={styles.superadminList}>
              {availableSuperadmins.map((superadmin) => (
                <div
                  key={superadmin.id}
                  className={`${styles.superadminItem} ${selectedSuperadminId === superadmin.id ? styles.selected : ''}`}
                  onClick={() => handleSuperadminSelect(superadmin)}
                >
                  <i className={`fa fa-user ${styles.userIcon}`}></i>
                  <div className={styles.superadminInfo}>
                    <div className={styles.superadminName}>{superadmin.name}</div>
                    <div className={styles.superadminEmail}>{superadmin.email}</div>
                  </div>
                  <span className={styles.superadminAction}>{Ltext('Select')}</span>
                </div>
              ))}
            </div>
          )}
          {searchingSuperadmins && (
            <div className={styles.searchingIndicator}>{Ltext('Loading...')}</div>
          )}
        </div>
      </form>
    </Modal>
  );
}

export default EditCustomer;


