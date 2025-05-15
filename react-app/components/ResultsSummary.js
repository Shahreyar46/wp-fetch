import React from 'react';

const ResultsSummary = ({ data }) => {
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

  return (
    <div id="results-summary">
      <div className="results-summary-content">
        <h4>Processing Complete</h4>
        
        {data.details && (
          <>
            <p><strong>Plugin:</strong> {data.details.name || 'Unknown'}</p>
            {data.details.version && (
              <p><strong>Version:</strong> {data.details.version}</p>
            )}
          </>
        )}
        
        {data.reviews && (
          <>
            <p><strong>Reviews fetched:</strong> {data.reviews.review_count || 0}</p>
            {data.reviews.review_count > 20 && (
              <p><em>Showing first 20 of {data.reviews.review_count} reviews. Use "Load More" to see additional reviews.</em></p>
            )}
            {data.reviews.reviews && data.reviews.reviews.length > 0 && (
              <p><strong>Average rating (sample):</strong> {calculateAverageRating(data.reviews.reviews).toFixed(1)}/5</p>
            )}
          </>
        )}
        
        {data.support && (
          <>
            <p><strong>Support threads fetched:</strong> {data.support.ticket_count || 0}</p>
            {data.support.ticket_count > 20 && (
              <p><em>Showing first 20 of {data.support.ticket_count} tickets. Use "Load More" to see additional tickets.</em></p>
            )}
          </>
        )}
        
        <p className="download-notice">
          <strong>Note:</strong> Use the download buttons to save complete results.
        </p>
      </div>
    </div>
  );
};

export default ResultsSummary;