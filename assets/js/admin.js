/**
 * WP Plugin Data Fetcher - Admin JavaScript
 * Version 4.0.0 - Batch Processing with REST API
 */

(function($) {
    'use strict';
    
    // Global variables
    let currentBatchId = null;
    let progressInterval = null;
    let batchData = {};
    let pagination = {
        reviews: { page: 1, per_page: 20, loading: false },
        support: { page: 1, per_page: 20, loading: false }
    };
    
    // Make loadMoreItems available globally for onclick handlers
    window.loadMoreItems = function(type) {
        loadMoreItemsInternal(type);
    };
    
    // DOM ready
    jQuery(document).ready(function($) {
        console.log('WP Plugin Data Fetcher loaded');
        
        // Start batch button
        $('#start-batch').on('click', startBatchProcess);
        
        // Cancel batch button
        $('#cancel-batch').on('click', cancelBatchProcess);
        
        // Download buttons
        $(document).on('click', '.download-json', function() {
            if (currentBatchId) {
                downloadResults($(this).data('type'));
            }
        });
        
        // Copy buttons
        $(document).on('click', '.copy-json', function() {
            copyToClipboard($(this).data('type'));
        });
        
        // Tab navigation
        $(document).on('click', '.data-tab', function() {
            // Remove active class from all tabs and panes
            $('.data-tab').removeClass('active');
            $('.tab-pane').removeClass('active');
            
            // Add active class to clicked tab
            $(this).addClass('active');
            
            // Show corresponding pane
            const tabName = $(this).data('tab');
            $('#tab-' + tabName).addClass('active');
        });
        
        // Preview type selector
        $('#preview-type').on('change', function() {
            updatePreviewContent($(this).val());
        });
        
        // Separate handlers for tab and preview load more buttons
        $(document).on('click', '.load-more-btn', function() {
            const type = $(this).data('type');
            loadMoreItemsInternal(type, false);
        });

         $(document).on('click', '.load-more-preview-btn', function() {
            const type = $(this).data('type');
            const isPreview = $(this).data('preview');
            loadMoreItemsInternal(type, isPreview);
        });


    });
    
    /**
     * Start batch process
     */
    function startBatchProcess() {
        console.log('Starting batch process...');
        
        const pluginSlug = $('#plugin-slug').val().trim();
        
        if (!pluginSlug) {
            alert('Please enter a plugin slug.');
            return;
        }
        
        // Disable the button
        $('#start-batch').prop('disabled', true).text('Starting...');
        $('#cancel-batch').show();
        
        // Reset UI
        resetUI();
        
        // Prepare data
        const data = {
            plugin_slug: pluginSlug,
            fetch_details: $('#fetch-details').is(':checked'),
            fetch_reviews: $('#fetch-reviews').is(':checked'),
            fetch_support: $('#fetch-support').is(':checked'),
            max_items: parseInt($('#max-items').val()),
            fetch_full_content: $('#fetch-full-content').is(':checked')
        };
        
        console.log('Sending data:', data);
        
        // Start batch process via REST API
        $.ajax({
            url: wpPluginDataFetcher.rest_url + '/batch/start',
            method: 'POST',
            data: JSON.stringify(data),
            contentType: 'application/json',
            dataType: 'json',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', wpPluginDataFetcher.nonce);
            },
            success: function(response) {
                console.log('Batch started:', response);
                currentBatchId = response.batch_id;
                
                // Show progress
                $('#batch-progress').removeClass('hidden');
                $('#batch-status').text('Processing started...');
                
                // Start monitoring progress
                startProgressMonitoring();
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('Start batch failed:', textStatus, errorThrown);
                handleError('Failed to start batch process: ' + errorThrown);
                resetButtons();
            }
        });
    }
    
    /**
     * Start progress monitoring
     */
    function startProgressMonitoring() {
        console.log('Starting progress monitoring...');
        
        // Initial check
        checkBatchStatus();
        
        // Set up interval
        progressInterval = setInterval(checkBatchStatus, 2000); // Check every 2 seconds
    }
    
    /**
     * Check batch status
     */
    function checkBatchStatus() {
        if (!currentBatchId) {
            console.log('No batch ID, stopping status check');
            return;
        }
        
        $.ajax({
            url: wpPluginDataFetcher.rest_url + '/batch/status/' + currentBatchId,
            method: 'GET',
            dataType: 'json',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', wpPluginDataFetcher.nonce);
            },
            success: function(response) {
                console.log('Status update:', response);
                updateProgress(response);
                
                if (response.status === 'running') {
                    // Process next batch
                    processNextBatch();
                } else if (response.status === 'completed') {
                    onBatchComplete();
                } else if (response.status === 'cancelled') {
                    onBatchCancelled();
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('Status check failed:', textStatus, errorThrown);
                if (progressInterval) {
                    clearInterval(progressInterval);
                    progressInterval = null;
                }
                handleError('Failed to check batch status');
            }
        });
    }
    
    /**
     * Process next batch
     */
    function processNextBatch() {
        if (!currentBatchId) return;
        
        $.ajax({
            url: wpPluginDataFetcher.rest_url + '/batch/process/' + currentBatchId,
            method: 'POST',
            dataType: 'json',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', wpPluginDataFetcher.nonce);
            },
            success: function(response) {
                console.log('Process next batch:', response);
                // Update will happen on next status check
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('Process batch failed:', textStatus, errorThrown);
                handleError('Failed to process batch');
            }
        });
    }
    
    /**
     * Update progress display
     */
    function updateProgress(data) {
        const progress = Math.round(data.progress || 0);
        
        $('.progress-bar-fill').css('width', progress + '%');
        $('.progress-text').text(progress + '%');
        
        let statusText = 'Processing...';
        let processInfo = '';
        
        if (data.current_type) {
            statusText = 'Fetching ' + data.current_type + '...';
            
            // Show current/total format for items
            if (data.current !== undefined && data.total !== undefined && data.total > 0) {
                statusText += ' (' + data.current + ' of ' + data.total + ')';
            }
        }
        
        $('#batch-status').text(statusText);
        
        // Show detailed progress information
        if (data.current_type === 'reviews' || data.current_type === 'support') {
            if (data.current !== undefined && data.total !== undefined) {
                processInfo = '<p>Processed ' + data.current + ' of ' + data.total + ' items</p>';
            }
        } else if (data.current_type === 'details') {
            processInfo = '<p>Fetching plugin details...</p>';
        }
        
        if (data.updated) {
            processInfo += '<p><small>Last updated: ' + new Date(data.updated).toLocaleString() + '</small></p>';
        }
        
        $('#batch-info').html(processInfo);
        
        // Handle visual progress reset when switching types
        if (progress === 0 && data.current_type && data.current === 0) {
            // Force immediate visual update for progress reset
            $('.progress-bar-fill').stop().css('width', '0%');
            $('.progress-text').text('0%');
        }
    }
    
    /**
     * On batch complete
     */
    function onBatchComplete() {
        console.log('Batch complete');
        
        if (progressInterval) {
            clearInterval(progressInterval);
            progressInterval = null;
        }
        
        // Don't show results section yet
        $('#batch-status').html('<span class="loading-spinner"></span>Finalizing results...');
        $('.progress-bar-fill').css('width', '100%');
        $('.progress-text').text('100%');
        
        // Wait a moment for the database to be fully updated
        setTimeout(function() {
            loadBatchResults();
        }, 2000); // 2 second delay
        
        resetButtons();
    }
    
    /**
     * On batch cancelled
     */
    function onBatchCancelled() {
        console.log('Batch cancelled');
        
        if (progressInterval) {
            clearInterval(progressInterval);
            progressInterval = null;
        }
        
        $('#batch-status').text('Processing cancelled.');
        resetButtons();
    }
    
    /**
     * Cancel batch process
     */
    function cancelBatchProcess() {
        if (!currentBatchId) return;
        
        if (confirm('Are you sure you want to cancel the current process?')) {
            $('#cancel-batch').prop('disabled', true);
            
            $.ajax({
                url: wpPluginDataFetcher.rest_url + '/batch/cancel/' + currentBatchId,
                method: 'POST',
                dataType: 'json',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', wpPluginDataFetcher.nonce);
                },
                success: function(response) {
                    console.log('Batch cancelled:', response);
                    onBatchCancelled();
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error('Cancel failed:', textStatus, errorThrown);
                    handleError('Failed to cancel batch');
                    $('#cancel-batch').prop('disabled', false);
                }
            });
        }
    }
    
    /**
     * Load batch results for preview
     */
    function loadBatchResults(retryCount = 0) {
        if (!currentBatchId) return;
        
        const maxRetries = 3;
        
        // Show loading status
        $('#batch-status').html('<span class="loading-spinner"></span>Loading results...');
        
        // Reset pagination
        pagination = {
            reviews: { page: 1, per_page: 20, loading: false },
            support: { page: 1, per_page: 20, loading: false }
        };
        
        // Load preview data with pagination
        $.ajax({
            url: wpPluginDataFetcher.rest_url + '/batch/download/' + currentBatchId,
            method: 'GET',
            data: { 
                type: 'all',
                page: 1,
                per_page: 20
            },
            dataType: 'json',
            timeout: 30000,
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', wpPluginDataFetcher.nonce);
            },
            success: function(response) {
                console.log('Results loaded:', response);
                
                // Store the data
                batchData = response.data;
                
                // Store pagination info
                if (response.pagination) {
                    pagination.reviews = response.pagination.reviews || { page: 1, per_page: 20 };
                    pagination.support = response.pagination.support || { page: 1, per_page: 20 };
                }
                
                // Create summary
                let summary = '<div class="results-summary-content">';
                summary += '<h4>Processing Complete</h4>';
                
                // Plugin info
                if (batchData.details) {
                    summary += '<p><strong>Plugin:</strong> ' + escapeHtml(batchData.details.name || response.plugin_slug || 'Unknown') + '</p>';
                    if (batchData.details.version) {
                        summary += '<p><strong>Version:</strong> ' + escapeHtml(batchData.details.version) + '</p>';
                    }
                }
                
                // Reviews info
                if (batchData.reviews) {
                    const reviewCount = batchData.reviews.review_count || 0;
                    summary += '<p><strong>Reviews fetched:</strong> ' + reviewCount + '</p>';
                    
                    if (reviewCount > 20) {
                        summary += '<p><em>Showing first 20 of ' + reviewCount + ' reviews. Use "Load More" to see additional reviews.</em></p>';
                    }
                    
                    if (batchData.reviews.reviews && batchData.reviews.reviews.length > 0) {
                        const avgRating = calculateAverageRating(batchData.reviews.reviews);
                        if (avgRating > 0) {
                            summary += '<p><strong>Average rating (sample):</strong> ' + avgRating.toFixed(1) + '/5</p>';
                        }
                    }
                }
                
                // Support info
                if (batchData.support) {
                    const ticketCount = batchData.support.ticket_count || 0;
                    summary += '<p><strong>Support threads fetched:</strong> ' + ticketCount + '</p>';
                    
                    if (ticketCount > 20) {
                        summary += '<p><em>Showing first 20 of ' + ticketCount + ' tickets. Use "Load More" to see additional tickets.</em></p>';
                    }
                }
                
                summary += '<p class="download-notice"><strong>Note:</strong> Use the download buttons to save complete results.</p>';
                summary += '</div>';
                
                $('#results-summary').html(summary);
                
                // Display JSON in tabs with pagination
                displayJSONInTabs();
                
                // Update preview
                updatePreviewContent('details');
                
                // Update status to complete
                $('#batch-status').text('Processing complete!');
                
                // Show the results section
                $('#batch-results').removeClass('hidden');
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('Load results failed:', textStatus, errorThrown);
                
                if (retryCount < maxRetries - 1) {
                    setTimeout(function() {
                        loadBatchResults(retryCount + 1);
                    }, 2000);
                } else {
                    // Show error message with download option
                    $('#batch-status').html('<span class="error">Unable to load preview. Data may be too large.</span>');
                    
                    let summary = '<div class="results-summary-content">';
                    summary += '<h4>Processing Complete</h4>';
                    summary += '<p class="error">Unable to load data preview. The data has been processed successfully.</p>';
                    summary += '<p><strong>Please use the download buttons below to save your results.</strong></p>';
                    summary += '</div>';
                    
                    $('#results-summary').html(summary);
                    $('#batch-results').removeClass('hidden');
                }
            }
        });
    }
    
    /**
     * Load more items for a specific type
     */
    function loadMoreItemsInternal(type, isPreview) {
        if (!currentBatchId || pagination[type].loading) return;
        
        // For preview, we might need to show more already loaded items first
        if (isPreview) {
            const currentData = type === 'reviews' ? batchData.reviews.reviews : batchData.support.tickets;
            const currentlyShowing = $('#preview-content .review-item, #preview-content .ticket-item').length;
            
            // If we have more loaded items to show, just show them
            if (currentlyShowing < currentData.length) {
                const nextBatch = Math.min(currentlyShowing + 10, currentData.length);
                if (type === 'reviews') {
                    updatePreviewContent('reviews');
                } else {
                    updatePreviewContent('support');
                }
                return;
            }
        }
        
        // Otherwise, load more from API
        pagination[type].loading = true;
        pagination[type].page++;
        
        const loadMoreBtn = isPreview ? 
            $('.load-more-preview-btn[data-type="' + type + '"]') : 
            $('.load-more-btn[data-type="' + type + '"]');
        
        loadMoreBtn.text('Loading...').prop('disabled', true);
        
        $.ajax({
            url: wpPluginDataFetcher.rest_url + '/batch/download/' + currentBatchId,
            method: 'GET',
            data: { 
                type: type,
                page: pagination[type].page,
                per_page: pagination[type].per_page
            },
            dataType: 'json',
            timeout: 30000,
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', wpPluginDataFetcher.nonce);
            },
            success: function(response) {
                console.log('More items loaded:', response);
                
                // Append new items to existing data
                if (type === 'reviews' && response.data.reviews) {
                    batchData.reviews.reviews = batchData.reviews.reviews.concat(response.data.reviews);
                } else if (type === 'support' && response.data.tickets) {
                    batchData.support.tickets = batchData.support.tickets.concat(response.data.tickets);
                }
                
                // Update pagination info
                pagination[type] = response.pagination;
                pagination[type].loading = false;
                
                // Update display based on context
                if (isPreview) {
                    // Update preview
                    updatePreviewContent(type);
                } else {
                    // Update tab
                    if (type === 'reviews') {
                        displayReviewsTab();
                    } else if (type === 'support') {
                        displaySupportTab();
                    }
                }
                
                // Check if we need to update both if they're showing the same data
                const activeTab = $('.data-tab.active').data('tab');
                const previewType = $('#preview-type').val();
                
                if (activeTab === type && !isPreview) {
                    // Tab was updated, check if preview needs update too
                    if (previewType === type) {
                        updatePreviewContent(type);
                    }
                } else if (previewType === type && isPreview) {
                    // Preview was updated, check if tab needs update too
                    if (activeTab === type) {
                        if (type === 'reviews') {
                            displayReviewsTab();
                        } else {
                            displaySupportTab();
                        }
                    }
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('Load more failed:', textStatus, errorThrown);
                pagination[type].loading = false;
                pagination[type].page--; // Revert page number
                loadMoreBtn.text('Load More (Retry)').prop('disabled', false);
                handleError('Failed to load more items. Please try again.');
            }
        });
    }
    
    /**
     * Display JSON data in tabs
     */
    function displayJSONInTabs() {
        if (!batchData) {
            batchData = {};
        }
        
        // Details tab
        if (batchData.details) {
            const detailsJSON = JSON.stringify(batchData.details, null, 2);
            $('#json-details').text(detailsJSON).removeClass('empty');
        } else {
            $('#json-details').text('{\n  "message": "No plugin details fetched"\n}').addClass('empty');
        }
        
        // Reviews tab
        displayReviewsTab();
        
        // Support tab
        displaySupportTab();
    }
    
    function displayReviewsTab() {
        const container = $('#tab-reviews');
        
        if (batchData.reviews) {
            const reviewCount = batchData.reviews.review_count || 0;
            let summaryText = 'Total: ' + reviewCount + ' reviews';
            
            if (reviewCount > 0 && batchData.reviews.reviews) {
                const avgRating = calculateAverageRating(batchData.reviews.reviews);
                if (avgRating > 0) {
                    summaryText += ', Average rating (showing ' + batchData.reviews.reviews.length + '): ' + avgRating.toFixed(1) + '/5';
                }
            }
            
            // Update or create summary
            let summaryEl = container.find('.review-summary');
            if (summaryEl.length === 0) {
                summaryEl = $('<div class="review-summary"></div>');
                container.prepend(summaryEl);
            }
            summaryEl.text(summaryText);
            
            // Display JSON with proper formatting
            const reviewsJSON = JSON.stringify(batchData.reviews, null, 2);
            let jsonViewer = container.find('#json-reviews');
            if (jsonViewer.length === 0) {
                jsonViewer = $('<pre class="json-viewer" id="json-reviews"></pre>');
                container.append(jsonViewer);
            }
            jsonViewer.text(reviewsJSON).removeClass('empty');
            
            // Remove old load more button
            container.find('.load-more-container').remove();
            
            // Add load more button if needed
            if (pagination.reviews && pagination.reviews.has_more) {
                const loadMoreHtml = '<div class="load-more-container">' +
                                '<button class="button load-more-btn" data-type="reviews">' +
                                'Load More Reviews</button></div>';
                container.append(loadMoreHtml);
            }
        } else {
            $('#json-reviews').text('{\n  "review_count": 0,\n  "reviews": []\n}').addClass('empty');
            container.find('.review-summary').text('No reviews fetched');
        }
    }

    /**
     * Display support tab with pagination
     */
    function displaySupportTab() {
        const container = $('#tab-support');
        
        if (batchData.support) {
            const ticketCount = batchData.support.ticket_count || 0;
            let summaryText = 'Total: ' + ticketCount + ' tickets (showing ' + 
                            batchData.support.tickets.length + ')';
            
            // Update or create summary
            let summaryEl = container.find('.support-summary');
            if (summaryEl.length === 0) {
                summaryEl = $('<div class="support-summary"></div>');
                container.prepend(summaryEl);
            }
            summaryEl.html('<div>' + summaryText + '</div>');
            
            // Display JSON with proper formatting
            const supportJSON = JSON.stringify(batchData.support, null, 2);
            let jsonViewer = container.find('#json-support');
            if (jsonViewer.length === 0) {
                jsonViewer = $('<pre class="json-viewer" id="json-support"></pre>');
                container.append(jsonViewer);
            }
            jsonViewer.text(supportJSON).removeClass('empty');
            
            // Remove old load more button
            container.find('.load-more-container').remove();
            
            // Add load more button if needed
            if (pagination.support && pagination.support.has_more) {
                const loadMoreHtml = '<div class="load-more-container">' +
                                '<button class="button load-more-btn" data-type="support">' +
                                'Load More Tickets</button></div>';
                container.append(loadMoreHtml);
            }
        } else {
            $('#json-support').text('{\n  "ticket_count": 0,\n  "tickets": []\n}').addClass('empty');
            container.find('.support-summary').html('<div>No support tickets fetched</div>');
        }
    }
    
    /**
     * Calculate average rating
     */
    function calculateAverageRating(reviews) {
        if (!reviews || !Array.isArray(reviews) || reviews.length === 0) {
            return 0;
        }
        
        let totalRating = 0;
        let ratedReviews = 0;
        
        reviews.forEach(function(review) {
            if (review.rating && !isNaN(review.rating)) {
                totalRating += parseInt(review.rating);
                ratedReviews++;
            }
        });
        
        return ratedReviews > 0 ? totalRating / ratedReviews : 0;
    }
    
    /**
     * Copy JSON to clipboard
     */
    function copyToClipboard(type) {
        let textToCopy = '';
        
        switch(type) {
            case 'details':
                textToCopy = batchData.details ? JSON.stringify(batchData.details, null, 2) : '{}';
                break;
            case 'reviews':
                textToCopy = batchData.reviews ? JSON.stringify(batchData.reviews, null, 2) : '{}';
                break;
            case 'support':
                textToCopy = batchData.support ? JSON.stringify(batchData.support, null, 2) : '{}';
                break;
            default:
                return;
        }
        
        // Create temporary textarea
        const textarea = document.createElement('textarea');
        textarea.value = textToCopy;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        
        // Select and copy
        textarea.select();
        document.execCommand('copy');
        
        // Remove textarea
        document.body.removeChild(textarea);
        
        // Show success message
        const button = $('[data-type="' + type + '"].copy-json');
        const originalText = button.text();
        button.text('Copied!');
        setTimeout(function() {
            button.text(originalText);
        }, 2000);
    }
    
    /**
     * Update preview content
     */
    function updatePreviewContent(type) {
        let content = '<div class="no-data">No data available</div>';
        
        switch(type) {
            case 'details':
                if (batchData.details) {
                    content = renderDetailsPreview(batchData.details);
                }
                break;
                
            case 'reviews':
                if (batchData.reviews && batchData.reviews.reviews) {
                    content = renderReviewsPreview(batchData.reviews.reviews);
                }
                break;
                
            case 'support':
                if (batchData.support && batchData.support.tickets) {
                    content = renderSupportPreview(batchData.support.tickets);
                }
                break;
        }
        
        $('#preview-content').html(content);
    }
    
    /**
     * Render details preview
     */
    function renderDetailsPreview(details) {
        let html = '<div class="plugin-details-preview">';
        
        if (details.name) {
            html += '<h3>' + escapeHtml(details.name) + '</h3>';
        }
        
        if (details.short_description) {
            html += '<p class="description">' + escapeHtml(details.short_description) + '</p>';
        }
        
        html += '<div class="details-grid">';
        
        if (details.version) {
            html += '<div class="detail-item"><strong>Version:</strong> ' + escapeHtml(details.version) + '</div>';
        }
        
        if (details.author) {
            html += '<div class="detail-item"><strong>Author:</strong> ' + details.author + '</div>';
        }
        
        if (details.active_installs) {
            html += '<div class="detail-item"><strong>Active Installs:</strong> ' + 
                    Number(details.active_installs).toLocaleString() + '+</div>';
        }
        
        if (details.rating) {
            html += '<div class="detail-item"><strong>Rating:</strong> ' + 
                    details.rating + '/100 (' + (details.num_ratings || 0) + ' ratings)</div>';
        }
        
        if (details.last_updated) {
            html += '<div class="detail-item"><strong>Last Updated:</strong> ' + details.last_updated + '</div>';
        }
        
        html += '</div>';
        
        if (details.sections && details.sections.description) {
            html += '<div class="detail-description">';
            html += '<h4>Description</h4>';
            html += details.sections.description;
            html += '</div>';
        }
        
        html += '</div>';
        
        return html;
    }
    
    /**
     * Render reviews preview with pagination
     */
    function renderReviewsPreview(reviews) {
        let html = '<div class="reviews-preview">';
        
        const totalCount = batchData.reviews ? batchData.reviews.review_count : reviews.length;
        const showingCount = Math.min(reviews.length, 10);
        
        html += '<p class="summary">Showing ' + showingCount + ' of ' + reviews.length + ' loaded reviews (Total: ' + totalCount + ')</p>';
        
        // Show up to 10 reviews
        for (let i = 0; i < showingCount; i++) {
            const review = reviews[i];
            html += '<div class="review-item">';
            html += '<div class="review-header">';
            html += '<span class="review-title">' + escapeHtml(review.title || 'Untitled') + '</span>';
            
            if (review.rating) {
                html += '<span class="review-rating">' + getStars(review.rating) + '</span>';
            }
            
            html += '</div>';
            
            if (review.author && review.author.name) {
                html += '<p class="review-meta">By ' + escapeHtml(review.author.name);
                if (review.date_formatted) {
                    html += ' on ' + escapeHtml(review.date_formatted);
                }
                html += '</p>';
            }
            
            if (review.description) {
                let desc = review.description.substring(0, 200);
                if (review.description.length > 200) desc += '...';
                html += '<p class="review-content">' + escapeHtml(desc) + '</p>';
            }
            
            html += '</div>';
        }
        
        if (showingCount < reviews.length) {
            html += '<p class="more-items">Showing first ' + showingCount + ' of ' + reviews.length + ' loaded reviews</p>';
        }
        
        // Add load more button for preview - separate from tabs
        const hasMoreToLoad = pagination.reviews && pagination.reviews.has_more;
        const hasMoreToShow = showingCount < reviews.length;
        
        if (hasMoreToLoad || hasMoreToShow) {
            html += '<div class="load-more-preview">';
            html += '<button class="button load-more-preview-btn" data-type="reviews" data-preview="true">Load More Reviews</button>';
            html += '</div>';
        }
        
        html += '</div>';
        
        return html;
    }
    
    /**
     * Render support preview with pagination
     */
    function renderSupportPreview(tickets) {
        let html = '<div class="support-preview">';
        
        const totalCount = batchData.support ? batchData.support.ticket_count : tickets.length;
        const showingCount = Math.min(tickets.length, 10);
        const resolved = tickets.slice(0, showingCount).filter(t => t.resolved).length;
        const open = showingCount - resolved;
        
        html += '<p class="summary">Showing ' + showingCount + ' of ' + tickets.length + 
                ' loaded tickets (Total: ' + totalCount + ') - ' + resolved + ' resolved, ' + open + ' open</p>';
        
        // Show up to 10 tickets
        for (let i = 0; i < showingCount; i++) {
            const ticket = tickets[i];
            html += '<div class="ticket-item">';
            html += '<div class="ticket-header">';
            html += '<span class="ticket-title">' + escapeHtml(ticket.title || 'Untitled') + '</span>';
            
            if (ticket.resolved) {
                html += '<span class="ticket-status resolved">Resolved</span>';
            } else {
                html += '<span class="ticket-status open">Open</span>';
            }
            
            html += '</div>';
            
            html += '<p class="ticket-meta">';
            if (ticket.author && ticket.author.name) {
                html += 'By ' + escapeHtml(ticket.author.name);
            }
            
            if (ticket.replies !== undefined) {
                html += ' · ' + ticket.replies + ' replies';
            }
            
            if (ticket.last_activity) {
                html += ' · Last activity: ' + escapeHtml(ticket.last_activity);
            }
            html += '</p>';
            
            if (ticket.initial_content) {
                let desc = ticket.initial_content.substring(0, 200);
                if (ticket.initial_content.length > 200) desc += '...';
                html += '<p class="ticket-content">' + escapeHtml(desc) + '</p>';
            }
            
            html += '</div>';
        }
        
        if (showingCount < tickets.length) {
            html += '<p class="more-items">Showing first ' + showingCount + ' of ' + tickets.length + ' loaded tickets</p>';
        }
        
        // Add load more button for preview - separate from tabs
        const hasMoreToLoad = pagination.support && pagination.support.has_more;
        const hasMoreToShow = showingCount < tickets.length;
        
        if (hasMoreToLoad || hasMoreToShow) {
            html += '<div class="load-more-preview">';
            html += '<button class="button load-more-preview-btn" data-type="support" data-preview="true">Load More Tickets</button>';
            html += '</div>';
        }
        
        html += '</div>';
        
        return html;
    }
    
   /**
    * Download results
    */
   function downloadResults(type) {
       if (!currentBatchId) return;
       
       // For actual download, append download flag
       const url = wpPluginDataFetcher.rest_url + '/batch/download/' + currentBatchId + '?type=' + type + '&download=true&_wpnonce=' + wpPluginDataFetcher.nonce;
       
       // Show loading message
       const button = $('[data-type="' + type + '"].download-json');
       const originalText = button.text();
       button.text('Preparing download...').prop('disabled', true);
       
       // Create temporary link and click it
       const link = document.createElement('a');
       link.href = url;
       link.setAttribute('download', '');
       
       document.body.appendChild(link);
       link.click();
       link.remove();
       
       // Reset button after a delay
       setTimeout(function() {
           button.text(originalText).prop('disabled', false);
       }, 2000);
   }
   
   /**
    * Reset UI
    */
   function resetUI() {
       $('#batch-progress').addClass('hidden');
       $('#batch-results').addClass('hidden');
       $('.progress-bar-fill').css('width', '0%');
       $('.progress-text').text('0%');
       $('#batch-status').text('');
       $('#batch-info').html('');
       $('#results-summary').html('');
       
       // Clear JSON viewers
       $('#json-details').text('').removeClass('empty');
       $('#json-reviews').text('').removeClass('empty');
       $('#json-support').text('').removeClass('empty');
       
       // Reset pagination
       pagination = {
           reviews: { page: 1, per_page: 20, loading: false },
           support: { page: 1, per_page: 20, loading: false }
       };
       
       // Clear batch data
       batchData = {};
   }
   
   /**
    * Reset buttons
    */
   function resetButtons() {
       $('#start-batch').prop('disabled', false).text('Start Fetching');
       $('#cancel-batch').hide().prop('disabled', false);
   }
   
   /**
    * Handle errors
    */
   function handleError(message) {
       console.error('Error:', message);
       $('#batch-status').html('<span class="error">' + message + '</span>');
       console.log(message);
   }
   
   /**
    * Escape HTML to prevent XSS
    */
   function escapeHtml(text) {
       if (!text) return '';
       const div = document.createElement('div');
       div.textContent = text;
       return div.innerHTML;
   }
   
   /**
    * Generate star rating display
    */
   function getStars(rating) {
       let stars = '';
       rating = parseInt(rating) || 0;
       for (let i = 1; i <= 5; i++) {
           stars += i <= rating ? '★' : '☆';
       }
       return stars;
   }
   
})(jQuery);