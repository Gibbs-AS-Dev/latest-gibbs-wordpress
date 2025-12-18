import React, { useState, useEffect } from 'react';
import { Ltext, getLanguage, setLanguage } from '../utils/translations';
import styles from '../assets/scss/layouts/Header.module.scss';

const Header = ({ 
  sidebarOpen = false,
  showSidebar = false,
  userInfo = { name: 'K Gibbs', account: 'Demo Enebakk' },
  logo = null,
  homeUrl = '/',
  onMenuToggle = null
}) => {
  const [currentLang, setCurrentLang] = useState(getLanguage());
  const [showLangDropdown, setShowLangDropdown] = useState(false);

  const { title  = "" } = window.pagedata;

  useEffect(() => {
    const handleLanguageChange = () => {
      setCurrentLang(getLanguage());
    };
    window.addEventListener('rmpLanguageChanged', handleLanguageChange);
    return () => window.removeEventListener('rmpLanguageChanged', handleLanguageChange);
  }, []);

  const handleLanguageSelect = (lang) => {
    setLanguage(lang);
    setShowLangDropdown(false);
  };

  const getLanguageFlag = (lang) => {
    switch(lang) {
      case 'no':
      case 'nb':
      case 'nn':
        return '🇳🇴';
      case 'en':
      default:
        return '🇬🇧';
    }
  };

  const headerClass = `${styles.header} ${
    showSidebar 
      ? (sidebarOpen ? styles.sidebarOpen : styles.sidebarClosed)
      : styles.noSidebar
  }`;

  return (
    <header className={headerClass}>
      <div className={styles.headerLeft}>
        {onMenuToggle && (
          <button 
            className={styles.menuToggle}
            onClick={onMenuToggle}
            aria-label="Toggle menu"
          >
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              {sidebarOpen ? (
                <>
                  <line x1="18" y1="6" x2="6" y2="18"/>
                  <line x1="6" y1="6" x2="18" y2="18"/>
                </>
              ) : (
                <>
                  <line x1="3" y1="6" x2="21" y2="6"/>
                  <line x1="3" y1="12" x2="21" y2="12"/>
                  <line x1="3" y1="18" x2="21" y2="18"/>
                </>
              )}
            </svg>
          </button>
        )}
        <h1 className={styles.pageTitle}>{Ltext(title)}</h1>
      </div>

      {/* <div className={styles.headerLeft}>
        
      </div> */}

      <div className={styles.headerRight}>
        <div className={styles.languageSwitcher}>
          <button 
            className={styles.langButton}
            onClick={() => setShowLangDropdown(!showLangDropdown)}
            aria-label="Change language"
          >
            <span className={styles.flag}>{getLanguageFlag(currentLang)}</span>
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <polyline points="6 9 12 15 18 9"/>
            </svg>
          </button>
          
          {showLangDropdown && (
            <>
              <div 
                className={styles.langOverlay}
                onClick={() => setShowLangDropdown(false)}
              />
              <div className={styles.langDropdown}>
                <button
                  className={styles.langOption}
                  onClick={() => handleLanguageSelect('no')}
                >
                  <span className={styles.flag}>🇳🇴</span>
                  <span>Norsk</span>
                </button>
                <button
                  className={styles.langOption}
                  onClick={() => handleLanguageSelect('en')}
                >
                  <span className={styles.flag}>🇬🇧</span>
                  <span>English</span>
                </button>
              </div>
            </>
          )}
        </div>

        {userInfo && (
          <div className={styles.userInfo}>
            <div className={styles.userText}>
              <div className={styles.userName}>{userInfo.name || 'User'}</div>
              {userInfo.account && (
                <div className={styles.userAccount}>{userInfo.account}</div>
              )}
            </div>
            <div className={styles.userAvatar}>
              <svg width="32" height="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="12" cy="8" r="4" fill="#1a9a94"/>
                <path d="M6 21v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2" stroke="#1a9a94" strokeWidth="2"/>
              </svg>
            </div>
          </div>
        )}
      </div>
    </header>
  );
};

export default Header;

