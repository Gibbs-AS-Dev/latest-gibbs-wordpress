import React, { useState, useEffect } from 'react';
import axios from 'axios';
import { Ltext, getLanguage } from '../utils/wallet-translations';
import Modal from '../components/Modal';
import Table from '../components/Table';
import Button from '../components/Button';
import styles from '../assets/scss/Wallet.module.scss';
import '../assets/scss/Wallet.scss';

function Wallet({ page_id, apiUrl, homeUrl, user_token }) {
  const [currentBalance, setCurrentBalance] = useState(0.00);
  const [transactions, setTransactions] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [showAddFundsModal, setShowAddFundsModal] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');
  const [amount, setAmount] = useState('');
  const [note, setNote] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [validationErrors, setValidationErrors] = useState({});
  const [isFromAdmin, setIsFromAdmin] = useState(false);

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
     fetchWalletData();
     fetchTransactions();
     
     // Add admin bar menu click handler
     const handleAdminBarClick = (e) => {
       console.log(e.target.closest('.admin-bar-add-funds-to-wallet'));
       if (e.target.closest('.admin-bar-add-funds-to-wallet')) {
         e.preventDefault();
         setIsFromAdmin(true); // Set flag to indicate admin bar click
         setShowAddFundsModal(true);
       }
     };
     
     // Add event listener for admin bar clicks
     document.addEventListener('click', handleAdminBarClick);
     
     // Cleanup event listener on unmount
     return () => {
       document.removeEventListener('click', handleAdminBarClick);
     };
  }, []);

  const fetchWalletData = async () => {
    try {
      setLoading(true);
      const response = await axios.get(`${apiUrl}`, {
        params: {
          action: 'get_balance'
        },
        headers: {
          'Authorization': `Bearer ${user_token}`
        }
      });
      if (response.data.success) {
        setCurrentBalance(response.data.data.balance || 0);
        //setTransactions(response.data.data.transactions || []);
      } else {
        setError(Ltext(response.data.message) || Ltext("Failed to load wallet data"));
      }
    } catch (err) {
      setError(Ltext("Error loading wallet data"));
    } finally {
      setLoading(false);
    }
  };

  const fetchTransactions = async () => {
    setLoading(true);   
    try {
      const response = await axios.get(`${apiUrl}`, {
        params: {
          action: 'getTransactionHistory'
        },
        headers: {
          'Authorization': `Bearer ${user_token}`
        }
      });
      if (response.data.success) {
        setTransactions(response.data.data.transactions || []);
      } else {
        setError(Ltext(response.data.message) || Ltext("Failed to load transactions"));
      }
    } catch (err) {
      setError(Ltext("Error loading transactions"));
    } finally {
      setLoading(false);
    }
  };

  const handleAddFunds = async () => {
    // Clear previous errors
    setValidationErrors({});

    // Validation
    const errors = {};
    if (!amount || amount.trim() === '') {
      errors.amount = Ltext("Please fill out this field.");
    } else if (isNaN(amount) || parseFloat(amount) <= 0) {
      errors.amount = Ltext("Please enter a valid amount.");
    }

    if (Object.keys(errors).length > 0) {
      setValidationErrors(errors);
      return;
    }

    try {
      setSubmitting(true);
      const data = {
        action: 'addFunds',
        amount: parseFloat(amount),
        description: note.trim(),
        user_token: user_token,
        back_url: window.location.href,
        source: isFromAdmin ? 'from_admin' : 'from_customer'
      };

      const response = await axios.post(`${apiUrl}`, data, {
        headers: {
          'Authorization': `Bearer ${user_token}`
        }
      });
      console.log(response.data);
      if (response.data.success && response.data?.data?.url) {
        window.location.href = response.data.data.url;
      }else if(response.data.success && response.data?.data?.admin){
        //setValidationErrors({ general: response.data.message || Ltext("Funds added successfully") });
        setAmount('');
        setNote('');
        setShowAddFundsModal(false);
        setIsFromAdmin(false); // Reset flag when modal is closed
        fetchWalletData();
        fetchTransactions();
      }else {
        setValidationErrors({ general: response.data.message || Ltext("Failed to add funds") });
      }
    } catch (err) {
      if(err?.response?.data?.message){
        setValidationErrors({ general: err?.response?.data?.message });
      }else if(err?.message){
        setValidationErrors({ general: err?.message });
      }else{
        setValidationErrors({ general: Ltext("An error occurred while adding funds") });
      }
    } finally {
      setSubmitting(false);
    }
  };



  const filteredTransactions = transactions.filter(transaction => {
    const searchLower = searchQuery.toLowerCase();
    return (
     // transaction.id.toLowerCase().includes(searchLower) ||
      transaction.type.toLowerCase().includes(searchLower) ||
      transaction.note.toLowerCase().includes(searchLower)
    );
  });

  const formatAmount = (amount, type = 'credit') => {
    const isNegative = amount < 0;
    const absAmount = Math.abs(amount);
    if(type == 'credit'){
      return `${'+'}${absAmount.toFixed(2)}`;
    }else{
      return `${'-'}${absAmount.toFixed(2)}`;
    }
  };

  const getStatusColor = (status) => {
    switch (status) {
      case 'Completed':
        return styles.statusCompleted;
      case 'Failed':
        return styles.statusFailed;
      case 'Pending':
        return styles.statusPending;
      case 'processing':
        return styles.statusProcessing;  
      default:
        return styles.statusDefault;
    }
  };

  if (loading) {
    return (
      <div className={styles.walletContainer}>
        <div className={styles.loading}>
          <div className={styles.spinner}></div>
          <p>{Ltext("Loading wallet...")}</p>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className={styles.walletContainer}>
        <div className={styles.error}>
          <h2>{Ltext("Error")}</h2>
          <p>{error}</p>
          <Button 
            variant="primary" 
            onClick={fetchWalletData}
          >
            {Ltext("Retry")}
          </Button>
        </div>
      </div>
    );
  }

  return (
    <div className={styles.walletContainer} style={{maxWidth: '100%', overflowX: 'auto'}}>
      {/* Current Balance Section */}
      <div className={`balanceWrapper ${styles.balanceWrapper}`}>
        <div className={styles.balanceContainer} style={{maxWidth: '100%', overflowX: 'auto'}}>
          <div className={styles.balanceSection}>
            <div className={styles.balanceHeader}>
              <h2 className={styles.balanceTitle}>{Ltext("Current balance")}</h2>
            </div>

            <div className={styles.balanceContent}>
              <div className={styles.balanceDisplay}>
                <div className={styles.balanceInfo}>
                  <span className={styles.currencyBadge}>NOK</span>
                  <span className={styles.balanceAmount}>{currentBalance.toFixed(2)}</span>
                </div>
                <div className={styles.balanceActions}>
                  <Button 
                    variant="primary"
                    onClick={() => {
                      setIsFromAdmin(false); // Reset flag for regular customer click
                      setShowAddFundsModal(true);
                    }}
                  >
                    {Ltext("Add funds")}
                  </Button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Transaction History Section */}
      <div className={`transactionWrapper ${styles.transactionWrapper}`}>
        <div className={styles.transactionContainer} style={{maxWidth: '100%', overflowX: 'auto'}}>
          <div className={styles.transactionSection}>
        <div className={styles.transactionHeader}>
          <h2 className={styles.transactionTitle}>{Ltext("Transaction history")}</h2>
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
                placeholder={Ltext("Search (type, note, id)...")}
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
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
            {Ltext("Displaying")} {filteredTransactions.length} {Ltext("transactions")}
          </div>
        </div>
            <div style={{padding: '20px 16px'}}>
              <Table
                tableClassName={styles.transactionTable}
                tableStyle={{minWidth: '800px'}}
                rowStyle={{height: 'auto'}}
                data={filteredTransactions}
                getRowKey={(row) => row.id}
                  columns={[
                    {
                      header: Ltext("When"),
                      thStyle: { width: '20%' },
                      tdStyle: { width: '20%' },
                      render: (transaction) => transaction.created_at
                    },
                    {
                      header: Ltext("Type"),
                      thStyle: { width: '15%' },
                      tdStyle: { width: '15%' },
                      render: (transaction) => transaction.type
                    },
                    {
                      header: Ltext("Note"),
                      thStyle: { width: '35%' },
                      tdStyle: { width: '35%' },
                      render: (transaction) => transaction.description
                    },
                    {
                      header: Ltext("Amount (NOK)"),
                      thStyle: { width: '15%' },
                      tdStyle: { width: '15%' },
                      tdClassName: styles.amountCell,
                      render: (transaction) => formatAmount(transaction.amount, transaction.type)
                    },
                    {
                      header: Ltext("Status"),
                      thStyle: { width: '15%' },
                      tdStyle: { width: '15%' },
                      render: (transaction) => (
                        <span className={`${styles.statusBadge} ${getStatusColor(transaction.status)}`}>
                          {transaction.status}
                        </span>
                      )
                    }
                  ]}
              />
            </div>  
          </div>
        </div>
      </div>

      {/* Add Funds Modal */}
      {showAddFundsModal && (
        <Modal
          isOpen={showAddFundsModal}
          onClose={() => {
            setShowAddFundsModal(false);
            setIsFromAdmin(false); // Reset flag when modal is closed
          }}
          title={Ltext("Add funds")}
        >
          <div className={styles.modalForm}>
            <div className={styles.formGroup}>
              <label htmlFor="amount" className={styles.formLabel}>
                {Ltext("Amount (NOK)")}
              </label>
              <input
                type="number"
                id="amount"
                value={amount}
                onChange={(e) => setAmount(e.target.value)}
                className={`${styles.formInput} ${validationErrors.amount ? styles.inputError : ''}`}
                placeholder="0.00"
                min="0"
                step="0.01"
              />
              {validationErrors.amount && (
                <div className={styles.errorTooltip}>{validationErrors.amount}</div>
              )}
            </div>

            <div className={styles.formGroup}>
              <label htmlFor="note" className={styles.formLabel}>
                {Ltext("Note (optional)")}
              </label>
              <input
                type="text"
                id="note"
                value={note}
                onChange={(e) => setNote(e.target.value)}
                className={styles.formInput}
                placeholder={Ltext("")}
              />
            </div>

            {validationErrors.general && (
              <div className={styles.generalError}>
                {validationErrors.general}
              </div>
            )}
          </div>
          
          {/* Modal Footer with Form Actions */}
          <div className={styles.modalActions}>
            <Button 
              variant="secondary"
              onClick={() => {
                setShowAddFundsModal(false);
                setIsFromAdmin(false); // Reset flag when cancel is clicked
              }}
            >
              {Ltext("Cancel")}
            </Button>
            <Button 
              variant="primary"
              onClick={handleAddFunds}
              disabled={submitting}
              loading={submitting}
            >
              {submitting ? Ltext("Adding...") : Ltext("Add funds")}
            </Button>
          </div>
        </Modal>
      )}
    </div>
  );
}

export default Wallet;
