import React, { useState, useEffect, useRef } from 'react';
import Modal from '../components/Modal';
import QuillEditor from '../components/QuillEditor';
import TextareaWithEmoji from '../components/TextareaWithEmoji';
import Button from '../components/Button';
import { Ltext, getLanguage } from '../utils/emailTemplate-translations';
import styles from '../assets/scss/templateCreationModal.module.scss';

function TemplateCreationModal({ 
  isOpen, 
  onClose, 
  onSubmit, 
  template = null,
  isEdit = false,
  isSubmitting = false,
  errorMessage = '',
  onClearError = () => {}
}) {
  const [formData, setFormData] = useState({
    name: template?.name || '',
    subject: template?.subject || '',
    active: template?.active !== false, // Default to true
    type: template?.type || 'email', // email or sms
    delay: template?.delay || 0,
    event: template?.event || 'order_created_paid',
    content: template?.content || '',
    copyTo: template?.copyTo || '',
    before_booking_unique_minute: template?.before_booking_unique_minute || 30, // Minutes for before_booking_start_unique
    send_once: template?.send_once || false, // Only send 1 time this email to customer
    editorType: template?.editorType || 'rich' // 'rich' for QuillEditor, 'html' for textarea
  });

  const [errors, setErrors] = useState({});
  const [showDataFields, setShowDataFields] = useState(false);
  const [editTemplateContent, setEditTemplateContent] = useState("");
  const [showHtmlPreview, setShowHtmlPreview] = useState(false);
  
  // Refs for editor components
  const quillEditorRef = useRef(null);
  const textareaWithEmojiRef = useRef(null);
  const smsTextareaRef = useRef(null);

  // Update form data when template prop changes (for editing)
  useEffect(() => {
    if (template) {
      setFormData({
        name: template.name || '',
        subject: template.subject || '',
        active: template.active !== false,
        type: template.type || 'email',
        delay: template.delay || 0,
        event: template.event || 'order_created_paid',
        content: template.content || '',
        copyTo: template.copyTo || '',
        before_booking_unique_minute: template.before_booking_unique_minute || 30,
        send_once: template.send_once || false,
        editorType: template.editorType || 'rich'
      });
      setEditTemplateContent(template.content);
    }
  }, [template]);

  // Reset editor type when switching to SMS
  useEffect(() => {
    if (formData.type === 'sms') {
      setFormData(prev => ({ ...prev, editorType: 'rich' })); // Reset to default when switching to SMS
    }
  }, [formData.type]);

  // Close data fields dropdown when clicking outside
  useEffect(() => {
    const handleClickOutside = (event) => {
      if (showDataFields && !event.target.closest(`.${styles.dataFieldsSection}`)) {
        setShowDataFields(false);
      }
    };

    document.addEventListener('mousedown', handleClickOutside);
    return () => {
      document.removeEventListener('mousedown', handleClickOutside);
    };
  }, [showDataFields]);

  const eventOptions = [
    { value: 'order_created_paid', label: Ltext('Booking confirmation (after payment)'), icon: '💰' },
    { value: 'before_booking_start', label: Ltext('Reminder: before booking'), icon: '⏰' },
    { value: 'before_booking_start_unique', label: Ltext('Check: X minutes before booking (if slot is free)'), icon: '⏰' },
    { value: 'after_booking_end', label: Ltext('Follow-up: after booking'), icon: '✅' }
  ];

  const handleInputChange = (field, value) => {
    setFormData(prev => ({ ...prev, [field]: value }));
    // Clear error when user starts typing
    if (errors[field]) {
      setErrors(prev => ({ ...prev, [field]: '' }));
    }
    if (errorMessage) {
      onClearError();
    }
  };

  const handleEditorTypeChange = (newEditorType) => {
    const currentContent = editTemplateContent;
    
    setFormData(prev => ({ 
      ...prev, 
      editorType: newEditorType,
      content: currentContent
    }));
    
    // Reset preview when switching editor types
    setShowHtmlPreview(false);
  };

  const toggleHtmlPreview = () => {
    setShowHtmlPreview(prev => !prev);
  };

  const validateForm = () => {
    const newErrors = {};
    
    if (!formData.name.trim()) {
      newErrors.name = Ltext('Name is required');
    }
    
    // Subject is only required for email type
    if (formData.type === 'email' && !formData.subject.trim()) {
      newErrors.subject = Ltext('Subject is required');
    }
    
    if (!formData.content.trim()) {
      newErrors.content = Ltext('Content is required');
    }
    
    if (formData.delay < 0) {
      newErrors.delay = Ltext('Delay cannot be negative');
    }
    
    // Validate before_booking_unique_minute field for before_booking_start_unique
    if (formData.event === 'before_booking_start_unique') {
      if (!formData.before_booking_unique_minute || formData.before_booking_unique_minute < 1) {
        newErrors.before_booking_unique_minute = Ltext('Minutes must be at least 1');
      }
    }
    
    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = (e) => {
    e.preventDefault();

    if (validateForm()) {
      // Decode HTML entities in content before submitting (handle double-encoded cases)
      const decodedFormData = {
        ...formData,
        content: formData.content ? decodeHtmlEntities(formData.content) : formData.content
      };
      // console.log('decodedFormData2', formData.content);
      // console.log('decodedFormData', decodedFormData);
      onSubmit(decodedFormData);
    }
  };

  const decodeHtmlEntities = (html) => {
    const textarea = document.createElement('textarea');
    textarea.innerHTML = html;
    return textarea.value;
  };

  // Function to decode HTML entities (multi-pass to handle double-encoded input)
  const decodeHtmlEntitiesDeep = (html) => {
    let previous = null;
    let current = html == null ? '' : String(html);

    // Run a few passes until the value stops changing
    for (let i = 0; i < 3; i += 1) {
      const textarea = document.createElement('textarea');
      textarea.innerHTML = current;
      const decoded = textarea.value;
      if (decoded === previous || decoded === current) {
        return decoded;
      }
      previous = current;
      current = decoded;
    }

    return current;
  };

  const handleCancel = () => {
    setFormData({
      name: '',
      subject: '',
      active: true,
      type: 'email',
      delay: 5,
      event: 'order_created_paid',
      content: '',
      copyTo: '',
      before_booking_unique_minute: 30,
      send_once: false,
      editorType: 'rich'
    });
    setErrors({});
    onClose();
  };

  const insertDataField = (field) => {
    const fieldPlaceholder = `{{${field}}}`;
    
    if (formData.type === 'email' && formData.editorType === 'rich') {
      // For QuillEditor
      if (quillEditorRef.current) {
        quillEditorRef.current.insertTextAtCursor(fieldPlaceholder);
      }
    } else if (formData.type === 'email' && formData.editorType === 'html') {
      // For TextareaWithEmoji (HTML editor)
      if (textareaWithEmojiRef.current) {
        textareaWithEmojiRef.current.insertTextAtCursor(fieldPlaceholder);
      }
    } else {
      // For SMS textarea
      if (smsTextareaRef.current) {
        smsTextareaRef.current.focus();
        const start = smsTextareaRef.current.selectionStart || 0;
        const end = smsTextareaRef.current.selectionEnd || 0;
        const newContent = formData.content.substring(0, start) + fieldPlaceholder + formData.content.substring(end);
        
        setFormData(prev => ({
          ...prev,
          content: newContent
        }));
        
        // Set cursor position after the inserted field
        setTimeout(() => {
          if (smsTextareaRef.current) {
            const newPosition = start + fieldPlaceholder.length;
            smsTextareaRef.current.setSelectionRange(newPosition, newPosition);
          }
        }, 10);
      }
    }
  };

  const dataFields = [
    { key: 'first_name', label: Ltext('First Name'), icon: 'fa-user', color: '#007bff' },
    { key: 'last_name', label: Ltext('Last Name'), icon: 'fa-user', color: '#007bff' },
    { key: 'customer_email', label: Ltext('Customer Email'), icon: 'fa-envelope', color: '#28a745' },
    { key: 'customer_phone', label: Ltext('Customer Phone'), icon: 'fa-phone', color: '#17a2b8' },
    { key: 'booking_start_date', label: Ltext('Booking Start Date'), icon: 'fa-calendar', color: '#ffc107' },
    { key: 'booking_end_date', label: Ltext('Booking End Date'), icon: 'fa-calendar', color: '#ffc107' },
    { key: 'amount', label: Ltext('Amount'), icon: 'fa-dollar-sign', color: '#28a745' },
  ];

  return (
    <Modal
      isOpen={isOpen}
      onClose={handleCancel}
      title={isEdit ? Ltext("Edit Template") : Ltext("Create Template")}
      size="large"
      closeOnOverlayClick={false}
    >
      <div className={styles.templateCreationWrapper}>
        <form onSubmit={handleSubmit} className={styles.templateForm}>
          {/* Name Field */}
          <div className={styles.formGroup}>
            <label htmlFor="templateName" className={styles.formLabel}>
              {Ltext("Template Name")} *
            </label>
            <input
              type="text"
              id="templateName"
              value={formData.name}
              onChange={(e) => handleInputChange('name', e.target.value)}
              className={`${styles.formInput} ${errors.name ? styles.inputError : ''}`}
              placeholder={Ltext("Enter template name")}
              disabled={isSubmitting}
            />
            {errors.name && <span className={styles.errorText}>{errors.name}</span>}
          </div>

          {/* Type Selection */}
          <div className={styles.formGroup}>
            <label htmlFor="templateType" className={styles.formLabel}>
              {Ltext("Template Type *")}
            </label>
            <select
              id="templateType"
              value={formData.type}
              onChange={(e) => handleInputChange('type', e.target.value)}
              className={styles.formSelect}
              disabled={isSubmitting}
            >
              <option value="email">{Ltext("Email")}</option>
              <option value="sms">{Ltext("SMS")}</option>
            </select>
          </div>

          {/* Subject Field - Only show for email type */}
          {formData.type === 'email' && (
            <div className={styles.formGroup}>
              <label htmlFor="templateSubject" className={styles.formLabel}>
                {Ltext("Email Subject *")}
              </label>
              <input
                type="text"
                id="templateSubject"
                value={formData.subject}
                onChange={(e) => handleInputChange('subject', e.target.value)}
                className={`${styles.formInput} ${errors.subject ? styles.inputError : ''}`}
                placeholder={Ltext("Enter email subject")}
                disabled={isSubmitting}
              />
              {errors.subject && <span className={styles.errorText}>{errors.subject}</span>}
            </div>
          )}

          {/* Active Status */}
          <div className={styles.formGroup}>
            <label className={styles.checkboxLabel}>
              <input
                type="checkbox"
                checked={formData.active}
                onChange={(e) => handleInputChange('active', e.target.checked)}
                className={styles.checkbox}
              disabled={isSubmitting}
              />
              <span className={styles.checkboxText}>{Ltext("Active")}</span>
            </label>
          </div>

          {/* Send Once Checkbox */}
            <div className={styles.formGroup}>
              <label className={styles.checkboxLabel}>
                <input
                  type="checkbox"
                  checked={formData.send_once}
                  onChange={(e) => handleInputChange('send_once', e.target.checked)}
                  className={styles.checkbox}
                  disabled={isSubmitting}
                />
                <span className={styles.checkboxText}>
                  {formData.type === 'email' 
                    ? Ltext("Only send 1 time this email to customer")
                    : Ltext("Only send 1 time this SMS to customer")
                  }
                </span>
              </label>
            </div>

          {/* Event Selection */}
          <div className={styles.formGroup}>
            <label htmlFor="templateEvent" className={styles.formLabel}>
              {Ltext("Trigger Event *")}
            </label>
            <select
              id="templateEvent"
              value={formData.event}
              onChange={(e) => handleInputChange('event', e.target.value)}
              className={styles.formSelect}
              disabled={isSubmitting}
            >
              {eventOptions.map(option => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </select>
          </div>

          {/* Minutes Field - Only show for before_booking_start_unique */}
          {formData.event === 'before_booking_start_unique' && (
            <div className={styles.formGroup}>
              <label htmlFor="templateBeforeBookingUniqueMinute" className={styles.formLabel}>
                {Ltext("Check Minutes Before Booking *")}
              </label>
              <input
                type="number"
                id="templateBeforeBookingUniqueMinute"
                value={formData.before_booking_unique_minute}
                onChange={(e) => handleInputChange('before_booking_unique_minute', parseInt(e.target.value) || 0)}
                className={`${styles.formInput} ${errors.before_booking_unique_minute ? styles.inputError : ''}`}
                min="1"
                step="1"
                placeholder={Ltext("Enter minutes (e.g., 30)")}
                disabled={isSubmitting}
              />
              {errors.before_booking_unique_minute && <span className={styles.errorText}>{errors.before_booking_unique_minute}</span>}
            </div>
          )}

          {/* Delay Field */}
          <div className={styles.formGroup}>
            <label htmlFor="templateDelay" className={styles.formLabel}>
              {Ltext("Send Delay (minutes) *")}
            </label>
            <input
              type="number"
              id="templateDelay"
              value={formData.delay}
              onChange={(e) => handleInputChange('delay', parseInt(e.target.value) || 0)}
              className={`${styles.formInput} ${errors.delay ? styles.inputError : ''}`}
              min="0"
              step="1"
              disabled={isSubmitting}
            />
            {errors.delay && <span className={styles.errorText}>{errors.delay}</span>}
          </div>

          {/* Email-specific fields - only show when email type is selected */}
          {formData.type === 'email' && (
            <>
              {/* Copy To Field */}
              <div className={styles.formGroup}>
                <label htmlFor="templateCopyTo" className={styles.formLabel}>
                  {Ltext("Copy to")}
                </label>
                <input
                  type="text"
                  id="templateCopyTo"
                  value={formData.copyTo}
                  onChange={(e) => handleInputChange('copyTo', e.target.value)}
                  className={styles.formInput}
                  placeholder={Ltext("Enter email addresses to copy (optional)")}
                disabled={isSubmitting}
                />
              </div>
            </>
          )}

          {/* Editor Type Toggle - Only show for email type */}
          {formData.type === 'email' && (
            <div className={styles.formGroup}>
              <label className={styles.formLabel}>
                {Ltext("Editor Type")}
              </label>
              <div className={styles.switchContainer}>
                <span className={`${styles.switchLabel} ${styles.switchLabelLeft} ${formData.editorType === 'rich' ? styles.switchLabelActive : ''}`}>
                  <i className="fa fa-edit"></i> {Ltext("Rich Editor")}
                </span>
                <label className={styles.switch}>
                  <input
                    type="checkbox"
                    checked={formData.editorType === 'html'}
                    onChange={(e) => handleEditorTypeChange(e.target.checked ? 'html' : 'rich')}
                    disabled={isSubmitting}
                  />
                  <span className={styles.slider}></span>
                </label>
                <span className={`${styles.switchLabel} ${styles.switchLabelRight} ${formData.editorType === 'html' ? styles.switchLabelActive : ''}`}>
                  <i className="fa fa-edit"></i> {Ltext("HTML Editor")}
                </span>
              </div>
            </div>
          )}

          {/* Content Field */}
          <div className={styles.formGroup}>
            <label htmlFor="templateContent" className={styles.formLabel}>
              {formData.type === 'email' ? Ltext("Email Content *") : Ltext("SMS Content *")}
            </label>
            {formData.type === 'email' ? (
              formData.editorType === 'rich' ? (
                <QuillEditor
                  value={formData.content}
                  onChange={(content) => handleInputChange('content', content)}
                  placeholder={Ltext("Enter your email content here...")}
                  disabled={isSubmitting}
                  height="200px"
                  error={!!errors.content}
                  showEmojiPicker={true}
                  toolbar="full"
                  onRef={quillEditorRef}
                />
              ) : (
                <div className={styles.htmlEditorContainer}>
                  <TextareaWithEmoji
                    value={formData.content}
                    onChange={(content) => handleInputChange('content', content)}
                    placeholder={Ltext("Enter your HTML email content here...")}
                    disabled={isSubmitting}
                    rows={8}
                    height="200px"
                    fontFamily="monospace"
                    showEmojiPicker={true}
                    error={!!errors.content}
                    onRef={textareaWithEmojiRef}
                  />
                  {showHtmlPreview && (
                    <div className={styles.htmlPreviewContainer}>
                      <div className={styles.htmlPreviewHeader}>
                        <span className={styles.htmlPreviewTitle}>{Ltext('HTML Preview')}</span>
                      </div>
                      <div 
                        className={styles.htmlPreviewContent}
                        dangerouslySetInnerHTML={{ __html: formData.content || '<p>No content to preview</p>' }}
                      />
                    </div>
                  )}
                </div>
              )
            ) : (
              <textarea
                ref={smsTextareaRef}
                id="templateContent"
                value={formData.content}
                onChange={(e) => handleInputChange('content', e.target.value)}
                className={`${styles.formTextarea} ${errors.content ? styles.textareaError : ''}`}
                rows={4}
                placeholder={Ltext("Enter your SMS content here...")}
                disabled={isSubmitting}
              />
            )}
            {errors.content && <span className={styles.errorText}>{errors.content}</span>}
          </div>

          {/* Action Buttons Section */}
            <div className={styles.dataFieldsSection}>
              <Button
                type="button"
                variant="outline"
                onClick={() => setShowDataFields(!showDataFields)}
                className={styles.insertDataFieldsBtn}
                disabled={isSubmitting}
                leftIcon={<i className="fa fa-plus"></i>}
                rightIcon={<i className={`fa ${showDataFields ? 'fa-chevron-up' : 'fa-chevron-down'}`}></i>}
              >
                {Ltext("Insert Data Fields")}
              </Button>
              
              {formData.type === 'email' && formData.editorType === 'html' && (
                <Button
                  type="button"
                  variant="outline"
                  onClick={toggleHtmlPreview}
                  className={styles.previewToggleBtn}
                  disabled={isSubmitting}
                  leftIcon={<i className={`fa ${showHtmlPreview ? 'fa-eye-slash' : 'fa-eye'}`}></i>}
                >
                  {showHtmlPreview ? Ltext('Hide Preview') : Ltext('Show Preview')}
                </Button>
              )}
              
              {showDataFields && (
                <div className={styles.dataFieldsDropdown}>
                  <div className={styles.dataFieldsGrid}>
                    {dataFields.map(field => (
                      <Button
                        key={field.key}
                        type="button"
                        variant="ghost"
                        size="small"
                        onClick={() => insertDataField(field.key)}
                        className={styles.dataFieldItem}
                        disabled={isSubmitting}
                        leftIcon={<i className={`fa ${field.icon}`} style={{ color: field.color }}></i>}
                      >
                        {field.label}
                      </Button>
                    ))}
                  </div>
                </div>
              )}
            </div>

          {/* Form Actions */}
          <div className={styles.formActions}>
            <Button
              type="button"
              variant="secondary"
              onClick={handleCancel}
              disabled={isSubmitting}
            >
              {Ltext("Cancel")}
            </Button>
            <Button
              type="submit"
              variant="primary"
              disabled={isSubmitting}
              loading={isSubmitting}
            >
              {isSubmitting ? Ltext('Saving...') : (isEdit ? Ltext('Update') : Ltext('Create'))}
            </Button>
          </div>

          {errorMessage && (
            <div
              className={styles.errorFooterBanner}
              role="alert"
              style={{
                marginTop: '16px',
                padding: '12px 14px',
                borderRadius: '8px',
                background: '#FFF5F5',
                color: '#B00020',
                border: '1px solid #F3C2C2',
                display: 'flex',
                alignItems: 'flex-start',
                gap: '10px'
              }}
            >
              <i className="fa fa-exclamation-triangle" style={{fontSize: '18px', lineHeight: 1}}></i>
              <div>
                <div style={{fontWeight: 600, marginBottom: 4}}>{Ltext("There was a problem")}</div>
                <div>{errorMessage}</div>
              </div>
            </div>
          )}
        </form>
      </div>
    </Modal>
  );
}

export default TemplateCreationModal;
