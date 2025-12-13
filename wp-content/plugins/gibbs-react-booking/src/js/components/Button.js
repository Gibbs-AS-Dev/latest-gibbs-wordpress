import React from 'react';
import styles from '../assets/scss/button.module.scss';

/**
 * Reusable Button component with multiple variants, sizes, and states.
 * 
 * @param {string} variant - Button style variant: 'primary', 'secondary', 'danger', 'outline', 'ghost', 'link'
 * @param {string} size - Button size: 'small', 'medium', 'large'
 * @param {boolean} disabled - Whether the button is disabled
 * @param {boolean} loading - Whether the button is in loading state
 * @param {boolean} fullWidth - Whether the button should take full width
 * @param {React.ReactNode} leftIcon - Icon to display on the left side
 * @param {React.ReactNode} rightIcon - Icon to display on the right side
 * @param {string} className - Additional CSS classes
 * @param {object} style - Additional inline styles
 * @param {React.ReactNode} children - Button content
 * @param {function} onClick - Click handler
 * @param {string} type - Button type: 'button', 'submit', 'reset'
 */
function Button({
  variant = 'primary',
  size = 'medium',
  disabled = false,
  loading = false,
  fullWidth = false,
  leftIcon,
  rightIcon,
  className = '',
  style = {},
  children,
  onClick,
  type = 'button',
  ...props
}) {
  const getVariantClass = () => {
    switch (variant) {
      case 'primary':
        return styles.primary;
      case 'secondary':
        return styles.secondary;
      case 'danger':
        return styles.danger;
      case 'outline':
        return styles.outline;
      case 'ghost':
        return styles.ghost;
      case 'cancel':
        return styles.cancel;
      case 'link':
        return styles.link;
      default:
        return styles.primary;
    }
  };

  const getSizeClass = () => {
    switch (size) {
      case 'small':
        return styles.small;
      case 'medium':
        return styles.medium;
      case 'large':
        return styles.large;
      default:
        return styles.medium;
    }
  };

  const buttonClasses = [
    styles.button,
    getVariantClass(),
    getSizeClass(),
    disabled && styles.disabled,
    loading && styles.loading,
    fullWidth && styles.fullWidth,
    className
  ].filter(Boolean).join(' ');

  const handleClick = (e) => {
    if (disabled || loading) {
      e.preventDefault();
      return;
    }
    if (onClick) {
      onClick(e);
    }
  };

  return (
    <button
      type={type}
      className={buttonClasses}
      style={style}
      onClick={handleClick}
      disabled={disabled || loading}
      aria-busy={loading}
      {...props}
    >
      {loading && (
        <span className={styles.spinner} aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
            <circle cx="12" cy="12" r="10" strokeOpacity="0.25" />
            <path d="M12 2A10 10 0 0 1 22 12" strokeLinecap="round" />
          </svg>
        </span>
      )}
      {!loading && leftIcon && (
        <span className={styles.leftIcon} aria-hidden="true">
          {leftIcon}
        </span>
      )}
      {children && <span className={styles.content}>{children}</span>}
      {!loading && rightIcon && (
        <span className={styles.rightIcon} aria-hidden="true">
          {rightIcon}
        </span>
      )}
    </button>
  );
}

export default Button;

