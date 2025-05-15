import React, { useState, useEffect } from 'react';
import DetailsPreview from './preview/DetailsPreview';
import ReviewsPreview from './preview/ReviewsPreview';
import SupportPreview from './preview/SupportPreview';

const PreviewTab = ({ data, pagination, onLoadMore }) => {
  const [previewType, setPreviewType] = useState('details');
  const [displayedItems, setDisplayedItems] = useState({
    reviews: 20,  // Start with 20 items displayed
    support: 20
  });

  // Update displayed items when data changes
  useEffect(() => {
    if (data.reviews?.reviews) {
      // Don't reduce displayedItems if data is reloaded
      setDisplayedItems(prev => ({
        ...prev,
        reviews: Math.max(prev.reviews, Math.min(20, data.reviews.reviews.length))
      }));
    }
    if (data.support?.tickets) {
      setDisplayedItems(prev => ({
        ...prev,
        support: Math.max(prev.support, Math.min(20, data.support.tickets.length))
      }));
    }
  }, [data]);

  console.log('PreviewTab data:', data);

  const handleLoadMore = async (type) => {
    const currentData = type === 'reviews' ? data.reviews?.reviews : data.support?.tickets;
    const currentlyShowing = displayedItems[type];
    const totalLoaded = currentData ? currentData.length : 0;
    const totalCount = type === 'reviews' ? data.reviews?.review_count : data.support?.ticket_count;
    
    console.log(`Load more ${type}: showing ${currentlyShowing}, loaded ${totalLoaded}, total ${totalCount}`);
    
    // First, show more from already loaded items
    if (currentlyShowing < totalLoaded) {
      // Show 10% more items at a time or at least 10 items, whichever is larger
      const increment = Math.max(10, Math.ceil(totalCount * 0.1));
      const newCount = Math.min(currentlyShowing + increment, totalLoaded);
      setDisplayedItems(prev => ({
        ...prev,
        [type]: newCount
      }));
      return; // Don't load from API yet
    } 
    
    // If we've shown all loaded items but there are more to load from API
    if (totalLoaded < totalCount && pagination[type]?.has_more) {
      // Load more from API
      await onLoadMore(type);
      // After loading, the useEffect will update displayedItems
    }
  };

  const renderPreviewContent = () => {
    switch(previewType) {
      case 'details':
        return data && data.details ? <DetailsPreview details={data.details} /> : (
          <div className="no-data">No plugin details available</div>
        );
      case 'reviews':
        if (!data || !data.reviews || !data.reviews.reviews || !Array.isArray(data.reviews.reviews)) {
          return <div className="no-data">No reviews available</div>;
        }
        const reviewsToShow = data.reviews.reviews.slice(0, displayedItems.reviews);
        const totalReviewCount = data.reviews.review_count || data.reviews.reviews.length;
        const totalLoadedReviews = data.reviews.reviews.length;
        const currentlyShowingReviews = displayedItems.reviews;
        
        // Show button if either:
        // 1. We have more loaded items to show
        // 2. We have more items to load from API
        const hasMoreReviews = currentlyShowingReviews < totalLoadedReviews || 
                              (totalLoadedReviews < totalReviewCount && pagination.reviews?.has_more);
        
        return (
          <ReviewsPreview 
            reviews={reviewsToShow}
            totalCount={totalReviewCount}
            currentlyShowing={currentlyShowingReviews}
            totalLoaded={totalLoadedReviews}
            pagination={pagination.reviews}
            onLoadMore={() => handleLoadMore('reviews')}
            hasMore={hasMoreReviews}
          />
        );
      case 'support':
        if (!data || !data.support || !data.support.tickets || !Array.isArray(data.support.tickets)) {
          return <div className="no-data">No support tickets available</div>;
        }
        const ticketsToShow = data.support.tickets.slice(0, displayedItems.support);
        const totalTicketCount = data.support.ticket_count || data.support.tickets.length;
        const totalLoadedTickets = data.support.tickets.length;
        const currentlyShowingTickets = displayedItems.support;
        
        // Show button if either:
        // 1. We have more loaded items to show
        // 2. We have more items to load from API
        const hasMoreTickets = currentlyShowingTickets < totalLoadedTickets || 
                              (totalLoadedTickets < totalTicketCount && pagination.support?.has_more);
        
        return (
          <SupportPreview 
            tickets={ticketsToShow}
            totalCount={totalTicketCount}
            currentlyShowing={currentlyShowingTickets}
            totalLoaded={totalLoadedTickets}
            pagination={pagination.support}
            onLoadMore={() => handleLoadMore('support')}
            hasMore={hasMoreTickets}
          />
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