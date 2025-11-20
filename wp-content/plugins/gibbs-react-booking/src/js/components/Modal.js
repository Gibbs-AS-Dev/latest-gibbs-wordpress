import React, { useEffect } from 'react';
import { Ltext } from '../utils/wallet-translations';
import styles from '../assets/scss/modal.module.scss';

function Modal({ 
  isOpen, 
  onClose, 
  title, 
  children, 
  size = 'medium',
  showCloseButton = true,
  closeOnOverlayClick = true,
  className = '',
  footer
}) {
  // Handle escape key press
  useEffect(() => {
    const handleEscape = (e) => {
      if (e.key === 'Escape' && isOpen) {
        onClose();
      }
    };

    if (isOpen) {
      document.addEventListener('keydown', handleEscape);
      // Prevent body scroll when modal is open
      document.body.style.overflow = 'hidden';
    }

    return () => {
      document.removeEventListener('keydown', handleEscape);
      document.body.style.overflow = 'unset';
    };
  }, [isOpen, onClose]);

  // Don't render if not open
  if (!isOpen) return null;

  const handleOverlayClick = (e) => {
    if (closeOnOverlayClick && e.target === e.currentTarget) {
      onClose();
    }
  };

  const getSizeClass = () => {
    switch (size) {
      case 'small':
        return styles.small;
      case 'large':
        return styles.large;
      case 'fullscreen':
        return styles.fullscreen;
      default:
        return styles.medium;
    }
  };

  return (
    <div className={`${styles.modalOverlay} ${className}`} onClick={handleOverlayClick}>
      <div className={`${styles.modalContent} ${getSizeClass()}`} onClick={(e) => e.stopPropagation()}>
        {/* Modal Header */}
        {title && (
          <div className={styles.modalHeader}>
            <h3 className={styles.modalTitle}>{title}</h3>
            {showCloseButton && (
              <button 
                className={styles.closeButton} 
                onClick={onClose}
                aria-label={Ltext("Close modal")}
              >
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                  <path d="M18 6L6 18M6 6l12 12" />
                </svg>
              </button>
            )}
          </div>
        )}

        {/* Modal Body */}
        <div className={styles.modalBody}>
          {children}
        </div>

        {/* Modal Footer */}
        {footer && (
          <div className={styles.modalFooter}>
            {footer}
          </div>
        )}
      </div>
    </div>
  );
}

export default Modal;
