import React, { useState, useEffect } from 'react';
import { createPortal } from 'react-dom';
import axios from 'axios';
import { Ltext, getLanguage } from '../utils/emailTemplate-translations';
import TemplateCreationModal from './TemplateCreationModal';
import styles from '../assets/scss/emailTemplates.module.scss';
import '../assets/scss/emailTemplates.scss';
import Modal from '../components/Modal'; // Added import for Modal
import Table from '../components/Table';
import Button from '../components/Button';

function EmailTemplate({ page_id, apiUrl, homeUrl, user_token, owner_id }) {
  const [templates, setTemplates] = useState([]);
  const [loading, setLoading] = useState(true);
  const [showCreateForm, setShowCreateForm] = useState(false);
  const [filteredTemplates, setFilteredTemplates] = useState([]);
  const [searchTerm, setSearchTerm] = useState('');
  const [triggerFilter, setTriggerFilter] = useState('all');
  const [showPopup, setShowPopup] = useState(false);
  const [selectedContent, setSelectedContent] = useState('');
  const [editingTemplate, setEditingTemplate] = useState(null);
  const [openDropdowns, setOpenDropdowns] = useState({});
  const [dropdownPositions, setDropdownPositions] = useState({});
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState('');
  const [showEmailSettings, setShowEmailSettings] = useState(false);
  const [emailSettingsError, setEmailSettingsError] = useState('');
  const [emailSettingsLoading, setEmailSettingsLoading] = useState(false);
  const [emailSettings, setEmailSettings] = useState({
    senderName: '',
    fromEmail: '',
    replyToEmail: '',
    companyName: '',
    address: '',
    postcode: '',
    area: '',
    country: ''
  });
  const [newTemplate, setNewTemplate] = useState({
    name: '',
    subject: '',
    active: true,
    type: 'email',
    delay: 5,
    event: 'order_created_paid',
    content: '',
    copyTo: '',
    before_booking_unique_minute: 30,
    send_once: false
  });

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

  useEffect(() => {
    fetchTemplates();
    setEmailSettingsLoading(true);
    fetchEmailSettings();
  }, []);

  useEffect(() => {
    filterTemplates();
  }, [templates, searchTerm, triggerFilter]);

  // Close dropdown when clicking outside
  useEffect(() => {
    const handleClickOutside = (event) => {
      // Check if click is on options button or dropdown menu
      const isOptionsButton = event.target.closest(`.${styles.optionsBtn}`);
      const isDropdownMenu = event.target.closest(`.${styles.optionsMenu}`);
      
      if (!isOptionsButton && !isDropdownMenu) {
        setOpenDropdowns({});
        setDropdownPositions({});
      }
    };

    const handleResize = () => {
      // Close all dropdowns on window resize
      setOpenDropdowns({});
      setDropdownPositions({});
    };

    document.addEventListener('mousedown', handleClickOutside);
    window.addEventListener('resize', handleResize);
    return () => {
      document.removeEventListener('mousedown', handleClickOutside);
      window.removeEventListener('resize', handleResize);
    };
  }, []);

  // Close dropdown when modal opens
  useEffect(() => {
    if (showCreateForm) {
      setOpenDropdowns({});
      setDropdownPositions({});
    }
  }, [showCreateForm]);

  const fetchTemplates = async () => {
    try {
      setLoading(true);
      const response = await axios.get(`${apiUrl}`, {
        params: {
          action: 'getEmailTemplates',
          owner_id: owner_id
        },
        headers: {
          'Authorization': `Bearer ${user_token}`
        }
      });
      
      if (response.data.success) {
        setTemplates(response.data.data.templates || []);
      } else {
        console.error('Failed to load templates:', response.data.message);
        // Add sample data for testing when API fails
        setTemplates([]);
      }
    } catch (err) {
      console.error('Error loading templates:', err);
      // Add sample data for testing when API fails
      setTemplates([]);
    } finally {
      setLoading(false);
    }
  };

  const fetchEmailSettings = async () => {
    try {
      setEmailSettingsLoading(true);
      const response = await axios.get(`${apiUrl}`, {
        params: {
          action: 'getEmailSettings',
          owner_id: owner_id
        },
        headers: {
          'Authorization': `Bearer ${user_token}`
        }
      });
      
      if (response.data.success && response.data.data.settings) {
        
        const apiSettings = response.data.data.settings;
        // Map API field names to frontend field names
        setEmailSettings({
          senderName: apiSettings.from_email_name || '',
          fromEmail: apiSettings.from_email || '',
          replyToEmail: apiSettings.reply_to_email || '',
          companyName: apiSettings.company_name || '',
          address: apiSettings.company_address || '',
          postcode: apiSettings.company_postcode || '',
          area: apiSettings.company_area || '',
          country: apiSettings.company_country || ''
        });
      }
    } catch (err) {
      console.error('Error loading email settings:', err);
      // Keep default empty settings if API fails
    } finally {
      setEmailSettingsLoading(false);
    }
  };

  const filterTemplates = () => {
    let filtered = templates;

    // Filter by search term
    if (searchTerm) {
      filtered = filtered.filter(template => 
        template.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
        template.subject?.toLowerCase().includes(searchTerm.toLowerCase()) ||
        (template.type === 'sms' ? 
          template.content?.toLowerCase().includes(searchTerm.toLowerCase()) :
          stripHtmlTags(template.content)?.toLowerCase().includes(searchTerm.toLowerCase())
        )
      );
    }

    // Filter by trigger
    if (triggerFilter !== 'all') {
      filtered = filtered.filter(template => template.event === triggerFilter);
    }

    setFilteredTemplates(filtered);
  };

  const handleCreateTemplate = () => {
    setShowCreateForm(true);
  };

  const handleSubmitTemplate = async (templateData) => {
    try {
      setIsSubmitting(true);
      setSubmitError('');
      const action = editingTemplate ? 'updateEmailTemplate' : 'createEmailTemplate';
      const requestData = editingTemplate 
        ? { ...templateData, template_id: editingTemplate.id }
        : templateData;

      const response = await axios.post(`${apiUrl}`, {
        action: action,
        template: requestData,
        owner_id: owner_id
      }, {
        headers: {
          'Authorization': `Bearer ${user_token}`,
          'Content-Type': 'application/json'
        }
      });

      if (response.data.success) {
        setShowCreateForm(false);
        setEditingTemplate(null);
        setNewTemplate({ 
          name: '', 
          subject: '',
          active: true, 
          type: 'email', 
          delay: 5, 
          event: 'order_created_paid', 
          content: '',
          copyTo: '',
          before_booking_unique_minute: 30,
          send_once: false
        });
        fetchTemplates();
      } else {
        console.error(`Failed to ${editingTemplate ? 'update' : 'create'} template:`, response.data.message);
        setSubmitError(response.data.message || Ltext('Something went wrong. Please try again.'));
      }
    } catch (err) {
      console.error(`Error ${editingTemplate ? 'updating' : 'creating'} template:`, err);
      const apiMessage = err?.response?.data?.message || err?.message || Ltext('Network error. Please try again.');
      setSubmitError(apiMessage);
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleCancelCreate = () => {
    setShowCreateForm(false);
    setNewTemplate({ 
      name: '', 
      subject: '',
      active: true, 
      type: 'email', 
      delay: 5, 
      event: 'order_created_paid', 
      content: '',
      copyTo: '',
      before_booking_unique_minute: 30,
      send_once: false
    });
  };

  const handleEditTemplate = (template) => {
    console.log('handleEditTemplate called with:', template);
    setEditingTemplate(template);
    setShowCreateForm(true);
    setOpenDropdowns({});
    setDropdownPositions({});
  };

  const handleDeleteTemplate = async (templateId) => {
    console.log('handleDeleteTemplate called with templateId:', templateId);
    const ok = window.confirm(Ltext('Are you sure you want to delete this template?'));
    if (!ok) {
      setOpenDropdowns({});
      setDropdownPositions({});
      return;
    }
    try {
      const response = await axios.post(`${apiUrl}`, {
        action: 'hardDeleteEmailTemplate',
        template_id: templateId,
        owner_id: owner_id
      }, {
        headers: {
          'Authorization': `Bearer ${user_token}`,
          'Content-Type': 'application/json'
        }
      });

      if (response.data.success) {
        fetchTemplates();
      } else {
        console.error('Failed to delete template:', response.data.message);
      }
    } catch (err) {
      console.error('Error deleting template:', err);
    } finally {
      setOpenDropdowns({});
      setDropdownPositions({});
    }
  };

  const handleCancelEdit = () => {
    setEditingTemplate(null);
    setShowCreateForm(false);
  };

  const clearFilters = () => {
    setSearchTerm('');
    setTriggerFilter('all');
  };

  const handleShowMore = (content) => {
    setSelectedContent(content);
    setShowPopup(true);
  };

  const closePopup = () => {
    setShowPopup(false);
    setSelectedContent('');
  };

  const getTriggerDisplayName = (event, before_booking_unique_minute = null) => {
    const triggerMap = {
      'order_created_paid': Ltext('Booking confirmation (after payment)'),
      'before_booking_start': Ltext('Reminder: before booking'),
      'before_booking_start_unique': before_booking_unique_minute ? `${Ltext('Check:')} ${before_booking_unique_minute} ${Ltext('minutes before booking (if slot is free)')}` : Ltext('Check: X minutes before booking (if slot is free)'),
      'after_booking_end': Ltext('Follow-up: after booking')
    };
    return triggerMap[event] || event;
  };

  const decodeHtml = (html) => {
    if (!html) return '';
    
    // Create a temporary div to decode HTML entities
    const txt = document.createElement('textarea');
    txt.innerHTML = html;
    return txt.value;
  };

  // Helper function to strip HTML tags for display
  const stripHtmlTags = (html) => {
    if (!html) return '';
    const decoded = decodeHtml(html);
    // Remove all HTML tags including those with attributes like style, class, etc.
    return decoded.replace(/<[^>]*>/g, '').trim();
  };


  // const stripHtmlTags = (html) => {
  //   if (!html) return '';
  //   // Create a temporary div element to parse HTML
  //   const temp = document.createElement('div');
  //   temp.innerHTML = html;
  //   return temp.textContent || temp.innerText || '';
  // };

  const truncateContent = (content, maxLength = 100) => {
    if (!content) return '';
    return content.length > maxLength ? content.substring(0, maxLength) + '...' : content;
  };

  const calculateDropdownPosition = (buttonElement) => {
    const rect = buttonElement.getBoundingClientRect();
    
    return {
      top: rect.bottom + 5, // 5px below button
      left: rect.left - 100
    };
  };

  const handleDropdownToggle = (templateId, event) => {
    const buttonElement = event.currentTarget;
    const position = calculateDropdownPosition(buttonElement);
    
    setDropdownPositions(prev => ({
      ...prev,
      [templateId]: position
    }));
    
    setOpenDropdowns(prev => ({
      ...prev,
      [templateId]: !prev[templateId]
    }));
  };

  // Dropdown component that renders outside the table
  const DropdownPortal = ({ templateId, position, onEdit, onDelete }) => {
    if (!position) return null;

    const handleEditClick = (e) => {
      e.preventDefault();
      e.stopPropagation();
      console.log('Edit button clicked for template:', templateId);
      onEdit();
      setOpenDropdowns({});
      setDropdownPositions({});
    };

    const handleDeleteClick = (e) => {
      e.preventDefault();
      e.stopPropagation();
      console.log('Delete button clicked for template:', templateId);
      onDelete();
    };

    const handleDropdownClick = (e) => {
      e.preventDefault();
      e.stopPropagation();
    };

    return createPortal(
      <div 
        className={styles.optionsMenu}
        style={{
          position: 'fixed',
          top: position.top,
          left: position.left,
          zIndex: 9999
        }}
        onClick={handleDropdownClick}
      >
        <Button 
          variant="ghost"
          size="small"
          className={styles.editBtn}
          onClick={handleEditClick}
          leftIcon={<i className="fa fa-edit" style={{ color: '#1a9a94' }}></i>}
        >
          {Ltext("Edit")}
        </Button>
        <Button 
          variant="ghost"
          size="small"
          className={styles.deleteBtn}
          onClick={handleDeleteClick}
          leftIcon={<i className="fa fa-trash" style={{ color: '#dc3545' }}></i>}
        >
          {Ltext("Delete")}
        </Button>
      </div>,
      document.body
    );
  };

  if (loading) {
    return (
      <div className={styles.emailTemplatesWrapper}>
        <div className={styles.emailTemplatesContainer}>
          <div className={styles.loading}>
            <div className={styles.spinner}></div>
            <p>{Ltext("Loading templates...")}</p>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className={styles.emailTemplatesWrapper}>
      <div className={styles.emailTemplatesContainer}>
        <div className={styles.emailTemplatesSection}>
          <div className={styles.emailTemplatesHeader}>
            <h2 className={styles.emailTemplatesTitle}>{Ltext("Email & SMS Templates")}</h2>
            <Button 
              variant="outline"
              onClick={() => {
                setShowEmailSettings(true);
              }}
              title={Ltext("Email Settings")}
              rightIcon={<i className="fa fa-cog"></i>}
            >
              {Ltext("Settings")}
            </Button>
          </div>

          {/* Control Bar */}
          <div className={styles.controlBar}>
            <div className={styles.leftControls}>
              <Button 
                variant="primary"
                onClick={handleCreateTemplate}
                rightIcon={<i className="fa fa-plus"></i>}
              >
                {Ltext("Create template")}
              </Button>
            </div>

            <div className={styles.filterControls}>
              <span className={styles.filterLabel}>{Ltext("Filter:")}</span>
              {(triggerFilter !== 'all' || searchTerm) && (
                <Button 
                  variant="ghost"
                  size="small"
                  onClick={clearFilters}
                  leftIcon={<i className="fa fa-search" style={{ color: '#6c757d' }}></i>}
                >
                  {Ltext("Show all")}
                </Button>
              )}
              
              <select 
                className={styles.filterDropdown}
                value={triggerFilter}
                onChange={(e) => setTriggerFilter(e.target.value)}
              >
                <option value="all">{Ltext("Trigger")}</option>
                <option value="order_created_paid">{Ltext("Booking confirmation (after payment)")}</option>
                <option value="before_booking_start">{Ltext("Reminder: before booking")}</option>
                <option value="before_booking_start_unique">{Ltext("Check: X minutes before booking (if slot is free)")}</option>
                <option value="after_booking_end">{Ltext("Follow-up: after booking")}</option>
              </select>
            </div>

            <div className={styles.searchControls}>
              <div className={styles.searchBox}>
                <input
                  type="text"
                  placeholder={Ltext("Search")}
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                  className={styles.searchInput}
                />
                <Button 
                  variant="ghost" 
                  size="small"
                  className={styles.searchBtn}
                  type="button"
                >
                  <i className="fa fa-search" style={{ color: '#6c757d' }}></i>
                </Button>
              </div>
            </div>

            <div className={styles.displayInfo}>
              {Ltext("Displaying")} {filteredTemplates.length} {Ltext("templates")}
            </div>
          </div>

          {/* Templates Table */}
          {filteredTemplates.length === 0 ? (
            <div className={styles.emptyState}>
              <div className={styles.emptyStateCard}>
                <p className={styles.emptyStateText}>
                  {searchTerm || triggerFilter !== 'all' 
                    ? Ltext("No templates match your current filters.")
                    : Ltext("No templates have been created yet.")
                  }
                </p>
              </div>
            </div>
          ) : (
            <div style={{padding: '20px 16px'}}>
              <Table
                tableClassName={styles.templatesTable}
                tableStyle={{minWidth: '800px'}}
                rowStyle={{height: 'auto'}}
                data={filteredTemplates}
                getRowKey={(row) => row.id}
                  columns={[
                    {
                      header: Ltext("Name"),
                      thStyle: { width: '20%' },
                      tdStyle: { width: '20%' },
                      render: (template) => (
                        <div className={styles.templateName}>
                          <span className={styles.templateIcon}>
                            <i 
                              className={`fa ${template.type === 'sms' ? 'fa-comment' : 'fa-envelope'}`}
                              style={{ 
                                color: template.type === 'sms' ? '#3F4254' : '#3F4254' 
                              }}
                            ></i>
                          </span>
                          <span className={styles.templateNameText}>{template.name}</span>
                        </div>
                      )
                    },
                    {
                      header: Ltext("Subject"),
                      thStyle: { width: '15%' },
                      tdStyle: { width: '15%' },
                      tdClassName: styles.subjectCell,
                      render: (template) => (
                        <div className={styles.subjectText}>
                          {template.type === 'email' ? (template.subject || Ltext('-')) : Ltext('-')}
                        </div>
                      )
                    },
                    {
                      header: Ltext("Content"),
                      thStyle: { width: '30%' },
                      tdStyle: { width: '100%', display: 'flex' },
                      tdClassName: styles.contentCell,
                      render: (template) => (
                        <>
                          <div className={styles.contentText} style={{ width: '80%' }}>
                            {template.content && (() => {
                              const displayContent = template.type === 'sms' ? template.content : stripHtmlTags(template.content);
                              return (displayContent || Ltext('-'));
                            })()}
                          </div>
                          {template.content && (() => {
                            const displayContent = template.type === 'sms' ? template.content : stripHtmlTags(template.content);
                            return displayContent.length > 100;
                          })() && (
                            <Button 
                              variant="link" 
                              size="small"
                              className={styles.showMoreBtn}
                              onClick={() => handleShowMore(template.type === 'sms' ? template.content : template.content)}
                            >
                              {Ltext("Show More")}
                            </Button>
                          )}
                        </>
                      )
                    },
                    {
                      header: Ltext("Trigger"),
                      thStyle: { width: '20%' },
                      tdStyle: { width: '20%' },
                      render: (template) => (
                        <span className={styles.triggerText}>{getTriggerDisplayName(template.event, template.before_booking_unique_minute)}</span>
                      )
                    },
                    {
                      header: Ltext("Delay [min]"),
                      thStyle: { width: '10%' },
                      tdStyle: { width: '10%' },
                      tdClassName: styles.delayCell,
                      render: (template) => (
                        <span className={styles.delayBadgeText}>
                          {template.delay || 0} {Ltext('min')}
                        </span>
                      )
                    },
                    {
                      header: Ltext("Active?"),
                      thStyle: { width: '10%' },
                      tdStyle: { width: '10%' },
                      render: (template) => (
                        <span className={`${styles.statusBadge} ${template.active ? styles.statusActive : styles.statusInactive}`}>
                          <i 
                            className={`fa ${template.active ? 'fa-check' : 'fa-times'}`}
                            style={{ 
                              color: template.active ? '#fff' : '#fff' 
                            }}
                          ></i>
                        </span>
                      )
                    },
                    {
                      header: "",
                      thStyle: { width: '5%' },
                      tdStyle: { width: '5%' },
                      render: (template) => (
                        <div className={styles.optionsDropdown}>
                          <Button 
                            variant="ghost"
                            size="small"
                            className={`${styles.optionsBtn} ${openDropdowns[template.id] ? styles.active : ''}`}
                            onClick={(event) => handleDropdownToggle(template.id, event)}
                          >
                            <i className="fa fa-ellipsis-v" style={{ color: '#6c757d' }}></i>
                          </Button>
                        </div>
                      )
                    }
                  ]}
              />
            </div>
          )}
        </div>
      </div>

      {/* Create Template Modal */}
      <TemplateCreationModal
        isOpen={showCreateForm}
        onClose={editingTemplate ? handleCancelEdit : handleCancelCreate}
        onSubmit={handleSubmitTemplate}
        template={editingTemplate || newTemplate}
        isEdit={!!editingTemplate}
        isSubmitting={isSubmitting}
        errorMessage={submitError}
        onClearError={() => setSubmitError('')}
      />

      {/* Content Popup Modal */}
      {showPopup && (
        <Modal
          isOpen={showPopup}
          onClose={closePopup}
          title={Ltext("Template Content")}
          size="large"
        >
          <div className={styles.popupBody}>
            <div className={styles.contentInfo}>
              <div className={styles.contentText}>
                <h4>{Ltext("Content:")}</h4>
                <div 
                    style={{ 
                      overflowY: 'auto',
                      padding: '10px',
                    }}
                    dangerouslySetInnerHTML={{ __html: selectedContent }}
                />
              </div>
              
              <div className={styles.contentDetails}>
                <div className={styles.detailRow}>
                  <span className={styles.detailLabel}>{Ltext("Character Count:")}</span>
                  <span className={styles.detailValue}>{selectedContent.length}</span>
                </div>
                <div className={styles.detailRow}>
                  <span className={styles.detailLabel}>{Ltext("Word Count:")}</span>
                  <span className={styles.detailValue}>{selectedContent.split(' ').length}</span>
                </div>
              </div>
            </div>
          </div>
        </Modal>
      )}

      {/* Portal Dropdowns - Rendered outside the table */}
      {filteredTemplates.map((template) => (
        openDropdowns[template.id] && (
          <DropdownPortal
            key={`dropdown-${template.id}`}
            templateId={template.id}
            position={dropdownPositions[template.id]}
            onEdit={() => handleEditTemplate(template)}
            onDelete={() => handleDeleteTemplate(template.id)}
          />
        )
      ))}

      {/* Email Settings Modal */}
      {showEmailSettings && (
        <Modal
          isOpen={showEmailSettings}
          onClose={() => setShowEmailSettings(false)}
          title={Ltext("Email Settings")}
          size="large"
        >
          <div className={styles.emailSettingsContent}>
            {emailSettingsLoading ? (
              <div className={styles.settingsLoading}>
                <div className={styles.settingsSpinner}></div>
                <p>{Ltext("Loading email settings...")}</p>
              </div>
            ) : (
              <>
                <div className={styles.settingsRow}>
                  <label className={styles.settingsLabel}>
                    {Ltext("Sender's name in e-mail:")}
                  </label>
                  <input 
                    type="text" 
                    className={styles.settingsInput}
                    value={emailSettings.senderName}
                    onChange={(e) => setEmailSettings(prev => ({ ...prev, senderName: e.target.value }))}
                    placeholder={Ltext("Enter sender's name")}
                  />
                </div>

                <div className={styles.settingsRow}>
                  <label className={styles.settingsLabel}>
                    {Ltext("From e-mail:")}
                  </label>
                  <input 
                    type="email" 
                    className={styles.settingsInput}
                    value={emailSettings.fromEmail}
                    onChange={(e) => setEmailSettings(prev => ({ ...prev, fromEmail: e.target.value }))}
                    placeholder={Ltext("Enter from email address")}
                  />
                </div>

                <div className={styles.settingsRow}>
                  <label className={styles.settingsLabel}>
                    {Ltext("Reply to e-mail:")}
                  </label>
                  <input 
                    type="email" 
                    className={styles.settingsInput}
                    value={emailSettings.replyToEmail}
                    onChange={(e) => setEmailSettings(prev => ({ ...prev, replyToEmail: e.target.value }))}
                    placeholder={Ltext("Enter reply-to email address")}
                  />
                </div>

                <div className={styles.settingsRow}>
                  <label className={styles.settingsLabel}>
                    {Ltext("Company name:")}
                  </label>
                  <input 
                    type="text" 
                    className={styles.settingsInput}
                    value={emailSettings.companyName}
                    onChange={(e) => setEmailSettings(prev => ({ ...prev, companyName: e.target.value }))}
                    placeholder={Ltext("Enter company name")}
                  />
                </div>

                <div className={styles.settingsRow}>
                  <label className={styles.settingsLabel}>
                    {Ltext("Address:")}
                  </label>
                  <input 
                    type="text" 
                    className={styles.settingsInput}
                    value={emailSettings.address}
                    onChange={(e) => setEmailSettings(prev => ({ ...prev, address: e.target.value }))}
                    placeholder={Ltext("Enter address")}
                  />
                </div>

                <div className={styles.settingsRow}>
                  <label className={styles.settingsLabel}>
                    {Ltext("Postcode:")}
                  </label>
                  <input 
                    type="text" 
                    className={styles.settingsInput}
                    value={emailSettings.postcode}
                    onChange={(e) => setEmailSettings(prev => ({ ...prev, postcode: e.target.value }))}
                    placeholder={Ltext("Enter postcode")}
                  />
                </div>

                <div className={styles.settingsRow}>
                  <label className={styles.settingsLabel}>
                    {Ltext("Area:")}
                  </label>
                  <input 
                    type="text" 
                    className={styles.settingsInput}
                    value={emailSettings.area}
                    onChange={(e) => setEmailSettings(prev => ({ ...prev, area: e.target.value }))}
                    placeholder={Ltext("Enter area")}
                  />
                </div>

                <div className={styles.settingsRow}>
                  <label className={styles.settingsLabel}>
                    {Ltext("Country:")}
                  </label>
                  <input 
                    type="text" 
                    className={styles.settingsInput}
                    value={emailSettings.country}
                    onChange={(e) => setEmailSettings(prev => ({ ...prev, country: e.target.value }))}
                    placeholder={Ltext("Enter country")}
                  />
                </div>

                <div className={styles.settingsActions}>
                  <Button 
                    variant="primary"
                    onClick={async () => {
                      try {
                        setIsSubmitting(true);
                        setEmailSettingsError('');
                        
                        // Map frontend field names to API field names
                        const apiSettings = {
                          from_email_name: emailSettings.senderName,
                          from_email: emailSettings.fromEmail,
                          reply_to_email: emailSettings.replyToEmail,
                          company_name: emailSettings.companyName,
                          company_address: emailSettings.address,
                          company_postcode: emailSettings.postcode,
                          company_area: emailSettings.area,
                          company_country: emailSettings.country
                        };
                        
                        const response = await axios.post(`${apiUrl}`, {
                          action: 'saveEmailSettings',
                          settings: apiSettings,
                          owner_id: owner_id
                        }, {
                          headers: {
                            'Authorization': `Bearer ${user_token}`,
                            'Content-Type': 'application/json'
                          }
                        });

                        if (response.data.success) {
                          setShowEmailSettings(false);
                          console.log('Email settings saved successfully');
                        } else {
                          console.error('Failed to save email settings:', response.data.message);
                          setEmailSettingsError(response.data.message || Ltext('Failed to save email settings. Please try again.'));
                        }
                      } catch (error) {
                        console.error('Error saving email settings:', error);
                        const errorMessage = error?.response?.data?.message || error?.message || Ltext('Network error. Please try again.');
                        setEmailSettingsError(errorMessage);
                      } finally {
                        setIsSubmitting(false);
                      }
                    }}
                    disabled={isSubmitting}
                    loading={isSubmitting}
                    leftIcon={<i className="fa fa-save" style={{ color: '#fff' }}></i>}
                  >
                    {Ltext("Save Settings")}
                  </Button>
                  <Button 
                    variant="secondary"
                    onClick={() => setShowEmailSettings(false)}
                  >
                    {Ltext("Cancel")}
                  </Button>
                </div>
                
                {/* Error Message */}
                {emailSettingsError && (
                  <div className={styles.errorMessage}>
                    <span className={styles.errorIcon}><i className="fa fa-exclamation-triangle" style={{ color: '#721c24' }}></i></span>
                    <span className={styles.errorText}>{emailSettingsError}</span>
                    <Button 
                      variant="ghost"
                      size="small"
                      className={styles.errorCloseBtn}
                      onClick={() => setEmailSettingsError('')}
                      title={Ltext("Close error message")}
                    >
                      <i className="fa fa-times" style={{ color: '#721c24' }}></i>
                    </Button>
                  </div>
                )}
              </>
            )}
          </div>
        </Modal>
      )}
    </div>
  );
}

export default EmailTemplate;

