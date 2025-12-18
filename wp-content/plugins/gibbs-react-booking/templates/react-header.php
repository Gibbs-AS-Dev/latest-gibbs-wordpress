<?php
global $wp_scripts, $wp_styles;
$version = time();


if(defined('GIBBS_VERSION')){
    $version = GIBBS_VERSION;
}

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php wp_title('|', true, 'right'); ?> <?php echo bloginfo('name'); ?></title>
    <link href="<?php echo get_stylesheet_directory_uri();?>/assets/fontawesome-pro/css/fontawesome.min.css" rel="stylesheet">
    <link href="<?php echo get_stylesheet_directory_uri();?>/assets/fontawesome-pro/css/solid.min.css" rel="stylesheet">
    <link href="<?php echo get_stylesheet_directory_uri();?>/assets/fontawesome-pro/css/svg-with-js.css" rel="stylesheet"> 
    <link href="<?php echo RMP_PLUGIN_URL;?>/src/css/gibbs-view.css?v=<?php echo $version; ?>" rel="stylesheet"> 
    <?php
    if (!empty($wp_styles->queue)) {
        foreach ($wp_styles->queue as $handle) {

            if($handle == 'application_form_common-style'){
                continue;
            }
            $style = $wp_styles->registered[$handle];
            $version = $style->ver??$version;
            echo '<link rel="stylesheet" href="' . esc_url($style->src) . '?v=' . $version . '" type="text/css" media="' . esc_attr($style->args) . '" />' . "\n";
        }
    }
    
    ?>
    <style>
        /* #wpadminbar{
            display: none;
        } */
        body{
          margin: 0;
          padding: 0;
        }
    </style>
    <?php

    $logo = get_option( 'pp_logo_upload', '' );
    $logo_transparent = get_option( 'pp_dashboard_logo_upload', '' );

    $logo_retina = get_option( 'pp_retina_logo_upload', '' );

    // Get WP nav menu items (by theme_location "primary" for example)
    $menu_items = [];
    $locations = get_nav_menu_locations();
    $theme_location = 'editor-dashboard'; // Change this to your menu location as needed

    if (isset($locations[$theme_location])) {
        $menu = wp_get_nav_menu_object($locations[$theme_location]);
        if ($menu) {
            $raw_menu_items = wp_get_nav_menu_items($menu->term_id);
            if (function_exists('_wp_menu_item_classes_by_context')) {
                _wp_menu_item_classes_by_context($raw_menu_items);
            }

            // Apply wp_nav_menu_objects filter to the menu items
            if (has_filter('wp_nav_menu_objects')) {
                global $wp_query;
                $args = array(
                    'menu' => $menu,
                    'theme_location' => $theme_location,
                    'menu_class' => '',
                    'menu_id' => '',
                    'container' => false,
                    'echo' => false,
                    'fallback_cb' => false,
                    'walker' => '',
                );
                // add these args in standard WordPress calls
                $filtered_menu_items = apply_filters('wp_nav_menu_objects', $raw_menu_items, (object) $args);
            } else {
                $filtered_menu_items = $raw_menu_items;
            }

            // echo "<pre>";
            // print_r($filtered_menu_items);
            // echo "</pre>";
            // exit;
            

            // Format the menu items array for use in React/JS
            foreach ((array)$filtered_menu_items as $item) {
                $menu_items[] = [
                    'ID' => $item->ID,
                    'title' => $item->title,
                    'url' => $item->url,
                    'parent' => $item->menu_item_parent,
                    'order' => $item->menu_order,
                    'classes' => $item->classes,
                    'attr_title' => $item->attr_title,
                    'target' => $item->target,
                    'description' => $item->description
                ];
            }
        }
    }

   
    // Prepare any PHP data you want to send to React
    // Example: $data = ['user'=>..., 'custom'=>..., ...];
    $data = [
        // Example keys, adjust to your needs:
        'user' => is_user_logged_in() ? wp_get_current_user()->data : null,
        'siteUrl' => get_site_url(),
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'layout' => 'true',
        'sidebar' => 'true',
        'header' => 'true',
        'footer' => 'true',
        'logo' => $logo,
        'logo_transparent' => $logo_transparent,
        'logo_retina' => $logo_retina,
        'userInfo' => is_user_logged_in() ? wp_get_current_user()->data : null,
        'menuItems' => $menu_items,
        'title' => get_the_title(),
        // Add more as needed
    ];

    // Allow filtering data to provide more via hooks
    $data = apply_filters('gibbs_react_page_data', $data);

    // JSON encode for inline script (don't escape quotes for <script> blocks)
    $json_data = wp_json_encode($data);
    ?>

    <script type="text/javascript">
        window.pagedata = <?php echo $json_data; ?>;
    </script>
</head>
<body class="react-view-body">