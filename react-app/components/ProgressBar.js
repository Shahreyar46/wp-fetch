import React from 'react';

const ProgressBar = ({ progress, status, current, total, currentType }) => {
  const getProgressInfo = () => {
    if (currentType === 'reviews' || currentType === 'support') {
      if (current !== undefined && total !== undefined) {
        return `<p>Processed ${current} of ${total} items</p>`;
      }
    } else if (currentType === 'details') {
      return '<p>Fetching plugin details...</p>';
    }
    return '';
  };

  return (
    <div id="batch-progress" className="card">
      <h2>Progress</h2>
      <div className="progress-bar">
        <div 
          className="progress-bar-fill"
          style={{ width: `${progress}%` }}
        />
        <div className="progress-text">{progress}%</div>
      </div>
      <div id="batch-status">{status}</div>
      <div 
        id="batch-info"
        dangerouslySetInnerHTML={{ __html: getProgressInfo() }}
      />
    </div>
  );
};

export default ProgressBar;