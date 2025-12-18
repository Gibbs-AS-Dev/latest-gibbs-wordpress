import React from 'react';
import { Ltext } from '../utils/translations';
import styles from '../assets/scss/layouts/Footer.module.scss';

const Footer = ({ 
  sidebarOpen,
  copyrightText = null,
  links = null
}) => {
  const currentYear = new Date().getFullYear();
  
  const defaultLinks = [
    { label: 'Terms of Service', path: '/terms' },
    { label: 'Privacy Policy', path: '/privacy' },
    { label: 'Contact', path: '/contact' }
  ];

  const footerLinks = links || defaultLinks;

  const getFooterClassName = () => {
    if (sidebarOpen === false) {
      return `${styles.footer} ${styles.withoutSidebar}`;
    }
    const baseClass = `${styles.footer} ${styles.withSidebar}`;
    return sidebarOpen === 'collapsed' ? `${baseClass} ${styles.collapsed}` : baseClass;
  };

  return (
    <footer className={getFooterClassName()}>
      <div className={styles.footerContent}>
        <div className={styles.footerLeft}>
          {copyrightText ? (
            <p className={styles.copyright}>{copyrightText}</p>
          ) : (
            <p className={styles.copyright}>
              © {currentYear} Gibbs.no. {Ltext("All rights reserved")}.
            </p>
          )}
        </div>
        <div className={styles.footerRight}>
          <nav className={styles.footerNav}>
            {footerLinks.map((link, index) => (
              <React.Fragment key={link.path || index}>
                <a 
                  href={link.path} 
                  className={styles.footerLink}
                  onClick={(e) => {
                    if (link.onClick) {
                      e.preventDefault();
                      link.onClick();
                    }
                  }}
                >
                  {Ltext(link.label)}
                </a>
                {index < footerLinks.length - 1 && (
                  <span className={styles.footerSeparator}>|</span>
                )}
              </React.Fragment>
            ))}
          </nav>
        </div>
      </div>
    </footer>
  );
};

export default Footer;

