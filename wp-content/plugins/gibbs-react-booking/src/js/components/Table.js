import React from 'react';
import tableStyles from '../assets/scss/table.module.scss';

/**
 * Reusable Table component with built-in table module styles.
 * All CSS from table.module.scss is applied by default.
 */
function Table({
  columns = [],
  data = [],
  getRowKey,
  tableClassName,
  tableStyle,
  rowStyle,
  wrapperClassName,
  wrapperStyle,
  noWrapper = false,
}) {
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

  const tableElement = (
    <table className={finalTableClassName} style={tableStyle}>
      <thead>
        <tr>
          {columns.map((col, idx) => (
            <th key={col.key || idx} className={col.thClassName} style={col.thStyle}>
              {col.header}
            </th>
          ))}
        </tr>
      </thead>
      <tbody>
        {data.map((row, rowIndex) => (
          <tr key={resolveRowKey(row, rowIndex)} style={typeof rowStyle === 'function' ? rowStyle(row, rowIndex) : rowStyle}>
            {columns.map((col, colIndex) => (
              <td key={(col.key || colIndex) + '-' + rowIndex} className={col.tdClassName} style={col.tdStyle}>
                {typeof col.render === 'function' ? col.render(row, rowIndex) : (col.key ? row[col.key] : null)}
              </td>
            ))}
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
    ? `${tableStyles.tableWrapper} ${wrapperClassName}` 
    : tableStyles.tableWrapper;

  return (
    <div className={finalWrapperClassName} style={wrapperStyle}>
      {tableElement}
    </div>
  );
}

export default Table;


