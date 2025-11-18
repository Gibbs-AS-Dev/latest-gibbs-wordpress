import React, { useEffect, useRef, useState } from 'react';
import ReactQuill from 'react-quill';
import 'react-quill/dist/quill.snow.css';
import ModernEmojiPicker from './ModernEmojiPicker';
import styles from '../assets/scss/quillEditor.module.scss';

// Standard Quill configuration without custom DIV blots

const QuillEditor = ({
  value = '',
  onChange,
  placeholder = '',
  disabled = false,
  height = '200px',
  toolbar = 'full',
  className = '',
  error = false,
  showEmojiPicker = true,
  onRef = null
}) => {
  const quillRef = useRef(null);
  const emojiButtonRef = useRef(null);
  const [isMounted, setIsMounted] = useState(false);
  const [showEmojiPickerState, setShowEmojiPickerState] = useState(false);
  const [showHtmlModal, setShowHtmlModal] = useState(false);
  const [htmlContent, setHtmlContent] = useState('');

  // Expose methods to parent component
  React.useImperativeHandle(onRef, () => ({
    insertTextAtCursor: (text) => {
      const editor = quillRef.current?.getEditor();
      if (editor) {
        editor.focus();
        const range = editor.getSelection();
        if (range) {
          editor.insertText(range.index, text);
          editor.setSelection(range.index + text.length);
        } else {
          // If no selection, insert at the end
          const length = editor.getLength();
          editor.insertText(length - 1, text);
          editor.setSelection(length + text.length - 1);
        }
      }
    }
  }));

  // Ensure component is mounted before rendering Quill
  useEffect(() => {
    setIsMounted(true);
  }, []);

  // (moved to module scope) Block blot registration occurs before editor mounts


  const toggleEmojiPicker = () => {
   // console.log('Toggle emoji picker clicked', !showEmojiPickerState);
   // console.log('showEmojiPicker prop:', showEmojiPicker);
    setShowEmojiPickerState(!showEmojiPickerState);
  };

  const toggleHtmlModal = () => {
   // console.log('HTML button clicked, current state:', showHtmlModal);
    
    if (!showHtmlModal) {
      // Opening modal - populate with current editor content
      const editor = quillRef.current?.getEditor();
      if (editor) {
        const currentContent = editor.root.innerHTML;
       // console.log('Current editor content:', currentContent);
        setHtmlContent(currentContent);
      }
    } else {
      // Closing modal - clear content
      setHtmlContent('');
    }
    
    setShowHtmlModal(!showHtmlModal);
  };

  const decodeHtmlEntities = (html) => {
    const textarea = document.createElement('textarea');
    textarea.innerHTML = html;
    return textarea.value;
  };

  const handleHtmlInsert = () => {
    const editor = quillRef.current?.getEditor();
    if (editor && htmlContent.trim()) {
      // Decode any HTML entities
      const decodedHtml = decodeHtmlEntities(htmlContent);
  
      // Use Quill's API to replace content safely
      editor.focus();
      editor.setContents([]); // Clear existing content
      editor.clipboard.dangerouslyPasteHTML(0, decodedHtml); // Insert new HTML
  
      // Move cursor to the end
      setTimeout(() => {
        const newLength = editor.getLength();
        editor.setSelection(newLength - 1);
      }, 0);
  
      // Close modal
      setShowHtmlModal(false);
      setHtmlContent('');
    }
  };


  // Toolbar configurations
  const toolbarConfigs = {
    full: [
      [{ 'header': [1, 2, 3, 4, 5, 6, false] }],
      ['bold', 'italic', 'underline', 'strike'],
      [{ 'color': [] }, { 'background': [] }],
      [{ 'list': 'ordered'}, { 'list': 'bullet' }],
      [{ 'indent': '-1'}, { 'indent': '+1' }],
      [{ 'align': [] }],
      ['link'],
      ['clean']
    ],
    simple: [
      ['bold', 'italic', 'underline'],
      [{ 'list': 'ordered'}, { 'list': 'bullet' }],
      ['link'],
      ['clean']
    ],
    minimal: [
      ['bold', 'italic'],
      ['clean']
    ]
  };

  const modules = {
    toolbar: {
      container: toolbarConfigs[toolbar] || toolbarConfigs.full,
    },
    clipboard: {
      matchVisual: false,
    }
  };

  const formats = [
    'header', 'font', 'size',
    'bold', 'italic', 'underline', 'strike', 'blockquote',
    'list', 'bullet', 'indent',
    'link', 'color', 'background',
    'align', 'direction',
    'script', 'code', 'code-block',
    'formula', 'image', 'video',
    'table', 'tr', 'td', 'th', 'tbody', 'thead', 'tfoot',
    'span', 'p', 'br', 'hr',
    'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
    'strong', 'em', 'u', 's', 'del', 'ins',
    'sub', 'sup', 'small', 'mark',
    'a', 'img', 'iframe', 'embed', 'object',
    'ul', 'ol', 'li', 'dl', 'dt', 'dd',
    'pre', 'code', 'kbd', 'samp', 'var',
    'abbr', 'acronym', 'address', 'bdo', 'big', 'cite',
    'dfn', 'q', 'samp', 'small', 'tt', 'var',
    'fieldset', 'legend', 'label', 'input', 'textarea', 'select', 'option',
    'button', 'form', 'optgroup',
    'article', 'aside', 'details', 'figcaption', 'figure',
    'footer', 'header', 'main', 'nav', 'section', 'summary',
    'time', 'mark', 'ruby', 'rt', 'rp',
    'canvas', 'svg', 'math', 'noscript'
  ];

  const handleChange = (content, delta, source, editor) => {
    if (onChange) {
      onChange(content);
    }
  };
  

  const handleEmojiSelect = (emoji) => {
    const editor = quillRef.current?.getEditor();
    if (editor) {
      // Focus the editor first
      editor.focus();
      
      const range = editor.getSelection();
      if (range) {
        editor.insertText(range.index, emoji);
        editor.setSelection(range.index + emoji.length);
      } else {
        // If no selection, insert at the end
        const length = editor.getLength();
        editor.insertText(length - 1, emoji);
        editor.setSelection(length + emoji.length - 1);
      }
      
      // Prevent height changes by maintaining scroll position
      const scrollContainer = editor.scrollingContainer;
      if (scrollContainer) {
        const currentScrollTop = scrollContainer.scrollTop;
        // Force a reflow to maintain height
        setTimeout(() => {
          scrollContainer.scrollTop = currentScrollTop;
          editor.focus();
        }, 0);
      } else {
        // Fallback: ensure editor stays focused
        setTimeout(() => {
          editor.focus();
        }, 100);
      }
    }
  };

  // Don't render Quill on server side or before component is mounted
  if (!isMounted) {
    return (
      <div 
        className={`${styles.quillEditor} ${className} ${error ? styles.error : ''}`}
        style={{ height }}
      >
        <div className={styles.loading}>Loading editor...</div>
      </div>
    );
  }

  return (
    <>
      <div className={`${styles.quillEditor} ${className} ${error ? styles.error : ''}`}>
        <ReactQuill
          ref={quillRef}
          theme="snow"
          value={value}
          onChange={handleChange}
          placeholder={placeholder}
          readOnly={disabled}
          modules={modules}
          formats={formats}
          style={{ 
            height: height,
            maxHeight: height,
            overflow: 'hidden'
          }}
          className={styles.quillContainer}
        />
        {showEmojiPicker && (
          <button
            ref={emojiButtonRef}
            type="button"
            onClick={toggleEmojiPicker}
            disabled={disabled}
            title="Insert emoji"
            style={{ 
              position: 'absolute', 
              top: '12px', 
              right: '12px', 
              zIndex: 10,
              background: showEmojiPickerState ? '#1a9a94' : '#f9fafb',
              border: `1px solid ${showEmojiPickerState ? '#1a9a94' : '#d1d5db'}`,
              borderRadius: '6px',
              padding: '8px 10px',
              fontSize: '16px',
              cursor: 'pointer',
              color: showEmojiPickerState ? 'white' : '#374151',
              transition: 'all 0.2s ease',
              boxShadow: showEmojiPickerState ? '0 2px 4px rgba(26, 154, 148, 0.2)' : '0 1px 2px rgba(0, 0, 0, 0.05)',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              minWidth: '36px',
              minHeight: '36px'
            }}
            onMouseEnter={(e) => {
              if (!showEmojiPickerState) {
                e.target.style.background = '#1a9a94';
                e.target.style.color = 'white';
                e.target.style.borderColor = '#1a9a94';
              }
            }}
            onMouseLeave={(e) => {
              if (!showEmojiPickerState) {
                e.target.style.background = '#f9fafb';
                e.target.style.color = '#374151';
                e.target.style.borderColor = '#d1d5db';
              }
            }}
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
      

      {/* HTML Insertion Modal */}
      {showHtmlModal && (
        <div
          style={{
            position: 'fixed',
            top: 0,
            left: 0,
            right: 0,
            bottom: 0,
            backgroundColor: 'rgba(0, 0, 0, 0.5)',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            zIndex: 1000
          }}
          onClick={(e) => {
            if (e.target === e.currentTarget) {
              setShowHtmlModal(false);
              setHtmlContent('');
            }
          }}
        >
          <div
            style={{
              backgroundColor: 'white',
              borderRadius: '8px',
              padding: '24px',
              width: '90%',
              maxWidth: '600px',
              maxHeight: '80vh',
              overflow: 'auto',
              boxShadow: '0 10px 25px rgba(0, 0, 0, 0.2)'
            }}
          >
            <h3 style={{ margin: '0 0 16px 0', fontSize: '18px', fontWeight: '600' }}>
              Insert HTML
            </h3>
            
            <div style={{ marginBottom: '16px' }}>
              <label
                htmlFor="htmlContent"
                style={{
                  display: 'block',
                  marginBottom: '8px',
                  fontSize: '14px',
                  fontWeight: '500',
                  color: '#374151'
                }}
              >
                HTML Content:
              </label>
              <textarea
                id="htmlContent"
                value={htmlContent}
                onChange={(e) => setHtmlContent(e.target.value)}
                placeholder="Enter your HTML code here..."
                disabled={disabled}
                style={{
                  width: '100%',
                  minHeight: '200px',
                  padding: '12px',
                  border: '1px solid #d1d5db',
                  borderRadius: '6px',
                  fontSize: '14px',
                  fontFamily: 'monospace',
                  resize: 'vertical',
                  outline: 'none'
                }}
                onFocus={(e) => {
                  e.target.style.borderColor = '#1a9a94';
                }}
                onBlur={(e) => {
                  e.target.style.borderColor = '#d1d5db';
                }}
              />
            </div>

            <div style={{ marginBottom: '16px' }}>
              <h4 style={{ margin: '0 0 8px 0', fontSize: '14px', fontWeight: '500', color: '#374151' }}>
                Preview:
              </h4>
              <div
                style={{
                  border: '1px solid #e5e7eb',
                  borderRadius: '6px',
                  padding: '12px',
                  minHeight: '100px',
                  backgroundColor: '#f9fafb',
                  fontSize: '14px'
                }}
                dangerouslySetInnerHTML={{ __html: htmlContent || '<em>Enter HTML to see preview...</em>' }}
              />
            </div>

            <div style={{ display: 'flex', gap: '12px', justifyContent: 'flex-end' }}>
              <button
                type="button"
                onClick={() => {
                  setShowHtmlModal(false);
                  setHtmlContent('');
                }}
                disabled={disabled}
                style={{
                  padding: '8px 16px',
                  border: '1px solid #d1d5db',
                  borderRadius: '6px',
                  backgroundColor: 'white',
                  color: '#374151',
                  cursor: 'pointer',
                  fontSize: '14px',
                  fontWeight: '500',
                  transition: 'all 0.2s'
                }}
                onMouseEnter={(e) => {
                  e.target.style.backgroundColor = '#f9fafb';
                }}
                onMouseLeave={(e) => {
                  e.target.style.backgroundColor = 'white';
                }}
              >
                Cancel
              </button>
              <button
                type="button"
                onClick={handleHtmlInsert}
                disabled={disabled || !htmlContent.trim()}
                style={{
                  padding: '8px 16px',
                  border: 'none',
                  borderRadius: '6px',
                  backgroundColor: htmlContent.trim() ? '#1a9a94' : '#9ca3af',
                  color: 'white',
                  cursor: htmlContent.trim() ? 'pointer' : 'not-allowed',
                  fontSize: '14px',
                  fontWeight: '500',
                  transition: 'all 0.2s'
                }}
                onMouseEnter={(e) => {
                  if (htmlContent.trim()) {
                    e.target.style.backgroundColor = '#158a85';
                  }
                }}
                onMouseLeave={(e) => {
                  if (htmlContent.trim()) {
                    e.target.style.backgroundColor = '#1a9a94';
                  }
                }}
              >
                Insert HTML
              </button>
            </div>
          </div>
        </div>
      )}
    </>
  );
};

export default QuillEditor;
