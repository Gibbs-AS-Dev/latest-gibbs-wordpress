<?php
if(isset($data->listing_id)){
    $season_status = get_post_meta($data->listing_id,"season_status",true);
    $season_discount_data = get_post_meta($data->listing_id,"season_discount_data",true);

    
}else{
    $season_status = "";
    $season_discount_data = array();
}

if($season_discount_data != ""){
    $season_data_json = json_encode($season_discount_data);
}else{
    $season_data_json = json_encode(array());
}
// Convert PHP array to JavaScript array


?>
<div class="add-listing-section row menu switcher-on season-price-wrapper" style="">	
    <div class="add-listing-headline template_div_main switcher-on">
        <h3><?php echo __("Season price","gibbs");?></h3>
        <p><?php echo __("Only available for new version of slots (In testing)","gibbs");?></p>
        <label class="switch" style="right: 19px;">
            <input id="season_status" name="season_status" type="checkbox" <?php if($season_status == "on"){echo "checked";}?>>
            <span class="slider round"></span>
        </label>
        <span class="toggle" style="display: none;"></span>
    </div>	
    <div class="inner_section" style="width: 100%;">
        <div class="col-md-12">
            <div id="season-price-container">
                <!-- Season price rows will be added dynamically via JavaScript -->
            </div>
            
            <div class="row mt-3">
                <div class="col-md-12">
                    <button type="button" class="btn btn-secondary" id="add-season-price">
                        <?php echo __("Add more","gibbs");?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>


<script>
jQuery(document).ready(function($) {
    let rowCounter = 0;
    
    // Get existing season data from PHP
    const existingSeasonData = <?php echo $season_data_json; ?>;
    
    // Function to create a new season price row
    function createSeasonPriceRow(rowNum, seasonData = null) {
        const isFirstRow = rowNum === 0;
        const deleteButton = isFirstRow ? '' : `<div class="delete-season-price-wrapper">
            <label style="visibility: hidden;"><?php echo __("Slett","gibbs");?></label>
            <div class="select-input disabled-first-option delete-season-price">
                <i class="fa fa-times"></i>
            </div>
        </div>`;
        
        // Set default values or use existing data
        const seasonName = seasonData ? seasonData.season_name : '';
        const seasonPercent = seasonData ? seasonData.season_price_percent : '';
        const seasonFrom = seasonData ? seasonData.season_price_from : '';
        const seasonTo = seasonData ? seasonData.season_price_to : '';
        const seasonActive = seasonData && seasonData.season_price_active === 'on' ? 'checked' : '';
        
        return `
            <div class="season-price-row" data-row="${rowNum}">
                <div class="row">
                    <div class="col-md-3">
                        <label for="season_name_${rowNum}"><?php echo __("Season Name","gibbs");?></label>
                        <input type="text" name="season_name[]" id="season_name_${rowNum}" placeholder="<?php echo __("Season name","gibbs");?>" class="form-control" value="${seasonName}">
                    </div>
                    <div class="col-md-3">
                        <label for="season_price_percent_${rowNum}"><?php echo __("Sesongpris","gibbs");?> %</label>
                        <input type="number" name="season_price_percent[]" id="season_price_percent_${rowNum}" placeholder="<?php echo __("Season Price","gibbs");?> %" class="form-control" step="0.01" min="-100" max="100" value="${seasonPercent}">
                    </div>
                    <div class="col-md-2">
                        <label for="season_price_from_${rowNum}"><?php echo __("Fra dato","gibbs");?></label>
                        <input type="date" name="season_price_from[]" id="season_price_from_${rowNum}" placeholder="<?php echo __("Fra dato","gibbs");?>" class="form-control" value="${seasonFrom}">
                    </div>
                    <div class="col-md-2">
                        <label for="season_price_to_${rowNum}"><?php echo __("Til dato","gibbs");?></label>
                        <input type="date" name="season_price_to[]" id="season_price_to_${rowNum}" placeholder="<?php echo __("Til dato","gibbs");?>" class="form-control" value="${seasonTo}">
                    </div>
                    <div class="col-md-2 d-flex">
                        <div class="show-hide-checkbox-wrapper">
                            <label class="show-hide-label"><?php echo __("Aktiv/Inaktiv","gibbs");?></label>
                            <label class="switch switch-sm" style="top: 47px;left: 30px;">
                                <input type="checkbox" id="season_price_active_${rowNum}" class="season-price-checkbox" ${seasonActive}>
                                <span class="slider round"></span>
                            </label>
                            <input type="hidden" class="season_price_active" name="season_price_active[]" value="${seasonData && seasonData.season_price_active === 'on' ? 'on' : 'off'}">
                        </div>
                        <div class="season-price-controls">
                            ${deleteButton}
                        </div>
                    </div>
                </div>
            </div>
        `;
    }
    
    // Add new season price row
    $('#add-season-price').on('click', function() {
        rowCounter++;
        const newRow = createSeasonPriceRow(rowCounter);
        $('#season-price-container').append(newRow);
    });
    
    // Delete season price row
    $(document).on('click', '.delete-season-price', function() {
        $(this).closest('.season-price-row').remove();
    });
    
    // Handle toggle switch changes to update hidden fields
    $(document).on('change', '.season-price-checkbox', function() {
        const row = $(this).closest('.season-price-row');
        const hiddenField = row.find('.season_price_active');
        const isChecked = $(this).is(':checked');
        
        // Update hidden field value
        hiddenField.val(isChecked ? 'on' : 'off');
    });
    
    // Toggle season price functionality
    $('#season_status').on('change', function() {
        if ($(this).is(':checked')) {
            $('#season-price-container').show();
            $('#add-season-price').show();
            jQuery('.season-price-wrapper').find('.inner_section').show();
        } else {
            $('#season-price-container').hide();
            $('#add-season-price').hide();
            jQuery('.season-price-wrapper').find('.inner_section').hide();
        }
    });
    
    // Initialize state and load existing data
    if ($('#season_status').is(':checked')) {
        $('#season-price-container').show();
        $('#add-season-price').show();
        
        // Load existing season data if available
        if (existingSeasonData && existingSeasonData.length > 0) {
            existingSeasonData.forEach((seasonData, index) => {
                const newRow = createSeasonPriceRow(rowCounter, seasonData);
                $('#season-price-container').append(newRow);
                rowCounter++;
            });
        } else {
            // Add the first row automatically if no existing data
            $('#season-price-container').append(createSeasonPriceRow(rowCounter));
            rowCounter++;
        }
    } else {
        $('#season-price-container').hide();
        jQuery('.season-price-wrapper').find('.inner_section').hide();
        $('#add-season-price').hide();
        $('#season-price-container').append(createSeasonPriceRow(rowCounter));
        rowCounter++;
    }
});
</script>