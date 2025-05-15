import React from 'react';

const ReviewsTab = ({ data, pagination, onCopy, onDownload, onLoadMore }) => {
  const calculateAverageRating = (reviews) => {
    if (!reviews || !Array.isArray(reviews) || reviews.length === 0) {
      return 0;
    }
    
    let totalRating = 0;
    let ratedReviews = 0;
    
    reviews.forEach(review => {
      if (review.rating && !isNaN(review.rating)) {
        totalRating += parseInt(review.rating);
        ratedReviews++;
      }
    });
    
    return ratedReviews > 0 ? totalRating / ratedReviews : 0;
  };

  const getSummaryText = () => {
    if (!data) return 'No reviews data available';
    
    const totalCount = data.review_count || 0;
    const currentCount = data.reviews ? data.reviews.length : 0;
    let summaryText = `Total: ${totalCount} reviews`;
    
    if (currentCount > 0 && data.reviews) {
      const avgRating = calculateAverageRating(data.reviews);
      if (avgRating > 0) {
        summaryText += `, Average rating (showing ${currentCount}): ${avgRating.toFixed(1)}/5`;
      }
    }
    
    return summaryText;
  };

  console.log('ReviewsTab data:', data);

  return (
    <div id="tab-reviews" className="tab-pane">
      <div className="review-summary">{getSummaryText()}</div>
      <button className="button copy-json" data-type="reviews" onClick={onCopy}>
        COPY
      </button>
      <button className="button download-json" onClick={onDownload}>
        Download JSON
      </button>
      <pre className="json-viewer" id="json-reviews">
        {data ? JSON.stringify(data, null, 2) : '{\n  "review_count": 0,\n  "reviews": []\n}'}
      </pre>
      {pagination && pagination.has_more && (
        <div className="load-more-container">
          <button 
            className="button load-more-btn" 
            onClick={onLoadMore}
            disabled={pagination.loading}
          >
            {pagination.loading ? 'Loading...' : 'Load More Reviews'}
          </button>
        </div>
      )}
    </div>
  );
};

export default ReviewsTab;