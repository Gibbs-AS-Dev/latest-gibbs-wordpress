import React, { useEffect, useState } from 'react';
import axios from 'axios';
import Button from '../components/Button';
import Modal from '../components/Modal';
import styles from '../assets/scss/CreateCustomer.module.scss';
import { Ltext } from '../utils/customer-translations';

/**
 * Edit customer notes in a modal.
 */
function EditCustomerNotes({ isOpen, onClose, apiUrl, user_token, owner_id, customer, onSuccess }) {
  const [notes, setNotes] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  // Set notes when modal opens or customer changes
  useEffect(() => {
    if (customer && isOpen) {
      // Get customer_notes from usermeta (preferred) or fallback to abdis_notes for backward compatibility
      setNotes(customer.customer_notes || customer.abdis_notes || '');
      setError(null);
    }
  }, [customer, isOpen]);

  // Reset form when modal closes
  useEffect(() => {
    if (!isOpen) {
      setNotes('');
      setError(null);
    }
  }, [isOpen]);

  const handleChange = (e) => {
    setNotes(e.target.value);
    if (error) setError(null);
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!customer || !customer.superadmin) return;

    setError(null);
    setLoading(true);

    try {
      const headers = user_token ? { Authorization: `Bearer ${user_token}` } : {};

      const payload = {
        action: 'updateCustomerNotes',
        superadmin_id: customer.superadmin,
        customer_notes: notes
      };

      const response = await axios.post(apiUrl, payload, { headers });

      if (response?.data?.success) {
        if (onSuccess) {
          onSuccess();
        }
        onClose();
      } else {
        setError(response?.data?.message || Ltext('Failed to update customer notes'));
      }
    } catch (err) {
      const message = err?.response?.data?.message || err?.message || Ltext('Failed to update customer notes');
      setError(message);
    } finally {
      setLoading(false);
    }
  };

  const handleClose = () => {
    if (!loading) {
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
          form="editCustomerNotesForm"
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
      title={Ltext('Edit Customer Notes')}
      size="medium"
      footer={renderFooter()}
      closeOnOverlayClick={false}
      className={styles.createCustomer}
    >
      <form id="editCustomerNotesForm" onSubmit={handleSubmit} className={styles.form}>
        {error && (
          <div className={styles.errorMessage}>
            {error}
          </div>
        )}

        <div className={`${styles.formRow} ${styles.formRowFullWidth}`}>
          <div className={styles.formGroup}>
            <label htmlFor="notes" className={styles.label}>
              {Ltext('Customer Notes')}
            </label>
            <textarea
              id="notes"
              name="notes"
              value={notes}
              onChange={handleChange}
              className={styles.input}
              disabled={loading}
              rows={8}
              placeholder={Ltext('Enter customer notes...')}
              style={{ resize: 'vertical', minHeight: '150px' }}
            />
          </div>
        </div>
      </form>
    </Modal>
  );
}

export default EditCustomerNotes;

