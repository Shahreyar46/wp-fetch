import React from 'react';

const ReviewsPreview = ({ reviews = [], totalCount = 0, currentlyShowing = 0, totalLoaded = 0, pagination = {}, onLoadMore, hasMore = false }) => {
  const getStars = (rating) => {
    let stars = '';
    rating = parseInt(rating) || 0;
    for (let i = 1; i <= 5; i++) {
      stars += i <= rating ? '★' : '☆';
    }
    return stars;
  };

  // Ensure reviews is an array
  const safeReviews = Array.isArray(reviews) ? reviews : [];
  const showingCount = safeReviews.length;

  return (
    <div className="reviews-preview">
      <p className="summary">
        Showing {showingCount} of {totalCount} reviews
      </p>
      
      {safeReviews.map((review, index) => (
        <div key={index} className="review-item">
          <div className="review-header">
            <span className="review-title">{review.title || 'Untitled'}</span>
            {review.rating && (
              <span className="review-rating">{getStars(review.rating)}</span>
            )}
          </div>
          
          {review.author && review.author.name && (
            <p className="review-meta">
              By {review.author.name}
              {review.date_formatted && ` on ${review.date_formatted}`}
            </p>
          )}
          
          {review.description && (
            <p className="review-content">
              {review.description.substring(0, 200)}
              {review.description.length > 200 && '...'}
            </p>
          )}
        </div>
      ))}
      
      {hasMore && (
        <div className="load-more-preview">
          <button 
            className="button load-more-preview-btn" 
            onClick={onLoadMore}
            disabled={pagination?.loading || false}
          >
            {pagination?.loading ? 'Loading...' : 'Load More Reviews'}
          </button>
        </div>
      )}
    </div>
  );
};

export default ReviewsPreview;