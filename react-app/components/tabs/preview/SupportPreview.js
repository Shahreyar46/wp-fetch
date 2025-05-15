import React from 'react';

const SupportPreview = ({ tickets = [], totalCount = 0, currentlyShowing = 0, totalLoaded = 0, pagination = {}, onLoadMore }) => {
  // Ensure tickets is an array
  const safeTickets = Array.isArray(tickets) ? tickets : [];
  const showingCount = safeTickets.length;
  const resolved = safeTickets.filter(t => t.resolved).length;
  const open = showingCount - resolved;
  const hasMoreToShow = currentlyShowing < totalLoaded;
  const hasMoreToLoad = pagination?.has_more || false;

  return (
    <div className="support-preview">
      <p className="summary">
        Showing {showingCount} of {totalLoaded} loaded tickets (Total: {totalCount}) - {resolved} resolved, {open} open
      </p>
      
      {safeTickets.map((ticket, index) => (
        <div key={index} className="ticket-item">
          <div className="ticket-header">
            <span className="ticket-title">{ticket.title || 'Untitled'}</span>
            <span className={`ticket-status ${ticket.resolved ? 'resolved' : 'open'}`}>
              {ticket.resolved ? 'Resolved' : 'Open'}
            </span>
          </div>
          
          <p className="ticket-meta">
            {ticket.author && ticket.author.name && `By ${ticket.author.name}`}
            {ticket.replies !== undefined && ` · ${ticket.replies} replies`}
            {ticket.last_activity && ` · Last activity: ${ticket.last_activity}`}
          </p>
          
          {ticket.initial_content && (
            <p className="ticket-content">
              {ticket.initial_content.substring(0, 200)}
              {ticket.initial_content.length > 200 && '...'}
            </p>
          )}
        </div>
      ))}
      
      {(hasMoreToShow || hasMoreToLoad) && (
        <div className="load-more-preview">
          <button 
            className="button load-more-preview-btn" 
            onClick={onLoadMore}
            disabled={pagination?.loading || false}
          >
            {pagination?.loading ? 'Loading...' : 'Load More Tickets'}
          </button>
        </div>
      )}
    </div>
  );
};

export default SupportPreview;