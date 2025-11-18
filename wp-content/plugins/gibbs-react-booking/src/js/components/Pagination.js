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
    const maxVisiblePages = 5;
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
        pages.push(<span key="ellipsis1" className={styles.ellipsis}>...</span>);
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
        pages.push(<span key="ellipsis2" className={styles.ellipsis}>...</span>);
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
          <span className={styles.pageInfo}>
            {Ltext("Page")} {currentPage} {Ltext("of")} {totalPages}
          </span>
          <span className={styles.itemInfo}>
            {Ltext("Showing")} {start}-{end} {Ltext("of")} {totalItems} {Ltext("items")}
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
          {/* <svg className={styles.chevronLeft} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
            <path d="M15 18l-6-6 6-6" />
          </svg> */}
          {Ltext("Previous")}
        </button>

        <div className={styles.pageNumbers}>
          {renderPageNumbers()}
        </div>

        <button
          onClick={() => onPageChange(currentPage + 1)}
          disabled={currentPage === totalPages}
          className={`${styles.navButton} ${styles.nextButton} ${currentPage === totalPages ? styles.disabled : ''}`}
          aria-label={Ltext("Next page")}
        >
          {Ltext("Next")}
          {/* <svg className={styles.chevronRight} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
            <path d="M9 18l6-6-6-6" />
          </svg> */}
        </button>
      </div>
    </div>
  );
}

export default Pagination; 