import React from 'react';

const TabNavigation = ({ activeTab, setActiveTab }) => {
  const tabs = [
    { id: 'details', label: 'Plugin Details' },
    { id: 'reviews', label: 'Reviews' },
    { id: 'support', label: 'Support' },
    { id: 'preview', label: 'Preview' }
  ];

  const handleTabClick = (tabId) => {
    console.log('Tab clicked:', tabId);
    setActiveTab(tabId);
  };

  return (
    <div className="data-tabs">
      {tabs.map(tab => (
        <button
          key={tab.id}
          className={`data-tab ${activeTab === tab.id ? 'active' : ''}`}
          onClick={() => handleTabClick(tab.id)}
          data-tab={tab.id}
        >
          {tab.label}
        </button>
      ))}
    </div>
  );
};

export default TabNavigation;