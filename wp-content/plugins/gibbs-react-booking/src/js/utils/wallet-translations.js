import en from '../translations/wallet-en.js';
import no from '../translations/wallet-no.js';

// Language mapping
const translations = {
  'en': en,
  'en-US': en,
  'en-GB': en,
  'no': no,
  'nb': no,
  'nb-NO': no,
  'nn': no,
  'nn-NO': no
};

// Default language
const DEFAULT_LANGUAGE = 'en';

/**
 * Get current language from document or localStorage
 * @returns {string} Language code
 */
function getCurrentLanguage() {
  // Try to get from document lang attribute
  const docLang = document.documentElement.lang;
  if (docLang && translations[docLang]) {
    return docLang;
  }
  
  // Try to get from localStorage
  const storedLang = localStorage.getItem('rmp_language');
  if (storedLang && translations[storedLang]) {
    return storedLang;
  }
  
  // Try to get from WordPress locale (if available)
  if (typeof window.rmpLocale !== 'undefined' && translations[window.rmpLocale]) {
    return window.rmpLocale;
  }
  
  // Fallback to default
  return DEFAULT_LANGUAGE;
}

/**
 * Translation function similar to WordPress __(), now called Ltext
 * @param {string} text - Text to translate
 * @param {string} context - Optional context (not used in this implementation)
 * @returns {string} Translated text
 */
export function Ltext(text, context = '') {
  const currentLang = getCurrentLanguage();
  //alert(currentLang);
  const langTranslations = translations[currentLang] || translations[DEFAULT_LANGUAGE];
  
  // First try exact match
  if (langTranslations[text]) {
    return langTranslations[text];
  }
  
  // If no exact match, try case-insensitive match
  const textLower = text.toLowerCase();
  for (const key in langTranslations) {
    if (key.toLowerCase() === textLower) {
      return langTranslations[key];
    }
  }
  
  // Return original text if no match found
  return text;
}

/**
 * Translation function with sprintf-like functionality
 * @param {string} text - Text to translate with placeholders
 * @param {Object} replacements - Object with replacement values
 * @returns {string} Translated text with replacements
 */
export function __dynamicText(text, replacements = {}) {
  let translatedText = Ltext(text);
  
  // Replace placeholders like {key} with values
  Object.keys(replacements).forEach(key => {
    const placeholder = `{${key}}`;
    translatedText = translatedText.replace(new RegExp(placeholder, 'g'), replacements[key]);
  });
  
  return translatedText;
}

export function __extraText(text) {
  let translatedText = Ltext(text);
  const currentLang = getCurrentLanguage();
  const langTranslations = translations[currentLang] || translations[DEFAULT_LANGUAGE]; 
  
  Object.keys(langTranslations).forEach(key => {
    // Case-insensitive replacement
    const regex = new RegExp(key, 'gi');
    if(translatedText.toLowerCase().includes(key.toLowerCase())){
      translatedText = translatedText.replace(regex, langTranslations[key]);
    }
  });
  
  return translatedText;
}

/**
 * Set language for the application
 * @param {string} language - Language code
 */
export function setLanguage(language) {
  if (translations[language]) {
    localStorage.setItem('rmp_language', language);
    // Trigger a custom event so components can react to language changes
    window.dispatchEvent(new CustomEvent('rmpLanguageChanged', { detail: { language } }));
  }
}

/**
 * Get available languages
 * @returns {Array} Array of available language codes
 */
export function getAvailableLanguages() {
  return Object.keys(translations);
}

/**
 * Get current language
 * @returns {string} Current language code
 */
export function getLanguage() {
  return getCurrentLanguage();
}

/**
 * Check if a language is supported
 * @param {string} language - Language code to check
 * @returns {boolean} True if language is supported
 */
export function isLanguageSupported(language) {
  return !!translations[language];
}

// Export default translation function
export default Ltext;