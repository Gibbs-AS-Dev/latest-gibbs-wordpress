import React, { useState, useEffect } from 'react';
import Header from './Header';
import Sidebar from './Sidebar';
import Footer from './Footer';
import styles from '../assets/scss/layouts/Layout.module.scss';
import '../assets/scss/layouts/Layout.scss';

const Layout = ({
  children,
  homeUrl = '/',
  sidebarOpen: initialSidebarOpen = true,
  activeMenuItem = 'dashboard',
  onMenuItemClick = null,
  menuItems = null,
  footerLinks = null,
  copyrightText = null,
}) => {

  if(!window.pagedata) {
    return children;
  }
  const { layout, sidebar, header, footer, logo, logo_transparent, logo_retina, userInfo, menuItems: wpMenuItems } = window.pagedata;
  if(!layout || layout === 'false') {
    return children;
  }
  // Check if mobile (window width <= 768px)
  const [isMobile, setIsMobile] = useState(() => {
    if (typeof window !== 'undefined') {
      return window.innerWidth <= 768;
    }
    return false;
  });

  // Initialize sidebar state - closed on mobile, open on desktop by default
  const getInitialSidebarState = () => {
    if (typeof window !== 'undefined') {
      const mobile = window.innerWidth <= 768;
      if (mobile) {
        return false; // Always closed on mobile by default
      }
      // On desktop: check localStorage first, then use initialSidebarOpen
      const savedState = localStorage.getItem('gibbs_sidebar_open');
      if (savedState !== null) {
        return savedState === 'true';
      }
      return initialSidebarOpen; // Default to true on desktop
    }
    return initialSidebarOpen;
  };

  const [sidebarOpen, setSidebarOpen] = useState(getInitialSidebarState);
  const [showSidebar, setShowSidebar] = useState(sidebar === 'true');
  const [showFooter, setShowFooter] = useState(footer === 'true');

  useEffect(() => {
    const checkMobile = () => {
      const wasMobile = isMobile;
      const nowMobile = window.innerWidth <= 768;
      setIsMobile(nowMobile);
      
      // If switching to mobile, close sidebar
      if (!wasMobile && nowMobile) {
        setSidebarOpen(false);
      }
      // If switching to desktop, restore saved state or default to open
      if (wasMobile && !nowMobile) {
        const savedState = localStorage.getItem('gibbs_sidebar_open');
        if (savedState !== null) {
          setSidebarOpen(savedState === 'true');
        } else {
          setSidebarOpen(initialSidebarOpen); // Default to true on desktop
        }
      }
    };
    
    checkMobile();
    window.addEventListener('resize', checkMobile);
    return () => window.removeEventListener('resize', checkMobile);
  }, [isMobile, initialSidebarOpen]);

  // Save sidebar state to localStorage when it changes (only on desktop)
  useEffect(() => {
    if (!isMobile) {
      localStorage.setItem('gibbs_sidebar_open', sidebarOpen.toString());
    }
  }, [sidebarOpen, isMobile]);

  const handleMenuToggle = () => {
    setSidebarOpen(!sidebarOpen);
  };

  return (
    <div className={`${styles.layout} ${showSidebar ? (sidebarOpen ? styles.sidebarOpen : styles.sidebarClosed) : styles.noSidebar}`}>
      {isMobile && showSidebar && sidebarOpen && (
        <div 
          className={styles.sidebarOverlay}
          onClick={handleMenuToggle}
        />
      )}
      <Header
        sidebarOpen={showSidebar ? sidebarOpen : false}
        showSidebar={showSidebar}
        userInfo={userInfo}
        logo={logo}
        homeUrl={homeUrl}
        onMenuToggle={isMobile && showSidebar ? handleMenuToggle : null}
      />
      
      <div className={styles.layoutBody}>
        {showSidebar && (
          <Sidebar
            sidebarOpen={sidebarOpen}
            activeMenuItem={activeMenuItem}
            onMenuItemClick={onMenuItemClick}
            menuItems={menuItems || wpMenuItems}
            logo={logo}
            homeUrl={homeUrl}
            onMenuToggle={!isMobile ? handleMenuToggle : null}
          />
        )}
        
        <main className={styles.mainContent}>
          {children}
        </main>
      </div>

      {showFooter && (
        <Footer
          sidebarOpen={showSidebar ? (sidebarOpen ? 'open' : 'collapsed') : false}
          copyrightText={copyrightText}
          links={footerLinks}
        />
      )}
    </div>
  );
};

export default Layout;

