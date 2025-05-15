import React, { useState, useEffect } from 'react';
import ResultsSummary from './ResultsSummary';
import TabNavigation from './TabNavigation';
import DetailsTab from './tabs/DetailsTab';
import ReviewsTab from './tabs/ReviewsTab';
import SupportTab from './tabs/SupportTab';
import PreviewTab from './tabs/PreviewTab';

const ResultsSection = ({ results, batchId, api, onDownload }) => {
  const [activeTab, setActiveTab] = useState('details');
  const [batchData, setBatchData] = useState(results.data || {});
  const [pagination, setPagination] = useState({
    reviews: results.pagination?.reviews || { page: 1, per_page: 1000, loading: false, has_more: false },
    support: results.pagination?.support || { page: 1, per_page: 1000, loading: false, has_more: false }
  });

  useEffect(() => {
    console.log('ResultsSection received results:', results);
    setBatchData(results.data || {});
    setPagination({
      reviews: results.pagination?.reviews || { page: 1, per_page: 1000, loading: false, has_more: false },
      support: results.pagination?.support || { page: 1, per_page: 1000, loading: false, has_more: false }
    });
  }, [results]);

  const loadMoreItems = async (type) => {
    if (pagination[type].loading) return;

    setPagination(prev => ({
      ...prev,
      [type]: { ...prev[type], loading: true }
    }));

    try {
      // Load with a very large page size to get all remaining data at once
      // This ensures we get ALL data regardless of the total count (10, 50, 100, 200, 500, 1000, 2000, 5000, or All)
      const response = await api.get(`/batch/download/${batchId}`, {
        params: {
          type: type,
          page: pagination[type].page + 1,
          per_page: 10000 // Very large to ensure we get everything
        }
      });

      console.log(`Load more ${type} response:`, response.data);

      // Extract the data based on type
      const responseData = response.data.data;
      const newItems = type === 'reviews' 
        ? (responseData.reviews || [])
        : (responseData.tickets || []);
      
      // Ensure current data exists
      const currentData = batchData[type] || {};
      const currentItems = type === 'reviews'
        ? (currentData.reviews || [])
        : (currentData.tickets || []);

      // Get the total count from the response or existing data
      const totalCount = type === 'reviews' 
        ? (responseData.review_count || currentData.review_count || 0)
        : (responseData.ticket_count || currentData.ticket_count || 0);

      // Merge with existing data
      setBatchData(prev => ({
        ...prev,
        [type]: {
          ...currentData,
          [type === 'reviews' ? 'reviews' : 'tickets']: [
            ...currentItems,
            ...newItems
          ],
          [type === 'reviews' ? 'review_count' : 'ticket_count']: totalCount
        }
      }));

      // Update pagination
      setPagination(prev => ({
        ...prev,
        [type]: {
          ...response.data.pagination,
          loading: false,
          page: response.data.pagination.page
        }
      }));
    } catch (error) {
      console.error('Failed to load more items:', error);
      setPagination(prev => ({
        ...prev,
        [type]: { ...prev[type], loading: false }
      }));
    }
  };

  const copyToClipboard = (type) => {
    let textToCopy = '';

    switch (type) {
      case 'details':
        textToCopy = JSON.stringify(batchData.details || {}, null, 2);
        break;
      case 'reviews':
        textToCopy = JSON.stringify(batchData.reviews || {}, null, 2);
        break;
      case 'support':
        textToCopy = JSON.stringify(batchData.support || {}, null, 2);
        break;
      default:
        return;
    }

    const fallbackCopy = () => {
      const tempTextArea = document.createElement('textarea');
      tempTextArea.value = textToCopy;
      document.body.appendChild(tempTextArea);
      tempTextArea.select();
      try {
        document.execCommand('copy');
      } catch (err) {
        console.error('Fallback: Copy failed', err);
      }
      document.body.removeChild(tempTextArea);
    };

    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(textToCopy).then(() => {
        showCopySuccess(type);
      }).catch(err => {
        console.warn('navigator.clipboard failed, falling back.', err);
        fallbackCopy();
        showCopySuccess(type);
      });
    } else {
      fallbackCopy();
      showCopySuccess(type);
    }
  };

  const showCopySuccess = (type) => {
    const button = document.querySelector(`[data-type="${type}"].copy-json`);
    if (button) {
      const originalText = button.textContent;
      button.textContent = 'Copied!';
      setTimeout(() => {
        button.textContent = originalText;
      }, 2000);
    }
  };


  const getTabStyle = (tabName) => ({
    display: activeTab === tabName ? 'block' : 'none',
    padding: '20px',
    backgroundColor: '#fff',
    borderRadius: '3px',
    position: 'relative'
  });

  return (
    <div id="batch-results" className="card">
      <h2>Results</h2>
      <ResultsSummary data={batchData} />
      
      <div className="download-buttons-top">
        <button className="button download-json" onClick={() => onDownload('all')}>
          Download All Data
        </button>
        <button className="button download-json" onClick={() => onDownload('details')}>
          Download Details
        </button>
        <button className="button download-json" onClick={() => onDownload('reviews')}>
          Download Reviews
        </button>
        <button className="button download-json" onClick={() => onDownload('support')}>
          Download Support
        </button>
      </div>
      
      <TabNavigation activeTab={activeTab} setActiveTab={setActiveTab} />
      
      <div className="tab-content" style={{ position: 'relative', minHeight: '400px' }}>
        <div 
          id="tab-details" 
          style={getTabStyle('details')}
        >
          <DetailsTab 
            data={batchData.details}
            onCopy={() => copyToClipboard('details')}
            onDownload={() => onDownload('details')}
          />
        </div>
        <div 
          id="tab-reviews" 
          style={getTabStyle('reviews')}
        >
          <ReviewsTab 
            data={batchData.reviews}
            pagination={pagination.reviews}
            onCopy={() => copyToClipboard('reviews')}
            onDownload={() => onDownload('reviews')}
            onLoadMore={() => loadMoreItems('reviews')}
          />
        </div>
        <div 
          id="tab-support" 
          style={getTabStyle('support')}
        >
          <SupportTab 
            data={batchData.support}
            pagination={pagination.support}
            onCopy={() => copyToClipboard('support')}
            onDownload={() => onDownload('support')}
            onLoadMore={() => loadMoreItems('support')}
          />
        </div>
        <div 
          id="tab-preview" 
          style={getTabStyle('preview')}
        >
          <PreviewTab 
            data={batchData}
            pagination={pagination}
            onLoadMore={loadMoreItems}
          />
        </div>
      </div>
    </div>
  );
};

export default ResultsSection;