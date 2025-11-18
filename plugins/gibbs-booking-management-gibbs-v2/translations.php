<?php
/**
 * Norwegian Translations for Booking Statuses
 */

// Make sure we're not accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Direct translation function for booking statuses
 * 
 * Can be used directly in code: gibbs_translate('waiting')
 * 
 * @param string $text The text to translate
 * @return string Translated text or original if no translation exists
 */
function gibbs_translate($text) {
    $translations = array(
        // Status translations
        'waiting' => 'Venter',
        'confirmed' => 'Bekreftet',
        'approved' => 'Godkjent',
        'paid' => 'Betalt',
        'expired' => 'Utløpt',
        'cancelled' => 'Kansellert',
        'declined' => 'Avslått',
        'all' => 'Alle',
        
        // Tab labels and other common phrases
        'Trenger oppmerksomhet' => 'Trenger oppmerksomhet',
        'Venter godkjenning' => 'Venter godkjenning',
        'Godkjent/Betalt' => 'Godkjent/Betalt',
        'Avslått/Utløpt' => 'Avslått/Utløpt',
        'Usendt faktura' => 'Usendt faktura',
        'Sendt faktura' => 'Sendt faktura',
        'Search' => 'Søk',
        'Filter' => 'Filtrer',
        'Close' => 'Lukk',
        'Save' => 'Lagre',
        'Customer' => 'Kunde',
        'Action' => 'Handling'
    );
    
    return isset($translations[$text]) ? $translations[$text] : $text;
}

// Add Norwegian translations
function gibbs_booking_status_translations() {
    // Register the translations
    if (function_exists('pll_register_string')) {
        // For Polylang plugin
        pll_register_string('waiting', 'waiting', 'gibbs');
        pll_register_string('confirmed', 'confirmed', 'gibbs');
        pll_register_string('approved', 'approved', 'gibbs');
        pll_register_string('paid', 'paid', 'gibbs');
        pll_register_string('expired', 'expired', 'gibbs');
        pll_register_string('cancelled', 'cancelled', 'gibbs');
        pll_register_string('declined', 'declined', 'gibbs');
        pll_register_string('invoice', 'invoice', 'gibbs');
        pll_register_string('invoice_sent', 'invoice_sent', 'gibbs');
        pll_register_string('all', 'all', 'gibbs');
    }
    
    // Add filters for translation
    add_filter('gettext', 'gibbs_translate_booking_status', 10, 3);
}
add_action('init', 'gibbs_booking_status_translations');

/**
 * Translate booking statuses to Norwegian
 */
function gibbs_translate_booking_status($translation, $text, $domain) {
    if ($domain !== 'gibbs') {
        return $translation;
    }
    
    return gibbs_translate($text);
} 