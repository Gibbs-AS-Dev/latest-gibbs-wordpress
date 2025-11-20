import React, { useState, useEffect } from 'react';
import axios from 'axios';
import { Ltext, getLanguage } from '../utils/smsLog-translations';
import Pagination from '../components/Pagination';
import Table from '../components/Table';
import Modal from '../components/Modal';
import Button from '../components/Button';
import styles from '../assets/scss/smsLog.module.scss';
import '../assets/scss/smsLog.scss';

function SmsLog({ page_id, apiUrl, homeUrl, user_token, owner_id }) {
  const [smsLogs, setSmsLogs] = useState([]);
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

  useEffect(() => {
    fetchSmsLogs();
  }, [currentPage, searchQuery]);

  const fetchSmsLogs = async () => {
    try {
      // Use tableLoading for searches, regular loading for initial load
      if (searchQuery) {
        setTableLoading(true);
      } else {
        setLoading(true);
      }
      
      const response = await axios.get(`${apiUrl}`, {
        params: {
          action: 'getSmsLogs',
          page: currentPage,
          per_page: itemsPerPage,
          search: searchQuery || "",
          owner_id: owner_id
        },
        headers: {
          'Authorization': `Bearer ${user_token}`
        }
      });
      if (response.data.success) {
        setSmsLogs(response.data.data.sms_logs || []);
        setTotalItems(response.data.data.total || 0);
        setTotalPages(Math.ceil((response.data.data.total || 0) / itemsPerPage));
      } else {
        setError(Ltext(response.data.message) || Ltext("Failed to load SMS logs"));
      }
    } catch (err) {
      setError(Ltext("Error loading SMS logs"));
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
      case 'sent':
      case 'delivered':
        return styles.statusSent; // Green for sent/delivered
      case 'failed':
      case 'not_delivered':
        return styles.statusFailed; // Red for failed
      case 'pending':
      case 'waiting':
        return styles.statusWaiting; // Orange for waiting
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

  // Calculate SMS price based on character count
  const calculateSmsPrice = (content) => {
    if (!content || content.trim() === '') return 1;  // Empty content = 1 SMS = 1 kr
    
    const charCount = Math.ceil(content.length / 160);

    return charCount;


    // if (charCount <= 160) return 1;      // 1 SMS = 1 kr
    // if (charCount <= 320) return 2;      // 2 SMS = 2 kr
    // return 3;                            // 3+ SMS = 3 kr
  };

 // console.log('Current state - showPopup:', showPopup, 'selectedMessage:', selectedMessage);

  if (loading) {
    return (
      <div className={styles.smsLogWrapper}>
        <div className={styles.smsLogContainer}>
          <div className={styles.loading}>
            <div className={styles.spinner}></div>
            <p>{Ltext("Loading SMS logs...")}</p>
          </div>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className={styles.smsLogWrapper}>
        <div className={styles.smsLogContainer}>
          <div className={styles.error}>
            <h2>{Ltext("Error")}</h2>
            <p>{error}</p>
            <Button 
              variant="primary" 
              onClick={fetchSmsLogs}
            >
              {Ltext("Retry")}
            </Button>
          </div>
        </div>
      </div>  
    );
  }

  return (
    <div className={`smsLogWrapper ${styles.smsLogWrapper}`}>
      <div className={styles.smsLogContainer} style={{maxWidth: '100%', overflowX: 'auto'}}>
        {/* SMS Logs Section */}
        <div className={styles.smsLogSection}>
          <div className={styles.smsLogHeader}>
            <h2 className={styles.smsLogTitle}>{Ltext("SMS Logs")}</h2>
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
                  placeholder={Ltext("Search (phone, message)...")}
                  value={searchQuery}
                  onChange={handleSearch}
                  className={styles.searchInput}
                />
                <Button 
                  variant="ghost" 
                  size="small"
                  className={styles.searchBtn}
                  type="button"
                >
                  <i className="fa fa-search"></i>
                </Button>
              </div>
            </div>

            <div className={styles.displayInfo}>
              {Ltext("Displaying")} {smsLogs.length} {Ltext("SMS logs")}
            </div>
          </div>

          <div style={{padding: '20px 16px'}}>

            {tableLoading ? (
              <div className={styles.tableLoading}>
                <div className={styles.spinnerSmall}></div>
                <p>{Ltext("Searching SMS logs...")}</p>
              </div>
            ) : (
              <Table
                tableClassName={styles.smsLogTable}
                tableStyle={{minWidth: '800px'}}
                rowStyle={{height: 'auto'}}
                data={smsLogs}
                getRowKey={(row) => row.id}
                  columns={[
                    {
                      header: Ltext("Date/Time"),
                      thStyle: { width: '12%' },
                      tdStyle: { width: '12%' },
                      tdClassName: styles.dateTimeCell,
                      render: (log) => {
                        const updatedDate = log.updated_at ? new Date(log.updated_at) : null;
                        const dateTimeStr = updatedDate ? updatedDate.toLocaleDateString('nb-NO', {
                          day: '2-digit', month: '2-digit', year: 'numeric', timeZone: 'Europe/Oslo'
                        }) + ' ' + updatedDate.toLocaleTimeString('nb-NO', { hour: '2-digit', minute: '2-digit', timeZone: 'Europe/Oslo' }) : '-';
                        return <div className={styles.dateTimeText}>{dateTimeStr}</div>;
                      }
                    },
                    {
                      header: Ltext("Phone Number"),
                      thStyle: { width: '15%' },
                      tdStyle: { width: '15%' },
                      render: (log) => (log.country_code && log.phone_number ? `+${log.country_code} ${log.phone_number}` : (log.phone_number || '-'))
                    },
                    {
                      header: Ltext("SMS Content"),
                      thStyle: { width: '45%' },
                      tdStyle: { width: '45%' },
                      tdClassName: styles.messageCell,
                      render: (log) => (
                        <>
                          <div className={styles.messageText}>
                            {log.sms_content && log.sms_content.length > 100 ? `${log.sms_content.substring(0, 100)}...` : (log.sms_content || '-')}
                          </div>
                          {log.sms_content && log.sms_content.length > 100 && (
                            <Button 
                              variant="link" 
                              size="small"
                              className={styles.showMoreBtn} 
                              onClick={() => handleShowMore(log.sms_content)}
                            >
                              {Ltext("Show More")}
                            </Button>
                          )}
                        </>
                      )
                    },
                    {
                      header: Ltext("SMS Cost (kr)"),
                      thStyle: { width: '15%' },
                      tdStyle: { width: '15%' },
                      tdClassName: styles.priceCell,
                      render: (log) => (
                        <>
                          <span className={styles.priceBadge}>{calculateSmsPrice(log.sms_content)} kr</span>
                          <small className={styles.smsCount}>({calculateSmsPrice(log.sms_content)} SMS)</small>
                        </>
                      )
                    },
                    {
                      header: Ltext("Status"),
                      thStyle: { width: '12%' },
                      tdStyle: { width: '12%' },
                      render: (log) => {
                        const cls = (log.sms_status && log.sms_status.includes('accepted_at'))
                          ? 'sent'
                          : ((log.sms_status === 'pending' || log.sms_status === 'waiting') ? 'waiting' : 'failed');
                        const label = (cls === 'sent') ? Ltext('Sent') : (cls === 'waiting' ? Ltext('Waiting') : Ltext('Not Delivered'));
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
            title={Ltext("SMS Message")}
          >
            <div className={styles.popupBody}>
              <div className={styles.messageInfo}>
                <div className={styles.messageContent}>
                  <h4>{Ltext("Message Content:")}</h4>
                  <p>{selectedMessage}</p>
                </div>
                
                <div className={styles.messageDetails}>
                  <div className={styles.detailRow}>
                    <span className={styles.detailLabel}>{Ltext("Character Count:")}</span>
                    <span className={styles.detailValue}>{selectedMessage.length}</span>
                  </div>
                  <div className={styles.detailRow}>
                    <span className={styles.detailLabel}>{Ltext("SMS Count:")}</span>
                    <span className={styles.detailValue}>{calculateSmsPrice(selectedMessage)}</span>
                  </div>
                  <div className={styles.detailRow}>
                    <span className={styles.detailLabel}>{Ltext("Total Cost:")}</span>
                    <span className={styles.detailValue}>{calculateSmsPrice(selectedMessage)} kr</span>
                  </div>
                </div>
              </div>
            </div>
          </Modal>
      )}
    </div>
  );
}

export default SmsLog;
