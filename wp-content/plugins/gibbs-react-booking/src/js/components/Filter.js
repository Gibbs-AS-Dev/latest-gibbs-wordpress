import React, { useState, useRef, useEffect } from 'react';
import styles from '../assets/scss/filter.module.scss';

/**
 * Reusable Filter component with dropdown
 * 
 * @param {string} label - Filter label
 * @param {React.ReactNode} children - Filter content
 * @param {number} count - Number of active filters
 * @param {string} className - Additional CSS classes
 */
function Filter({ label, children, count = 0, className = '' }) {
  const [isOpen, setIsOpen] = useState(false);
  const filterRef = useRef(null);

  useEffect(() => {
    const handleClickOutside = (event) => {
      if (filterRef.current && !filterRef.current.contains(event.target)) {
        setIsOpen(false);
      }
    };

    if (isOpen) {
      document.addEventListener('mousedown', handleClickOutside);
    }

    return () => {
      document.removeEventListener('mousedown', handleClickOutside);
    };
  }, [isOpen]);

  const toggleFilter = () => {
    setIsOpen(!isOpen);
  };

  return (
    <div className={`${styles.filterContainer} ${className}`} ref={filterRef}>
      <button
        className={`${styles.filterButton} ${isOpen ? styles.active : ''}`}
        onClick={toggleFilter}
        type="button"
        aria-expanded={isOpen}
        aria-haspopup="true"
      >
        <span className={styles.filterIcon}>
          <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M2 4H14M4 8H12M6 12H10" stroke="currentColor" strokeWidth="2" strokeLinecap="round"/>
          </svg>
        </span>
        <span className={styles.filterLabel}>{label}</span>
        {count > 0 && <span className={styles.filterCount}>{count}</span>}
        <span className={styles.filterChevron}>
          <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M3 4.5L6 7.5L9 4.5" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round"/>
          </svg>
        </span>
      </button>
      {isOpen && (
        <div className={styles.filterDropdown}>
          <div className={styles.filterContent}>
            {children}
          </div>
        </div>
      )}
    </div>
  );
}

export default Filter;

