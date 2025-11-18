import React, { useRef, useEffect } from 'react';
import EmojiPicker from 'emoji-picker-react';
import styles from '../assets/scss/emojiPicker.module.scss';

const ModernEmojiPicker = ({ 
  onEmojiSelect, 
  isOpen, 
  onClose, 
  disabled = false
}) => {
  const pickerRef = useRef(null);

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

    if (isOpen) {
      // Add a small delay to prevent immediate closing when opening
      const timeoutId = setTimeout(() => {
        document.addEventListener('mousedown', handleClickOutside, true);
        document.addEventListener('keydown', handleEscape);
      }, 100);

      return () => {
        clearTimeout(timeoutId);
        document.removeEventListener('mousedown', handleClickOutside, true);
        document.removeEventListener('keydown', handleEscape);
      };
    }
  }, [isOpen, onClose]);

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
    //console.log('EmojiPicker: Emoji clicked:', emojiData.emoji);
    if (onEmojiSelect) {
      onEmojiSelect(emojiData.emoji);
    } else {
      //console.error('EmojiPicker: onEmojiSelect is not defined');
    }
    onClose();
  };

  //console.log('EmojiPicker render - isOpen:', isOpen);

  if (!isOpen) return null;

  return (
    <div 
      ref={pickerRef}
      style={{
        position: 'fixed',
        top: '50%',
        left: '50%',
        transform: 'translate(-50%, -50%)',
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

export default ModernEmojiPicker;
