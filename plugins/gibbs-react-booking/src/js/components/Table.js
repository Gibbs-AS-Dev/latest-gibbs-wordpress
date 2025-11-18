import React from 'react';

/**
 * Reusable Table component.
 * Styling is controlled by the caller via class names passed in props.
 */
function Table({
  columns = [],
  data = [],
  getRowKey,
  tableClassName,
  tableStyle,
  rowStyle,
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

  return (
    <table className={tableClassName} style={tableStyle}>
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
}

export default Table;


