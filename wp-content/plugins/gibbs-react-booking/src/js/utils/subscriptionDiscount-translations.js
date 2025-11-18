import en from '../translations/subscriptionDiscount-en.js';
import no from '../translations/subscriptionDiscount-no.js';

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

const DEFAULT_LANGUAGE = 'en';

function getCurrentLanguage() {
  const docLang = document?.documentElement?.lang;
  if (docLang && translations[docLang]) {
    return docLang;
  }

  const storedLang = typeof window !== 'undefined' ? window.localStorage.getItem('rmp_language') : null;
  if (storedLang && translations[storedLang]) {
    return storedLang;
  }

  if (typeof window !== 'undefined' && window.rmpLocale && translations[window.rmpLocale]) {
    return window.rmpLocale;
  }

  return DEFAULT_LANGUAGE;
}

export function Ltext(text) {
  const lang = getCurrentLanguage();
  const catalog = translations[lang] || translations[DEFAULT_LANGUAGE];

  if (catalog[text]) {
    return catalog[text];
  }

  const lower = text.toLowerCase();
  for (const key in catalog) {
    if (key.toLowerCase() === lower) {
      return catalog[key];
    }
  }

  return text;
}

export function getLanguage() {
  return getCurrentLanguage();
}

export default Ltext;

