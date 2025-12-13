import React, { useEffect, useRef, useState } from 'react';
import tableStyles from '../assets/scss/table.module.scss';

/**
 * Reusable Table component with built-in table module styles.
 * All CSS from table.module.scss is applied by default.
 * 
 * @param {Object} props
 * @param {Array} props.columns - Array of column definitions. Each column can have:
 *   - key: unique identifier
 *   - header: string or React element (header content)
 *   - sortable: boolean (if true, enables sorting for this column)
 *   - render: function(row, rowIndex) to render cell content
 *   - thStyle, thClassName: styles for header cell
 *   - tdStyle, tdClassName: styles for data cells
 * @param {Array} props.data - Array of row data objects
 * @param {Function|String} props.getRowKey - Function(row, index) or string key name to get unique row key
 * @param {Object} props.sortConfig - Current sort configuration: { key: string, direction: 'asc'|'desc' }
 * @param {Function} props.onSort - Callback when column header is clicked: (columnKey: string) => void
 * @param {String} props.tableClassName - Additional class name for table element
 * @param {Object} props.tableStyle - Inline styles for table element
 * @param {Function|Object} props.rowStyle - Function(row, index) or object for row styles
 * @param {String} props.wrapperClassName - Additional class name for wrapper div
 * @param {Object} props.wrapperStyle - Inline styles for wrapper div
 * @param {Boolean} props.noWrapper - If true, don't wrap table in div
 * @param {Function} props.onRowMouseEnter - Callback: (row, rowIndex) => void
 * @param {Function} props.onRowMouseLeave - Callback: (row, rowIndex) => void
 * @param {Boolean} props.enableDragScroll - If true, enable horizontal drag-to-scroll on the wrapper
 */
function Table({
  columns = [],
  data = [],
  getRowKey,
  sortConfig,
  onSort,
  tableClassName,
  tableStyle,
  rowStyle,
  wrapperClassName,
  wrapperStyle,
  noWrapper = false,
  onRowMouseEnter,
  onRowMouseLeave,
  enableDragScroll = true,
}) {
  const wrapperRef = useRef(null);
  const topScrollRef = useRef(null);
  const [isDragging, setIsDragging] = useState(false);
  const [dragStartX, setDragStartX] = useState(0);
  const [dragScrollLeft, setDragScrollLeft] = useState(0);
  const [topScrollWidth, setTopScrollWidth] = useState(0);

  useEffect(() => {
    if (wrapperRef.current) {
      setTopScrollWidth(wrapperRef.current.scrollWidth || wrapperRef.current.clientWidth);
    }
  }, [columns, data]);

  const handleMouseDown = (e) => {
    if (!enableDragScroll || !wrapperRef.current) return;

    // Only start drag if clicking on the table container, not on interactive elements
    if (
      e.target.tagName === 'BUTTON' ||
      e.target.closest('button') ||
      e.target.closest('input') ||
      e.target.closest('a') ||
      e.target.closest('select') ||
      e.target.closest('[data-sortable="true"]')
    ) {
      return;
    }

    const scrollableElement = wrapperRef.current;
    setIsDragging(true);
    const rect = scrollableElement.getBoundingClientRect();
    setDragStartX(e.pageX - rect.left);
    setDragScrollLeft(scrollableElement.scrollLeft);
  };

  const handleMouseMove = (e) => {
    if (!enableDragScroll || !isDragging || !wrapperRef.current) return;
    e.preventDefault();
    const scrollableElement = wrapperRef.current;
    const rect = scrollableElement.getBoundingClientRect();
    const x = e.pageX - rect.left;
    const walk = (x - dragStartX) * 2; // Scroll speed multiplier
    scrollableElement.scrollLeft = dragScrollLeft - walk;
  };

  const stopDragging = () => {
    if (!isDragging) return;
    setIsDragging(false);
  };

  const handleWrapperScroll = () => {
    if (!wrapperRef.current || !topScrollRef.current) return;
    if (topScrollRef.current.scrollLeft !== wrapperRef.current.scrollLeft) {
      topScrollRef.current.scrollLeft = wrapperRef.current.scrollLeft;
    }
  };

  const handleTopScroll = () => {
    if (!wrapperRef.current || !topScrollRef.current) return;
    if (wrapperRef.current.scrollLeft !== topScrollRef.current.scrollLeft) {
      wrapperRef.current.scrollLeft = topScrollRef.current.scrollLeft;
    }
  };

  const resolveRowKey = (item, index) => {
    if (typeof getRowKey === 'function') {
      return getRowKey(item, index);
    }
    if (typeof getRowKey === 'string' && item && item[getRowKey] !== undefined) {
      return String(item[getRowKey]);
    }
    return index;
  };

  // Combine default table styles with any custom className
  const finalTableClassName = tableClassName 
    ? `${tableStyles.table} ${tableClassName}` 
    : tableStyles.table;

  // Render header content - handles sortable columns
  const renderHeader = (col, idx) => {
    const isSortable = col.sortable === true && onSort;
    const isActiveSort = isSortable && sortConfig && sortConfig.key === col.key;
    const sortDirection = isActiveSort ? sortConfig.direction : null;

    // If header is already a React element, use it as-is
    if (React.isValidElement(col.header)) {
      return col.header;
    }

    // If column is sortable and header is a string, wrap it in sortable UI
    if (isSortable && typeof col.header === 'string') {
      return (
        <div 
          className={tableStyles.sortableHeader}
          data-sortable="true"
          onClick={(e) => {
            e.stopPropagation();
            e.preventDefault();
            onSort(col.key);
          }}
        >
          {col.header}
          {isActiveSort ? (
            <>
              {sortDirection === 'asc' ? (
                <>
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" width="12" 
              height="14"><path fill='#1a9a94' d="M342.6 41.4C330.1 28.9 309.8 28.9 297.3 41.4L169.3 169.4C156.8 181.9 156.8 202.2 169.3 214.7C181.8 227.2 202.1 227.2 214.6 214.7L288 141.3L288 576C288 593.7 302.3 608 320 608C337.7 608 352 593.7 352 576L352 141.3L425.4 214.7C437.9 227.2 458.2 227.2 470.7 214.7C483.2 202.2 483.2 181.9 470.7 169.4L342.7 41.4z"/></svg>
                </>
              ) : (
                <>
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" width="12" 
              height="14"><path fill='#1a9a94' d="M297.4 598.6C309.9 611.1 330.2 611.1 342.7 598.6L470.7 470.6C483.2 458.1 483.2 437.8 470.7 425.3C458.2 412.8 437.9 412.8 425.4 425.3L352 498.7L352 64C352 46.3 337.7 32 320 32C302.3 32 288 46.3 288 64L288 498.7L214.6 425.3C202.1 412.8 181.8 412.8 169.3 425.3C156.8 437.8 156.8 458.1 169.3 470.6L297.3 598.6z"/></svg>
                </>
              )}
            </>
          ) : (
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" width="12" 
              height="14"><path opacity="0.2" d="M342.6 41.4C330.1 28.9 309.8 28.9 297.3 41.4L169.3 169.4C156.8 181.9 156.8 202.2 169.3 214.7C181.8 227.2 202.1 227.2 214.6 214.7L288 141.3L288 576C288 593.7 302.3 608 320 608C337.7 608 352 593.7 352 576L352 141.3L425.4 214.7C437.9 227.2 458.2 227.2 470.7 214.7C483.2 202.2 483.2 181.9 470.7 169.4L342.7 41.4z"/></svg>
          )}
        </div>
      );
    }

    // Otherwise, return header as-is (string or null)
    return col.header;
  };

  const tableElement = (
    <table className={finalTableClassName} style={tableStyle}>
      <thead>
        <tr>
          {columns.map((col, idx) => (
            <th 
              key={col.key || idx} 
              className={col.thClassName} 
              style={col.thStyle}
            >
              {renderHeader(col, idx)}
            </th>
          ))}
        </tr>
      </thead>
      <tbody>
        {data.map((row, rowIndex) => (
          <tr 
            key={resolveRowKey(row, rowIndex)} 
            style={typeof rowStyle === 'function' ? rowStyle(row, rowIndex) : rowStyle}
            onMouseEnter={onRowMouseEnter ? () => onRowMouseEnter(row, rowIndex) : undefined}
            onMouseLeave={onRowMouseLeave ? () => onRowMouseLeave(row, rowIndex) : undefined}
          >
            {columns.map((col, colIndex) => {
              const tdStyle = typeof col.tdStyle === 'function' ? col.tdStyle(row, rowIndex) : col.tdStyle;
              return (
                <td key={(col.key || colIndex) + '-' + rowIndex} className={col.tdClassName} style={tdStyle}>
                  {typeof col.render === 'function' ? col.render(row, rowIndex) : (col.key ? row[col.key] : null)}
                </td>
              );
            })}
          </tr>
        ))}
      </tbody>
    </table>
  );

  // If noWrapper is true, return just the table (for backward compatibility)
  if (noWrapper) {
    return tableElement;
  }

  // Otherwise, wrap in tableWrapper with default styles
  const finalWrapperClassName = wrapperClassName 
    ? `${tableStyles.tableBodyScroll} ${wrapperClassName}` 
    : tableStyles.tableBodyScroll;

  return (
    <div className={tableStyles.tableWrapper}>
      <div
        ref={topScrollRef}
        className={tableStyles.tableTopScroll}
        onScroll={handleTopScroll}
      >
        <div
          style={{
            width: topScrollWidth,
            height: 1,
          }}
        />
      </div>
      <div
        ref={wrapperRef}
        className={finalWrapperClassName}
        style={wrapperStyle}
        onScroll={handleWrapperScroll}
        onMouseDown={enableDragScroll ? handleMouseDown : undefined}
        onMouseMove={enableDragScroll ? handleMouseMove : undefined}
        onMouseUp={enableDragScroll ? stopDragging : undefined}
        onMouseLeave={enableDragScroll ? stopDragging : undefined}
      >
        {tableElement}
      </div>
     
    </div>
  );
}

export default Table;


