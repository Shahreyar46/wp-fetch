import React from 'react';

const DetailsPreview = ({ details }) => {
  return (
    <div className="plugin-details-preview">
      {details.name && <h3>{details.name}</h3>}
      
      {details.short_description && (
        <p className="description">{details.short_description}</p>
      )}
      
      <div className="details-grid">
        {details.version && (
          <div className="detail-item">
            <strong>Version:</strong> {details.version}
          </div>
        )}
        
        {details.author && (
          <div className="detail-item">
            <strong>Author:</strong> <span dangerouslySetInnerHTML={{ __html: details.author }} />
          </div>
        )}
        
        {details.active_installs && (
          <div className="detail-item">
            <strong>Active Installs:</strong> {Number(details.active_installs).toLocaleString()}+
          </div>
        )}
        
        {details.rating && (
          <div className="detail-item">
            <strong>Rating:</strong> {details.rating}/100 ({details.num_ratings || 0} ratings)
          </div>
        )}
        
        {details.last_updated && (
          <div className="detail-item">
            <strong>Last Updated:</strong> {details.last_updated}
          </div>
        )}
      </div>
      
      {details.sections && details.sections.description && (
        <div className="detail-description">
          <h4>Description</h4>
          <div dangerouslySetInnerHTML={{ __html: details.sections.description }} />
        </div>
      )}
    </div>
  );
};

export default DetailsPreview;