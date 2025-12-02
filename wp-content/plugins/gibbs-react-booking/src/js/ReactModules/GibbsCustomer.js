import React, { useState, useEffect, useCallback, useMemo, useRef } from 'react';
import axios from 'axios';
import Table from '../components/Table';
import Tabs from '../components/Tabs';
import Filter from '../components/Filter';
import Button from '../components/Button';
import Pagination from '../components/Pagination';
import styles from '../assets/scss/GibbsCustomer.module.scss';
import '../assets/scss/GibbsCustomer.scss';
import Select from 'react-select';

function GibbsCustomer({ apiUrl, user_token, owner_id }) {
  const [customers, setCustomers] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [activeTab, setActiveTab] = useState('all');
  const [searchQuery, setSearchQuery] = useState('');
  const [expandedRows, setExpandedRows] = useState(new Set());
  const [page, setPage] = useState(1);
  const [paginationMeta, setPaginationMeta] = useState({ total: 0, totalPages: 1 });
  const [sortConfig, setSortConfig] = useState({ key: 'company_name', direction: 'asc' });
  const [filterCount, setFilterCount] = useState(0);
  const [isFilterOpen, setIsFilterOpen] = useState(false);
  const [isSettingsOpen, setIsSettingsOpen] = useState(false);
  const [isShowRowsOpen, setIsShowRowsOpen] = useState(false);
  const [isColumnsOpen, setIsColumnsOpen] = useState(false);
  const [hoveredRowIndex, setHoveredRowIndex] = useState(null);
  const [selectedHideColumns, setSelectedHideColumns] = useState([]);
  const [rowsPerPage, setRowsPerPage] = useState(10);
  const [isDragging, setIsDragging] = useState(false);
  const [startX, setStartX] = useState(0);
  const [scrollLeft, setScrollLeft] = useState(0);
  // Applied status filter (used in API params)
  const [statusFilter, setStatusFilter] = useState('all');
  // Draft value in the dropdown before clicking "Filter"
  const [statusFilterDraft, setStatusFilterDraft] = useState('all');
  const tableScrollRef = useRef(null);

  const [groupLicenses, setGroupLicenses] = useState([]);
  const [groupLicenseSelections, setGroupLicenseSelections] = useState({});

  const tabs = [
    { id: 'all', label: 'All', count: paginationMeta.total },
    { id: 'invoice', label: 'Invoice', count: paginationMeta.total },
    { id: 'stripe', label: 'Stripe', count: paginationMeta.total }
  ];

  const fetchCustomers = useCallback(async (requestedPageParam) => {
    const targetPage = requestedPageParam ?? page;
    if (!apiUrl) {
      setError('Missing API URL');
      setLoading(false);
      return;
    }

    setLoading(true);
    setError(null);

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
        sort_direction: sortConfig.direction
      };

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
        setError(response?.data?.message || 'Failed to load customers');
        setCustomers([]);
        setPaginationMeta({ total: 0, totalPages: 1 });
      }
    } catch (err) {
      const message = err?.response?.data?.message || err?.message || 'Failed to load customers';
      setError(message);
      setCustomers([]);
      setPaginationMeta({ total: 0, totalPages: 1 });
    } finally {
      setLoading(false);
    }
  }, [apiUrl, owner_id, page, rowsPerPage, activeTab, searchQuery, sortConfig, statusFilter, user_token]);

  useEffect(() => {
    fetchCustomers(page);
  }, [fetchCustomers, page, activeTab, searchQuery, sortConfig, statusFilter]);

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

  const handleRowsPerPageChange = (newRowsPerPage) => {
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

  // Drag scroll handlers
  const handleMouseDown = (e) => {
    if (!tableScrollRef.current) return;
    // Only start drag if clicking on the table container, not on interactive elements
    if (e.target.tagName === 'BUTTON' || 
        e.target.closest('button') || 
        e.target.closest('input') || 
        e.target.closest('a') || 
        e.target.closest('select') ||
        e.target.closest('[data-sortable="true"]')) {
      return;
    }
    // Find the actual scrollable element (tableWrapper inside tableSection)
    const scrollableElement = tableScrollRef.current.querySelector('.gibbs-customer-root') || tableScrollRef.current;
    setIsDragging(true);
    const rect = scrollableElement.getBoundingClientRect();
    setStartX(e.pageX - rect.left);
    setScrollLeft(scrollableElement.scrollLeft);
    // Store reference to scrollable element
    tableScrollRef.current._scrollableElement = scrollableElement;
  };

  const handleMouseMove = (e) => {
    if (!isDragging || !tableScrollRef.current) return;
    const scrollableElement = tableScrollRef.current._scrollableElement || tableScrollRef.current.querySelector('.gibbs-customer-root') || tableScrollRef.current;
    if (!scrollableElement) return;
    e.preventDefault();
    const rect = scrollableElement.getBoundingClientRect();
    const x = e.pageX - rect.left;
    const walk = (x - startX) * 2; // Scroll speed multiplier
    scrollableElement.scrollLeft = scrollLeft - walk;
  };

  const handleMouseUp = () => {
    setIsDragging(false);
    if (tableScrollRef.current) {
      tableScrollRef.current._scrollableElement = null;
    }
  };

  const handleMouseLeave = () => {
    setIsDragging(false);
    if (tableScrollRef.current) {
      tableScrollRef.current._scrollableElement = null;
    }
  };

  const getLicenseBadgeClass = (licenseType) => {
    switch (licenseType?.toLowerCase()) {
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

  const allColumns = [
    {
      key: 'company_name',
      header: 'COMPANY NAME',
      sortable: true,
      thStyle: { width: '20%', minWidth: '200px' },
      tdStyle: { minWidth: '200px' },
      render: (row) => {
        if (row.isUsergroup) {
          return (
            <div className={styles.usergroupCell}>
              <span className={styles.usergroupName}>{row.name || '—'}</span>
              <span className={styles.usergroupLabel}>Usergroup</span>
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
              >
                <i className={`fa ${expandedRows.has(row.id) ? 'fa-chevron-down' : 'fa-chevron-right'}`}></i>
              </Button>
            )}
            <div>
              <div className={styles.companyName}>{row.company_name || row.name || '—'}</div>
              <div className={styles.usergroupCount}>
                {hasUsergroups ? `${row.usergroups.length} Usergroups` : '0 Usergroups'}
              </div>
            </div>
          </div>
        );
      }
    },
    {
      key: 'superadmin',
      header: 'SUPERADMIN',
      sortable: true,
      thStyle: { width: '12%' },
      render: (row) => (row.first_name || row.last_name ? row.first_name + ' ' + row.last_name : '—')
    },
    {
      key: 'email',
      header: 'EMAIL',
      thStyle: { width: '12%' },
      render: (row) => row.email || '—'
    },
    {
      key: 'phone',
      header: 'PHONE',
      thStyle: { width: '8%' },
      render: (row) => (row.country_code ? row.country_code + ' ' + row.phone : row.phone) || '—'
    },
    {
      key: 'gibbs_licenses',
      header: 'GIBBS LICENSES',
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
                  const ids = (selected || []).map((opt) => opt.value);
                  setGroupLicenseSelections((prev) => ({
                    ...prev,
                    [row.id]: ids,
                  }));
                }}
                isClearable={false}
                placeholder="Select licenses"
                classNamePrefix="gibbs-license-select"
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
      key: 'created',
      header: 'CREATED',
      thStyle: { width: '10%' },
      render: (row) => formatDate(row.created_at || row.created)
    },
    {
      key: 'stripe_license',
      header: 'STRIPE LICENSE',
      thStyle: { width: '12%' },
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
      header: 'PAYMENT',
      thStyle: { width: '10%' },
      render: (row) => row.payment || '—'
    },
    {
      key: 'next_invoice',
      header: 'NEXT INVOICE',
      thStyle: { width: '12%' },
      render: (row) => formatDate(row.next_invoice || row.next_invoice_date)
    },
    {
      key: 'edit',
      header: 'EDIT',
      thStyle: { width: '11%' , textAlign: 'center'},
      render: (row) => {
        return (
          <div className={styles.actionButtons}>
            <Button
              variant="ghost"
              size="small"
              onClick={() => {/* Handle edit */}}
              aria-label="Edit"
              className={styles.actionButton}
            >
              <i className="fa fa-edit"></i>
            </Button>
            <Button
              variant="ghost"
              size="small"
              onClick={() => {/* Handle delete */}}
              aria-label="Delete"
              className={styles.actionButton}
            >
              <i className="fa fa-trash"></i>
            </Button>
          </div>
        );
      }
    }
  ];

  // Filter columns based on selectedHideColumns
  const columns = allColumns.filter(col => !selectedHideColumns.includes(col.key));

  return (
    <div className={styles.wrapper}>
      <div className={styles.container}>
        <div className={styles.header}>
          <div className={styles.headerLeft}>
            <Tabs tabs={tabs} activeTab={activeTab} onTabChange={handleTabChange} />
          </div>
          <div className={styles.headerRight}>
            <Button
              variant="primary"
              onClick={() => {/* Handle new customer */}}
              leftIcon={<span>+</span>}
              className={styles.newCustomerButton}
            >
              New Customer
            </Button>
            <div className={styles.searchBox}>
              <i className={`fa fa-search ${styles.searchIcon}`}></i>
              <input
                type="text"
                className={styles.searchInput}
                placeholder="Search by name, email..."
                value={searchQuery}
                onChange={handleSearchChange}
              />
            </div>
            <div className={styles.filterWrapper}>
              <Button
                variant="ghost"
                size="small"
                className={`${styles.iconButton} ${isFilterOpen ? styles.active : ''}`}
                aria-label="Filter"
                onClick={() => {
                  setIsFilterOpen(!isFilterOpen);
                  setIsSettingsOpen(false);
                }}
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-funnel w-5 h-5" aria-hidden="true"><path d="M10 20a1 1 0 0 0 .553.895l2 1A1 1 0 0 0 14 21v-7a2 2 0 0 1 .517-1.341L21.74 4.67A1 1 0 0 0 21 3H3a1 1 0 0 0-.742 1.67l7.225 7.989A2 2 0 0 1 10 14z"></path></svg>
              </Button>
              {isFilterOpen && (
                <div className={styles.filterDropdownMenu}>
                  <div className={styles.filterDropdownHeader}>
                    <h3>Filter</h3>
                  </div>
                  <div className={styles.filterDropdownBody}>
                    <div className={styles.filterSection}>
                      <label className={styles.filterLabel}>Filter</label>
                      <select
                        className={styles.filterSelect}
                        value={statusFilterDraft}
                        onChange={(e) => {
                          setStatusFilterDraft(e.target.value);
                        }}
                      >
                        <option value="all">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                      </select>
                    </div>
                    <Button
                      variant="primary"
                      onClick={() => {
                        setStatusFilter(statusFilterDraft);
                        setPage(1);
                        setIsFilterOpen(false);
                      }}
                      className={styles.filterApplyButton}
                    >
                      Filter
                    </Button>
                  </div>
                </div>
              )}
            </div>
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
                <div className={styles.settingsDropdownMenu}>
                  <div className={styles.settingsDropdownHeader}>
                    <h3>Settings</h3>
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
                          <span>Select columns</span>
                        </div>
                        <i className={`fa ${isColumnsOpen ? 'fa-chevron-up' : 'fa-chevron-down'}`}></i>
                      </div>
                      {isColumnsOpen && (
                        <div className={styles.settingsCheckboxes}>
                          {[
                            { key: 'company_name', label: 'COMPANY NAME' },
                            { key: 'superadmin', label: 'SUPERADMIN' },
                            { key: 'email', label: 'EMAIL' },
                            { key: 'phone', label: 'PHONE' },
                            { key: 'gibbs_licenses', label: 'GIBBS LICENSES' },
                            { key: 'created', label: 'CREATED' },
                            { key: 'stripe_license', label: 'STRIPE LICENSE' },
                            { key: 'payment', label: 'PAYMENT' },
                            { key: 'next_invoice', label: 'NEXT INVOICE' }
                          ].map(col => {
                            const isChecked = !selectedHideColumns.includes(col.key);
                            return (
                              <label key={col.key} className={styles.settingsCheckbox+` ${!isChecked ? styles.hideColumnChecked : ''}`}>
                                <input
                                  type="checkbox"
                                  checked={isChecked}
                                  onChange={(e) => {
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
                                <span>{col.label}</span>
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
                          <span>Show rows</span>
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
                              <span>{rows} rows</span>
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
            <div>Loading customers...</div>
          </div>
        ) : error ? (
          <div className={styles.errorState}>
            <p>{error}</p>
            <Button variant="primary" onClick={() => fetchCustomers(page)}>
              Retry
            </Button>
          </div>
        ) : tableData.length === 0 ? (
          <div className={styles.emptyState}>No customers found</div>
        ) : (
          <div 
            ref={tableScrollRef}
            className={`${styles.tableSection} ${isDragging ? styles.dragging : ''}`}
            onMouseDown={handleMouseDown}
            onMouseMove={handleMouseMove}
            onMouseUp={handleMouseUp}
            onMouseLeave={handleMouseLeave}
          >
            <Table
              data={tableData}
              getRowKey={(row) => row.rowId}
              wrapperClassName="gibbs-customer-root"
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
    </div>
  );
}

export default GibbsCustomer;

