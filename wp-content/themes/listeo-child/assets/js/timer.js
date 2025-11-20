jQuery(function($){

    var storageKey = "booking_timer_data";

  //  var booking_timer_data = myAjax.booking_timer;

    var booking_data = JSON.parse(localStorage.getItem(storageKey)) || [];

    //var booking_data = booking_timer_data || [];

    if(booking_data && booking_data.length > 0){

        var $page_name = "";

        if(typeof hasPage != "undefined"){
            $page_name = hasPage;
        }
        

        function closeBooking(close_bk_id){

            booking_data = booking_data.filter(booking => parseInt(booking.bk_id) !== parseInt(close_bk_id));

            localStorage.setItem(storageKey, JSON.stringify(booking_data));

            var data = {
                action: 'remove_timer_callback', 
                booking_id: close_bk_id
            };
    
            // Perform AJAX request
            $.post(myAjax.ajaxurl, data, function(response) {
                console.log('Response from server: ' + response);
            });

            
    
            // Remove the HTML element
            $("#booking_timer_" + close_bk_id).remove();

        }

        function displayTimers(booking_data) {
            $(".booking-timer").remove();
        
            var timerHtml = "<div class='main-booking-timer'>";
        
            booking_data.forEach(function(booking) {
                if (
                    !booking.bk_id || 
                    booking.bk_id === "null" || 
                    booking.bk_id === "undefined" || 
                    booking.bk_id === "NaN" || 
                    isNaN(booking.bk_id) || 
                    booking.bk_id === 0
                ) {
                    return; 
                }
        
                // AJAX to check status
                $.ajax({
                    url: listeo.ajaxurl,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'check_booking_status',
                        bk_id: booking.bk_id
                    },
                    success: function(response) {
                        if (response && response.data && response.data.status === "pay_to_confirm") {
                            // Only render if status matches
                            var html = `
                                <div class="row booking-timer" id="booking_timer_${booking.bk_id}">
                                    <div class="col-md-12 listing_title" style="margin-top:15px">
                                        <div class="alert alert-info" role="alert">
                                            <div class="info-div">
                                                <span> Fullfør bestillingen før reservasjonen utløper: </span>
                                                <span id="bk_timer_${booking.bk_id}"></span>
                                            </div>
                                            <div class="timer-btns">
                                                
                                                ${$page_name != "gibbspay" ? `<button class="btn btn-primary complete_bk" data-bkid="${booking.bk_id}" data-url="${booking.current_url}">Fullfør</button>` : ''}
                                                <button class="btn btn-secondary close_bk" data-bkid="${booking.bk_id}">Avslutt</button>
                                            </div>
                                        </div>
                                    </div> 
                                </div>
                            `;
                            $(".main-booking-timer").append(html);
                        }else if (response && response.data && response.data.status === "paid") {
                            closeBooking(booking.bk_id)
                        }else if (response && response.data && response.data.status === "waiting") {
                            closeBooking(booking.bk_id)
                        }else if (response && response.data && response.data.status === "payment_failed") {
                            closeBooking(booking.bk_id)
                        }
                    }
                });
            });
        
            timerHtml += "</div>";
            $("body").append(timerHtml);
        
            if (window.location.href.includes("gibbspay")) {
                $(".timer-btns").hide();
            }
        }
    
        // Display timers initially
        displayTimers(booking_data);

        let intervalId = null;
    
        // Function to update all countdown timers
        function updateTimers() {
           
            const now = new Date().getTime();

            booking_data = booking_data.filter(function(booking) {
                const elapsed = Math.floor((now - booking.start_time) / 1000);
                const remaining = booking.time - elapsed;

                if(booking.bk_id == undefined || booking.bk_id == null || booking.bk_id == "" || booking.bk_id == "null" || booking.bk_id == "undefined" || booking.bk_id == "NaN" || booking.bk_id == NaN || booking.bk_id == 0){
                    return false;
                }

                if (remaining > 0) {
                    const mins = Math.floor(remaining / 60);
                    const secs = Math.floor(remaining % 60);
                    $("#bk_timer_" + booking.bk_id).html(
                        (mins < 10 ? "0" + mins : mins) + ":" + (secs < 10 ? "0" + secs : secs)
                    );
                    return true;
                } else {
                    $("#bk_timer_" + booking.bk_id).html("Utgått");
                    setTimeout(() => {
                        $("#booking_timer_" + booking.bk_id).remove();
                    }, 2000);

                    closeBooking(booking.bk_id);

                    if($page_name == "form-pay"){
                        window.location.href = booking.listing_linkk;
                    } else {
                        const timerHtml2 = `<div class='close-body-timer'>
                            <div class="row booking-timer">
                                <div class="col-md-12 listing_title" style="margin-top:15px">
                                    <div class="alert alert-info" role="alert">
                                        <div class=info-div"">
                                            <span> Time has run out. Would you like to try again. </span>
                                        </div>
                                        <div class="close-timer-btns d-flex justify-content-center align-items-center gap-5">
                                            <a href="${booking.listing_linkk}"><button class="btn btn-primary">Try Again</button></a>
                                            <button class="btn btn-secondary close_body_timer">Avslutt</button>
                                        </div>
                                    </div>
                                </div> 
                            </div>
                        </div>`;
                        $("body").append(timerHtml2);
                    }

                    if(intervalId){
                        clearInterval(intervalId);
                    }

                    return false;
                }
            });
        }
    
        // Start countdown interval for all active timers
        intervalId = setInterval(updateTimers, 1000);

        

        $(document).on("click", ".complete_bk", function() {
            var bookingUrl = $(this).data("url");
            if (bookingUrl) {
                window.location.href = bookingUrl; 
            }
        });
    
        // Event Listener for "Close Booking"
        $(document).on("click", ".close_bk", function() {
            var close_bk_id = $(this).data("bkid");
            closeBooking(close_bk_id)
           
        });
        $(document).on("click", ".close_body_timer", function() {
            jQuery(".close-body-timer").remove();
        });

        
    }

    
});