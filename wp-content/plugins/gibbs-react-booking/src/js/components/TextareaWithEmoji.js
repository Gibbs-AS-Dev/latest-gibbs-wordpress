import React, { useRef, useState, useEffect } from 'react';
import ModernEmojiPicker from './ModernEmojiPicker';
import styles from '../assets/scss/textareaWithEmoji.module.scss';

const TextareaWithEmoji = ({
  value = '',
  onChange,
  placeholder = '',
  disabled = false,
  rows = 4,
  className = '',
  error = false,
  showEmojiPicker = true,
  height = '200px',
  fontFamily = 'monospace',
  onRef = null
}) => {
  const textareaRef = useRef(null);
  const [showEmojiPickerState, setShowEmojiPickerState] = useState(false);

  // Expose methods to parent component
  React.useImperativeHandle(onRef, () => ({
    insertTextAtCursor: (text) => {
      if (textareaRef.current) {
        textareaRef.current.focus();
        const start = textareaRef.current.selectionStart || 0;
        const end = textareaRef.current.selectionEnd || 0;
        const newContent = value.substring(0, start) + text + value.substring(end);
        
        // Update the value
        if (onChange) {
          onChange(newContent);
        }
        
        // Set cursor position after the inserted text
        setTimeout(() => {
          if (textareaRef.current) {
            textareaRef.current.focus();
            const newPosition = start + text.length;
            textareaRef.current.setSelectionRange(newPosition, newPosition);
          }
        }, 10);
      }
    }
  }));

  const toggleEmojiPicker = () => {
    //console.log('Toggle emoji picker clicked', !showEmojiPickerState);
    setShowEmojiPickerState(!showEmojiPickerState);
  };

  const handleEmojiSelect = (emoji) => {
    //console.log('Emoji selected:', emoji);
    
    if (textareaRef.current) {
      const textarea = textareaRef.current;
      
      // Focus the textarea first
      textarea.focus();
      
      const start = textarea.selectionStart || 0;
      const end = textarea.selectionEnd || 0;
      
      const newContent = value.substring(0, start) + emoji + value.substring(end);
      
      // Update the value
      if (onChange) {
        onChange(newContent);
      }
      
      // Set cursor position after the emoji
      setTimeout(() => {
        if (textareaRef.current) {
          textareaRef.current.focus();
          const newPosition = start + emoji.length;
          textareaRef.current.setSelectionRange(newPosition, newPosition);
        }
      }, 10);
    }
    
    // Close the emoji picker
    setShowEmojiPickerState(false);
  };

  const handleChange = (e) => {
    if (onChange) {
      onChange(e.target.value);
    }
  };

  const handleKeyDown = (e) => {
    // Close emoji picker on Escape
    if (e.key === 'Escape' && showEmojiPickerState) {
      setShowEmojiPickerState(false);
    }
  };

  // Let EmojiPicker handle its own click outside detection
  // No need for duplicate logic here

  return (
    <>
      <div className={`${styles.textareaContainer} ${className} ${error ? styles.error : ''}`}>
        <textarea
          ref={textareaRef}
          value={value}
          onChange={handleChange}
          onKeyDown={handleKeyDown}
          placeholder={placeholder}
          disabled={disabled}
          rows={rows}
          className={`${styles.textarea} ${fontFamily === 'monospace' ? styles.monospace : ''}`}
          style={{ 
            height: height,
            fontFamily: fontFamily === 'monospace' ? 'Monaco, Menlo, Ubuntu Mono, monospace' : 'inherit'
          }}
        />
        {showEmojiPicker && (
          <button
            type="button"
            onClick={toggleEmojiPicker}
            disabled={disabled}
            title="Insert emoji"
            className={`${styles.emojiButton} ${showEmojiPickerState ? styles.emojiButtonActive : ''}`}
          >
            😀
          </button>
        )}
        {showEmojiPicker && showEmojiPickerState && (
            <ModernEmojiPicker
                onEmojiSelect={handleEmojiSelect}
                isOpen={showEmojiPickerState}
                onClose={() => setShowEmojiPickerState(false)}
                disabled={disabled}
            />
        )}
      </div>
      
    </>
  );
};

export default TextareaWithEmoji;
