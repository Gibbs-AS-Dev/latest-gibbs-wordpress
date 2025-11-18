import React, { useCallback, useEffect, useMemo, useState } from 'react';
import axios from 'axios';
import Table from '../components/Table';
import Modal from '../components/Modal';
import { Ltext, getLanguage } from '../utils/subscriptionDiscount-translations';
import styles from '../assets/scss/SubscriptionDiscount.module.scss';
import tableStyles from '../assets/scss/table.module.scss';
import '../assets/scss/SubscriptionDiscount.scss';

const INITIAL_FORM_STATE = {
  name: '',
  code: '',
  type: 'percentage',
  value: '',
  maxRedemptions: '',
  startDate: '',
  endDate: '',
  notes: '',
  status: 'active'
};

function SubscriptionDiscount({ apiUrl, user_token, owner_id }) {
  const [discounts, setDiscounts] = useState([]);
  const [page, setPage] = useState(1);
  const [perPage] = useState(20);
  const [paginationMeta, setPaginationMeta] = useState({ total: 0, totalPages: 1 });
  const [statusFilter, setStatusFilter] = useState('all');
  const [searchValue, setSearchValue] = useState('');
  const [searchQuery, setSearchQuery] = useState('');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [feedback, setFeedback] = useState(null);
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [formData, setFormData] = useState(INITIAL_FORM_STATE);
  const [formErrors, setFormErrors] = useState({});
  const [submitting, setSubmitting] = useState(false);

  const locale = getLanguage();

  const numberFormatter = useMemo(() => {
    const localeMap = {
      no: 'nb-NO',
      'nb-NO': 'nb-NO',
      'nn-NO': 'nb-NO',
    };
    const resolved = localeMap[locale] || 'en-US';
    return new Intl.NumberFormat(resolved, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }, [locale]);

  useEffect(() => {
    const debounce = setTimeout(() => {
      setSearchQuery(searchValue.trim());
    }, 400);

    return () => clearTimeout(debounce);
  }, [searchValue]);

  useEffect(() => {
    setPage(1);
  }, [searchQuery, statusFilter]);

  const fetchDiscounts = useCallback(async (requestedPage = 1) => {
    if (!apiUrl) {
      setError('Missing API URL');
      setLoading(false);
      return;
    }

    if (!owner_id) {
      setError(Ltext('Authentication required to view discounts.'));
      setLoading(false);
      return;
    }

    setLoading(true);
    setError(null);

    try {
      const params = {
        action: 'getSubscriptionDiscounts',
        owner_id,
        page: requestedPage,
        per_page: perPage
      };

      if (searchQuery) {
        params.search = searchQuery;
      }

      if (statusFilter !== 'all') {
        params.status = statusFilter;
      }

      const headers = user_token ? { Authorization: `Bearer ${user_token}` } : {};
      const response = await axios.get(apiUrl, { params, headers });
      if (response?.data?.success) {
        const payload = response.data.data || {};
        const fetchedDiscounts = payload.discounts || [];
        const pagination = payload.pagination || {};

        const total = pagination.total ?? fetchedDiscounts.length;
        const totalPages = pagination.total_pages ?? 1;

        setDiscounts(fetchedDiscounts);
        setPaginationMeta({
          total,
          totalPages: totalPages > 0 ? totalPages : 1
        });

        if (requestedPage !== page) {
          setPage(requestedPage);
        }

        if (requestedPage > (totalPages || 1) && (totalPages || 1) > 0) {
          setPage(totalPages);
        }
      } else {
        setError(response?.data?.message || Ltext('Failed to load subscription discounts'));
      }
    } catch (err) {
      const message =
        err?.response?.data?.message ||
        err?.message ||
        Ltext('Failed to load subscription discounts');
      setError(message);
    } finally {
      setLoading(false);
    }
  }, [apiUrl, owner_id, perPage, searchQuery, statusFilter, user_token, page]);

  useEffect(() => {
    fetchDiscounts(page);
  }, [fetchDiscounts, page]);

  const resetForm = useCallback(() => {
    setFormData(INITIAL_FORM_STATE);
    setFormErrors({});
  }, []);

  const openModal = () => {
    resetForm();
    setIsModalOpen(true);
  };

  const closeModal = () => {
    setIsModalOpen(false);
    setSubmitting(false);
  };

  const handleInputChange = (event) => {
    const { name, value } = event.target;
    setFormData((prev) => ({
      ...prev,
      [name]: value
    }));
  };

  const validateForm = () => {
    const errors = {};
    const trimmedName = formData.name.trim();
    const trimmedCode = formData.code.trim().toUpperCase();

    if (!trimmedName) {
      errors.name = Ltext('Discount name') + ' *';
    }

    if (!trimmedCode) {
      errors.code = Ltext('Discount code') + ' *';
    } else if (!/^[A-Z0-9\-_]+$/.test(trimmedCode)) {
      errors.code = Ltext('Code may only contain letters, numbers, dashes, or underscores');
    }

    const numericValue = parseFloat(formData.value);
    if (Number.isNaN(numericValue) || numericValue <= 0) {
      errors.value = Ltext('Value must be greater than zero');
    } else if (formData.type === 'percentage' && numericValue > 100) {
      errors.value = Ltext('Percentage cannot exceed 100');
    }

    if (formData.maxRedemptions !== '') {
      const numericMax = parseInt(formData.maxRedemptions, 10);
      if (Number.isNaN(numericMax) || numericMax < 1) {
        errors.maxRedemptions = Ltext('Maximum redemptions must be at least 1');
      }
    }

    if (formData.startDate && formData.endDate) {
      const start = new Date(formData.startDate);
      const end = new Date(formData.endDate);
      if (end < start) {
        errors.endDate = Ltext('End date cannot be before start date');
      }
    }

    setFormErrors(errors);
    return { isValid: Object.keys(errors).length === 0, trimmedName, trimmedCode, numericValue };
  };

  const handleSubmit = async (event) => {
    event.preventDefault();
    if (submitting) return;

    const { isValid, trimmedName, trimmedCode, numericValue } = validateForm();
    if (!isValid) {
      return;
    }

    setSubmitting(true);
    setFeedback(null);

    const payload = {
      action: 'createSubscriptionDiscount',
      owner_id,
      discount: {
        name: trimmedName,
        code: trimmedCode,
        type: formData.type,
        value: numericValue,
        max_redemptions: formData.maxRedemptions !== '' ? parseInt(formData.maxRedemptions, 10) : null,
        start_date: formData.startDate || null,
        end_date: formData.endDate || null,
        notes: formData.notes ? formData.notes.trim() : null,
        status: formData.status || 'active'
      }
    };

    try {
      const headers = user_token ? { Authorization: `Bearer ${user_token}` } : {};
      const response = await axios.post(apiUrl, payload, { headers });
      if (response?.data?.success) {
        setFeedback({ type: 'success', message: response.data?.data?.message || Ltext('New discount created') });
        closeModal();
        resetForm();
        setPage(1);
        fetchDiscounts(1);
      } else {
        const message = response?.data?.message || Ltext('Failed to create discount');
        setFeedback({ type: 'error', message });
      }
    } catch (err) {
      const message =
        err?.response?.data?.message ||
        err?.message ||
        Ltext('Failed to create discount');
      setFeedback({ type: 'error', message });
    } finally {
      setSubmitting(false);
      setTimeout(() => {
        setFeedback(null);
      }, 5000);
    }
  };

  const handleRetry = () => {
    setError(null);
    fetchDiscounts(page);
  };

  const goToPreviousPage = () => {
    if (page > 1) {
      setPage(page - 1);
    }
  };

  const goToNextPage = () => {
    if (page < paginationMeta.totalPages) {
      setPage(page + 1);
    }
  };

  const formatValue = (discount) => {
    if (discount.type === 'percentage') {
      return `${discount.value}%`;
    }
    return `${numberFormatter.format(discount.value)}`;
  };

  const formatUsage = (discount) => {
    const used = discount.redemption_count || 0;
    if (discount.max_redemptions === null) {
      return `${Ltext('Used')} ${used} (${Ltext('Unlimited')})`;
    }
    return `${Ltext('Used')} ${used} ${Ltext('of')} ${discount.max_redemptions}`;
  };

  const formatValidity = (discount) => {
    const { start_date: startDate, end_date: endDate } = discount;
    if (!startDate && !endDate) {
      return '—';
    }

    const formatter = new Intl.DateTimeFormat(locale === 'no' ? 'nb-NO' : 'en-GB', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });

    if (startDate && endDate) {
      return `${formatter.format(new Date(startDate))} → ${formatter.format(new Date(endDate))}`;
    }

    if (startDate) {
      return `${Ltext('Start date')}: ${formatter.format(new Date(startDate))}`;
    }

    return `${Ltext('End date')}: ${formatter.format(new Date(endDate))}`;
  };

  const resolveStatusBadge = (discount) => {
    switch (discount.lifecycle) {
      case 'active':
        return `${styles.statusBadge} ${styles.statusActive}`;
      case 'inactive':
        return `${styles.statusBadge} ${styles.statusInactive}`;
      case 'scheduled':
        return `${styles.statusBadge} ${styles.statusScheduled}`;
      case 'expired':
        return `${styles.statusBadge} ${styles.statusExpired}`;
      default:
        return styles.statusBadge;
    }
  };

  const formatCreatedAt = (date) => {
    if (!date) return '—';
    const formatter = new Intl.DateTimeFormat(locale === 'no' ? 'nb-NO' : 'en-GB', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
    return formatter.format(new Date(date));
  };

  if (!user_token) {
    return (
      <div className={styles.wrapper}>
        <div className={styles.container}>
          <div className={styles.emptyState}>{Ltext('Authentication required to view discounts.')}</div>
        </div>
      </div>
    );
  }

  return (
    <div className={styles.wrapper}>
      <div className={styles.container}>
        <div className={styles.header}>
          <div className={styles.titleGroup}>
            <h1 className={styles.title}>{Ltext('Subscription discounts')}</h1>
            <p className={styles.subtitle}>{Ltext('Manage your subscription discount codes in one place.')}</p>
          </div>
          <div className={styles.headerActions}>
            <button className={styles.primaryButton} onClick={openModal}>
              <span>＋</span> {Ltext('Create discount')}
            </button>
          </div>
        </div>

        {feedback && (
          <div
            className={`${styles.feedbackMessage} ${
              feedback.type === 'success' ? styles.feedbackSuccess : styles.feedbackError
            }`}
          >
            {feedback.message}
          </div>
        )}

        <div className={styles.filtersBar}>
          <div className={styles.searchBox}>
            <input
              type="search"
              className={styles.searchInput}
              placeholder={Ltext('Search discounts...')}
              value={searchValue}
              onChange={(event) => setSearchValue(event.target.value)}
            />
            <span className={styles.searchIcon}>🔍</span>
          </div>
          <div className={styles.filterGroup}>
            <span className={styles.filterLabel}>{Ltext('Filter:')}</span>
            <select
              className={styles.select}
              value={statusFilter}
              onChange={(event) => setStatusFilter(event.target.value)}
            >
              <option value="all">{Ltext('All statuses')}</option>
              <option value="active">{Ltext('Active')}</option>
              <option value="inactive">{Ltext('Inactive')}</option>
            </select>
          </div>
        </div>

        {loading ? (
          <div className={styles.loadingState}>
            <div className={styles.loadingSpinner}></div>
            <div>{Ltext('Loading discounts...')}</div>
          </div>
        ) : error ? (
          <div className={styles.emptyState}>
            <p>{error}</p>
            <button className={styles.primaryButton} onClick={handleRetry}>
              {Ltext('Retry')}
            </button>
          </div>
        ) : discounts.length === 0 ? (
          <div className={styles.emptyState}>{Ltext('No discounts found')}</div>
        ) : (
          <>
            <div className={`${styles.tableWrapper} subscription-discount-root`}>
              <Table
                data={discounts}
                getRowKey={(row) => row.id}
                tableClassName={tableStyles.table}
                columns={[
                  {
                    header: Ltext('Code'),
                    thClassName: tableStyles.w12,
                    render: (discount) => <strong>{discount.code}</strong>
                  },
                  {
                    header: Ltext('Name'),
                    thClassName: tableStyles.w20,
                    render: (discount) => discount.name
                  },
                  {
                    header: Ltext('Type'),
                    thClassName: tableStyles.w10,
                    render: (discount) => (discount.type === 'percentage' ? Ltext('Percentage') : Ltext('Amount'))
                  },
                  {
                    header: Ltext('Value'),
                    thClassName: tableStyles.w10,
                    render: (discount) => <span className={styles.valueBadge}>{formatValue(discount)}</span>
                  },
                  {
                    header: Ltext('Usage'),
                    thClassName: tableStyles.w15,
                    render: (discount) => <span className={styles.usageText}>{formatUsage(discount)}</span>
                  },
                  {
                    header: Ltext('Validity'),
                    thClassName: tableStyles.w18,
                    render: (discount) => <span className={styles.validityText}>{formatValidity(discount)}</span>
                  },
                  {
                    header: Ltext('Status'),
                    thClassName: tableStyles.w10,
                    render: (discount) => (
                      <span className={resolveStatusBadge(discount)}>
                        {Ltext(discount.lifecycle ? discount.lifecycle.charAt(0).toUpperCase() + discount.lifecycle.slice(1) : discount.status)}
                      </span>
                    )
                  },
                  {
                    header: Ltext('Created'),
                    thClassName: tableStyles.w15,
                    render: (discount) => formatCreatedAt(discount.created_at)
                  }
                ]}
              />
            </div>
            <div className={styles.paginationBar}>
              <div className={styles.paginationInfo}>
                {Ltext('Displaying')} {discounts.length} {Ltext('discounts')} · {Ltext('Showing')} {(page - 1) * perPage + 1}-
                {Math.min(page * perPage, paginationMeta.total)} {Ltext('of')} {paginationMeta.total}
              </div>
              <div className={styles.paginationControls}>
                <button className={styles.paginationButton} onClick={goToPreviousPage} disabled={page === 1}>
                  {Ltext('Previous')}
                </button>
                <button
                  className={styles.paginationButton}
                  onClick={goToNextPage}
                  disabled={page >= paginationMeta.totalPages}
                >
                  {Ltext('Next')}
                </button>
              </div>
            </div>
          </>
        )}
      </div>

      <Modal
        isOpen={isModalOpen}
        onClose={closeModal}
        title={Ltext('Create subscription discount')}
        size="medium"
        closeOnOverlayClick={!submitting}
      >
        <form className={styles.modalBody} onSubmit={handleSubmit}>
          <div className={styles.formFields}>
            <div className={styles.formGroup}>
              <label className={styles.formLabel} htmlFor="discount-name">
                {Ltext('Discount name')}
              </label>
              <input
                id="discount-name"
                name="name"
                type="text"
                className={styles.formInput}
                value={formData.name}
                onChange={handleInputChange}
                disabled={submitting}
              />
              {formErrors.name && <div className={styles.errorText}>{formErrors.name}</div>}
            </div>

            <div className={styles.formGroup}>
              <label className={styles.formLabel} htmlFor="discount-code">
                {Ltext('Discount code')}
              </label>
              <input
                id="discount-code"
                name="code"
                type="text"
                className={styles.formInput}
                value={formData.code}
                onChange={handleInputChange}
                disabled={submitting}
              />
              <div className={styles.helperText}>{Ltext('Code may only contain letters, numbers, dashes, or underscores')}</div>
              {formErrors.code && <div className={styles.errorText}>{formErrors.code}</div>}
            </div>

            <div className={styles.formGroup}>
              <label className={styles.formLabel} htmlFor="discount-type">
                {Ltext('Discount type')}
              </label>
              <select
                id="discount-type"
                name="type"
                className={styles.formSelect}
                value={formData.type}
                onChange={handleInputChange}
                disabled={submitting}
              >
                <option value="percentage">{Ltext('Percentage')}</option>
                <option value="amount">{Ltext('Amount')}</option>
              </select>
            </div>

            <div className={styles.formGroup}>
              <label className={styles.formLabel} htmlFor="discount-value">
                {Ltext('Discount value')}
              </label>
              <input
                id="discount-value"
                name="value"
                type="number"
                step="0.01"
                className={styles.formInput}
                value={formData.value}
                onChange={handleInputChange}
                disabled={submitting}
              />
              {formErrors.value && <div className={styles.errorText}>{formErrors.value}</div>}
            </div>

            <div className={styles.formGroup}>
              <label className={styles.formLabel} htmlFor="max-redemptions">
                {Ltext('Maximum redemptions')}
              </label>
              <input
                id="max-redemptions"
                name="maxRedemptions"
                type="number"
                className={styles.formInput}
                value={formData.maxRedemptions}
                onChange={handleInputChange}
                disabled={submitting}
              />
              <div className={styles.helperText}>{Ltext('Leave blank for unlimited')}</div>
              {formErrors.maxRedemptions && <div className={styles.errorText}>{formErrors.maxRedemptions}</div>}
            </div>

            <div className={styles.formGroup}>
              <label className={styles.formLabel} htmlFor="start-date">
                {Ltext('Start date')}
              </label>
              <input
                id="start-date"
                name="startDate"
                type="datetime-local"
                className={styles.formInput}
                value={formData.startDate}
                onChange={handleInputChange}
                disabled={submitting}
              />
            </div>

            <div className={styles.formGroup}>
              <label className={styles.formLabel} htmlFor="end-date">
                {Ltext('End date')}
              </label>
              <input
                id="end-date"
                name="endDate"
                type="datetime-local"
                className={styles.formInput}
                value={formData.endDate}
                onChange={handleInputChange}
                disabled={submitting}
              />
              {formErrors.endDate && <div className={styles.errorText}>{formErrors.endDate}</div>}
            </div>

            <div className={styles.formGroup}>
              <label className={styles.formLabel} htmlFor="discount-status">
                {Ltext('Status (optional)')}
              </label>
              <select
                id="discount-status"
                name="status"
                className={styles.formSelect}
                value={formData.status}
                onChange={handleInputChange}
                disabled={submitting}
              >
                <option value="active">{Ltext('Active')}</option>
                <option value="inactive">{Ltext('Inactive')}</option>
              </select>
            </div>

            <div className={styles.formGroup}>
              <label className={styles.formLabel} htmlFor="discount-notes">
                {Ltext('Notes')}
              </label>
              <textarea
                id="discount-notes"
                name="notes"
                className={styles.formTextarea}
                value={formData.notes}
                onChange={handleInputChange}
                disabled={submitting}
              />
            </div>
          </div>

          <div className={styles.buttonRow}>
            <button type="button" className={styles.secondaryButton} onClick={closeModal} disabled={submitting}>
              {Ltext('Cancel')}
            </button>
            <button type="submit" className={styles.submitButton} disabled={submitting}>
              {submitting ? Ltext('Saving...') : Ltext('Save')}
            </button>
          </div>
        </form>
      </Modal>
    </div>
  );
}

export default SubscriptionDiscount;

