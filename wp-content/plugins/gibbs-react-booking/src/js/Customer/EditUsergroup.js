import React, { useEffect, useState } from 'react';
import axios from 'axios';
import Button from '../components/Button';
import Modal from '../components/Modal';
import styles from '../assets/scss/CreateCustomer.module.scss';
import { Ltext } from '../utils/customer-translations';

/**
 * Edit existing usergroup in a modal.
 * Fields: Usergroup Name, Email, Email CC
 */
function EditUsergroup({ isOpen, onClose, apiUrl, user_token, owner_id, usergroup, onSuccess }) {
  const [formData, setFormData] = useState({
    name: '',
    email: '',
    email_cc: ''
  });
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [fetchingUsergroup, setFetchingUsergroup] = useState(false);

  // Fetch usergroup data when modal opens
  useEffect(() => {
    if (!usergroup || !isOpen) return;
    
    const fetchUsergroupData = async () => {
      setFetchingUsergroup(true);
      setError(null);
      
      try {
        const headers = user_token ? { Authorization: `Bearer ${user_token}` } : {};
        const response = await axios.get(apiUrl, {
          params: {
            action: 'getGibbsUsergroup',
            owner_id,
            usergroup_id: usergroup.id
          },
          headers
        });

        if (response?.data?.success && response.data.data?.usergroup) {
          const usergroupData = response.data.data.usergroup;
          setFormData({
            name: usergroupData.name || '',
            email: usergroupData.email || '',
            email_cc: usergroupData.email_cc || ''
          });
        } else {
          // If API fails, use data from props
          setFormData({
            name: usergroup.name || '',
            email: usergroup.email || '',
            email_cc: usergroup.email_cc || ''
          });
        }
      } catch (err) {
        // If API fails, use data from props
        setFormData({
          name: usergroup.name || '',
          email: usergroup.email || '',
          email_cc: usergroup.email_cc || ''
        });
      } finally {
        setFetchingUsergroup(false);
      }
    };

    fetchUsergroupData();
  }, [usergroup, isOpen, apiUrl, owner_id, user_token]);

  // Reset form when modal closes
  useEffect(() => {
    if (!isOpen) {
      setFormData({ name: '', email: '', email_cc: '' });
      setError(null);
      setFetchingUsergroup(false);
    }
  }, [isOpen]);

  const handleChange = (e) => {
    const { name, value } = e.target;
    setFormData((prev) => ({
      ...prev,
      [name]: value
    }));
    if (error) setError(null);
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!usergroup) return;

    setError(null);
    setLoading(true);

    try {
      const headers = user_token ? { Authorization: `Bearer ${user_token}` } : {};

      const payload = {
        action: 'updateGibbsUsergroup',
        usergroup_id: usergroup.id,
        ...formData
      };

      const response = await axios.put(apiUrl, payload, { headers });

      if (response?.data?.success) {
        if (onSuccess) {
          onSuccess();
        }
        onClose();
      } else {
        setError(response?.data?.message || Ltext('Failed to update usergroup'));
      }
    } catch (err) {
      const message = err?.response?.data?.message || err?.message || Ltext('Failed to update usergroup');
      setError(message);
    } finally {
      setLoading(false);
    }
  };

  const handleClose = () => {
    if (!loading && !fetchingUsergroup) {
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
          disabled={loading || fetchingUsergroup}
        >
          {Ltext('Cancel')}
        </Button>
        <Button
          type="submit"
          variant="primary"
          loading={loading}
          disabled={loading || fetchingUsergroup}
          form="editUsergroupForm"
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
      title={Ltext('Edit Usergroup')}
      size="medium"
      footer={renderFooter()}
     // closeOnOverlayClick={!loading && !fetchingUsergroup}
      closeOnOverlayClick={false}
      className={styles.createCustomer}
    >
      <form id="editUsergroupForm" onSubmit={handleSubmit} className={styles.form}>
        {fetchingUsergroup && (
          <div className={styles.errorMessage} style={{ backgroundColor: '#f0f9ff', color: '#0369a1', borderColor: '#bae6fd' }}>
            {Ltext('Loading usergroup data...')}
          </div>
        )}
        {error && (
          <div className={styles.errorMessage}>
            {error}
          </div>
        )}

        <div className={`${styles.formRow} ${styles.formRowFullWidth}`}>
          <div className={styles.formGroup}>
            <label htmlFor="name" className={styles.label}>
              {Ltext('Usergroup Name')}
            </label>
            <input
              type="text"
              id="name"
              name="name"
              value={formData.name}
              onChange={handleChange}
              className={styles.input}
              disabled={loading || fetchingUsergroup}
              required
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
              disabled={loading || fetchingUsergroup}
              required
            />
          </div>
        </div>

        <div className={`${styles.formRow} ${styles.formRowFullWidth}`}>
          <div className={styles.formGroup}>
            <label htmlFor="email_cc" className={styles.label}>
              {Ltext('Email CC')}
            </label>
            <input
              type="text"
              id="email_cc"
              name="email_cc"
              value={formData.email_cc}
              onChange={handleChange}
              className={styles.input}
              disabled={loading || fetchingUsergroup}
              placeholder={Ltext('cc@example.com')}
            />
          </div>
        </div>
      </form>
    </Modal>
  );
}

export default EditUsergroup;

