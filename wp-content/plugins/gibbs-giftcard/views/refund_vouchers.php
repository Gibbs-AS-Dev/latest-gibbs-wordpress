<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="gift_all_bookings">
    <div class="search-box">
        <i class="fas fa-search search-icon"></i>
        <input type="text" id="search-email" placeholder="Søk med e-post eller kode" onkeyup="searchGiftCard()">
    </div>

    <div id="noDataMessage" style="display: none;">
        Ingen tilgodelapper funnet
    </div>

    <table id="refundVouchersTable" class="display gift-card-table" style="display:none;">
        <thead>
            <tr>
                <th>Kode</th>
                <th>Kunde</th>
                <th>Tilgodelapp beløp</th>
                <th>Saldo</th>
                <th>Refundert dato</th>
                <th>Utløpsdato</th>
                <th>Kanselleringsårsak</th>
                <th>Handlinger</th>
            </tr>
        </thead>
    </table>
</div>

<style>
    table.dataTable.dtr-inline.collapsed > tbody > tr[role="row"] > td:first-child::before {
        font-family: "Font Awesome 5 Free";
        content: "\f067"; /* Plus icon */
        font-weight: 900;
        margin-right: 10px;
        color: #008474;
    }

    table.dataTable.dtr-inline.collapsed > tbody > tr.parent > td:first-child::before {
        content: "\f068"; /* Minus icon */
    }
    #refundVouchersTable_paginate a {
        font-family: Source Sans Pro;
        font-weight: bold;
        border-radius: 5px;
        padding: 2px 12px;
        margin: 0px 1px;
        color: #008474 !important;
        text-decoration: none;
    }
    #refundVouchersTable_paginate, #refundVouchersTable_info {
        margin-top: 30px;
    }
    
    #refundVouchersTable_paginate a.active, #refundVouchersTable_paginate a.current, #refundVouchersTable_paginate a:hover {
        border: 1px solid #008474 !important;
        background-color: #585858;
        background: linear-gradient(to bottom, #008474 0%, #008474 100%) !important;
        color: #fff !important;
    }
    #refundVouchersTable_paginate .disabled{
        display: none;
    }
    
    /* Styles for cancelled orders without vouchers */
    .no-voucher {
        display: inline-block;
        padding: 3px 8px;
        background-color: #f8f8f8;
        border-left: 3px solid #888;
        color: #888;
        font-style: italic;
    }
    
    .zero-amount {
        color: #888;
        font-style: italic;
    }
    
    .not-applicable {
        color: #888;
        font-style: italic;
    }
    
    .no-actions {
        color: #888;
        font-style: italic;
    }
    
    /* Highlighting the row for zero-amount cancellations */
    table.dataTable tbody tr.zero-amount-row {
        background-color: #f9f9f9;
    }
</style>

<script type="text/javascript">
jQuery(document).ready(function($) {
    console.log('Initializing refund vouchers table');
    // Initialize DataTable
    let vouchersTable = $('#refundVouchersTable').DataTable({
        "processing": true,
        "serverSide": true,
        "responsive": true,
        "order": [[4, "desc"]], // Standardsortering etter refundert dato (nyeste først)
        "searching": false, // Deaktiver standard søkeboks
        "lengthMenu": [10, 25, 50, 100], // Alternativer for "Vis oppføringer" nedtrekksmeny
        "pageLength": 10, // Standard antall oppføringer å vise
        "lengthChange": false, // Skjul "oppføringer per side" nedtrekksmeny
        "ajax": {
            "url": "<?php echo admin_url('admin-ajax.php'); ?>",
            "type": "POST",
            "data": function(d) {
                d.action = "fetch_voucher_data"; // Custom AJAX action
                d.searchData = $('#search-email').val(); // Include search email in AJAX data
                console.log('Sending AJAX request with data:', d);
                return d;
            },
            "dataSrc": function(json) {
                console.log('Received AJAX response:', json);
                if (json.data.length > 0) {
                    console.log('Data found, showing table');
                    $(".dataTables_length").show();
                    $('#refundVouchersTable_wrapper').show();
                    $('#refundVouchersTable').show();
                    $('#noDataMessage').hide();
                } else {
                    console.log('No data found, showing message');
                    $(".dataTables_length").hide();
                    $('#refundVouchersTable_wrapper').hide();
                    $('#refundVouchersTable').hide();
                    $('#noDataMessage').show();
                }
                return json.data;
            },
            "error": function(xhr, error, thrown) {
                console.error('AJAX error:', error, thrown);
            }
        },
        "columns": [
            { 
                "data": "code",
                "render": function(data, type, row) {
                    if (data === 'N/A') {
                        return '<span class="no-voucher">Ingen tilgodelapp</span>';
                    }
                    return '<span class="gift-card-code">' + data + '</span>'; 
                }
            },
            { "data": "purchased_by" },
            { 
                "data": "purchased_amount",
                "render": function(data, type, row) {
                    if (data === '0') {
                        return '<span class="zero-amount">Kr 0,-</span>';
                    }
                    return data;
                }
            },
            { 
                "data": "remaining_saldo",
                "render": function(data, type, row) {
                    if (data === '0') {
                        return '<span class="zero-amount">Kr 0,-</span>';
                    }
                    return data;
                }
            },
            { "data": "purchased_date" },
            { 
                "data": "expire_date",
                "render": function(data, type, row) {
                    if (data === 'N/A') {
                        return '<span class="not-applicable">Ikke relevant</span>';
                    }
                    return data;
                }
            },
            { 
                "data": "cancellation_reason",
                "render": function(data) {
                    return data ? data : 'Ikke spesifisert';
                }
            },
            { 
                "data": "actions",
                "defaultContent": "<button class='btn'>Ingen handlinger</button>",
                "render": function(data, type, row) {
                    if (row.code === 'N/A') {
                        return '<span class="no-actions">Ingen handlinger tilgjengelig</span>';
                    }
                    
                    const pdfButton = '<button class="btn btn-pdf" onclick="downloadPDF(\'' + row.code + '\')">Last ned PDF</button>';
                    if (row.show_actions) {
                        const actionButton = row.is_active
                            ? '<button class="btn btn-deactivate" onclick="deactivateGiftCard(\'' + row.id + '\')">Deaktiver</button>'
                            : '<button class="btn btn-activate" onclick="activateGiftCard(\'' + row.id + '\')">Aktiver</button>';
                        return pdfButton + ' ' + actionButton;
                    } else {
                        return pdfButton;
                    }
                }
            }
        ],
        "language": {
            "processing": "Laster inn...",
            "lengthMenu": "Vis _MENU_ oppføringer per side",
            "zeroRecords": "Ingen tilgodelapper funnet 😊",
            "info": "_TOTAL_ tilgodelapper",
            "infoEmpty": "Ingen oppføringer tilgjengelig",
            "infoFiltered": "(_MAX_ totalt)",
            "search": "Søk:",
            "paginate": {
                "next": "Neste",
                "previous": "Forrige"
            },
            "emptyTable": "Ingen data tilgjengelig",
            "loadingRecords": "Laster...",
            "thousands": " ",
            "decimal": ",",
            "aria": {
                "sortAscending": ": aktiver for å sortere kolonnen stigende",
                "sortDescending": ": aktiver for å sortere kolonnen synkende"
            }
        }
    });

    // Reload DataTable on search input change
    window.searchGiftCard = function() {
        vouchersTable.ajax.reload();
    };

    // Apply custom styling to pagination buttons after the table is initialized
    vouchersTable.on('draw', function() {
        // Fjern inline-stiler satt av DataTables
        $('.dataTables_paginate .paginate_button').removeAttr('style');

        // Bruk egendefinerte stiler
        $('.dataTables_paginate .paginate_button').css({
            'display': 'inline-block',
            'padding': '8px 16px',
            'margin': '0 5px',
            'font-size': '14px',
            'color': '#008474',
            'background-color': 'transparent',
            'border-radius': '20px',
            'font-weight': 'bold',
            'cursor': 'pointer',
            'text-align': 'center'
        });

        // Stil for aktiv knapp
        $('.dataTables_paginate .paginate_button.current').css({
            'background-color': '#008474',
            'color': '#fff',
            'pointer-events': 'none'
        });

        // Hover-effekt
        $('.dataTables_paginate .paginate_button').hover(
            function() {
                $(this).css({
                    'background-color': '#006b5c',
                    'color': '#fff'
                });
            },
            function() {
                if (!$(this).hasClass('current')) {
                    $(this).css({
                        'background-color': 'transparent',
                        'color': '#008474'
                    });
                }
            }
        );
        
        // Mark zero-amount cancellation rows
        $('#refundVouchersTable tbody tr').each(function() {
            var $row = $(this);
            var codeCell = $row.find('td:first-child');
            
            if (codeCell.find('.no-voucher').length > 0) {
                $row.addClass('zero-amount-row');
            }
        });
    });
});

// Function to download PDF
function downloadPDF(giftcode) {
    const form = jQuery("<form>", {
        action: '<?php echo admin_url("admin-ajax.php"); ?>',
        method: "POST"
    });
    form.append(jQuery("<input>", { type: "hidden", name: "action", value: "downloadVoucherPDF" }));
    form.append(jQuery("<input>", { type: "hidden", name: "refund_code", value: giftcode }));
    jQuery("body").append(form);
    form.submit();
    form.remove(); // Clean up
}

// Function to deactivate a gift card
function deactivateGiftCard(postId) {
    jQuery.post('<?php echo admin_url('admin-ajax.php'); ?>', {
        action: 'deactivate_giftcard',
        post_id: postId
    }, function(response) {
        jQuery('#refundVouchersTable').DataTable().ajax.reload(null, false);
    });
}

// Function to activate a gift card
function activateGiftCard(postId) {
    jQuery.post('<?php echo admin_url('admin-ajax.php'); ?>', {
        action: 'activate_giftcard',
        post_id: postId
    }, function(response) {
        jQuery('#refundVouchersTable').DataTable().ajax.reload(null, false);
    });
}
</script> 