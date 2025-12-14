import React, { useState } from 'react';
import axios from 'axios';
import PhoneInput from 'react-phone-input-2';
import 'react-phone-input-2/lib/style.css';
import Button from '../components/Button';
import Modal from '../components/Modal';
import styles from '../assets/scss/CreateCustomer.module.scss';
import { Ltext } from '../utils/customer-translations';

function CreateCustomer({ isOpen, onClose, apiUrl, user_token, owner_id, onSuccess, industries, countries, packages }) {
  const [formData, setFormData] = useState({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    country_code: '+47',
    stripe_license: '',
    payment: '',
    group_name: '',
    package_id: '',
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
  });
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  const handleChange = (e) => {
    const { name, value } = e.target;
    setFormData(prev => ({
      ...prev,
      [name]: value
    }));
    // Clear error when user starts typing
    if (error) setError(null);
  };

  const handleCountryCodeChange = (value, country) => {
    const countryCode = country.dialCode ? `+${country.dialCode}` : '+47';
    setFormData(prev => ({
      ...prev,
      country_code: countryCode
    }));
    // Clear error when user starts typing
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

  // Get country code digits for PhoneInput value (without +)
  const getCountryCodeDigits = () => {
    return formData.country_code.replace('+', '') || '47';
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError(null);
    setLoading(true);

    try {
      const headers = user_token ? { Authorization: `Bearer ${user_token}` } : {};
      
      // Create new customer
      const response = await axios.post(apiUrl, {
        action: 'createGibbsCustomer',
        owner_id,
        ...formData
      }, { headers });

      if (response?.data?.success) {
        resetForm();
        onClose();
        if (onSuccess) {
          onSuccess();
        }
      } else {
        setError(response?.data?.message || Ltext('Failed to create customer'));
      }
    } catch (err) {
      const message = err?.response?.data?.message || err?.message || Ltext('Failed to create customer');
      setError(message);
    } finally {
      setLoading(false);
    }
  };

  const resetForm = () => {
    setFormData({
      company_name: '',
      first_name: '',
      last_name: '',
      email: '',
      phone: '',
      country_code: '+47',
      stripe_license: '',
      payment: '',
      group_name: '',
      package_id: '',
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
    });
    setError(null);
  };

  const handleClose = () => {
    if (!loading) {
      resetForm();
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
          disabled={loading}
        >
          {Ltext('Cancel')}
        </Button>
        <Button
          type="submit"
          variant="primary"
          loading={loading}
          disabled={loading}
          form="createCustomerForm"
        >
          {Ltext('Register')}
        </Button>
      </div>
    );
  };

  return (
    <Modal
      isOpen={isOpen}
      onClose={handleClose}
      title={Ltext('Add New Customer')}
      size="medium"
      footer={renderFooter()}
     // closeOnOverlayClick={!loading}
      closeOnOverlayClick={false}
      className={styles.createCustomer}
    >
      <form id="createCustomerForm" onSubmit={handleSubmit} className={styles.form}>
        {error && (
          <div className={styles.errorMessage}>
            {error}
          </div>
        )}
        {/* Superadmin information */}
        <div className={styles.sectionTitle}>{Ltext('Superadmin Information')}</div>
        <div className={styles.formRow}>
          <div className={styles.formGroup}>
            <label htmlFor="first_name" className={styles.label}>
              {Ltext('First Name')} <span className={styles.required}>*</span>
            </label>
            <input
              type="text"
              id="first_name"
              name="first_name"
              value={formData.first_name}
              onChange={handleChange}
              className={styles.input}
              placeholder={Ltext('Ola')}
              required
              disabled={loading}
            />
          </div>
          <div className={styles.formGroup}>
            <label htmlFor="last_name" className={styles.label}>
              {Ltext('Last Name')} <span className={styles.required}>*</span>
            </label>
            <input
              type="text"
              id="last_name"
              name="last_name"
              value={formData.last_name}
              onChange={handleChange}
              className={styles.input}
              placeholder={Ltext('Nordmann')}
              required
              disabled={loading}
            />
          </div>
        </div>

        <div className={`${styles.formRow} ${styles.formRowFullWidth}`}>
          <div className={styles.formGroup}>
            <label htmlFor="email" className={styles.label}>
              {Ltext('Email')} <span className={styles.required}>*</span>
            </label>
            <input
              type="email"
              id="email"
              name="email"
              value={formData.email}
              onChange={handleChange}
              className={styles.input}
              required
              disabled={loading}
            />
          </div>
        </div>

        <div className={`${styles.formRow} ${styles.formRowPhone} react_phone_input_container`}>
          <div className={styles.formGroup}>
            <label htmlFor="country_code" className={styles.label}>
              {Ltext('Country Code')} <span className={styles.required}>*</span>
            </label>
            <PhoneInput
              country={'no'}
              value={getCountryCodeDigits()}
              onChange={handleCountryCodeChange}
              inputProps={{
                readOnly: true,
                disabled: loading,
                required: true
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
              disabled={loading}
            />
          </div>
          <div className={styles.formGroup}>
            <label htmlFor="phone" className={styles.label}>
              {Ltext('Phone')} <span className={styles.required}>*</span>
            </label>
            <input
              type="tel"
              id="phone"
              name="phone"
              value={formData.phone}
              onChange={handleChange}
              className={styles.input}
              placeholder={Ltext('900 00 000')}
              required
              disabled={loading}
            />
          </div>
        </div>

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
              disabled={loading}
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
                disabled={loading}
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
              disabled={loading}
              required={true}
            >
              <option value="">{Ltext('Select Industry')}</option>
              {industries && Object.entries(industries).map(([key, Industryvalue]) => (
                <option key={key} value={Industryvalue}>{Ltext(Industryvalue)}</option>
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
                disabled={loading}
                required={true}>
                <option value="">{Ltext('Select Country')}</option>
                {countries.map((country) => (
                  <option key={country.code} value={country.code}>{country.name}</option>
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
              disabled={loading}
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
                disabled={loading}
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
              disabled={loading}
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
              disabled={loading}
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
              disabled={loading}
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
              disabled={loading}
            />
          </div>
        </div>
        
        {/* Group Name */}
        <div className={styles.sectionTitle}>{Ltext('Group information')}</div>
        <div className={`${styles.formRow} ${styles.formRowFullWidth}`}>
          <div className={styles.formGroup}>
            <label htmlFor="group_name" className={styles.label}>
              {Ltext('Group Name')} <span className={styles.required}>*</span>
            </label>
            <input
              type="text"
              id="group_name"
              name="group_name"
              value={formData.group_name}
              onChange={handleChange}
              className={styles.input}
              required
              disabled={loading}
            />
          </div>
        </div>
        {/* Package Selection */}
        <div className={styles.sectionTitle}>{Ltext('Package Information')}</div>
        <div className={`${styles.formRow} ${styles.formRowFullWidth}`}>
          <div className={styles.formGroup}>
            <label htmlFor="package_id" className={styles.label}>
              {Ltext('Package')} <span className={styles.required}>*</span>
            </label>
            <select
              id="package_id"
              name="package_id"
              value={formData.package_id}
              onChange={handleChange}
              className={styles.input}
              required
              disabled={loading}
            >
              <option value="">{Ltext('Select Package')}</option>
              {packages && packages.map((pkg) => (
                <option key={pkg.ID} value={pkg.ID}>
                  {pkg.post_title}
                </option>
              ))}
            </select>
          </div>
        </div>
      </form>
    </Modal>
  );
}

export default CreateCustomer;

