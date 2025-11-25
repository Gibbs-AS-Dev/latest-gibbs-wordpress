<?php
global $wp_scripts, $wp_styles;
$version = time();


if(defined('GIBBS_VERSION')){
    $version = GIBBS_VERSION;
}
?>
<?php
    if (!empty($wp_scripts->queue)) {
        foreach ($wp_scripts->queue as $handle) {
            $script = $wp_scripts->registered[$handle];
            // If script is already enqueued, you can add it manually
            echo '<script src="' . esc_url($script->src) . '" type="' . esc_attr($script->type) . '"';
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