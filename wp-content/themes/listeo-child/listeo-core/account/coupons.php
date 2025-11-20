<?php 
$ids = '';
if(isset($data)) :
	$ids	 	= (isset($data->ids)) ? $data->ids : '' ;
endif; 
$message = $data->message;
$no_coupons = array();


?> 
<?php if(!empty($message )) { echo $message; } ?>

<?php 

// Initialize arrays for counting
$active_coupons = array();
$expired_coupons = array();

if(!empty($ids)) {
	$current_date = current_time('Y-m-d H:i:s');
	
	// Separate coupons into active and expired
	foreach ($ids as $coupon_id) {
		$coupon = new WC_Coupon($coupon_id->ID);
		$expiry_date = $coupon->get_date_expires();
		
		if ($expiry_date && $expiry_date->getTimestamp() < strtotime($current_date)) {
			$expired_coupons[] = $coupon_id;
		} else {
			$active_coupons[] = $coupon_id;
		}
	}
}
?>

<?php if(!empty($ids)) : ?>
<div class="woocommerce dashboard-list-box margin-top-0">

<style>
.coupon-tabs {
    display: flex;
    background: white;
    border-radius: 8px 8px 0 0;
    margin-bottom: 0;
    border-bottom: 1px solid #e0e0e0;
    justify-content: space-between;
    align-items: center;
}

.coupon-tab {
    padding: 15px 20px;
    cursor: pointer;
    color: #666;
    border: none;
    background: none;
    font-weight: 600;
    font-size: 18px;
    transition: all 0.3s ease;
    position: relative;
}

.coupon-tab.active {
    color: #008474;
    font-weight: 600;
    font-size: 18px;
}

.coupon-tab.active::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 2px;
    background-color: #008474;
}

.coupon-tab-content {
    display: none;
}

.coupon-tab-content.active {
    display: block;
}

.tab-count {
    margin-left: 2px;
    font-weight: 600;
}

.sortable {
    cursor: pointer;
    user-select: none;
    position: relative;
    padding-right: 20px;
}

.sortable:hover {
    background-color: #f5f5f5;
}

.sortable::after {
    content: '↕';
    position: absolute;
    right: 5px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 12px;
    color: #999;
}

.sortable.asc::after {
    content: '↑';
    color: #008474;
}

.sortable.desc::after {
    content: '↓';
    color: #008474;
}

.create-coupon-container {
    display: flex;
    justify-content: flex-end;
}
</style>

<div class="coupon-tabs">
    <div class="tab-buttons">
        <button class="coupon-tab active" data-tab="active">
            Aktive <span class="tab-count" id="active-count">(<?php echo count($active_coupons); ?>)</span>
        </button>
        <button class="coupon-tab" data-tab="expired">
            Utløpt <span class="tab-count" id="expired-count">(<?php echo count($expired_coupons); ?>)</span>
        </button>
    </div>
    <div class="create-coupon-container">
        <a href="<?php echo get_permalink( get_option( 'listeo_coupons_page' ) ); ?>/?add_new_coupon=true" class="button"><?php esc_html_e('Opprett rabattkode','listeo_core'); ?></a>
    </div>
</div>

<div class="coupon-tab-content active" id="active-content">
<table class="my_account_orders shop_table shop_table_responsive" id="active-table">
	<thead>
	<tr>
		<th class="sortable" data-column="code" data-type="string"><?php echo esc_html_e('Kode','listeo_core'); ?></th>
		<th class="sortable" data-column="type" data-type="string"><?php echo esc_html_e('Type','listeo_core'); ?></th>
		<th class="sortable" data-column="value" data-type="number"><?php echo esc_html_e('Verdi','listeo_core'); ?></th>
		<th class="sortable" data-column="usage" data-type="number"><?php echo esc_html_e('Usage/Limit','listeo_core'); ?></th>
		<th class="sortable" data-column="start-date" data-type="date"><?php echo esc_html_e('Start date','listeo_core'); ?></th>
		<th class="sortable" data-column="expiry-date" data-type="date"><?php echo esc_html_e('Expiry date','listeo_core'); ?></th>
		<th><?php echo esc_html_e('Actions','listeo_core'); ?></th>
	</tr>
	</thead>
	<tbody>
	<?php
	
	$nonce = wp_create_nonce("listeo_core_remove_fav_nonce");
	
	// Display active coupons
	foreach ($active_coupons as $coupon_id) {
		$code = get_the_title( $coupon_id );
		$coupon = new WC_Coupon($coupon_id->ID);
		
		// Get data for sorting
		$coupon_type = wc_get_coupon_type( $coupon->get_discount_type() );
		$coupon_value = $coupon->get_amount();
		$usage_count = $coupon->get_usage_count();
		$usage_limit = $coupon->get_usage_limit();
		$date_start = get_post_meta( $coupon_id->ID, "date_start", true );
		$expiry_date = $coupon->get_date_expires();
		
		$start_date_formatted = $date_start ? date_i18n("Y-m-d", strtotime($date_start)) : '';
		$expiry_date_formatted = $expiry_date ? $expiry_date->date('Y-m-d') : '';
		?>
		<tr data-code="<?php echo esc_attr($code); ?>" 
			data-type="<?php echo esc_attr($coupon_type); ?>" 
			data-value="<?php echo esc_attr($coupon_value); ?>" 
			data-usage="<?php echo esc_attr($usage_count); ?>" 
			data-start-date="<?php echo esc_attr($start_date_formatted); ?>" 
			data-expiry-date="<?php echo esc_attr($expiry_date_formatted); ?>">
			<td data-title="<?php echo esc_html_e('Kode','listeo_core'); ?>" class="listeo-coupons-table-coupon-name"><pre><?php echo get_the_title( $coupon_id->ID );?></pre></td>
			<td data-title="<?php echo esc_html_e('Type','listeo_core'); ?>" ><?php echo esc_html( wc_get_coupon_type( $coupon->get_discount_type() ) ); ?></td>
			<td data-title="<?php echo esc_html_e('Verdi','listeo_core'); ?>" ><?php echo esc_html( wc_format_localized_price( $coupon->get_amount() ) );?></td>
			<td data-title="<?php echo esc_html_e('Usage/Limit','listeo_core'); ?>" ><?php 
				$usage_count =  $coupon->get_usage_count();
				$usage_limit =  $coupon->get_usage_limit();

				printf(
					/* translators: 1: count 2: limit */
					__( '%1$s / %2$s', 'woocommerce' ),
					esc_html( $usage_count ),
					$usage_limit ? esc_html( $usage_limit ) : '&infin;'
				);
				 ?></td>
			<td data-title="<?php echo esc_html_e('Start Date','listeo_core'); ?>" >
				<?php  
				$date_start = get_post_meta( $coupon_id->ID, "date_start", true ); 

				if ( $date_start ) {
					$date_start = date_i18n("F j, Y", strtotime($date_start));
					echo esc_html( $date_start );
				} else {
					echo '&ndash;';
				} ?>
					
			</td>	 
			<td data-title="<?php echo esc_html_e('Expiry Date','listeo_core'); ?>" >
				<?php $expiry_date = $coupon->get_date_expires();

				if ( $expiry_date ) {
					echo esc_html( $expiry_date->date_i18n( 'F j, Y' ) );
				} else {
					echo '&ndash;';
				} ?>
					
			</td>
			<td  data-title="<?php echo esc_html_e('Actions','listeo_core'); ?>">


				<?php $actions = array();

						$actions['coupon_edit'] = array( 
							'label' => __( 'Edit', 'listeo_core' ), 
							'icon' => 'sl sl-icon-note', 
							'nonce' => false,
							'css'	=> 'pay'
							);
						$actions['delete'] = array( 
							'label' => __( 'Delete', 'listeo_core' ), 
							'icon' => 'sl sl-icon-close', 
							'nonce' => true,
							'css'	=> 'cancel'
							 );

						$actions           = apply_filters( 'listeo_core_coupons_actions', $actions, $coupon_id );

						foreach ( $actions as $action => $value ) {
							if($action == 'edit' ){
								$action_url = add_query_arg( array( 'action' => $action,  'coupon_id' => $coupon_id->ID ), get_permalink( get_option( 'listeo_coupon_page' )) );
							} else {
								$action_url = add_query_arg( array( 'action' => $action,  'coupon_id' => $coupon_id->ID ) );
							}
							if ( $value['nonce'] ) {
								$action_url = wp_nonce_url( $action_url, 'listeo_core_coupons_actions' );
							}
					
							echo '<a href="' . esc_url( $action_url ) . '" class="woocommerce-button button ' . esc_attr( $value['css'] ) . ' listeo_core-dashboard-action-' . esc_attr( $action ) . '">';
							
							if(isset($value['icon']) && !empty($value['icon'])) {
								echo '<i class="'.$value['icon'].'"></i>';
							}

							 echo esc_html( $value['label'] ) . '</a>';
						} ?>	
							
			</td>
		</tr>
		<?php
	}
	
	// Show message if no active coupons
	if (empty($active_coupons)) {
		echo '<tr><td colspan="7" style="text-align: center; padding: 20px;">Ingen aktive rabattkoder funnet.</td></tr>';
	}
	?>
		
</tbody>
	</table>
</div>

<div class="coupon-tab-content" id="expired-content">
<table class="my_account_orders shop_table shop_table_responsive" id="expired-table">
	<thead>
	<tr>
		<th class="sortable" data-column="code" data-type="string"><?php echo esc_html_e('Kode','listeo_core'); ?></th>
		<th class="sortable" data-column="type" data-type="string"><?php echo esc_html_e('Type','listeo_core'); ?></th>
		<th class="sortable" data-column="value" data-type="number"><?php echo esc_html_e('Verdi','listeo_core'); ?></th>
		<th class="sortable" data-column="usage" data-type="number"><?php echo esc_html_e('Usage/Limit','listeo_core'); ?></th>
		<th class="sortable" data-column="start-date" data-type="date"><?php echo esc_html_e('Start date','listeo_core'); ?></th>
		<th class="sortable" data-column="expiry-date" data-type="date"><?php echo esc_html_e('Expiry date','listeo_core'); ?></th>
		<th><?php echo esc_html_e('Actions','listeo_core'); ?></th>
	</tr>
	</thead>
	<tbody>
	<?php
	// Display expired coupons
	foreach ($expired_coupons as $coupon_id) {
		$code = get_the_title( $coupon_id );
		$coupon = new WC_Coupon($coupon_id->ID);
		
		// Get data for sorting
		$coupon_type = wc_get_coupon_type( $coupon->get_discount_type() );
		$coupon_value = $coupon->get_amount();
		$usage_count = $coupon->get_usage_count();
		$usage_limit = $coupon->get_usage_limit();
		$date_start = get_post_meta( $coupon_id->ID, "date_start", true );
		$expiry_date = $coupon->get_date_expires();
		
		$start_date_formatted = $date_start ? date_i18n("Y-m-d", strtotime($date_start)) : '';
		$expiry_date_formatted = $expiry_date ? $expiry_date->date('Y-m-d') : '';
		?>
		<tr data-code="<?php echo esc_attr($code); ?>" 
			data-type="<?php echo esc_attr($coupon_type); ?>" 
			data-value="<?php echo esc_attr($coupon_value); ?>" 
			data-usage="<?php echo esc_attr($usage_count); ?>" 
			data-start-date="<?php echo esc_attr($start_date_formatted); ?>" 
			data-expiry-date="<?php echo esc_attr($expiry_date_formatted); ?>">
			<td data-title="<?php echo esc_html_e('Kode','listeo_core'); ?>" class="listeo-coupons-table-coupon-name"><pre><?php echo get_the_title( $coupon_id->ID );?></pre></td>
			<td data-title="<?php echo esc_html_e('Type','listeo_core'); ?>" ><?php echo esc_html( wc_get_coupon_type( $coupon->get_discount_type() ) ); ?></td>
			<td data-title="<?php echo esc_html_e('Verdi','listeo_core'); ?>" ><?php echo esc_html( wc_format_localized_price( $coupon->get_amount() ) );?></td>
			<td data-title="<?php echo esc_html_e('Usage/Limit','listeo_core'); ?>" ><?php 
				$usage_count =  $coupon->get_usage_count();
				$usage_limit =  $coupon->get_usage_limit();

				printf(
					/* translators: 1: count 2: limit */
					__( '%1$s / %2$s', 'woocommerce' ),
					esc_html( $usage_count ),
					$usage_limit ? esc_html( $usage_limit ) : '&infin;'
				);
				 ?></td>
			<td data-title="<?php echo esc_html_e('Start Date','listeo_core'); ?>" >
				<?php  
				$date_start = get_post_meta( $coupon_id->ID, "date_start", true ); 

				if ( $date_start ) {
					$date_start = date_i18n("F j, Y", strtotime($date_start));
					echo esc_html( $date_start );
				} else {
					echo '&ndash;';
				} ?>
					
			</td>	 
			<td data-title="<?php echo esc_html_e('Expiry Date','listeo_core'); ?>" >
				<?php $expiry_date = $coupon->get_date_expires();

				if ( $expiry_date ) {
					echo esc_html( $expiry_date->date_i18n( 'F j, Y' ) );
				} else {
					echo '&ndash;';
				} ?>
					
			</td>
			<td  data-title="<?php echo esc_html_e('Actions','listeo_core'); ?>">


				<?php $actions = array();

						$actions['coupon_edit'] = array( 
							'label' => __( 'Edit', 'listeo_core' ), 
							'icon' => 'sl sl-icon-note', 
							'nonce' => false,
							'css'	=> 'pay'
							);
						$actions['delete'] = array( 
							'label' => __( 'Delete', 'listeo_core' ), 
							'icon' => 'sl sl-icon-close', 
							'nonce' => true,
							'css'	=> 'cancel'
							 );

						$actions           = apply_filters( 'listeo_core_coupons_actions', $actions, $coupon_id );

						foreach ( $actions as $action => $value ) {
							if($action == 'edit' ){
								$action_url = add_query_arg( array( 'action' => $action,  'coupon_id' => $coupon_id->ID ), get_permalink( get_option( 'listeo_coupon_page' )) );
							} else {
								$action_url = add_query_arg( array( 'action' => $action,  'coupon_id' => $coupon_id->ID ) );
							}
							if ( $value['nonce'] ) {
								$action_url = wp_nonce_url( $action_url, 'listeo_core_coupons_actions' );
							}
					
							echo '<a href="' . esc_url( $action_url ) . '" class="woocommerce-button button ' . esc_attr( $value['css'] ) . ' listeo_core-dashboard-action-' . esc_attr( $action ) . '">';
							
							if(isset($value['icon']) && !empty($value['icon'])) {
								echo '<i class="'.$value['icon'].'"></i>';
							}

							 echo esc_html( $value['label'] ) . '</a>';
						} ?>	
							
			</td>
		</tr>
		<?php
	}
	
	// Show message if no expired coupons
	if (empty($expired_coupons)) {
		echo '<tr><td colspan="7" style="text-align: center; padding: 20px;">Ingen utløpte rabattkoder funnet.</td></tr>';
	}
	?>
		
</tbody>
	</table>
</div>

<?php else: ?>
	<div class="create-coupon-container">
        <a href="<?php echo get_permalink( get_option( 'listeo_coupons_page' ) ); ?>/?add_new_coupon=true" class="button"><?php esc_html_e('Opprett rabattkode','listeo_core'); ?></a>
    </div>
	<div class="notification notice ">
		<p><span><?php esc_html_e('No coupons!','listeo_core'); ?></span> <?php esc_html_e('You haven\'t created any coupons yet.','listeo_core'); ?></p>
		
	</div>

<?php endif;
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const tabs = document.querySelectorAll('.coupon-tab');
    const contents = document.querySelectorAll('.coupon-tab-content');
    
    // Tab switching functionality
    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            const targetTab = this.getAttribute('data-tab');
            
            // Remove active class from all tabs and contents
            tabs.forEach(t => t.classList.remove('active'));
            contents.forEach(c => c.classList.remove('active'));
            
            // Add active class to clicked tab and corresponding content
            this.classList.add('active');
            document.getElementById(targetTab + '-content').classList.add('active');
        });
    });
    
    // Table sorting functionality
    function sortTable(table, column, type, direction) {
        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        
        // Remove empty message rows
        const dataRows = rows.filter(row => !row.querySelector('td[colspan]'));
        
        dataRows.sort((a, b) => {
            let aVal = a.getAttribute('data-' + column);
            let bVal = b.getAttribute('data-' + column);
            
            // Handle empty values
            if (!aVal) aVal = '';
            if (!bVal) bVal = '';
            
            if (type === 'number') {
                aVal = parseFloat(aVal) || 0;
                bVal = parseFloat(bVal) || 0;
            } else if (type === 'date') {
                aVal = new Date(aVal) || new Date(0);
                bVal = new Date(bVal) || new Date(0);
            } else {
                aVal = aVal.toString().toLowerCase();
                bVal = bVal.toString().toLowerCase();
            }
            
            if (aVal < bVal) return direction === 'asc' ? -1 : 1;
            if (aVal > bVal) return direction === 'asc' ? 1 : -1;
            return 0;
        });
        
        // Clear tbody and re-append sorted rows
        tbody.innerHTML = '';
        dataRows.forEach(row => tbody.appendChild(row));
        
        // Re-add empty message if no data rows
        if (dataRows.length === 0) {
            const emptyRow = document.createElement('tr');
            emptyRow.innerHTML = '<td colspan="7" style="text-align: center; padding: 20px;">Ingen rabattkoder funnet.</td>';
            tbody.appendChild(emptyRow);
        }
    }
    
    // Add click handlers to sortable headers
    document.querySelectorAll('.sortable').forEach(header => {
        header.addEventListener('click', function() {
            const table = this.closest('table');
            const column = this.getAttribute('data-column');
            const type = this.getAttribute('data-type');
            
            // Check if this column is already sorted
            const isCurrentlyAsc = this.classList.contains('asc');
            const isCurrentlyDesc = this.classList.contains('desc');
            
            // Remove sort classes from all headers in this table
            table.querySelectorAll('.sortable').forEach(h => {
                h.classList.remove('asc', 'desc');
            });
            
            // Determine sort direction
            let direction = 'asc'; // Default to ascending
            
            if (isCurrentlyAsc) {
                // If currently ascending, switch to descending
                direction = 'desc';
            } else if (isCurrentlyDesc) {
                // If currently descending, switch to ascending
                direction = 'asc';
            }
            // If not sorted at all, default to ascending (already set above)
            
            // Add sort class to current header
            this.classList.add(direction);
            
            // Sort the table
            sortTable(table, column, type, direction);
        });
    });
});
</script>

