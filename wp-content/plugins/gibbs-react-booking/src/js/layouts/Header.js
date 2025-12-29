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
  const [showUserDropdown, setShowUserDropdown] = useState(false);
  const [showAccountDropdown, setShowAccountDropdown] = useState(false);
  const [showProfileDropdown, setShowProfileDropdown] = useState(false);
  const { 
    title  = "", 
    top_user_name = "", 
    group_name_display = "", 
    user_avatar_url = "", 
    sub_users = {}, 
    active_group_id = "", 
    all_joined_groups_results = [], 
    groups_results = [], 
    current_user = {}, 
    parent_user_id = "", 
    post_id = "",
    display_user_email = "",
    display_user_name = "",
    sub_user_link = "",
    language_switcher = {},
    current_language = {}
  } = window.pagedata || {};

  // Build a unified user info object based on pagedata, with sane fallbacks
  const resolvedUserInfo = {
    ...userInfo,
    // Normalise accounts into { id, name, switch_group_url }
    accounts: (all_joined_groups_results || []).map((g) => ({
      id: g.id,
      name: g.group_name || g.name,
      switch_group_url: g.switch_group_url,
    })),
    users: Object.values(sub_users || {}),
  };

  //console.log(resolvedUserInfo);

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
          {current_language && current_language.flag_link && (
            <button 
              className={styles.langButton}
              onClick={() => setShowLangDropdown(!showLangDropdown)}
              aria-label="Change language"
            >
              <span className={styles.flag}><img src={current_language.flag_link} alt={current_language.language_name} width="20" height="20" /></span>
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <polyline points="6 9 12 15 18 9"/>
              </svg>
            </button>
          )}
          
          {showLangDropdown && (
            <>
              <div 
                className={styles.langOverlay}
                onClick={() => setShowLangDropdown(false)}
              />
              <div className={styles.langDropdown}>
                  {language_switcher && Object.keys(language_switcher).length > 0 && Object.values(language_switcher).map((lang, index) => (
                      <button
                        key={lang.language_code || index}
                        className={styles.langOption}
                        onClick={() => {
                          window.location.href = lang.current_page_url;
                        }}
                      >
                        <span className={styles.flag}><img src={lang.flag_link} alt={lang.language_name} width="20" height="20" /></span>
                        <span>{lang.language_name}</span>
                      </button>
                  ))}
              </div>
            </>
          )}
        </div>

        {resolvedUserInfo && (
          <div className={styles.userMenu}>
            <button
              type="button"
              className={styles.userInfo}
              onClick={() => {
                const next = !showUserDropdown;
                setShowUserDropdown(next);
                if (!next) {
                  setShowAccountDropdown(false);
                  setShowProfileDropdown(false);
                }
              }}
              aria-haspopup="true"
              aria-expanded={showUserDropdown}
            >
              <div className={styles.userText}>
                <div className={styles.userName}>{top_user_name}</div>
                {group_name_display && (
                  <div className={styles.userAccount}>{group_name_display}</div>
                )}
              </div>
              <div className={styles.userAvatar}>
                {user_avatar_url ? (
                  <img src={user_avatar_url} alt="User Avatar"/>
                ) : (
                  <svg width="32" height="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="12" cy="8" r="4" fill="#1a9a94"/>
                    <path d="M6 21v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2" stroke="#1a9a94" strokeWidth="2"/>
                  </svg>
                )}
              </div>
            </button>

            {showUserDropdown && (
              <>
                <div
                  className={styles.userOverlay}
                  onClick={() => {
                    setShowUserDropdown(false);
                    setShowAccountDropdown(false);
                    setShowProfileDropdown(false);
                  }}
                />
                <div className={styles.userDropdown}>
                  <div className={styles.userDropdownHeader}>
                    <div className={styles.userDropdownAvatar}>
                      {user_avatar_url ? (
                        <img src={user_avatar_url} alt="User Avatar"/>
                      ) : (
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                          <circle cx="12" cy="8" r="4" fill="#1a9a94"/>
                          <path d="M6 21v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2" stroke="#1a9a94" strokeWidth="2"/>
                        </svg>
                      )}
                    </div>
                    <div className={styles.userDropdownText}>
                      <div className={styles.userDropdownName}>{display_user_name}</div>
                      {display_user_email && (
                        <div className={styles.userDropdownEmail}>{display_user_email}</div>
                      )}
                    </div>
                  </div>

                  <div className={styles.userDropdownSection}>
                    <div className={styles.userDropdownLabel}>Konto</div>
                    <button
                      type="button"
                      className={styles.selectorRow}
                      onClick={() => {
                        setShowAccountDropdown(!showAccountDropdown);
                        setShowProfileDropdown(false);
                      }}
                    >
                      <div className={styles.selectorLeft}>
                        <span className={styles.selectorIcon}>👥</span>
                        <span className={styles.selectorText}>
                          {group_name_display || '--'}
                        </span>
                      </div>
                      <span className={styles.selectorChevron}>
                        {showAccountDropdown ? '▴' : '▾'}
                      </span>
                    </button>

                    {showAccountDropdown && (
                      <div className={styles.selectorOptions}>
                        {(resolvedUserInfo.accounts || [resolvedUserInfo.account])
                          .filter(Boolean)
                          .map((acc, idx) => {
                            const id = acc && acc.id ? acc.id : idx;
                            const label = acc && (acc.name || acc.group_name) ? (acc.name || acc.group_name) : String(acc);
                            const isActive = String(active_group_id) === String(id);

                            return (
                              <button
                                key={`${idx}-${id}`}
                                type="button"
                                className={styles.selectorOption}
                                data-active={isActive ? 'true' : 'false'}
                                onClick={() => {
                                  if (acc && acc.switch_group_url) {
                                    // Decode any HTML entities
                                    const txt = document.createElement("textarea");
                                    txt.innerHTML = acc.switch_group_url;
                                    const url = txt.value;
                                    window.location.href = url;
                                  }
                                }}
                              >
                                {label}
                              </button>
                            );
                          })}
                      </div>
                    )}
                  </div>

                  <div className={styles.userDropdownSection}>
                    <div className={styles.userDropdownLabelRow}>
                      <div className={styles.userDropdownLabel}>Bruker</div>
                      <button
                        type="button"
                        className={styles.userEditButton}
                        aria-label="Rediger bruker"
                        onClick={() => {
                          if(sub_user_link) {
                            window.location.href = sub_user_link;
                          }
                        }}
                      >
                        ✎
                      </button>
                    </div>

                    <button
                      type="button"
                      className={styles.selectorRow}
                      onClick={() => {
                        setShowProfileDropdown(!showProfileDropdown);
                        setShowAccountDropdown(false);
                      }}
                    >
                      <div className={styles.selectorLeft}>
                        <span className={styles.selectorIcon}>👥</span>
                        <span className={styles.selectorText}>
                          {resolvedUserInfo.name || '--'}
                        </span>
                      </div>
                      <span className={styles.selectorChevron}>
                        {showProfileDropdown ? '▴' : '▾'}
                      </span>
                    </button>

                    {showProfileDropdown && (
                      <div className={styles.selectorOptions}>
                        {(resolvedUserInfo.users || [resolvedUserInfo.name])
                          .filter(Boolean)
                          .map((user, idx) => (
                            <button
                              key={idx}
                              type="button"
                              className={styles.selectorOption}
                            >
                              {user}
                            </button>
                          ))}
                      </div>
                    )}
                  </div>

                  <div className={styles.userDropdownLinks}>
                    <button type="button" className={styles.userDropdownLink}>
                      Min profil
                    </button>
                    <button type="button" className={styles.userDropdownLink}>
                      Brukerverifisering
                    </button>
                    <button type="button" className={styles.userDropdownLinkLogout}>
                      Logg ut
                    </button>
                  </div>
                </div>
              </>
            )}
          </div>
        )}
      </div>
    </header>
  );
};

export default Header;

