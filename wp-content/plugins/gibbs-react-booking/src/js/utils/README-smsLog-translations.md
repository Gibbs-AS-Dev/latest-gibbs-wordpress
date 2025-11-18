# SMS Log Translations

This directory contains the standalone translation system for the SMS Log component.

## Files

- `smsLog-translations.js` - Main translation utility (similar to wallet-translations.js)
- `../translations/smsLog-en.js` - English translations
- `../translations/smsLog-no.js` - Norwegian translations

## Usage

### Basic Translation

```javascript
import { Ltext } from '../utils/smsLog-translations';

// Use the Ltext function to translate strings
const translatedText = Ltext("SMS Logs");
```

### Available Functions

- `Ltext(text)` - Main translation function
- `getLanguage()` - Get current language
- `setLanguage(lang)` - Set language
- `getAvailableLanguages()` - Get list of supported languages
- `isLanguageSupported(lang)` - Check if language is supported
- `__dynamicText(text, replacements)` - Translation with placeholders

### Language Detection

The system automatically detects the language from:
1. Document `lang` attribute
2. LocalStorage (`rmp_language`)
3. WordPress locale (`window.rmpLocale`)
4. Falls back to English

### Adding New Languages

1. Create a new translation file (e.g., `smsLog-de.js`)
2. Add the language to the translations object in `smsLog-translations.js`
3. Import the new translation file

### Example Translation File

```javascript
// smsLog-de.js
export default {
  "SMS Logs": "SMS-Protokolle",
  "Loading SMS logs...": "SMS-Protokolle werden geladen...",
  // ... more translations
};
```

### Integration with Components

```javascript
import { Ltext, getLanguage } from '../utils/smsLog-translations';

function MyComponent() {
  const currentLang = getLanguage();
  
  return (
    <div>
      <h1>{Ltext("SMS Logs")}</h1>
      <p>{Ltext("Loading SMS logs...")}</p>
    </div>
  );
}
```

## Supported Languages

- English (`en`, `en-US`, `en-GB`)
- Norwegian (`no`, `nb`, `nb-NO`, `nn`, `nn-NO`)

## Notes

- This is a standalone system, separate from wallet-translations.js
- Uses the same localStorage key (`rmp_language`) for consistency
- Automatically handles language fallbacks
- Case-insensitive matching for better translation lookup 