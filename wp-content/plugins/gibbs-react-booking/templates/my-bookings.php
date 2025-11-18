<?php
/**
 * My Bookings Template
 * 
 * This template is used for the my bookings page
 * URL: /my-bookings/
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

get_header();
?>

<div class="rmp-my-bookings-page">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <div class="page-header">
                    <h1>My Bookings</h1>
                    <p class="lead">Manage and view all your bookings</p>
                </div>

                <div class="bookings-navigation">
                    <ul class="nav nav-tabs" id="bookingsTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="upcoming-tab" data-bs-toggle="tab" data-bs-target="#upcoming" type="button" role="tab">
                                <i class="fas fa-calendar-alt"></i> Upcoming Bookings
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="past-tab" data-bs-toggle="tab" data-bs-target="#past" type="button" role="tab">
                                <i class="fas fa-history"></i> Past Bookings
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="cancelled-tab" data-bs-toggle="tab" data-bs-target="#cancelled" type="button" role="tab">
                                <i class="fas fa-times-circle"></i> Cancelled Bookings
                            </button>
                        </li>
                    </ul>
                </div>

                <div class="tab-content" id="bookingsTabContent">
                    <div class="tab-pane fade show active" id="upcoming" role="tabpanel">
                        <div class="bookings-section">
                            <h3>Upcoming Bookings</h3>
                            <div id="rmp-upcoming-bookings" class="bookings-container">
                                <!-- React component will load here -->
                                <div class="loading-spinner">
                                    <i class="fas fa-spinner fa-spin"></i> Loading upcoming bookings...
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="past" role="tabpanel">
                        <div class="bookings-section">
                            <h3>Past Bookings</h3>
                            <div id="rmp-past-bookings" class="bookings-container">
                                <!-- React component will load here -->
                                <div class="loading-spinner">
                                    <i class="fas fa-spinner fa-spin"></i> Loading past bookings...
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="cancelled" role="tabpanel">
                        <div class="bookings-section">
                            <h3>Cancelled Bookings</h3>
                            <div id="rmp-cancelled-bookings" class="bookings-container">
                                <!-- React component will load here -->
                                <div class="loading-spinner">
                                    <i class="fas fa-spinner fa-spin"></i> Loading cancelled bookings...
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main React component container -->
                <div id="rmp-my-bookings"></div>
            </div>
        </div>
    </div>
</div>

<style>
.rmp-my-bookings-page {
    padding: 40px 0;
    background-color: #f8f9fa;
    min-height: 60vh;
}

.page-header {
    text-align: center;
    margin-bottom: 30px;
}

.page-header h1 {
    color: #333;
    margin-bottom: 10px;
}

.bookings-navigation {
    margin-bottom: 30px;
}

.bookings-section {
    background: white;
    border-radius: 10px;
    padding: 25px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin-bottom: 20px;
}

.bookings-section h3 {
    color: #333;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid #007bff;
}

.bookings-container {
    min-height: 200px;
}

.loading-spinner {
    text-align: center;
    padding: 40px;
    color: #666;
}

.loading-spinner i {
    font-size: 2rem;
    margin-bottom: 10px;
    display: block;
}

.nav-tabs .nav-link {
    color: #666;
    border: none;
    padding: 12px 20px;
    margin-right: 5px;
    border-radius: 5px 5px 0 0;
}

.nav-tabs .nav-link.active {
    background-color: #007bff;
    color: white;
    border: none;
}

.nav-tabs .nav-link:hover {
    border: none;
    background-color: #e9ecef;
}

.nav-tabs .nav-link.active:hover {
    background-color: #0056b3;
}
</style>

<script>
// Initialize React components when page loads
document.addEventListener('DOMContentLoaded', function() {
    // This will be handled by your React components
    console.log('My Bookings page loaded');
});
</script>

<?php
get_footer();
?> 