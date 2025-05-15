import React, { useState } from 'react';
import DetailsPreview from './preview/DetailsPreview';
import ReviewsPreview from './preview/ReviewsPreview';
import SupportPreview from './preview/SupportPreview';

const PreviewTab = ({ data, pagination, onLoadMore }) => {
  const [previewType, setPreviewType] = useState('details');

  console.log('PreviewTab data:', data);

  const renderPreviewContent = () => {
    switch(previewType) {
      case 'details':
        return data && data.details ? <DetailsPreview details={data.details} /> : (
          <div className="no-data">No plugin details available</div>
        );
      case 'reviews':
        return data && data.reviews && data.reviews.reviews && data.reviews.reviews.length > 0 ? (
          <ReviewsPreview 
            reviews={data.reviews.reviews}
            totalCount={data.reviews.review_count}
            pagination={pagination.reviews}
            onLoadMore={() => onLoadMore('reviews')}
          />
        ) : (
          <div className="no-data">No reviews available</div>
        );
      case 'support':
        return data && data.support && data.support.tickets && data.support.tickets.length > 0 ? (
          <SupportPreview 
            tickets={data.support.tickets}
            totalCount={data.support.ticket_count}
            pagination={pagination.support}
            onLoadMore={() => onLoadMore('support')}
          />
        ) : (
          <div className="no-data">No support tickets available</div>
        );
      default:
        return <div className="no-data">No data available</div>;
    }
  };

  return (
    <div id="tab-preview" className="tab-pane">
      <div className="preview-controls">
        <label htmlFor="preview-type">Select preview type:</label>
        <select 
          id="preview-type"
          value={previewType}
          onChange={(e) => setPreviewType(e.target.value)}
        >
          <option value="details">Plugin Details</option>
          <option value="reviews">Reviews</option>
          <option value="support">Support Threads</option>
        </select>
      </div>
      <div id="preview-content">
        {renderPreviewContent()}
      </div>
    </div>
  );
};

export default PreviewTab;