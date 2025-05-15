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
    reviews: results.pagination?.reviews || { page: 1, per_page: 20, loading: false, has_more: false },
    support: results.pagination?.support || { page: 1, per_page: 20, loading: false, has_more: false }
  });

  useEffect(() => {
    console.log('ResultsSection received results:', results);
    setBatchData(results.data || {});
    setPagination({
      reviews: results.pagination?.reviews || { page: 1, per_page: 20, loading: false, has_more: false },
      support: results.pagination?.support || { page: 1, per_page: 20, loading: false, has_more: false }
    });
  }, [results]);

  const loadMoreItems = async (type) => {
    if (pagination[type].loading) return;

    setPagination(prev => ({
      ...prev,
      [type]: { ...prev[type], loading: true, page: prev[type].page + 1 }
    }));

    try {
      const response = await api.get(`/batch/download/${batchId}`, {
        params: {
          type: type,
          page: pagination[type].page + 1,
          per_page: pagination[type].per_page
        }
      });

      setBatchData(prev => ({
        ...prev,
        [type]: {
          ...prev[type],
          [type === 'reviews' ? 'reviews' : 'tickets']: [
            ...prev[type][type === 'reviews' ? 'reviews' : 'tickets'],
            ...response.data.data[type === 'reviews' ? 'reviews' : 'tickets']
          ]
        }
      }));

      setPagination(prev => ({
        ...prev,
        [type]: {
          ...response.data.pagination,
          loading: false
        }
      }));
    } catch (error) {
      console.error('Failed to load more items:', error);
      setPagination(prev => ({
        ...prev,
        [type]: { ...prev[type], loading: false, page: prev[type].page - 1 }
      }));
    }
  };

  const copyToClipboard = (type) => {
    let textToCopy = '';
    
    switch(type) {
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
    
    navigator.clipboard.writeText(textToCopy).then(() => {
      const button = document.querySelector(`[data-type="${type}"].copy-json`);
      if (button) {
        const originalText = button.textContent;
        button.textContent = 'Copied!';
        setTimeout(() => {
          button.textContent = originalText;
        }, 2000);
      }
    });
  };

  // Simple tab style for active/inactive
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