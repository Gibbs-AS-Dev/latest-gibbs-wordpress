import React, { useState, useEffect, useCallback } from 'react';
import axios from 'axios';
import Modal from '../components/Modal';
import Button from '../components/Button';
import { Ltext } from '../utils/customer-translations';
import styles from '../assets/scss/CreateCustomer.module.scss';

function UserSearchModal({ 
  isOpen, 
  onClose, 
  onSelect, 
  apiUrl, 
  user_token,
  currentUserId = null 
}) {
  const [searchQuery, setSearchQuery] = useState('');
  const [users, setUsers] = useState([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [selectedUserId, setSelectedUserId] = useState(null);

  // Debounced search
  useEffect(() => {
    if (!isOpen) {
      setSearchQuery('');
      setUsers([]);
      setSelectedUserId(null);
      setError(null);
      return;
    }

    const timeoutId = setTimeout(() => {
      if (searchQuery.trim().length >= 2 || searchQuery.trim().length === 0) {
        searchUsers(searchQuery);
      }
    }, 300);

    return () => clearTimeout(timeoutId);
  }, [searchQuery, isOpen]);

  const searchUsers = useCallback(async (query = '') => {
    if (!apiUrl) return;

    setLoading(true);
    setError(null);

    try {
      const headers = user_token ? { Authorization: `Bearer ${user_token}` } : {};
      const response = await axios.get(apiUrl, {
        params: {
          action: 'searchUsers',
          search: query,
          per_page: 20,
          sort_by: 'ID',
          sort_direction: 'asc',
        },
        headers
      });

      if (response?.data?.success) {
        const usersData = response.data.data?.users || [];
        // Filter out current user if provided
        const filteredUsers = currentUserId 
          ? usersData.filter(user => user.id !== currentUserId)
          : usersData;
        setUsers(filteredUsers);
      } else {
        setError(response?.data?.message || Ltext('Failed to load users'));
        setUsers([]);
      }
    } catch (err) {
      const message = err?.response?.data?.message || err?.message || Ltext('Failed to search users');
      setError(message);
      setUsers([]);
    } finally {
      setLoading(false);
    }
  }, [apiUrl, user_token, currentUserId]);

  const handleUserSelect = (user) => {
    setSelectedUserId(user.id);
  };

  const handleConfirm = () => {
    if (selectedUserId && onSelect) {
      const selectedUser = users.find(u => u.id === selectedUserId);
      if (selectedUser) {
        onSelect(selectedUser);
        onClose();
      }
    }
  };

  const handleClear = () => {
    if (onSelect) {
      onSelect(null);
      onClose();
    }
  };

  const renderFooter = () => {
    return (
      <div className={styles.footer}>
        <Button
          type="button"
          variant="cancel"
          onClick={handleClear}
        >
          {Ltext('Clear')}
        </Button>
        <Button
          type="button"
          variant="primary"
          onClick={handleConfirm}
          disabled={!selectedUserId}
        >
          {Ltext('Select')}
        </Button>
      </div>
    );
  };

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      title={Ltext('Select User')}
      size="medium"
      footer={renderFooter()}
      closeOnOverlayClick={false}
      className={styles.createCustomer}
    >
      <div className={styles.form}>
        {error && (
          <div className={styles.errorMessage}>
            {error}
          </div>
        )}

        {/* Search Input */}
        <div className={styles.superadminSearchWrapper}>
          <div className={styles.superadminSearchInput}>
            <i className={`fa fa-search ${styles.searchIcon}`}></i>
            <input
              type="text"
              placeholder={Ltext('Search for user...')}
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className={styles.superadminSearch}
              disabled={loading}
              autoFocus
            />
          </div>

          {/* Users List */}
          {users.length > 0 && (
            <div className={styles.superadminList}>
              {users.map((user) => {
                const userName = `${user.first_name || ''} ${user.last_name || ''}`.trim() || user.display_name || user.user_login || '—';
                return (
                  <div
                    key={user.id}
                    className={`${styles.superadminItem} ${selectedUserId === user.id ? styles.selected : ''}`}
                    onClick={() => handleUserSelect(user)}
                  >
                    <i className={`fa fa-user ${styles.userIcon}`}></i>
                    <div className={styles.superadminInfo}>
                      <div className={styles.superadminName}>{userName}</div>
                      <div className={styles.superadminEmail}>{user.user_email || '—'}</div>
                    </div>
                    <span className={styles.superadminAction}>{Ltext('Select')}</span>
                  </div>
                );
              })}
            </div>
          )}

          {loading && (
            <div className={styles.searchingIndicator}>{Ltext('Loading...')}</div>
          )}

          {!loading && users.length === 0 && searchQuery.trim().length >= 2 && (
            <div className={styles.searchingIndicator}>{Ltext('No users found')}</div>
          )}

          {!loading && users.length === 0 && searchQuery.trim().length < 2 && (
            <div className={styles.searchingIndicator}>{Ltext('Type at least 2 characters to search')}</div>
          )}
        </div>
      </div>
    </Modal>
  );
}

export default UserSearchModal;

