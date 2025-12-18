import React, { useState } from 'react';
import { Ltext } from '../utils/translations';
import styles from '../assets/scss/layouts/Sidebar.module.scss';

const Sidebar = ({ 
  sidebarOpen, 
  activeMenuItem = 'dashboard',
  onMenuItemClick,
  menuItems = null,
  logo = null,
  homeUrl = '/',
  onMenuToggle = null
}) => {
  // State to track expanded menu items
  const [expandedItems, setExpandedItems] = useState(new Set());

  const [disableActiveExpanded, setDisableActiveExpanded] = useState(false);
  // Extract text content from HTML (for badge extraction, but keep HTML for rendering)
  const getTextContent = (html) => {
    if (!html) return '';
    // Create a temporary div element
    const tmp = document.createElement('div');
    tmp.innerHTML = html;
    // Get text content (strips all HTML)
    return tmp.textContent || tmp.innerText || '';
  };

  // Extract Font Awesome icon from span element in HTML
  const extractIconFromSpan = (html) => {
    if (!html) return null;
    const tmp = document.createElement('div');
    tmp.innerHTML = html;
    const span = tmp.querySelector('i[class*="fa-"], i[class*="fas"], i[class*="far"], i[class*="fab"], i[class*="fal"], i[class*="fad"]');
    if (span) {
      return span.className;
    }
    return null;
  };

  // Remove span with Font Awesome icon from HTML
  const removeIconSpanFromHtml = (html) => {
    if (!html) return html;
    const tmp = document.createElement('div');
    tmp.innerHTML = html;
    const span = tmp.querySelector('i[class*="fa-"], i[class*="fas"], i[class*="far"], i[class*="fab"], i[class*="fal"], i[class*="fad"]');
    if (span) {
      span.remove();
      return tmp.innerHTML.trim();
    }
    return html;
  };

  // Transform WordPress menu items to Sidebar format
  const transformMenuItems = (wpMenuItems) => {
    if (!wpMenuItems || !Array.isArray(wpMenuItems)) {
      return [];
    }

    // Sort by order
    const sortedItems = [...wpMenuItems].sort((a, b) => (a.order || 0) - (b.order || 0));
    
    // Get top-level items (items with parent = 0 or empty)
    const topLevelItems = sortedItems.filter(item => 
      !item.parent || item.parent === '0' || item.parent === 0
    );

    return topLevelItems.map((item) => {
      // Get children of this item
      const children = sortedItems.filter(child => 
        child.parent && (child.parent === item.ID || child.parent === String(item.ID))
      );
      const hasChildren = children.length > 0;

      // Extract icon from span element in title HTML first (priority)
      const titleHtml = item.title || '';
      let icon = '•';
      let labelHtml = titleHtml.trim();
      
      // First, try to extract Font Awesome icon from span in title HTML
      const iconFromSpan = extractIconFromSpan(titleHtml);
      

      if (iconFromSpan) {
        icon = iconFromSpan;
        // Remove the icon span from the label HTML
        labelHtml = removeIconSpanFromHtml(titleHtml).trim();
      }
      // Then try to get icon from description (if it's an emoji or icon code)
      else {
        const cleanDescription = item.description ? getTextContent(item.description).trim() : '';
        if (cleanDescription && cleanDescription.length <= 2) {
          icon = cleanDescription;
        }
        // Then try to get from classes (Font Awesome, etc.)
        else if (item.classes && Array.isArray(item.classes)) {
          const iconClass = item.classes.find(cls => 
            cls.includes('icon-') || 
            cls.includes('fa-') || 
            cls.includes('fas ') || 
            cls.includes('far ') || 
            cls.includes('fab ') ||
            cls.includes('fal ') ||
            cls.includes('fad ')
          );
          if (iconClass) {
            // Extract Font Awesome class name
            const faMatch = iconClass.match(/fa[srbld]?\s+fa-([\w-]+)/);
            if (faMatch) {
              icon = `fa-${faMatch[1]}`;
            } else {
              icon = iconClass;
            }
          }
        }
      }

      // Keep HTML in label, but extract badge from text content
      let badge = null;
      const titleText = getTextContent(labelHtml);
      const badgeMatch = titleText.match(/\((\d+)\)/);
      let label = labelHtml;
      if (badgeMatch) {
        badge = badgeMatch[1];
        // Remove badge from HTML title if it exists
        label = labelHtml.replace(/\s*\((\d+)\)\s*$/, '').trim();
      }

      // Transform children items
      const transformedChildren = children.map((childItem) => {
        const childTitleHtml = childItem.title || '';
        let childIcon = '•';
        let childLabelHtml = childTitleHtml.trim();
        
        const childIconFromSpan = extractIconFromSpan(childTitleHtml);
        if (childIconFromSpan) {
          childIcon = childIconFromSpan;
          childLabelHtml = removeIconSpanFromHtml(childTitleHtml).trim();
        } else {
          const childCleanDescription = childItem.description ? getTextContent(childItem.description).trim() : '';
          if (childCleanDescription && childCleanDescription.length <= 2) {
            childIcon = childCleanDescription;
          } else if (childItem.classes && Array.isArray(childItem.classes)) {
            const childIconClass = childItem.classes.find(cls => 
              cls.includes('icon-') || cls.includes('fa-') || cls.includes('fas ') || 
              cls.includes('far ') || cls.includes('fab ') || cls.includes('fal ') || cls.includes('fad ')
            );
            if (childIconClass) {
              const childFaMatch = childIconClass.match(/fa[srbld]?\s+fa-([\w-]+)/);
              if (childFaMatch) {
                childIcon = `fa-${childFaMatch[1]}`;
              } else {
                childIcon = childIconClass;
              }
            }
          }
        }

        const childTitleText = getTextContent(childLabelHtml);
        const childBadgeMatch = childTitleText.match(/\((\d+)\)/);
        let childLabel = childLabelHtml;
        let childBadge = null;
        if (childBadgeMatch) {
          childBadge = childBadgeMatch[1];
          childLabel = childLabelHtml.replace(/\s*\((\d+)\)\s*$/, '').trim();
        }

        return {
          id: childItem.ID || `menu-item-${childItem.order}`,
          label: childLabel,
          labelHtml: childLabel,
          icon: childIcon,
          path: childItem.url || '#',
          badge: childBadge,
          hasSubmenu: false,
          target: childItem.target || '_self',
          title: childItem.attr_title || childItem.title || '',
          classes: childItem.classes || [],
        };
      });

      return {
        id: item.ID || `menu-item-${item.order}`,
        label: label,
        labelHtml: label, // Keep HTML version
        icon: icon,
        path: item.url || '#',
        badge: badge,
        hasSubmenu: hasChildren,
        children: transformedChildren,
        target: item.target || '_self',
        title: item.attr_title || item.title || '',
        classes: item.classes || [],
      };
    });
  };

  // Default menu items (fallback)
  const defaultMenuItems = [
    { 
      id: 'dashboard', 
      label: 'Dashbord', 
      icon: '✓',
      path: '/dashboard',
      badge: null
    }
  ];

  // Use transformed WordPress menu items if available, otherwise use provided menuItems or defaults
  const items = menuItems 
    ? (Array.isArray(menuItems) && menuItems.length > 0 && menuItems[0].ID 
        ? transformMenuItems(menuItems) 
        : menuItems)
    : defaultMenuItems;

  const toggleExpanded = (itemId) => {
    setExpandedItems(prev => {
      const newSet = new Set(prev);
      if (newSet.has(itemId)) {
        newSet.delete(itemId);
      } else {
        newSet.add(itemId);
      }
      return newSet;
    });
  };

  const handleItemClick = (item) => {

    setDisableActiveExpanded(true);
    // If item has children, toggle expansion instead of navigating
    if (item.hasSubmenu && item.children && item.children.length > 0) {
      toggleExpanded(item.id);
      return;
    }

    if (onMenuItemClick) {
      onMenuItemClick(item);
    } else {
      // Default behavior - navigate to path
      if (item.path && item.path !== '#') {
        if (item.target === '_blank') {
          window.open(item.path, '_blank');
        } else {
          window.location.href = item.path;
        }
      }
    }
  };

  const getIcon = (icon) => {
    if (!icon) {
      return <span className={styles.iconText}>•</span>;
    }

    // If icon is an emoji or simple text (1-2 characters), return it
    if (typeof icon === 'string' && icon.length <= 2) {
      return <span className={styles.iconEmoji}>{icon}</span>;
    }
    
    // If icon contains Font Awesome classes (full class string from span)
    if (typeof icon === 'string' && (
      icon.includes('fa-') || 
      icon.includes('fas ') || 
      icon.includes('far ') || 
      icon.includes('fab ') ||
      icon.includes('fal ') ||
      icon.includes('fad ')
    )) {
      // Use the full class string as-is (e.g., "fas fa-home" or "far fa-user")
      return <i className={icon} />;
    }
    
    // If icon is just a Font Awesome class name (starts with fa-)
    if (typeof icon === 'string' && icon.startsWith('fa-')) {
      return <i className={`fas ${icon}`} />;
    }
    
    // Otherwise, try to render as text
    return <span className={styles.iconText}>{icon}</span>;
  };

  return (
    <aside className={`${styles.sidebar} ${sidebarOpen ? styles.open : styles.closed}`}>
      <nav className={styles.nav}>
        <div className={styles.logoContainer}>
          {logo && (
            <a href={homeUrl} className={styles.logo}>
              {typeof logo === 'string' && logo.match(/\.(jpg|jpeg|png|gif|svg|webp)$/i) ? (
                <img src={logo} alt="Gibbs.no" />
              ) : (
                <>
                  <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 2L2 7L12 12L22 7L12 2Z" fill="#1a9a94"/>
                    <path d="M2 17L12 22L22 17V12L12 17L2 12V17Z" fill="#1a9a94"/>
                  </svg>
                  {sidebarOpen && <span className={styles.logoText}>Gibbs.no</span>}
                </>
              )}
            </a>
          )}
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
        </div>
        <ul className={styles.menuList}>
          {items.map((item) => {
            let isExpanded = expandedItems.has(item.id);

            let isActive = false;
            if(item.classes.includes('current-menu-item') || item.classes.includes('current-menu-parent') && !disableActiveExpanded){
              isActive = true;
              isExpanded = true;
            }
            
            return (
              <li key={item.id} className={`${styles.menuItem} react-menu-item`}>
                <button
                  className={`${styles.menuLink} ${activeMenuItem === item.id || isActive ? styles.active : ''} ${isExpanded ? styles.expanded : ''}`}
                  onClick={() => handleItemClick(item)}
                  aria-label={Ltext(getTextContent(item.labelHtml || item.label || ''))}
                  aria-expanded={item.hasSubmenu ? isExpanded : undefined}
                >
                  <span className={styles.menuIcon}>
                    {getIcon(item.icon)}
                  </span>
                  {sidebarOpen && (
                    <>
                      <span 
                        className={`${styles.menuLabel} react-menu-label`}
                        dangerouslySetInnerHTML={{ __html: item.labelHtml || item.label || '' }}
                      />
                      {item.hasSubmenu && (
                        <svg 
                          className={`${styles.submenuArrow} ${isExpanded ? styles.rotated : ''}`}
                          width="12" 
                          height="12" 
                          viewBox="0 0 24 24" 
                          fill="none" 
                          stroke="currentColor" 
                          strokeWidth="2"
                        >
                          <polyline points="6 9 12 15 18 9"/>
                        </svg>
                      )}
                      {item.badge && (
                        <span className={styles.badge}>{item.badge}</span>
                      )}
                    </>
                  )}
                </button>
                {item.hasSubmenu && item.children && item.children.length > 0 && isExpanded && sidebarOpen && (
                  <ul className={styles.submenuList}>
                    {item.children.map((childItem) => {
                      const isActiveChild = childItem.classes.includes('current-menu-item') || childItem.classes.includes('current-menu-parent');
                      return (
                      <li key={childItem.id} className={styles.submenuItem}>
                        <button
                          className={`${styles.menuLink} ${activeMenuItem === childItem.id || isActiveChild ? styles.active : ''}`}
                          onClick={() => handleItemClick(childItem)}
                          aria-label={Ltext(getTextContent(childItem.labelHtml || childItem.label || ''))}
                        >
                          <span className={styles.menuIcon}>
                            {getIcon(childItem.icon)}
                          </span>
                          <span 
                            className={styles.menuLabel}
                            dangerouslySetInnerHTML={{ __html: childItem.labelHtml || childItem.label || '' }}
                          />
                          {childItem.badge && (
                            <span className={styles.badge}>{childItem.badge}</span>
                          )}
                        </button>
                      </li>
                      );
                    })}
                  </ul>
                )}
              </li>
            );
          })}
        </ul>
      </nav>
    </aside>
  );
};

export default Sidebar;

