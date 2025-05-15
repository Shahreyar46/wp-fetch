import React, { useState } from 'react';

const PluginForm = ({ onSubmit, isProcessing, onCancel, error }) => {
  const [formData, setFormData] = useState({
    plugin_slug: '',
    fetch_details: true,
    fetch_reviews: true,
    fetch_support: true,
    max_items: 100,
    fetch_full_content: false
  });

  const handleSubmit = (e) => {
    e.preventDefault();
    
    if (!formData.plugin_slug.trim()) {
      alert('Please enter a plugin slug.');
      return;
    }
    
    onSubmit(formData);
  };

  const handleInputChange = (e) => {
    const { name, value, type, checked } = e.target;
    setFormData(prev => ({
      ...prev,
      [name]: type === 'checkbox' ? checked : value
    }));
  };

  return (
    <div className="card">
      <h2>Fetch Plugin Data</h2>
      <p>Enter a WordPress.org plugin slug to fetch its data, reviews, and support threads.</p>
      
      {error && (
        <div className="error-message">{error}</div>
      )}
      
      <form onSubmit={handleSubmit}>
        <div className="form-group">
          <label htmlFor="plugin-slug">Plugin Slug:</label>
          <input
            type="text"
            id="plugin-slug"
            name="plugin_slug"
            value={formData.plugin_slug}
            onChange={handleInputChange}
            placeholder="wp-file-manager"
            className="regular-text"
            disabled={isProcessing}
          />
          <p className="description">
            The slug is the last part of the plugin URL. For example, for https://wordpress.org/plugins/wp-file-manager/, the slug is "wp-file-manager".
          </p>
        </div>
        
        <div className="form-group">
          <label>Data to fetch:</label>
          <div className="checkbox-group">
            <label>
              <input
                type="checkbox"
                name="fetch_details"
                checked={formData.fetch_details}
                onChange={handleInputChange}
                disabled={isProcessing}
              />
              Plugin Details
            </label>
            <label>
              <input
                type="checkbox"
                name="fetch_reviews"
                checked={formData.fetch_reviews}
                onChange={handleInputChange}
                disabled={isProcessing}
              />
              Reviews
            </label>
            <label>
              <input
                type="checkbox"
                name="fetch_support"
                checked={formData.fetch_support}
                onChange={handleInputChange}
                disabled={isProcessing}
              />
              Support Threads
            </label>
          </div>
        </div>
        
        <div className="form-group">
          <label htmlFor="max-items">Maximum Items to Fetch:</label>
          <select
            id="max-items"
            name="max_items"
            value={formData.max_items}
            onChange={handleInputChange}
            className="regular-text"
            disabled={isProcessing}
          >
            <option value="10">10 items</option>
            <option value="50">50 items</option>
            <option value="100">100 items</option>
            <option value="200">200 items</option>
            <option value="500">500 items</option>
            <option value="1000">1,000 items</option>
            <option value="2000">2,000 items</option>
            <option value="5000">5,000 items</option>
            <option value="999999">All items</option>
          </select>
          <p className="description">
            Number of reviews and support threads to fetch. Large numbers are processed in batches.
          </p>
        </div>
        
        <div className="form-group">
          <label>
            <input
              type="checkbox"
              name="fetch_full_content"
              checked={formData.fetch_full_content}
              onChange={handleInputChange}
              disabled={isProcessing}
            />
            Fetch full content (includes descriptions, slower)
          </label>
        </div>
        
        <div className="button-group">
          <button
            type="submit"
            className="button button-primary"
            disabled={isProcessing}
          >
            {isProcessing ? 'Processing...' : 'Start Fetching'}
          </button>
          {isProcessing && (
            <button
              type="button"
              id="cancel-batch"
              className="button"
              onClick={onCancel}
            >
              Cancel
            </button>
          )}
        </div>
      </form>
    </div>
  );
};

export default PluginForm;