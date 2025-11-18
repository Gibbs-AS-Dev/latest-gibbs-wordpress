import React, { useState, useEffect } from 'react';
import axios from 'axios';
import { Ltext, getLanguage } from '../utils/smsLog-translations';
import Pagination from '../components/Pagination';
import Table from '../components/Table';
import tableStyles from '../assets/scss/table.module.scss';
import Modal from '../components/Modal';
import styles from '../assets/scss/emailLog.module.scss';

function EmailLog({ page_id, apiUrl, homeUrl, user_token, owner_id }) {
  const [emailLogs, setEmailLogs] = useState([]);
  const [loading, setLoading] = useState(true);
  const [tableLoading, setTableLoading] = useState(false);
  const [error, setError] = useState(null);
  const [searchQuery, setSearchQuery] = useState('');
  
  // Pagination state
  const [currentPage, setCurrentPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [totalItems, setTotalItems] = useState(0);
  const [itemsPerPage] = useState(20);

  // Popup state
  const [showPopup, setShowPopup] = useState(false);
  const [selectedMessage, setSelectedMessage] = useState('');

  useEffect(() => {
    fetchEmailLogs();
  }, [currentPage, searchQuery]);

  // Add custom CSS to head
  useEffect(() => {
    const style = document.createElement('style');
    style.textContent = `
      #wrapper, html {
        background-color: #F2F3F7 !important;
      }
    `;
    document.head.appendChild(style);

    // Cleanup function to remove the style when component unmounts
    return () => {
      if (document.head.contains(style)) {
        document.head.removeChild(style);
      }
    };
  }, []);

  const fetchEmailLogs = async () => {
    try {
      // Use tableLoading for searches, regular loading for initial load
      if (searchQuery) {
        setTableLoading(true);
      } else {
        setLoading(true);
      }
      
      const response = await axios.get(`${apiUrl}`, {
        params: {
          action: 'getEmailLogsData',
          page: currentPage,
          per_page: itemsPerPage,
          search: searchQuery || "",
          owner_id: owner_id
        },
        headers: {
          'Authorization': `Bearer ${user_token}`
        }
      });
      //console.log(response.data);
      if (response.data.success) {
        setEmailLogs(response.data.data.email_logs || []);
        setTotalItems(response.data.data.total || 0);
        setTotalPages(Math.ceil((response.data.data.total || 0) / itemsPerPage));
      } else {
        setError(Ltext(response.data.message) || Ltext("Failed to load email logs"));
      }
    } catch (err) {
      setError(Ltext("Error loading email logs"));
    } finally {
      if (searchQuery) {
        setTableLoading(false);
      } else {
        setLoading(false);
      }
    }
  };

  const handleSearch = (e) => {
    setSearchQuery(e.target.value);
    setCurrentPage(1); // Reset to first page when searching
  };

  const handlePageChange = (page) => {
    setCurrentPage(page);
  };

  const getStatusColor = (status) => {
    switch (status) {
       // Green for sent/delivered
      case 0:
      case '0':
      case 2:
      case '2':
      case 'failed':
      case 'stopped':
      case 'bounced':
      case 'sent_once':
        return styles.statusFailed; // Red for failed
      case 1:
      case '1':
      case 'delivered':
      case 'sent':
        return styles.statusSent;  
      case 'waiting_for_sending':
      case 'waiting':
        return styles.statusWaiting; // Orange for waiting
      case 'opened':
        return styles.statusSent; // Green for opened
      default:
        return styles.statusDefault;
    }
  };

  const handleShowMore = (message) => {
   // console.log('handleShowMore called with:', message);
    setSelectedMessage(message);
    setShowPopup(true);
   // console.log('showPopup set to true');
  };

  const closePopup = () => {
    //console.log('closePopup called');
    setShowPopup(false);
    setSelectedMessage('');
  };

  // Helper function to decode HTML entities and clean up HTML
  const decodeHtml = (html) => {
    if (!html) return '';
    
    // Create a temporary div to decode HTML entities
    const txt = document.createElement('textarea');
    txt.innerHTML = html;
    return txt.value;
  };

  // Helper function to strip HTML tags for display
  const stripHtml = (html) => {
    if (!html) return '';
    const decoded = decodeHtml(html);
    // Remove all HTML tags including those with attributes like style, class, etc.
    return decoded.replace(/<[^>]*>/g, '').trim();
  };


 // console.log('Current state - showPopup:', showPopup, 'selectedMessage:', selectedMessage);

  if (loading) {
    return (
      <div className={styles.emailLogWrapper}>
        <div className={styles.emailLogContainer}>
          <div className={styles.loading}>
            <div className={styles.spinner}></div>
            <p>{Ltext("Loading email logs...")}</p>
          </div>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className={styles.emailLogWrapper}>
        <div className={styles.emailLogContainer}>
          <div className={styles.error}>
            <h2>{Ltext("Error")}</h2>
            <p>{error}</p>
            <button 
              className={styles.btn} 
              onClick={fetchEmailLogs}
            >
              {Ltext("Retry")}
            </button>
          </div>
        </div>
      </div>  
    );
  }

  return (
    <div className={`emailLogWrapper ${styles.emailLogWrapper}`}>
      <div className={styles.emailLogContainer} style={{maxWidth: '100%', overflowX: 'auto'}}>
        {/* Email Logs Section */}
        <div className={styles.emailLogSection}>
          <div className={styles.emailLogHeader}>
            <h2 className={styles.emailLogTitle}>{Ltext("Email Logs")}</h2>
          </div>

          {/* Control Bar */}
          <div className={styles.controlBar}>
            <div className={styles.leftControls}>
              {/* Add any left controls here if needed */}
            </div>

            <div className={styles.filterControls}>
              <span className={styles.filterLabel}>{Ltext("Filter:")}</span>
              {/* Add filter controls here if needed */}
            </div>

            <div className={styles.searchControls}>
              <div className={styles.searchBox}>
                <input
                  type="text"
                  placeholder={Ltext("Search (email, subject, message)...")}
                  value={searchQuery}
                  onChange={handleSearch}
                  className={styles.searchInput}
                />
                <button className={styles.searchBtn}>
                  <i className="fa fa-search"></i>
                </button>
              </div>
            </div>

            <div className={styles.displayInfo}>
              {Ltext("Displaying")} {emailLogs.length} {Ltext("email logs")}
            </div>
          </div>

          <div className={tableStyles.tableWrapper}>
            {tableLoading ? (
              <div className={styles.tableLoading}>
                <div className={styles.spinnerSmall}></div>
                <p>{Ltext("Searching email logs...")}</p>
              </div>
            ) : (
              <Table
                tableClassName={`${tableStyles.table} ${styles.emailLogTable}`}
                tableStyle={{minWidth: '1000px'}}
                rowStyle={{height: 'auto'}}
                data={emailLogs}
                getRowKey={(row) => row.id}
                columns={[
                  {
                    header: Ltext("Date"),
                    thClassName: tableStyles.w15,
                    thStyle: { width: '15%' },
                    tdClassName: styles.dateTimeCell,
                    tdStyle: { width: '15%' },
                    render: (log) => {
                      const sentDate = log.sent_date ? new Date(log.sent_date) : null;
                      const dateTimeStr = sentDate ? sentDate.toLocaleDateString('nb-NO', {
                        day: '2-digit', month: '2-digit', year: 'numeric', timeZone: 'Europe/Oslo'
                      }) + ' ' + sentDate.toLocaleTimeString('nb-NO', { hour: '2-digit', minute: '2-digit', timeZone: 'Europe/Oslo' }) : '-';
                      return (
                        <div className={styles.dateTimeText}>{dateTimeStr}</div>
                      );
                    }
                  },
                  {
                    header: Ltext("Email Address"),
                    thClassName: tableStyles.w15,
                    thStyle: { width: '15%' },
                    tdClassName: styles.emailCell,
                    tdStyle: { width: '15%' },
                    render: (log) => (
                      <div className={styles.emailText}>{log.sent_to_email || '-'}</div>
                    )
                  },
                  {
                    header: Ltext("Subject"),
                    thClassName: tableStyles.w20,
                    thStyle: { width: '30%' },
                    tdClassName: styles.subjectCell,
                    tdStyle: { width: '30%' },
                    render: (log) => (
                      <div className={styles.subjectText}>{log.subject}</div>
                    )
                  },
                  {
                    header: Ltext("Email Content"),
                    thClassName: tableStyles.w40,
                    thStyle: { width: '30%' },
                    tdClassName: styles.messageCell,
                    tdStyle: { width: '30%' },
                    render: (log) => (
                      <>
                        <div className={styles.messageText}>{(log.message ? stripHtml(log.message) : '-')}</div>
                        {log.message && log.message.length > 100 && (
                          <button className={styles.showMoreBtn} onClick={() => handleShowMore(log.message)}>
                            {Ltext("Show More")}
                          </button>
                        )}
                      </>
                    )
                  },
                  {
                    header: Ltext("Status"),
                    thClassName: tableStyles.w10,
                    thStyle: { width: '10%' },
                    tdStyle: { width: '10%' },
                    render: (log) => {
                      const cls = (log.delivery_status == 0 || log.delivery_status == 2 || log.delivery_status == '2')
                        ? 'failed'
                        : ((log.delivery_status == 1 || log.delivery_status == '1') ? 'sent' : log.status);
                      let label = Ltext('Unknown');
                      if (log.delivery_status === 0 || log.delivery_status === '0' || log.delivery_status === 2 || log.delivery_status === '2' || log.status === 'failed' || log.status === 'stopped' || log.status === 'bounced') {
                        if (log.status === 'failed') label = Ltext('failed');
                        else if (log.status === 'stopped') label = Ltext('Not Sent, Requirments Not Met');
                        else if (log.status === 'bounced') label = Ltext('bounced');
                        else label = Ltext('Failed');
                      } else if (log.delivery_status === 1 || log.delivery_status === '1' || log.status === 'sent' || log.status === 'delivered') {
                        label = Ltext('Sent');
                      } else if (log.status === 'waiting_for_sending' || log.status === 'waiting') {
                        label = Ltext('Waiting');
                      } else if (log.status === 'created') {
                        label = Ltext('Created');
                      } else if (log.status === 'sent_once') {
                        label = Ltext('Sent Once');
                      }
                      return (
                        <span className={`${styles.statusBadge} ${getStatusColor(cls)}`}>{label}</span>
                      );
                    }
                  }
                ]}
              />
            )}
          </div>

          {/* Pagination Component */}
          <Pagination
            currentPage={currentPage}
            totalPages={totalPages}
            onPageChange={handlePageChange}
            totalItems={totalItems}
            itemsPerPage={itemsPerPage}
            showInfo={true}
          />
        </div>
      </div>

      {/* Message Popup */}
      {showPopup && (
          <Modal
            isOpen={showPopup}
            onClose={closePopup}
            title={Ltext("Email Message")}
            size="large"
          >
            <div className={styles.popupBody}>
              <div className={styles.messageInfo}>
                <div className={styles.messageContent}>
                  <h4>{Ltext("Message Content:")}</h4>
                  <div 
                    style={{ 
                      overflowY: 'auto',
                      padding: '10px',
                    }}
                    dangerouslySetInnerHTML={{ __html: selectedMessage }}
                  />
                </div>
                
                <div className={styles.messageDetails}>
                  <div className={styles.detailRow}>
                    <span className={styles.detailLabel}>{Ltext("Character Count:")}</span>
                    <span className={styles.detailValue}>{selectedMessage.length}</span>
                  </div>
                  <div className={styles.detailRow}>
                    <span className={styles.detailLabel}>{Ltext("Word Count:")}</span>
                    <span className={styles.detailValue}>{selectedMessage.split(/\s+/).filter(word => word.length > 0).length}</span>
                  </div>
                </div>
              </div>
            </div>
          </Modal>
      )}
    </div>
  );
}

export default EmailLog;
