/**
 * Escape HTML to prevent XSS
 */
export function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Generate star rating display
 */
export function getStars(rating) {
    let stars = '';
    rating = parseInt(rating) || 0;
    for (let i = 1; i <= 5; i++) {
        stars += i <= rating ? '★' : '☆';
    }
    return stars;
}

/**
 * Calculate average rating
 */
export function calculateAverageRating(reviews) {
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
}