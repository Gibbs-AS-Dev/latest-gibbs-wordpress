import React from 'react';
import { Ltext } from '../utils/smsLog-translations';
import styles from '../assets/scss/pagination.module.scss';

function Pagination({ 
  currentPage, 
  totalPages, 
  onPageChange, 
  totalItems, 
  itemsPerPage,
  showInfo = true,
  className = ''
}) {
  if (totalPages <= 1) return null;

  const renderPageNumbers = () => {
    const pages = [];
    const isMobile = typeof window !== 'undefined'
      ? window.matchMedia('(max-width: 600px)').matches
      : false;
    const maxVisiblePages = isMobile ? 6 : 10;
    let startPage = Math.max(1, currentPage - Math.floor(maxVisiblePages / 2));
    let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);

    if (endPage - startPage + 1 < maxVisiblePages) {
      startPage = Math.max(1, endPage - maxVisiblePages + 1);
    }

    // First page
    if (startPage > 1) {
      pages.push(
        <button
          key="1"
          onClick={() => onPageChange(1)}
          className={styles.pageButton}
          aria-label={`Go to page 1`}
        >
          1
        </button>
      );
      if (startPage > 2) {
        pages.push(<button key="ellipsis1" className={styles.pageButton}>...</button>);
      }
    }

    // Page numbers
    for (let i = startPage; i <= endPage; i++) {
      pages.push(
        <button
          key={i}
          onClick={() => onPageChange(i)}
          className={`${styles.pageButton} ${currentPage === i ? styles.active : ''}`}
          aria-label={`Go to page ${i}`}
          aria-current={currentPage === i ? 'page' : undefined}
        >
          {i}
        </button>
      );
    }

    // Last page
    if (endPage < totalPages) {
      if (endPage < totalPages - 1) {
        pages.push(<button key="ellipsis2" className={styles.pageButton}>...</button>);
      }
      pages.push(
        <button
          key={totalPages}
          onClick={() => onPageChange(totalPages)}
          className={styles.pageButton}
          aria-label={`Go to page ${totalPages}`}
        >
          {totalPages}
        </button>
      );
    }

    return pages;
  };

  const getItemRange = () => {
    const start = ((currentPage - 1) * itemsPerPage) + 1;
    const end = Math.min(currentPage * itemsPerPage, totalItems);
    return { start, end };
  };

  const { start, end } = getItemRange();

  return (
    <div className={`${styles.gibbsPagination} ${className}`}>
      {showInfo && (
        <div className={styles.paginationInfo}>
          <span className={styles.itemInfo}>
            {Ltext("Showing")} {start} {Ltext("to")} {end} {Ltext("of")} {totalItems} {Ltext("results")}
          </span>
        </div>
      )}
      
      <div className={styles.paginationControls}>
        <button
          onClick={() => onPageChange(currentPage - 1)}
          disabled={currentPage === 1}
          className={`${styles.navButton} ${styles.prevButton} ${currentPage === 1 ? styles.disabled : ''}`}
          aria-label={Ltext("Previous page")}
        >
          <i className="fa fa-chevron-left"></i>
        </button>

        {/* <div className={styles.pageNumbers}> */}
          {renderPageNumbers()}
        {/* </div> */}

        <button
          onClick={() => onPageChange(currentPage + 1)}
          disabled={currentPage === totalPages}
          className={`${styles.navButton} ${styles.nextButton} ${currentPage === totalPages ? styles.disabled : ''}`}
          aria-label={Ltext("Next page")}
        >
          <i className="fa fa-chevron-right"></i>
        </button>
      </div>
    </div>
  );
}

export default Pagination; 