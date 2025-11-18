import React, { useState, useRef, useEffect } from 'react';
import EmojiPicker from 'emoji-picker-react';
import styles from '../assets/scss/emojiPicker.module.scss';

const EmojiPickerComponent = ({ 
  onEmojiSelect, 
  isOpen, 
  onClose, 
  position = 'bottom-left',
  disabled = false,
  buttonRef = null
}) => {
  const pickerRef = useRef(null);
  const [pickerPosition, setPickerPosition] = useState({ top: 0, left: 0 });

  // Calculate position relative to button
  useEffect(() => {
    if (isOpen && buttonRef?.current) {
      const buttonRect = buttonRef.current.getBoundingClientRect();
      const viewportWidth = window.innerWidth;
      const viewportHeight = window.innerHeight;
      const pickerWidth = 320; // minWidth
      const pickerHeight = 350; // maxHeight
      
      let top = buttonRect.bottom + 8; // 8px gap from button
      let left = buttonRect.left;
      
      // Adjust based on position prop
      if (position === 'bottom-right') {
        left = buttonRect.right - pickerWidth;
      } else if (position === 'top-left') {
        top = buttonRect.top - pickerHeight - 8;
      } else if (position === 'top-right') {
        top = buttonRect.top - pickerHeight - 8;
        left = buttonRect.right - pickerWidth;
      }
      
      // Ensure picker stays within viewport bounds
      if (left < 8) {
        left = 8;
      } else if (left + pickerWidth > viewportWidth - 8) {
        left = viewportWidth - pickerWidth - 8;
      }
      
      if (top < 8) {
        top = buttonRect.bottom + 8; // Fallback to bottom if no space above
      } else if (top + pickerHeight > viewportHeight - 8) {
        top = buttonRect.top - pickerHeight - 8; // Fallback to top if no space below
        if (top < 8) {
          top = 8; // If still doesn't fit, position at top of viewport
        }
      }
      
      setPickerPosition({ top, left });
    }
  }, [isOpen, buttonRef, position]);

  useEffect(() => {
    const handleClickOutside = (event) => {
      if (pickerRef.current && !pickerRef.current.contains(event.target)) {
        onClose();
      }
    };

    const handleEscape = (event) => {
      if (event.key === 'Escape') {
        onClose();
      }
    };

    const handleResize = () => {
      // Recalculate position on window resize
      if (isOpen && buttonRef?.current) {
        const buttonRect = buttonRef.current.getBoundingClientRect();
        const viewportWidth = window.innerWidth;
        const viewportHeight = window.innerHeight;
        const pickerWidth = 320;
        const pickerHeight = 350;
        
        let top = buttonRect.bottom + 8;
        let left = buttonRect.left;
        
        if (position === 'bottom-right') {
          left = buttonRect.right - pickerWidth;
        } else if (position === 'top-left') {
          top = buttonRect.top - pickerHeight - 8;
        } else if (position === 'top-right') {
          top = buttonRect.top - pickerHeight - 8;
          left = buttonRect.right - pickerWidth;
        }
        
        // Ensure picker stays within viewport bounds
        if (left < 8) {
          left = 8;
        } else if (left + pickerWidth > viewportWidth - 8) {
          left = viewportWidth - pickerWidth - 8;
        }
        
        if (top < 8) {
          top = buttonRect.bottom + 8;
        } else if (top + pickerHeight > viewportHeight - 8) {
          top = buttonRect.top - pickerHeight - 8;
          if (top < 8) {
            top = 8;
          }
        }
        
        setPickerPosition({ top, left });
      }
    };

    if (isOpen) {
      // Add a small delay to prevent immediate closing when opening
      const timeoutId = setTimeout(() => {
        document.addEventListener('mousedown', handleClickOutside, true);
        document.addEventListener('keydown', handleEscape);
        window.addEventListener('resize', handleResize);
      }, 100);

      return () => {
        clearTimeout(timeoutId);
        document.removeEventListener('mousedown', handleClickOutside, true);
        document.removeEventListener('keydown', handleEscape);
        window.removeEventListener('resize', handleResize);
      };
    }
  }, [isOpen, onClose, position, buttonRef]);

  // Add custom scrollbar styles
  useEffect(() => {
    const style = document.createElement('style');
    style.textContent = `
      .emoji-picker-scroll::-webkit-scrollbar {
        width: 6px;
      }
      .emoji-picker-scroll::-webkit-scrollbar-track {
        background: #f1f5f9;
        border-radius: 3px;
      }
      .emoji-picker-scroll::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 3px;
      }
      .emoji-picker-scroll::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
      }
    `;
    document.head.appendChild(style);

    return () => {
      document.head.removeChild(style);
    };
  }, []);

  const handleEmojiClick = (emojiData) => {
   // console.log('EmojiPicker: Emoji clicked:', emojiData.emoji);
    if (onEmojiSelect) {
      onEmojiSelect(emojiData.emoji);
    } else {
     // console.error('EmojiPicker: onEmojiSelect is not defined');
    }
    onClose();
  };

  //  console.log('EmojiPicker render - isOpen:', isOpen, 'position:', position);

  if (!isOpen) return null;

  return (
    <div 
      ref={pickerRef}
      style={{
        position: 'fixed',
        top: `${pickerPosition.top}px`,
        left: `${pickerPosition.left}px`,
        zIndex: 9999,
        background: 'white',
        border: '1px solid #d1d5db',
        borderRadius: '12px',
        boxShadow: '0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04)',
        overflow: 'hidden',
        minWidth: '320px',
        maxWidth: '400px'
      }}
    >
      <div style={{
        padding: '12px',
        background: '#f9fafb',
        borderBottom: '1px solid #e5e7eb',
        fontSize: '14px',
        fontWeight: '600',
        color: '#374151'
      }}>
        Choose an emoji
      </div>
      <div 
        style={{ 
          maxHeight: '350px', 
          overflow: 'auto',
          scrollbarWidth: 'thin',
          scrollbarColor: '#cbd5e1 #f1f5f9'
        }}
        className="emoji-picker-scroll"
      >
        <EmojiPicker
          onEmojiClick={handleEmojiClick}
          width="100%"
          height={400}
          searchDisabled={false}
          skinTonesDisabled={true}
          previewConfig={{
            showPreview: false
          }}
          searchPlaceHolder="Search emojis..."
          theme="light"
          disabled={disabled}
          style={{
            border: 'none',
            boxShadow: 'none',
            fontFamily: 'inherit',
            height: 'auto',
            maxHeight: 'none'
          }}
        />
      </div>
    </div>
  );
};

export default EmojiPickerComponent;
