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
    <title><?php wp_title('|', true, 'right'); ?></title>
     <!-- Manually output enqueued styles in the <head> -->
     <?php
    if (!empty($wp_styles->queue)) {
        foreach ($wp_styles->queue as $handle) {
            $style = $wp_styles->registered[$handle];
            echo '<link rel="stylesheet" href="' . esc_url($style->src) . '" type="text/css" media="' . esc_attr($style->args) . '" />' . "\n";
        }
    }
    ?>
    <style>
        /* #wpadminbar{
            display: none;
        } */
    </style>
</head>
<body class="react-booking-body">