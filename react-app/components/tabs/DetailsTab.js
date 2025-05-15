import React from 'react';

const DetailsTab = ({ data, onCopy, onDownload }) => {
  console.log('DetailsTab rendering with data:', data);
  
  // Force render a simple version first
  return (
    <div style={{ position: 'relative', minHeight: '300px', padding: '20px' }}>
      <div style={{ marginBottom: '20px' }}>
        <button 
          className="button copy-json" 
          data-type="details" 
          onClick={onCopy}
          style={{ marginRight: '10px' }}
        >
          COPY
        </button>
        <button 
          className="button download-json" 
          onClick={onDownload}
        >
          Download JSON
        </button>
      </div>
      <pre 
        className="json-viewer" 
        id="json-details"
        style={{ 
          display: 'block',
          background: '#f5f5f5',
          border: '1px solid #ddd',
          padding: '15px',
          borderRadius: '3px',
          overflow: 'auto',
          minHeight: '200px'
        }}
      >
        {data ? JSON.stringify(data, null, 2) : '{\n  "message": "No plugin details fetched"\n}'}
      </pre>
    </div>
  );
};

export default DetailsTab;