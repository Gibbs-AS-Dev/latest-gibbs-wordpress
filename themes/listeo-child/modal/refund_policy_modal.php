<?php

$refund_policies = get_refund_policies();

$current_refund_policy = "no_refund";

if(isset($_GET['listing_id'])){

  $current_refund_policy_data = get_post_meta($_GET['listing_id'],"refund_policy",true);

  if($current_refund_policy_data != ""){
    $current_refund_policy = $current_refund_policy_data;
  }

}

?>


<div id="refundPolicyModal" class="modal template_modal">

  <!-- Modal content -->
   <div class="modal-content">
    <div class="modal-header">
      <span class="close close_user">&times;</span>
      <h2><?php echo __("Velg kanselleringsvilkår","Gibbs");?></h2>
    </div>
    <div class="modal-body">
       <div class="listing_demo_div dashboard-list-box">
            <ul>
            <?php
              foreach ($refund_policies as $refund_policy) {
              ?>  
                <li>
                  <div class="list-box-listing">
                    <div class="list-box-listing-content">
                      <div class="inner">
                        <h3><?php echo $refund_policy["title"]; ?></h3>
                        <p><?php echo $refund_policy["description"]; ?></p>
                      </div>
                    </div>
                  </div>
                  <div class="buttons-to-right">
                      <button type="button" data-refund_policy="<?php echo $refund_policy["name"];?>" class="button select_refund_policy <?php if($current_refund_policy == $refund_policy['name']){ echo 'selected';}?> gray">
                      <i class="fa-regular fa-file-lines"></i> <span><?php if($current_refund_policy == $refund_policy['name']){ echo 'Valgt';}else{ echo "Velg";}?></span></button>
                  </div>
                </li>
            <?php } ?>
            </ul>
       </div>
    </div>
  </div>

</div>

<script type="text/javascript">
  
// Get the modal
//var team_sizeModal = document.getElementById("team_sizeModal");
let refundPolicyModal = document.getElementById("refundPolicyModal");


// Get the <span> element that closes the modal
//var span = document.getElementsByClassName("close")[0];
var close_user = document.getElementsByClassName("close_user")[0];

// When the user clicks the button, open the modal 
/*team_sizebtn.onclick = function() {
  team_sizeModal.style.display = "block";
}*/
  jQuery(document).on("click",".close_user, .close_template_btn",function(){
    jQuery("#refundPolicyModal").hide();
  })
  jQuery(document).on("click",".select_refund_policy_btn",function(){
    jQuery("#refundPolicyModal").show();
  })
  

  // When the user clicks anywhere outside of the modal, close it
  window.onclick = function(event) {
    /*if (event.target == team_sizeModal) {
      team_sizeModal.style.display = "none";
    } */
    if (event.target == refundPolicyModal) {
      refundPolicyModal.style.display = "none";
    }
  }
  jQuery(document).ready(function($){
    $(".select_refund_policy").click(function(){
        var $button = $(this);
        
        // First remove any existing loaders
        $('.loader-ajax-container').remove();
        
        // Update button appearance
        $(".select_refund_policy").removeClass("selected").find("span").text("Velg");
        $button.addClass("selected").find("span").text("Valgt");

        // Create and insert loader after the span
        var $loader = $('<div class="loader-ajax-container"><div class="loader-ajax"></div></div>');
        $button.find('span').after($loader);
        
        $button.prop('disabled', true);

        let refund_policy = $button.attr("data-refund_policy");
        let selected_refund_policy = refund_policy;
        let listing_id = "<?php echo $_GET['listing_id'];?>";

        $.ajax({
            type : "POST",
            url : "<?php echo admin_url( 'admin-ajax.php' );?>",
            data : {
                action: "selected_refund_policy",
                'listing_id': listing_id,
                selected_refund_policy: selected_refund_policy
            },
            success: function(response) {
                $(".refund_pl_main").html(response);
                $("#refundPolicyModal").hide();
                $('.loader-ajax-container').remove();
                $('.select_refund_policy').prop('disabled', false);
            },
            error: function() {
                $('.loader-ajax-container').remove();
                $('.select_refund_policy').prop('disabled', false);
            }
        }); 
    });
  });
</script>

<style>
.loader-ajax-container {
    display: inline-block;
    margin-left: 10px;
    vertical-align: middle;
}

.loader-ajax {
    width: 16px;
    height: 16px;
    border: 2px solid #ffffff;
    border-radius: 50%;
    border-top: 2px solid #3498db;
    animation: spin 1s linear infinite;
    display: inline-block;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>