<?php
global $wpdb;
$all_joined_groups_results = array();
$current_user = wp_get_current_user();  
$parent_user_id = $current_user->ID;
$sub_users = array();

$all_joined_groups_results = $wpdb->get_results( 
    $wpdb->prepare("SELECT * FROM {$wpdb->prefix}users_groups WHERE id IN (SELECT users_groups_id FROM {$wpdb->prefix}users_and_users_groups WHERE users_id = %d AND role IN (3,4,5) )", $current_user->ID), ARRAY_A
);
$group_ids = array();

if(isset($_GET['group'])) {
    // Handle comma-separated group IDs
    $group_param = $_GET['group'];
    $group_ids = array();
    
    if (is_array($group_param)) {
        // If it's still an array (for backward compatibility), join them
        $group_ids = $group_param;
    } else {
        // Split comma-separated values
        $group_ids = array_filter(array_map('trim', explode(',', $group_param)));
    }

    $filtered_groups = array_filter($all_joined_groups_results, function ($item) use ($group_ids) {
        return in_array($item['id'], $group_ids);
    });

}else{
    $filtered_groups = $all_joined_groups_results;
}

// Handle date range parameters
$start_date = '';
$end_date = '';
$display_date_range = '';
if(isset($_GET['start_date']) && isset($_GET['end_date'])) {
    $start_date = sanitize_text_field($_GET['start_date']);
    $end_date = sanitize_text_field($_GET['end_date']);
    
    // Convert YYYY-MM-DD to DD/MM/YYYY for display
    if ($start_date && $end_date) {
        $start_display = date('d/m/Y', strtotime($start_date));
        $end_display = date('d/m/Y', strtotime($end_date));
        $display_date_range = $start_display . ' - ' . $end_display;
    }
} else {
    // Set default date range: 3 months ago to today
    $default_start = date('Y-m-d', strtotime('-3 months'));
    $default_end = date('Y-m-d');
    $start_display = date('d/m/Y', strtotime('-3 months'));
    $end_display = date('d/m/Y');
    $display_date_range = $start_display . ' - ' . $end_display;
}

// Legacy month parameter support
$month = '';
if(isset($_GET['month'])) {
    $month = $_GET['month'];
}

?>

<style>
.saldo-main {
    margin-top: 2.5rem;
}
.saldo-filter-bar {
    display: flex;
    gap: 1rem;
    margin-bottom: 2rem;
    flex-wrap: nowrap;
    overflow-x: auto;
    align-items: center;
}
.saldo-filter-bar select {
    padding: 0.5rem 1rem;
    border-radius: 6px;
    border: 1px solid #ccc;
    font-size: 1.4rem;
}
.saldo-filter-bar input {
    padding: 0.5rem 1rem;
    border-radius: 6px;
    border: 1px solid #ccc;
    font-size: 1.4rem;
    min-width: 200px;
}
.filter-submit-btn {
    background: #008475;
    color: white;
    border: none;
    border-radius: 6px;
    padding: 0.5rem 1.5rem;
    font-size: 1.4rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    min-height: 42px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-top: -16px;
}
.filter-submit-btn:hover {
    background: #008475;
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(62, 193, 74, 0.3);
}
.filter-submit-btn:active {
    transform: translateY(0);
    box-shadow: 0 1px 4px rgba(62, 193, 74, 0.3);
}
.saldo-box-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 2rem;
}
@media (max-width: 700px) {
    .saldo-box-grid {
        grid-template-columns: 1fr;
    }
}
.saldo-box:hover {
    transform: translateY(-4px) scale(1.02);
    box-shadow: 0 8px 24px rgba(0,0,0,0.12);
}
.saldo-box-title {
    font-size: 1.6rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
    margin: 4px 13px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100%;
}
/* Select2 custom styles for the group filter */
.select2-container {
    width: 100% !important;
    min-height: 71px;
}
.select2-selection.select2-selection--multiple {
    border: 1px solid #ccc;
    border-radius: 6px;
    min-height: 56px !important;
    padding: 5px;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    height: 50px !important;
}
.select2-container--default .select2-selection--multiple .select2-selection__rendered {
    flex-wrap: wrap;
    align-items: center;
    gap: 2px;
    padding: 0;
}
.select2-container--default .select2-selection--multiple .select2-selection__choice {
    background-color: #008475;
    color: white;
    border: none;
    border-radius: 4px;
    padding: 2px 8px;
    margin: 1px;
    display: flex;
    align-items: center;
    font-size: 1.2rem;
}
.select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
    color: white;
    margin-right: 5px;
    font-weight: bold;
}
.select2-container--default .select2-selection--multiple .select2-selection__choice__remove:hover {
    color: #f0f0f0;
}
.select2-container--default .select2-search--inline {
    flex: 1;
    min-width: 100px;
}
.select2-container--default .select2-search--inline .select2-search__field {
    width: 100% !important;
    margin: 0;
    padding: 2px;
    border: none;
    outline: none;
    font-size: 1.2rem;
}
.select2-dropdown {
    border: 1px solid #ccc;
    border-radius: 6px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
.select2-container--default .select2-results__option--highlighted[aria-selected] {
    background-color: #008475;
    color: white;
}
.select2-container--default .select2-results__option[aria-selected=true] {
    background-color: #e8f5e8;
    color: #333;
}
/* Date range picker custom styles */
.daterangepicker {
    font-family: inherit;
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
}
.daterangepicker .ranges li {
    padding: 8px 12px;
    font-size: 1.3rem;
    border-radius: 4px;
    margin: 2px 0;
}
.daterangepicker .ranges li:hover {
    background-color: #f8f9fa;
}
.daterangepicker .ranges li.active {
    background-color: #008475;
    color: white;
}
.daterangepicker .drp-buttons {
    border-top: 1px solid #e9ecef;
    padding: 10px;
}
.daterangepicker .drp-selected {
    font-size: 1.2rem;
    color: #6c757d;
}
.daterangepicker .cancelBtn {
    background: #6c757d;
    border: none;
    color: white;
    padding: 6px 12px;
    border-radius: 4px;
    font-size: 1.2rem;
}
.daterangepicker .applyBtn {
    background: #008475;
    border: none;
    color: white;
    padding: 6px 12px;
    border-radius: 4px;
    font-size: 1.2rem;
}
.daterangepicker .cancelBtn:hover {
    background: #5a6268;
}
.daterangepicker .applyBtn:hover {
    background: #008475;
}
.daterangepicker select.monthselect {
    float: inline-start;
}
</style>

<div class="saldo-main">
    <div class="saldo-filter-bar">
        <select name="user_group[]" id="user_group" multiple class="select2_field" style="min-width: 200px;">
            <?php foreach ($all_joined_groups_results as $key => $user_group): ?>
                <option value="<?php echo esc_attr($user_group["id"]); ?>" <?php echo in_array($user_group["id"], $group_ids) ? 'selected' : ''; ?>><?php echo esc_html($user_group["name"]); ?></option>
            <?php endforeach; ?>
        </select>
        <input type="text" name="date_range" id="date_range" placeholder="Velg datoområde..." value="<?php echo esc_attr($display_date_range); ?>" readonly>
        <button type="button" id="filter_submit" class="filter-submit-btn">Filtrer</button>
    </div>

    <div class="saldo-box-grid" id="saldo-box-grid">
        <?php foreach ($filtered_groups as $key => $user_group): ?>

            <?php $group_saldos = get_group_saldo($user_group);
            // echo '<pre>';
            // print_r($group_saldos);
            // echo '</pre>';
            ?>
             <?php
                if(!empty($group_saldos)){
                    foreach($group_saldos as $data_bal_key => $group_saldo){
                ?> 
                    <div class="dashboard-stat color-1">
                        <div class="saldo-box-title"><?php echo esc_html($user_group['name']); ?></div>
                                
                                <div class="dashboard-stat-content wallet-totals"><h4><?php echo wc_price($group_saldo,array('currency'=>' ','decimal_separator' => '.' )) ;?></h4> <span><?php esc_html_e('Til utbetaling') ?> <strong class="wallet-currency"><?php echo $data_bal_key; ?></strong></span></div>
                            
                        
                    </div>
                    <?php } ?>  
            <?php }else{ ?>
                <div class="dashboard-stat color-1">
                    <div class="saldo-box-title"><?php echo esc_html($user_group['name']); ?></div>
                            
                    <div class="dashboard-stat-content wallet-totals"><h4>0</h4> <span><?php esc_html_e('Til utbetaling') ?> <strong class="wallet-currency">NOK</strong></span></div>
                </div>
            <?php } ?>
            <!-- <div class="saldo-box" data-group-id="<?php echo esc_attr($user_group['id']); ?>">
                <div class="saldo-box-title"><?php echo esc_html($user_group['name']); ?></div>
                <div class="saldo-box-amount-container d-flex flex-column justify-content-between">
                    <?php
                    if(!empty($group_saldos)){
                        foreach($group_saldos as $data_bal_key => $group_saldo){
                    ?>        
                        <div class="saldo-box-amount-container-item">
                            <div class="saldo-box-amount">
                                <?php echo wc_price($group_saldo,array('currency'=>' ','decimal_separator' => '.' )) ;?>
                            </div>
                            <div class="saldo-box-desc"><span><?php esc_html_e('Til utbetaling') ?> <strong class="wallet-currency"><?php echo $data_bal_key; ?></strong></span></div>
                        </div>
                    <?php } ?>  
                    <?php }else{ ?>
                        <div class="saldo-box-amount-container-item">
                            <div class="saldo-box-amount">
                                <?php echo wc_price(0,array('currency'=>' ','decimal_separator' => '.' )) ;?>
                            </div>
                            <div class="saldo-box-desc"><span><?php esc_html_e('Ingen utbetalinger') ?></span> <strong class="wallet-currency">NOK</strong></div>
                        </div>
                    <?php } ?>
                </div>
            </div> -->
        <?php endforeach; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const userGroupSelect = document.getElementById('user_group');
    const dateRangeInput = document.getElementById('date_range');
    const saldoBoxGrid = document.getElementById('saldo-box-grid');
    const saldoBoxes = document.querySelectorAll('.saldo-box');

    // Initialize Select2 for the group filter
    if (typeof jQuery !== 'undefined' && jQuery.fn.select2) {
        jQuery('#user_group').select2({
            placeholder: 'Velg grupper...',
            allowClear: true,
            width: '100%',
            minimumResultsForSearch: 999999,
            closeOnSelect: false,
            selectionCssClass: 'select2-selection--multiple-custom'
        });
        
        // Close Select2 dropdown when clicking outside
        jQuery(document).on('click', function(e) {
            if (!jQuery(e.target).closest('.select2-container').length) {
                jQuery('#user_group').select2('close');
            }
        });
    }

    // Initialize DateRangePicker
    if (typeof jQuery !== 'undefined' && jQuery.fn.daterangepicker) {
        jQuery('#date_range').daterangepicker({
            opens: 'left',
            locale: {
                format: 'DD/MM/YYYY',
                separator: ' - ',
                applyLabel: 'Bruk',
                cancelLabel: 'Avbryt',
                fromLabel: 'Fra',
                toLabel: 'Til',
                customRangeLabel: 'Egendefinert',
                weekLabel: 'U',
                daysOfWeek: ['Sø', 'Ma', 'Ti', 'On', 'To', 'Fr', 'Lø'],
                monthNames: ['Januar', 'Februar', 'Mars', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Desember'],
                firstDay: 1
            },
            ranges: {
                'I dag': [moment(), moment()],
                'I går': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                'Siste 7 dager': [moment().subtract(6, 'days'), moment()],
                'Siste 30 dager': [moment().subtract(29, 'days'), moment()],
                'Siste 3 måneder': [moment().subtract(3, 'months'), moment()],
                'Siste 6 måneder': [moment().subtract(6, 'months'), moment()],
                'Siste 12 måneder': [moment().subtract(1, 'year'), moment()],
                'Denne måneden': [moment().startOf('month'), moment().endOf('month')],
                'Forrige måned': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
                'Dette året': [moment().startOf('year'), moment().endOf('year')],
                'Forrige år': [moment().subtract(1, 'year').startOf('year'), moment().subtract(1, 'year').endOf('year')]
            },
            startDate: <?php echo $start_date ? 'moment("' . $start_date . '", "YYYY-MM-DD")' : 'moment().subtract(3, "months")'; ?>,
            endDate: <?php echo $end_date ? 'moment("' . $end_date . '", "YYYY-MM-DD")' : 'moment()'; ?>,
            showDropdowns: false,
            showWeekNumbers: false,
            timePicker: false,
            timePickerIncrement: 1,
            timePicker24Hour: true,
            autoApply: false,
            linkedCalendars: false,
            showCustomRangeLabel: false,
            alwaysShowCalendars: true
        });

        // Handle date range selection
        jQuery('#date_range').on('apply.daterangepicker', function(ev, picker) {
            // Update the input value
            jQuery(this).val(picker.startDate.format('DD/MM/YYYY') + ' - ' + picker.endDate.format('DD/MM/YYYY'));
        });

        // Handle date range cancellation
        jQuery('#date_range').on('cancel.daterangepicker', function(ev, picker) {
            jQuery(this).val('');
        });
    }

    // Handle submit button click
    const filterSubmitBtn = document.getElementById('filter_submit');
    if (filterSubmitBtn) {
        filterSubmitBtn.addEventListener('click', function() {
            const selectedGroups = jQuery('#user_group').val();
            const dateRange = jQuery('#date_range').val();
            const url = new URL(window.location);
            
            // Handle group parameters - use comma-separated values
            url.searchParams.delete('group');
            if (selectedGroups && selectedGroups.length > 0) {
                url.searchParams.set('group', selectedGroups.join(','));
            }
            
            // Handle date range parameters
            url.searchParams.delete('start_date');
            url.searchParams.delete('end_date');
            if (dateRange) {
                const dates = dateRange.split(' - ');
                if (dates.length === 2) {
                    // Convert DD/MM/YYYY to YYYY-MM-DD for backend processing
                    const startDate = moment(dates[0], 'DD/MM/YYYY').format('YYYY-MM-DD');
                    const endDate = moment(dates[1], 'DD/MM/YYYY').format('YYYY-MM-DD');
                    url.searchParams.set('start_date', startDate);
                    url.searchParams.set('end_date', endDate);
                }
            }
            
            // Remove legacy month parameter
            url.searchParams.delete('month');
            
            window.location.href = url.toString();
        });
    }

    // Optional: Add Enter key support for better UX
    document.addEventListener('keypress', function(e) {
        if (e.key === 'Enter' && (e.target.id === 'user_group' || e.target.id === 'date_range')) {
            e.preventDefault();
            filterSubmitBtn.click();
        }
    });
});
</script>
