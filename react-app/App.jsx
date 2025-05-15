import React, { useState } from 'react';
import axios from 'axios';
import PluginForm from './components/PluginForm';
import ProgressBar from './components/ProgressBar';
import ResultsSection from './components/ResultsSection';

const App = () => {
  const [isProcessing, setIsProcessing] = useState(false);
  const [currentBatchId, setCurrentBatchId] = useState(null);
  const [progressData, setProgressData] = useState({
    progress: 0,
    status: '',
    current: 0,
    total: 0,
    currentType: ''
  });
  const [batchResults, setBatchResults] = useState(null);
  const [error, setError] = useState(null);

  // Create axios instance with WordPress nonce
  const api = axios.create({
    baseURL: window.wpPluginDataFetcher.rest_url,
    headers: {
      'X-WP-Nonce': window.wpPluginDataFetcher.nonce,
      'Content-Type': 'application/json'
    }
  });

  const startBatchProcess = async (formData) => {
    setIsProcessing(true);
    setError(null);
    setBatchResults(null);
    
    try {
      const response = await api.post('/batch/start', formData);
      const { batch_id } = response.data;
      setCurrentBatchId(batch_id);
      
      // Start monitoring progress
      startProgressMonitoring(batch_id);
    } catch (err) {
      setError('Failed to start batch process: ' + err.message);
      setIsProcessing(false);
    }
  };

  const startProgressMonitoring = (batchId) => {
    const checkStatus = async () => {
      try {
        const statusResponse = await api.get(`/batch/status/${batchId}`);
        const data = statusResponse.data;
        
        setProgressData({
          progress: data.progress || 0,
          status: getStatusText(data),
          current: data.current || 0,
          total: data.total || 0,
          currentType: data.current_type || ''
        });

        if (data.status === 'running') {
          // Process next batch
          await api.post(`/batch/process/${batchId}`);
          // Continue monitoring
          setTimeout(() => checkStatus(), 2000);
        } else if (data.status === 'completed') {
          onBatchComplete(batchId);
        } else if (data.status === 'cancelled') {
          setIsProcessing(false);
        }
      } catch (err) {
        setError('Failed to check batch status');
        setIsProcessing(false);
      }
    };

    checkStatus();
  };

  const getStatusText = (data) => {
    if (!data.current_type) return 'Processing...';
    
    let text = `Fetching ${data.current_type}...`;
    if (data.current !== undefined && data.total !== undefined && data.total > 0) {
      text += ` (${data.current} of ${data.total})`;
    }
    console.log('Status text:', text, 'Data:', data);
    return text;
  };

  const onBatchComplete = async (batchId) => {
    setProgressData(prev => ({ ...prev, progress: 100, status: 'Processing complete!' }));
    
    // Wait a moment for database to be fully updated
    setTimeout(async () => {
      try {
        const resultsResponse = await api.get(`/batch/download/${batchId}`, {
          params: { type: 'all', page: 1, per_page: 20 }
        });
        console.log('Batch complete results:', resultsResponse.data);
        setBatchResults(resultsResponse.data);
        setIsProcessing(false);
      } catch (err) {
        setError('Failed to load results: ' + err.message);
        setIsProcessing(false);
      }
    }, 2000);
  };

  const cancelBatch = async () => {
    if (!currentBatchId || !window.confirm('Are you sure you want to cancel the current process?')) {
      return;
    }

    try {
      await api.post(`/batch/cancel/${currentBatchId}`);
      setIsProcessing(false);
      setProgressData(prev => ({ ...prev, status: 'Processing cancelled.' }));
    } catch (err) {
      setError('Failed to cancel batch');
    }
  };

  const downloadResults = async (type) => {
    if (!currentBatchId) return;
    
    const url = `${window.wpPluginDataFetcher.rest_url}/batch/download/${currentBatchId}?type=${type}&download=true&_wpnonce=${window.wpPluginDataFetcher.nonce}`;
    
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', '');
    document.body.appendChild(link);
    link.click();
    link.remove();
  };

  return (
    <div className="wrap">
      <h1>WordPress Plugin Data Fetcher</h1>
      
      <PluginForm 
        onSubmit={startBatchProcess}
        isProcessing={isProcessing}
        onCancel={cancelBatch}
        error={error}
      />
      
      {isProcessing && (
        <ProgressBar 
          progress={progressData.progress}
          status={progressData.status}
          current={progressData.current}
          total={progressData.total}
          currentType={progressData.currentType}
        />
      )}
      
      {batchResults && !isProcessing && (
        <ResultsSection 
          results={batchResults}
          batchId={currentBatchId}
          api={api}
          onDownload={downloadResults}
        />
      )}
    </div>
  );
};

export default App;