import React from 'react';
import { setLanguage, getLanguage, getAvailableLanguages } from '../utils/translations';

const LanguageSwitcher = () => {
  const currentLang = getLanguage();
  const availableLanguages = getAvailableLanguages();

  const handleLanguageChange = (event) => {
    const newLanguage = event.target.value;
    setLanguage(newLanguage);
    // Force re-render by dispatching a custom event
    window.dispatchEvent(new CustomEvent('rmpLanguageChanged', { 
      detail: { language: newLanguage } 
    }));
  };

  const languageNames = {
    'en': 'English',
    'no': 'Norsk',
    'nb': 'Norsk Bokmål',
    'nn': 'Norsk Nynorsk'
  };

  return (
    <div className="rmp-language-switcher" style={{ 
      position: 'absolute', 
      top: '10px', 
      right: '10px',
      zIndex: 1000 
    }}>
      <select 
        value={currentLang} 
        onChange={handleLanguageChange}
        style={{
          padding: '5px 10px',
          borderRadius: '4px',
          border: '1px solid #ddd',
          fontSize: '12px',
          backgroundColor: '#fff'
        }}
      >
        {availableLanguages.map(lang => (
          <option key={lang} value={lang}>
            {languageNames[lang] || lang}
          </option>
        ))}
      </select>
    </div>
  );
};

export default LanguageSwitcher; 