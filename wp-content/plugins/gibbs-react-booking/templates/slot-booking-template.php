<?php
/*
Template Name: React Booking Template
Description: A custom template that loads only React JS and CSS files for the gibbs-react-booking plugin
Version: 1.0
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
global $wp_scripts;
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
    <?php
        $css_file = file_exists(RMP_PLUGIN_PATH . 'assets/css/components.min.css') ? 'components.min.css' : 'components.css';
        $js_file = file_exists(RMP_PLUGIN_PATH . 'assets/js/components.min.js') ? 'components.min.js' : 'components.js';
    ?>
    <link rel="stylesheet" href="<?php echo RMP_PLUGIN_URL . 'assets/css/' . $css_file; ?>?v=<?php echo $version; ?>">
    <script src="<?php echo RMP_PLUGIN_URL . 'react/react.production.min.js'; ?>?v=18.2.0"></script>
    <script src="<?php echo RMP_PLUGIN_URL . 'react/react-dom.production.min.js'; ?>?v=18.2.0"></script>
    <script src="<?php echo RMP_PLUGIN_URL . 'assets/js/' . $js_file; ?>?v=<?php echo $version; ?>"></script>
    <style>
        #wpadminbar{
            display: none;
        }
    </style>
</head>
<body class="react-booking-body">
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
</body>
</html>
