import React from 'react';

const SupportTab = ({ data, pagination, onCopy, onDownload, onLoadMore }) => {
  const getSummaryText = () => {
    if (!data) return 'No support tickets data available';
    
    const ticketCount = data.ticket_count || 0;
    const ticketsLength = data.tickets?.length || 0;
    return `Total: ${ticketCount} tickets (showing ${ticketsLength})`;
  };

  console.log('SupportTab data:', data);

  return (
    <div id="tab-support" className="tab-pane">
      <div className="support-summary">
        <div>{getSummaryText()}</div>
      </div>
      <button className="button copy-json" data-type="support" onClick={onCopy}>
        COPY
      </button>
      <button className="button download-json" onClick={onDownload}>
        Download JSON
      </button>
      <pre className="json-viewer" id="json-support">
        {data ? JSON.stringify(data, null, 2) : '{\n  "ticket_count": 0,\n  "tickets": []\n}'}
      </pre>
      {pagination && pagination.has_more && data && data.tickets && data.tickets.length > 0 && (
        <div className="load-more-container">
          <button 
            className="button load-more-btn" 
            onClick={onLoadMore}
            disabled={pagination.loading}
          >
            {pagination.loading ? 'Loading...' : 'Load More Tickets'}
          </button>
        </div>
      )}
    </div>
  );
};

export default SupportTab;