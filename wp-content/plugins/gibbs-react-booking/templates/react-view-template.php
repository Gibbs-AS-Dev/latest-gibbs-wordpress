<?php
/*
Template Name: React View Template
Description: A custom template that loads only React JS and CSS files for the gibbs-react-booking plugin
Version: 1.0
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$react_modules_plugin = new ReactModulesPlugin();
$react_modules_plugin->enqueue_scripts();
echo $react_modules_plugin->react_header();
?>
    <div id="app" class="site">
        <?php
            while (have_posts()) :
                the_post();
                ?>
                <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
                    <div class="entry-content">
                        <?php
                        the_content();
                        ?>
                    </div>
                </article>
        <?php
            endwhile;
        ?>
    </div>
<?php
echo $react_modules_plugin->react_footer();
?>
