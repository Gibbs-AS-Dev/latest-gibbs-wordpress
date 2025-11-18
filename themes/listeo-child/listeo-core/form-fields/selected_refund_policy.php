<ul>
    <?php
        foreach ($refund_policies as $refund_poli) {

        if($refund_poli["name"] == $current_refund_policy){

        ?>  

            
            <li>
                <div class="list-box-listing">
                <div class="list-box-listing-content">
                    <div class="inner">
                    <h3><?php echo $refund_poli["title"]; //echo listeo_core_get_post_status($listing_id) ?></h3>
                    <p><?php echo $refund_poli["description"]; //echo listeo_core_get_post_status($listing_id) ?></p>
                    </div>
                </div>
                </div>
                <div class="buttons-to-right">
                    <!--          <button type="button" data-booking_system="<?php echo $refund_poli["name"];?>" class="button selected gray">
                    <i class="fa-regular fa-file-lines"></i> <?php echo 'Valgt'; ?></button> -->
                    <br>
                    <button type='button' class='select_refund_policy_btn btn-primary'>Endre </button>
                    <input type="hidden" name="refund_policy" value="<?php echo $refund_poli["name"];?>">
                </div>
            </li>
            
    <?php } 
    }
?>
</ul>