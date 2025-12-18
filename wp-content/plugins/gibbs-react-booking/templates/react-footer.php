<?php
global $wp_scripts, $wp_styles;
$version = time();


if(defined('GIBBS_VERSION')){
    $version = GIBBS_VERSION;
}
?>
<script defer src="<?php echo get_stylesheet_directory_uri();?>/assets/fontawesome-pro/js/solid.min.js"></script>
<script defer src="<?php echo get_stylesheet_directory_uri();?>/assets/fontawesome-pro/js/fontawesome.min.js"></script>
<?php
    if (!empty($wp_scripts->queue)) {
        foreach ($wp_scripts->queue as $handle) {
            if($handle == 'inteljs'){
                continue;
            }
            $script = $wp_scripts->registered[$handle];
            $version = $script->ver??$version;
            // If script is already enqueued, you can add it manually
            echo '<script src="' . esc_url($script->src) . '?v=' . $version . '" type="' . esc_attr($script->type) . '"';
            // Check if script is in footer
            if ($script->extra['group'] == 1) {
                echo ' async defer';
            }
            echo '></script>' . "\n";
        }
    }
?>
</body>
</html>