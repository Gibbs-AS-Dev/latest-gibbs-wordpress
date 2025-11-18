import React, { useCallback, useEffect, useMemo, useState } from 'react';
import axios from 'axios';
import Select from 'react-select';
import Table from '../components/Table';
import Modal from '../components/Modal';
import Pagination from '../components/Pagination';
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
  status: 'active',
  subscriptionProducts: [],
  listingIds: []
};

function SubscriptionDiscount({ apiUrl, user_token, owner_id }) {
  const [discounts, setDiscounts] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [feedback, setFeedback] = useState(null);
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [formData, setFormData] = useState(INITIAL_FORM_STATE);
  const [formErrors, setFormErrors] = useState({});
  const [submitting, setSubmitting] = useState(false);
  const [subscriptionOptions, setSubscriptionOptions] = useState({ products: [], listings: [] });
  const [page, setPage] = useState(1);
  const [perPage] = useState(20);
  const [paginationMeta, setPaginationMeta] = useState({ total: 0, totalPages: 1 });
  const [optionsLoading, setOptionsLoading] = useState(true);
  const [optionsError, setOptionsError] = useState(null);
  const [togglingDiscountIds, setTogglingDiscountIds] = useState(() => new Set());
  const [editingDiscount, setEditingDiscount] = useState(null);

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

  const fetchOptions = useCallback(async () => {
    if (!apiUrl) {
      return;
    }
    setOptionsLoading(true);
    setOptionsError(null);
    try {
      const headers = user_token ? { Authorization: `Bearer ${user_token}` } : {};
      const response = await axios.get(`${apiUrl}`, {
        params: {
          action: 'getSubscriptionOptions',
          owner_id,
        },
        headers,
      });

      if (response?.data?.success) {
        const data = response.data.data || {};
        setSubscriptionOptions({
          products: Array.isArray(data.products) ? data.products : [],
          listings: Array.isArray(data.listings) ? data.listings : []
        });
      } else {
        setSubscriptionOptions({ products: [], listings: [] });
        setOptionsError(response?.data?.message || Ltext('Failed to load subscription options'));
      }
    } catch (err) {
      setSubscriptionOptions({ products: [], listings: [] });
      if (err?.response?.data?.message) {
        setOptionsError(err.response.data.message);
      } else if (err?.message) {
        setOptionsError(err.message);
      } else {
        setOptionsError(Ltext('Failed to load subscription options'));
      }
    } finally {
      setOptionsLoading(false);
    }
  }, [apiUrl, owner_id, user_token]);

  useEffect(() => {
    fetchOptions();
  }, [fetchOptions]);

  const computeLifecycle = useCallback((status, startDate, endDate) => {
    if (status === 'inactive') {
      return 'inactive';
    }

    const now = new Date();
    const start = startDate ? new Date(startDate) : null;
    const end = endDate ? new Date(endDate) : null;

    // if (end && !Number.isNaN(end.getTime()) && end < now) {
    //   return 'expired';
    // }

    if (start && !Number.isNaN(start.getTime()) && start > now) {
      return 'scheduled';
    }

    return 'active';
  }, []);

  const normalizeDiscountPayload = useCallback((rawDiscounts) => {
    if (!Array.isArray(rawDiscounts)) {
      return [];
    }

    const normalizeIdArray = (value) => {
      if (!Array.isArray(value)) {
        return [];
      }
      return Array.from(
        new Set(
          value
            .map((id) => Number(id))
            .filter((num) => Number.isInteger(num) && num > 0)
        )
      );
    };

    return rawDiscounts
      .map((discount) => {
        if (!discount || typeof discount !== 'object') {
          return null;
        }

        const meta = discount.meta || {};
        const startDate = meta.discount_start_date || null;
        const endDate = meta.discount_end_date || null;
        const rawStatus = (meta.discount_status || (discount.post_status === 'publish' ? 'active' : 'inactive')).toString().toLowerCase();
        const status = rawStatus === 'inactive' ? 'inactive' : 'active';
        const type = (meta.discount_type || 'percentage').toString().toLowerCase();
        const value = parseFloat(meta.discount_value || 0) || 0;

        return {
          id: Number(discount.ID),
          owner_id: Number(discount.post_author),
          name: discount.post_title || '',
          code: meta.discount_code || '',
          type,
          value,
          start_date: startDate,
          end_date: endDate,
          status,
          lifecycle: computeLifecycle(status, startDate, endDate),
          subscription_products: normalizeIdArray(meta.discount_subscription_products),
          listing_ids: normalizeIdArray(meta.discount_listing_ids),
          created_at: discount.post_date || null,
          updated_at: discount.post_modified || null
        };
      })
      .filter(Boolean);
  }, [computeLifecycle]);

  const fetchDiscounts = useCallback(async (requestedPageParam) => {
    const targetPage = requestedPageParam ?? page;
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
        page: targetPage,
        per_page: perPage
      };

      const headers = user_token ? { Authorization: `Bearer ${user_token}` } : {};
      const response = await axios.get(apiUrl, { params, headers });
      if (response?.data?.success) {
        const payload = response.data.data || {};
        const rawDiscounts = payload.discounts || [];
        const normalizedDiscounts = normalizeDiscountPayload(rawDiscounts);
        const pagination = payload.pagination || {};
        const total = Number(pagination.total ?? normalizedDiscounts.length ?? 0);
        const inferredTotalPages = Math.ceil(Math.max(total, 1) / perPage) || 1;
        const totalPages = Math.max(1, Number(pagination.total_pages ?? inferredTotalPages));
        const resolvedPage = Number(pagination.page ?? targetPage ?? 1);

        setDiscounts(normalizedDiscounts);
        setPaginationMeta({
          total,
          totalPages
        });

        setPage((prev) => (resolvedPage !== prev ? resolvedPage : prev));
      } else {
        setError(response?.data?.message || Ltext('Failed to load subscription discounts'));
        setDiscounts([]);
        setPaginationMeta({ total: 0, totalPages: 1 });
      }
    } catch (err) {
      const message =
        err?.response?.data?.message ||
        err?.message ||
        Ltext('Failed to load subscription discounts');
      setError(message);
      setDiscounts([]);
      setPaginationMeta({ total: 0, totalPages: 1 });
    } finally {
      setLoading(false);
    }
  }, [apiUrl, normalizeDiscountPayload, owner_id, page, perPage, user_token]);

  useEffect(() => {
    fetchDiscounts(page);
  }, [fetchDiscounts, page]);

  const resetForm = useCallback(() => {
    setFormData(INITIAL_FORM_STATE);
    setFormErrors({});
  }, []);

  const openCreateModal = useCallback(() => {
    resetForm();
    setEditingDiscount(null);
    setIsModalOpen(true);
  }, [resetForm]);

  const normalizeDateForInput = (value) => {
    if (!value || typeof value !== 'string') {
      return '';
    }
    if (value.includes('T')) {
      return value.slice(0, 10);
    }
    if (value.includes(' ')) {
      return value.split(' ')[0];
    }
    return value;
  };

  const normalizeIds = (value) => {
    if (!Array.isArray(value)) {
      return [];
    }
    return Array.from(
      new Set(
        value
          .map((id) => Number(id))
          .filter((num) => Number.isInteger(num) && num > 0)
      )
    );
  };

  const extractIds = (rawIds, items) => {
    let ids = normalizeIds(rawIds);
    if ((!ids || ids.length === 0) && Array.isArray(items) && items.length > 0) {
      ids = normalizeIds(items.map((item) => item.id));
    }
    return ids;
  };

  const openEditModal = (discount) => {
    if (!discount || typeof discount !== 'object') {
      return;
    }

    const resolvedType = discount.type || discount.discount_type || 'percentage';
    const resolvedValue =
      discount.value !== undefined && discount.value !== null
        ? String(discount.value)
        : discount.discount_value !== undefined && discount.discount_value !== null
          ? String(discount.discount_value)
          : '';

    const resolvedProducts = extractIds(discount.subscription_products ?? discount.subscriptionProducts);
    const resolvedListings = extractIds(discount.listing_ids ?? discount.listingIds);

    setFormData({
      ...INITIAL_FORM_STATE,
      type: resolvedType,
      value: resolvedValue,
      startDate: normalizeDateForInput(discount.start_date || discount.startDate),
      subscriptionProducts: resolvedProducts,
      listingIds: resolvedListings
    });
    setFormErrors({});
    setEditingDiscount(discount);
    setIsModalOpen(true);
  };

  const closeModal = () => {
    setIsModalOpen(false);
    setSubmitting(false);
    setFormData(INITIAL_FORM_STATE);
    setFormErrors({});
    setEditingDiscount(null);
  };

  const handleInputChange = (event) => {
    const { name, value } = event.target;
    setFormData((prev) => ({
      ...prev,
      [name]: value
    }));
  };

  const validateForm = (mode = 'create') => {
    const errors = {};

    const numericValue = parseFloat(formData.value);
    if (Number.isNaN(numericValue) || numericValue <= 0) {
      errors.value = Ltext('Value must be greater than zero');
    } else if (formData.type === 'percentage' && numericValue > 100) {
      errors.value = Ltext('Percentage cannot exceed 100');
    }

    if (!formData.subscriptionProducts || formData.subscriptionProducts.length === 0) {
      errors.subscriptionProducts = Ltext('Select at least one product');
    }

    if (!formData.listingIds || formData.listingIds.length === 0) {
      errors.listingIds = Ltext('Select at least one listing');
    }

    const result = {
      isValid: Object.keys(errors).length === 0,
      numericValue
    };

    if (mode === 'create') {
      const now = new Date();
      result.generatedName = `${Ltext('Subscription discounts')} ${now.toISOString().slice(0, 10)}-${now.getHours()}${now.getMinutes()}`;
      result.generatedCode = `SUB-${now.getTime()}`;
    }

    setFormErrors(errors);
    return result;
  };

  const handleSubmit = async (event) => {
    event.preventDefault();
    if (submitting) return;

    const isEditing = Boolean(editingDiscount);
    const { isValid, generatedName, generatedCode, numericValue } = validateForm(isEditing ? 'edit' : 'create');
    if (!isValid) {
      return;
    }

    setSubmitting(true);
    setFeedback(null);

    try {
      const headers = user_token ? { Authorization: `Bearer ${user_token}` } : {};
      if (isEditing) {
        const updatesPayload = {
          action: 'updateSubscriptionDiscount',
          owner_id,
          discount_id: editingDiscount.id,
          updates: {
            type: formData.type,
            value: numericValue,
            start_date: formData.startDate ? formData.startDate : null,
            subscription_products: formData.subscriptionProducts,
            listing_ids: formData.listingIds
          }
        };

        const response = await axios.put(apiUrl, updatesPayload, { headers });
        if (response?.data?.success) {
          setFeedback({
            type: 'success',
            message: response?.data?.data?.message || Ltext('Discount updated successfully')
          });
          closeModal();
          fetchDiscounts(page);
        } else {
          const message = response?.data?.message || Ltext('Failed to update discount');
          setFeedback({ type: 'error', message });
        }
      } else {
        const createPayload = {
          action: 'createSubscriptionDiscount',
          owner_id,
          discount: {
            name: generatedName,
            code: generatedCode,
            type: formData.type,
            value: numericValue,
            start_date: formData.startDate || null,
            subscription_products: formData.subscriptionProducts,
            listing_ids: formData.listingIds
          }
        };

        const response = await axios.post(apiUrl, createPayload, { headers });
        if (response?.data?.success) {
          setFeedback({ type: 'success', message: response.data?.data?.message || Ltext('New discount created') });
          closeModal();
          setPage(1);
          fetchDiscounts(1);
        } else {
          const message = response?.data?.message || Ltext('Failed to create discount');
          setFeedback({ type: 'error', message });
        }
      }
    } catch (err) {
      const message =
        err?.response?.data?.message ||
        err?.message ||
        Ltext(isEditing ? 'Failed to update discount' : 'Failed to create discount');
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

  const handlePageChange = useCallback(
    (newPage) => {
      if (newPage < 1 || newPage > paginationMeta.totalPages || newPage === page) {
        return;
      }
      setPage(newPage);
    },
    [page, paginationMeta.totalPages]
  );

  const formatValue = (discount) => {
    if (discount.type === 'percentage') {
      return `${discount.value}%`;
    }
    return `${numberFormatter.format(discount.value)}`;
  };

  const formatDateShort = (date) => {
    if (!date) {
      return '—';
    }
    try {
      const dt = new Date(date);
      if (Number.isNaN(dt.getTime())) {
        return '—';
      }
      const formatter = new Intl.DateTimeFormat(locale === 'no' ? 'nb-NO' : 'en-GB', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
      });
      return formatter.format(dt);
    } catch (error) {
      return '—';
    }
  };

  const resolveNames = (ids = [], options = []) => {
    if (!Array.isArray(ids) || ids.length === 0) {
      return [];
    }
    const optionMap = new Map(options.map((option) => [Number(option.id), option.title]));
    return ids.map((id) => optionMap.get(Number(id)) || `#${id}`);
  };

  const productSelectOptions = useMemo(() =>
    subscriptionOptions.products.map((option) => ({
      value: Number(option.id),
      label: option.title
    })),
  [subscriptionOptions.products]);

  const listingSelectOptions = useMemo(() =>
    subscriptionOptions.listings.map((option) => ({
      value: Number(option.id),
      label: option.title
    })),
  [subscriptionOptions.listings]);

  const productSelectValue = useMemo(() => {
    if (!formData.subscriptionProducts || !formData.subscriptionProducts.length) {
      return [];
    }
    const selectedSet = new Set(formData.subscriptionProducts.map(Number));
    return productSelectOptions.filter((option) => selectedSet.has(Number(option.value)));
  }, [formData.subscriptionProducts, productSelectOptions]);

  const listingSelectValue = useMemo(() => {
    if (!formData.listingIds || !formData.listingIds.length) {
      return [];
    }
    const selectedSet = new Set(formData.listingIds.map(Number));
    return listingSelectOptions.filter((option) => selectedSet.has(Number(option.value)));
  }, [formData.listingIds, listingSelectOptions]);

  const handleProductSelectChange = (selectedOptions) => {
    setFormData((prev) => ({
      ...prev,
      subscriptionProducts: (selectedOptions || []).map((option) => Number(option.value))
    }));
  };

  const handleListingSelectChange = (selectedOptions) => {
    setFormData((prev) => ({
      ...prev,
      listingIds: (selectedOptions || []).map((option) => Number(option.value))
    }));
  };

  const handleToggleStatus = useCallback(async (discount) => {
    if (!discount || !discount.id) {
      return;
    }

    if (!apiUrl) {
      setFeedback({ type: 'error', message: Ltext('Missing API URL') });
      setTimeout(() => {
        setFeedback(null);
      }, 5000);
      return;
    }

    if (!owner_id) {
      setFeedback({ type: 'error', message: Ltext('Authentication required to update status.') });
      setTimeout(() => {
        setFeedback(null);
      }, 5000);
      return;
    }

    const discountId = discount.id;

    setTogglingDiscountIds((prev) => {
      const next = new Set(prev);
      next.add(discountId);
      return next;
    });

    try {
      const headers = user_token ? { Authorization: `Bearer ${user_token}` } : {};
      const response = await axios.put(
        apiUrl,
        {
          action: 'toggleSubscriptionDiscountStatus',
          owner_id,
          discount_id: discountId,
          status: discount.status === 'active' ? 'inactive' : 'active'
        },
        { headers }
      );

      if (response?.data?.success) {
        const message =
          response?.data?.data?.message ||
          response?.data?.message ||
          Ltext('Discount status updated');
        setFeedback({ type: 'success', message });
        await fetchDiscounts();
      } else {
        const message = response?.data?.message || Ltext('Failed to update discount status');
        setFeedback({ type: 'error', message });
      }
    } catch (err) {
      const message =
        err?.response?.data?.message ||
        err?.message ||
        Ltext('Failed to update discount status');
      setFeedback({ type: 'error', message });
    } finally {
      setTogglingDiscountIds((prev) => {
        const next = new Set(prev);
        next.delete(discountId);
        return next;
      });

      setTimeout(() => {
        setFeedback(null);
      }, 5000);
    }
  }, [apiUrl, fetchDiscounts, owner_id, user_token]);

  const resolveStatusBadge = (discount) => {
    switch (discount.lifecycle) {
      case 'active':
        return styles.statusActive;
      case 'inactive':
        return styles.statusInactive;
      case 'scheduled':
        return styles.statusScheduled;
      case 'expired':
        return styles.statusExpired;
      default:
        return styles.statusDefault;
    }
  };

  const isEditing = Boolean(editingDiscount);

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
            <button className={styles.primaryButton} onClick={openCreateModal}>
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
                    header: Ltext('Discount type'),
                    thClassName: tableStyles.w15,
                    render: (discount) =>
                      discount.type === 'percentage'
                        ? Ltext('Percentage discount')
                        : Ltext('Amount discount')
                  },
                  {
                    header: Ltext('Amount'),
                    thClassName: tableStyles.w15,
                    render: (discount) => <span className={styles.valueBadge}>{formatValue(discount)}</span>
                  },
                  {
                    header: Ltext('Activation date'),
                    thClassName: tableStyles.w20,
                    render: (discount) => formatDateShort(discount.start_date)
                  },
                  // {
                  //   header: Ltext('Products'),
                  //   thClassName: tableStyles.w25,
                  //   render: (discount) => {
                  //     const names = resolveNames(discount.subscription_products || [], subscriptionOptions.products);
                  //     return names.length ? names.join(', ') : '—';
                  //   }
                  // },
                  // {
                  //   header: Ltext('Listings'),
                  //   thClassName: tableStyles.w25,
                  //   render: (discount) => {
                  //     const names = resolveNames(discount.listing_ids || [], subscriptionOptions.listings);
                  //     return names.length ? names.join(', ') : '—';
                  //   }
                  // },
                  {
                    header: Ltext('Status'),
                    thClassName: tableStyles.w10,
                    render: (discount) => (
                      <span className={`${styles.statusBadge} ${resolveStatusBadge(discount)}`}>
                        {Ltext(discount.lifecycle ? discount.lifecycle.charAt(0).toUpperCase() + discount.lifecycle.slice(1) : discount.status)}
                      </span>
                    )
                  },
                  {
                    header: Ltext('Actions'),
                    thClassName: tableStyles.w15,
                    render: (discount) => {
                      const isActive = discount.status === 'active';
                      const isProcessing = togglingDiscountIds.has(discount.id);
                      const buttonClassName = [
                        styles.actionButton,
                        isActive ? styles.deactivateButton : styles.activateButton
                      ].join(' ');

                      return (
                        <div className={styles.actionGroup}>
                          <button
                            type="button"
                            className={`${styles.actionButton} ${styles.editButton}`}
                            onClick={() => openEditModal(discount)}
                            disabled={isProcessing || loading}
                          >
                            {Ltext('Edit')}
                          </button>
                          <button
                            type="button"
                            className={buttonClassName}
                            onClick={() => handleToggleStatus(discount)}
                            disabled={isProcessing || loading}
                          >
                            {isProcessing
                              ? Ltext('Updating...')
                              : isActive
                                ? Ltext('Deactivate')
                                : Ltext('Activate')}
                          </button>
                        </div>
                      );
                    }
                  }
                ]}
              />
            </div>
            <Pagination
              currentPage={page}
              totalPages={paginationMeta.totalPages}
              totalItems={paginationMeta.total}
              itemsPerPage={perPage}
              onPageChange={handlePageChange}
              showInfo
            />
          </>
        )}
      </div>

      <Modal
        isOpen={isModalOpen}
        onClose={closeModal}
        title={isEditing ? Ltext('Edit subscription discount') : Ltext('Create subscription discount')}
        size="medium"
        closeOnOverlayClick={!submitting}
      >
        <form className={styles.modalForm} onSubmit={handleSubmit}>
          <div className={styles.cardStack}>
            <div className={styles.card}>
              <div className={styles.cardHeader}>
                <h3>{Ltext('Discount Settings')}</h3>
              </div>
              <div className={styles.cardBody}>
                <div className={styles.formGrid}>
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
                      <option value="percentage">{Ltext('Percentage discount')}</option>
                      <option value="amount">{Ltext('Amount discount')}</option>
                    </select>
                  </div>
                  <div className={styles.formGroup}>
                    <label className={styles.formLabel} htmlFor="discount-value">
                      {Ltext('Amount')}
                    </label>
                    <input
                      id="discount-value"
                      name="value"
                      type="number"
                      step="0.01"
                      className={styles.formInput}
                      placeholder={Ltext('e.g., 10 for 10%')}
                      value={formData.value}
                      onChange={handleInputChange}
                      disabled={submitting}
                    />
                    {formErrors.value && <div className={styles.errorText}>{formErrors.value}</div>}
                  </div>
                </div>

                <div className={styles.formGroup}>
                  <label className={styles.formLabel} htmlFor="start-date">
                    {Ltext('Activation date')}
                  </label>
                  <input
                    id="start-date"
                    name="startDate"
                    type="date"
                    className={styles.formInput}
                    value={formData.startDate}
                    onChange={handleInputChange}
                    disabled={submitting}
                  />
                </div>
              </div>
            </div>

            <div className={styles.card}>
              <div className={styles.cardHeader}>
                <h3>{Ltext('Subscription Products')}</h3>
              </div>
              <div className={styles.cardBody}>
                {optionsError && <div className={styles.feedbackError}>{optionsError}</div>}
                {optionsLoading ? (
                  <div className={styles.optionsLoading}>{Ltext('Loading options...')}</div>
                ) : (
                  <>
                    <div className={styles.selectorGroup}>
                      <label className={styles.formLabel}>{Ltext('Products')}</label>
                      <Select
                        className={styles.selectWrapper}
                        classNamePrefix="gibbs-select"
                        isMulti
                        options={productSelectOptions}
                        value={productSelectValue}
                        onChange={handleProductSelectChange}
                        placeholder={Ltext('Select products...')}
                        isDisabled={submitting}
                      />
                      {formErrors.subscriptionProducts && (
                        <div className={styles.errorText}>{formErrors.subscriptionProducts}</div>
                      )}
                    </div>
                    <div className={styles.selectorGroup}>
                      <label className={styles.formLabel}>{Ltext('Listings')}</label>
                      <Select
                        className={styles.selectWrapper}
                        classNamePrefix="gibbs-select"
                        isMulti
                        options={listingSelectOptions}
                        value={listingSelectValue}
                        onChange={handleListingSelectChange}
                        placeholder={Ltext('Select listings...')}
                        isDisabled={submitting}
                      />
                      {formErrors.listingIds && <div className={styles.errorText}>{formErrors.listingIds}</div>}
                    </div>
                    
                  </>
                )}
              </div>
            </div>
          </div>

          <div className={styles.buttonRow}>
            <button type="button" className={styles.secondaryButton} onClick={closeModal} disabled={submitting}>
              {Ltext('Cancel')}
            </button>
            <button type="submit" className={styles.submitButton} disabled={submitting}>
              {submitting
                ? isEditing
                  ? Ltext('Updating...')
                  : Ltext('Saving...')
                : isEditing
                  ? Ltext('Update')
                  : Ltext('Save')}
            </button>
          </div>
        </form>
      </Modal>
    </div>
  );
}

export default SubscriptionDiscount;

