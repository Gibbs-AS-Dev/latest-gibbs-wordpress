import React from 'react';
import styles from '../assets/scss/tabs.module.scss';

/**
 * Reusable Tabs component
 * 
 * @param {Array} tabs - Array of tab objects with { id, label, count }
 * @param {string} activeTab - Currently active tab ID
 * @param {function} onTabChange - Callback when tab changes
 * @param {string} className - Additional CSS classes
 */
function Tabs({ tabs = [], activeTab, onTabChange, className = '' }) {
  const handleTabClick = (tabId) => {
    if (onTabChange && tabId !== activeTab) {
      onTabChange(tabId);
    }
  };

  return (
    <div className={`${styles.tabsContainer} ${className}`}>
      <ul className={styles.tabsList}>
        {tabs.map((tab) => (
          <li
            key={tab.id}
            className={`${styles.tabItem} ${activeTab === tab.id ? styles.active : ''}`}
            onClick={() => handleTabClick(tab.id)}
            role="button"
            tabIndex={0}
            onKeyDown={(e) => {
              if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                handleTabClick(tab.id);
              }
            }}
            aria-selected={activeTab === tab.id}
          >
            <span className={styles.tabLabel}>{tab.label}</span>
            {tab.count !== undefined && tab.count !== null && tab.count > 0 && activeTab === tab.id && (
              <span className={styles.tabCount}>({tab.count})</span>
            )}
          </li>
        ))}
      </ul>
    </div>
  );
}

export default Tabs;

