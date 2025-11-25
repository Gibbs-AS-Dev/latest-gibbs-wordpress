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


    <div id="page" class="site">
        <div id="content" class="site-content">
            <main id="main" class="site-main">
                <div class="container">
                    <?php
                    while (have_posts()) :
                        the_post();
                        ?>
                        <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
                            <header class="entry-header">
                                <?php the_title('<h1 class="entry-title">', '</h1>'); ?>
                            </header>

                    <div class="entry-content">
                        <?php
                        the_content();
                        ?>
                    </div>
                </article>
            <?php
            endwhile;
            ?>
            </main>
        </div>
    </div>
<?php
echo $react_modules_plugin->react_footer();
?>
