import React, { useState } from 'react';
import Table from '../components/Table';
import Modal from '../components/Modal';
import Pagination from '../components/Pagination';
import QuillEditor from '../components/QuillEditor';
import TextareaWithEmoji from '../components/TextareaWithEmoji';
import LanguageSwitcher from '../components/LanguageSwitcher';
import EmojiPickerComponent from '../components/EmojiPicker';
import ModernEmojiPicker from '../components/ModernEmojiPicker';
import Button from '../components/Button';

const ComponentGallery = () => {
  const [currentPage, setCurrentPage] = useState(1);
  const [modalOpen, setModalOpen] = useState(false);
  const [quillValue, setQuillValue] = useState('<p>Hello <strong>World</strong>!</p>');
  const [textareaValue, setTextareaValue] = useState('');
  const [emojiPickerOpen, setEmojiPickerOpen] = useState(false);
  const [modernEmojiPickerOpen, setModernEmojiPickerOpen] = useState(false);
  const [emojiButtonRef, setEmojiButtonRef] = useState(null);
  const [buttonLoading, setButtonLoading] = useState(false);

  // Sample data for Table component
  const tableColumns = [
    { key: 'id', header: 'ID', thClassName: 'text-left', tdClassName: 'text-left' },
    { key: 'name', header: 'Name', thClassName: 'text-left', tdClassName: 'text-left' },
    { key: 'email', header: 'Email', thClassName: 'text-left', tdClassName: 'text-left' },
    { 
      key: 'status', 
      header: 'Status', 
      thClassName: 'text-center', 
      tdClassName: 'text-center',
      render: (row) => (
        <span style={{ 
          padding: '4px 8px', 
          borderRadius: '4px', 
          backgroundColor: row.status === 'Active' ? '#d1fae5' : '#fee2e2',
          color: row.status === 'Active' ? '#065f46' : '#991b1b',
          fontSize: '12px',
          fontWeight: '500'
        }}>
          {row.status}
        </span>
      )
    }
  ];

  const tableData = [
    { id: 1, name: 'John Doe', email: 'john@example.com', status: 'Active' },
    { id: 2, name: 'Jane Smith', email: 'jane@example.com', status: 'Inactive' },
    { id: 3, name: 'Bob Johnson', email: 'bob@example.com', status: 'Active' },
  ];

  const components = [
    {
      name: 'Button',
      description: 'A versatile button component with multiple variants, sizes, loading states, and icon support.',
      component: (
        <div style={{ display: 'flex', flexDirection: 'column', gap: '20px' }}>
          {/* Variants */}
          <div>
            <h4 style={{ marginBottom: '12px', fontSize: '14px', fontWeight: '600', color: '#374151' }}>Variants</h4>
            <div style={{ display: 'flex', flexWrap: 'wrap', gap: '8px' }}>
              <Button variant="primary">Primary</Button>
              <Button variant="secondary">Secondary</Button>
              <Button variant="danger">Danger</Button>
              <Button variant="outline">Outline</Button>
              <Button variant="ghost">Ghost</Button>
              <Button variant="link">Link</Button>
            </div>
          </div>

          {/* Sizes */}
          <div>
            <h4 style={{ marginBottom: '12px', fontSize: '14px', fontWeight: '600', color: '#374151' }}>Sizes</h4>
            <div style={{ display: 'flex', flexWrap: 'wrap', gap: '8px', alignItems: 'center' }}>
              <Button size="small">Small</Button>
              <Button size="medium">Medium</Button>
              <Button size="large">Large</Button>
            </div>
          </div>

          {/* With Icons */}
          <div>
            <h4 style={{ marginBottom: '12px', fontSize: '14px', fontWeight: '600', color: '#374151' }}>With Icons</h4>
            <div style={{ display: 'flex', flexWrap: 'wrap', gap: '8px' }}>
              <Button
                leftIcon={
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <path d="M12 5v14M5 12h14" />
                  </svg>
                }
              >
                Add Item
              </Button>
              <Button
                rightIcon={
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <path d="M5 12h14M12 5l7 7-7 7" />
                  </svg>
                }
              >
                Continue
              </Button>
            </div>
          </div>

          {/* States */}
          <div>
            <h4 style={{ marginBottom: '12px', fontSize: '14px', fontWeight: '600', color: '#374151' }}>States</h4>
            <div style={{ display: 'flex', flexWrap: 'wrap', gap: '8px' }}>
              <Button>Normal</Button>
              <Button disabled>Disabled</Button>
              <Button loading={buttonLoading} onClick={() => {
                setButtonLoading(true);
                setTimeout(() => setButtonLoading(false), 2000);
              }}>
                {buttonLoading ? 'Loading...' : 'Click to Load'}
              </Button>
            </div>
          </div>

          {/* Full Width */}
          <div>
            <h4 style={{ marginBottom: '12px', fontSize: '14px', fontWeight: '600', color: '#374151' }}>Full Width</h4>
            <Button fullWidth>Full Width Button</Button>
          </div>
        </div>
      ),
      code: `import Button from './components/Button';

// Basic usage
<Button variant="primary" size="medium">Click Me</Button>

// With icons
<Button
  leftIcon={<PlusIcon />}
  rightIcon={<ArrowIcon />}
>
  Add Item
</Button>

// Loading state
<Button loading={isLoading}>Submit</Button>

// Disabled state
<Button disabled>Disabled</Button>

// Full width
<Button fullWidth>Full Width</Button>

// Variants: 'primary', 'secondary', 'danger', 'outline', 'ghost', 'link'
// Sizes: 'small', 'medium', 'large'`
    },
    {
      name: 'Table',
      description: 'A reusable table component with customizable columns and data rendering.',
      component: (
        <Table
          columns={tableColumns}
          data={tableData}
          getRowKey={(row) => row.id}
          tableClassName="gibbs-table"
        />
      ),
      code: `import Table from './components/Table';

<Table
  columns={[
    { key: 'id', header: 'ID' },
    { key: 'name', header: 'Name' },
    { key: 'email', header: 'Email' }
  ]}
  data={[
    { id: 1, name: 'John Doe', email: 'john@example.com' }
  ]}
  getRowKey={(row) => row.id}
/>`
    },
    {
      name: 'Modal',
      description: 'A flexible modal component with customizable size, header, body, and footer.',
      component: (
        <div>
          <button
            onClick={() => setModalOpen(true)}
            style={{
              padding: '8px 16px',
              backgroundColor: '#1a9a94',
              color: 'white',
              border: 'none',
              borderRadius: '6px',
              cursor: 'pointer',
              fontSize: '14px',
              fontWeight: '500'
            }}
          >
            Open Modal
          </button>
          <Modal
            isOpen={modalOpen}
            onClose={() => setModalOpen(false)}
            title="Example Modal"
            size="medium"
            footer={
              <div style={{ display: 'flex', gap: '8px', justifyContent: 'flex-end' }}>
                <button
                  onClick={() => setModalOpen(false)}
                  style={{
                    padding: '8px 16px',
                    border: '1px solid #d1d5db',
                    borderRadius: '6px',
                    backgroundColor: 'white',
                    cursor: 'pointer'
                  }}
                >
                  Cancel
                </button>
                <button
                  onClick={() => setModalOpen(false)}
                  style={{
                    padding: '8px 16px',
                    border: 'none',
                    borderRadius: '6px',
                    backgroundColor: '#1a9a94',
                    color: 'white',
                    cursor: 'pointer'
                  }}
                >
                  Save
                </button>
              </div>
            }
          >
            <p>This is a modal component example. You can customize the content, size, and footer.</p>
          </Modal>
        </div>
      ),
      code: `import Modal from './components/Modal';

<Modal
  isOpen={isOpen}
  onClose={() => setIsOpen(false)}
  title="Modal Title"
  size="medium" // small, medium, large, fullscreen
  footer={<button>Action</button>}
>
  <p>Modal content goes here</p>
</Modal>`
    },
    {
      name: 'Pagination',
      description: 'A pagination component with page numbers, navigation buttons, and item info.',
      component: (
        <Pagination
          currentPage={currentPage}
          totalPages={10}
          onPageChange={setCurrentPage}
          totalItems={100}
          itemsPerPage={10}
          showInfo={true}
        />
      ),
      code: `import Pagination from './components/Pagination';

<Pagination
  currentPage={currentPage}
  totalPages={10}
  onPageChange={setCurrentPage}
  totalItems={100}
  itemsPerPage={10}
  showInfo={true}
/>`
    },
    {
      name: 'QuillEditor',
      description: 'A rich text editor based on Quill with emoji picker support and HTML insertion.',
      component: (
        <div style={{ position: 'relative' }}>
          <QuillEditor
            value={quillValue}
            onChange={setQuillValue}
            placeholder="Start typing..."
            height="200px"
            toolbar="full"
            showEmojiPicker={true}
          />
        </div>
      ),
      code: `import QuillEditor from './components/QuillEditor';

<QuillEditor
  value={value}
  onChange={setValue}
  placeholder="Start typing..."
  height="200px"
  toolbar="full" // full, simple, minimal
  showEmojiPicker={true}
/>`
    }
  ];

  const [expandedComponent, setExpandedComponent] = useState(null);

  return (
    <div style={{
      padding: '24px',
      maxWidth: '1200px',
      margin: '0 auto',
      fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif'
    }}>
      <div style={{ marginBottom: '32px' }}>
        <h1 style={{
          fontSize: '32px',
          fontWeight: '700',
          color: '#111827',
          marginBottom: '8px'
        }}>
          Component Gallery
        </h1>
        <p style={{
          fontSize: '16px',
          color: '#6b7280',
          lineHeight: '1.6'
        }}>
          Browse and test all available React components from the components folder.
        </p>
      </div>

      <div style={{ display: 'grid', gap: '24px' }}>
        {components.map((comp, index) => (
          <div
            key={index}
            style={{
              border: '1px solid #e5e7eb',
              borderRadius: '12px',
              overflow: 'hidden',
              backgroundColor: 'white',
              boxShadow: '0 1px 3px rgba(0, 0, 0, 0.1)'
            }}
          >
            <div style={{
              padding: '20px',
              borderBottom: '1px solid #e5e7eb',
              backgroundColor: '#f9fafb'
            }}>
              <div style={{
                display: 'flex',
                justifyContent: 'space-between',
                alignItems: 'flex-start',
                marginBottom: '8px'
              }}>
                <h2 style={{
                  fontSize: '20px',
                  fontWeight: '600',
                  color: '#111827',
                  margin: 0
                }}>
                  {comp.name}
                </h2>
                <button
                  onClick={() => setExpandedComponent(expandedComponent === index ? null : index)}
                  style={{
                    padding: '4px 12px',
                    border: '1px solid #d1d5db',
                    borderRadius: '6px',
                    backgroundColor: 'white',
                    cursor: 'pointer',
                    fontSize: '12px',
                    fontWeight: '500',
                    color: '#374151'
                  }}
                >
                  {expandedComponent === index ? 'Hide Code' : 'Show Code'}
                </button>
              </div>
              <p style={{
                fontSize: '14px',
                color: '#6b7280',
                margin: 0,
                lineHeight: '1.5'
              }}>
                {comp.description}
              </p>
            </div>

            <div style={{ padding: '24px' }}>
              <div style={{
                marginBottom: expandedComponent === index ? '16px' : 0,
                padding: '16px',
                backgroundColor: '#f9fafb',
                borderRadius: '8px',
                border: '1px solid #e5e7eb'
              }}>
                {comp.component}
              </div>

              {expandedComponent === index && (
                <div style={{
                  marginTop: '16px',
                  padding: '16px',
                  backgroundColor: '#1f2937',
                  borderRadius: '8px',
                  overflow: 'auto'
                }}>
                  <pre style={{
                    margin: 0,
                    color: '#f9fafb',
                    fontSize: '13px',
                    lineHeight: '1.6',
                    fontFamily: 'Monaco, Menlo, "Ubuntu Mono", monospace'
                  }}>
                    <code>{comp.code}</code>
                  </pre>
                </div>
              )}
            </div>
          </div>
        ))}
      </div>

      <div style={{
        marginTop: '48px',
        padding: '20px',
        backgroundColor: '#f0f9ff',
        border: '1px solid #bae6fd',
        borderRadius: '8px'
      }}>
        <h3 style={{
          fontSize: '16px',
          fontWeight: '600',
          color: '#0369a1',
          marginBottom: '8px'
        }}>
          📦 Total Components: {components.length}
        </h3>
        <p style={{
          fontSize: '14px',
          color: '#075985',
          margin: 0,
          lineHeight: '1.5'
        }}>
          All components are located in <code style={{
            backgroundColor: '#e0f2fe',
            padding: '2px 6px',
            borderRadius: '4px',
            fontSize: '12px'
          }}>src/js/components/</code> folder and can be imported and used in your React applications.
        </p>
      </div>
    </div>
  );
};

export default ComponentGallery;

