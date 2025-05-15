import React from 'react';

const SupportPreview = ({ tickets = [], totalCount = 0, currentlyShowing = 0, totalLoaded = 0, pagination = {}, onLoadMore, hasMore = false }) => {
  // Ensure tickets is an array
  const safeTickets = Array.isArray(tickets) ? tickets : [];
  const showingCount = safeTickets.length;
  const resolved = safeTickets.filter(t => t.resolved).length;
  const open = showingCount - resolved;
  console.log(safeTickets);

  return (
    <div className="support-preview">
      <p className="summary">
        Showing {showingCount} of {totalCount} tickets - {resolved} resolved, {open} open
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
            {ticket.author && ticket.author.name && ticket.author.url && (
              <>
                By{' '}
                <a
                  href={ticket.author.url}
                  target="_blank"
                  rel="noopener noreferrer"
                >
                  {ticket.author.name}
                </a>
              </>
            )}
            {ticket.replies !== undefined && ` · ${ticket.replies} replies`}
            {ticket.last_activity && ` · Last activity: ${ticket.last_activity}`}
          </p>

          
          {ticket.initial_content && (
            <p className="ticket-content">
              {ticket.initial_content}
            </p>
          )}

          {ticket.url && (
            <div className="ticket-actions">
              <a 
                href={ticket.url} 
                target="_blank" 
                rel="noopener noreferrer" 
                className="view-ticket-link"
              >
                View Support →
              </a>
            </div>
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
            {pagination?.loading ? 'Loading...' : 'Load More Tickets'}
          </button>
        </div>
      )}
    </div>
  );
};

export default SupportPreview;