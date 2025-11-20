<?php
$logoutOrlogIn = '<ul id="%1$s" class="%2$s">%3$s<!--li class="profile-icon';
if(is_user_logged_in()){

     global $post;
     
     $ancestors = get_post_ancestors($post->ID);
     if(in_array(get_page_by_title('Min Gibbs')->ID, $ancestors) || get_page_by_title('Min Gibbs')->ID == $post->ID){
        $logoutOrlogIn .= " current-page-ancestor";
     }
     $logoutOrlogIn .= '"><a href="' . get_permalink(get_page_by_title('Min Gibbs')) . '">Min gibbs</a></li--></ul>';

     wp_nav_menu( array(
     	'theme_location' => 'main-nav',
     	'container_class' => $navWrapper,
          'items_wrap' => $logoutOrlogIn));

} else {

     $isTopMenu = ($navWrapper == 'main-nav');

     $navWrapper = "mobile-header";

     $enable_header = get_field('show_outlogged_header');

     if($enable_header != ""){
     

          $outlogoutOrlogIn = '<div class="menu-toggle2"><i class="fa fa-bars"></i></div><nav><ul id="%1$s" class="%2$s outlogged-menu">%3$s</ul><i class="fa fa-times close-tgl"></i></nav>';

          wp_nav_menu(array(
               'theme_location' => 'outlogged-nav',
               'container_class' => $navWrapper,
               'items_wrap' => $outlogoutOrlogIn,
               'walker' => new Custom_Dropdown_Walker()
          ));
     }

     //echo "<pre>"; print_r($languages = trp_custom_language_switcher()); die;

     ?>
     

	<div class="main-nav" <?php if($isTopMenu)  ?> >
		<ul id="menu-new-main-menu" class="menu">
			<?php
		         global $post;
                   $ancestors = get_post_ancestors($post->ID);
                   $homeShouldBeActive = (is_home() || is_front_page());
                   $profileShouldBeActive = (in_array(get_page_by_title('Min Gibbs')->ID, $ancestors) || get_page_by_title('Min Gibbs')->ID == $post->ID);
			?>
               <!-- <li <?php if($isTopMenu) { echo 'style="text-align:left;"'; } ?> class="home-icon <?php if($homeShouldBeActive) {echo ' current-page-ancestor';} ?>"><a <?php if($isTopMenu) {echo 'style="padding-left:25px;" '; } ?> href="<?php echo get_home_url(); ?>">Utforsk</a></li> -->
               <li class="get_your_trial <?php if($homeShouldBeActive)  ?>" <?php if($isTopMenu) { echo 'style="text-align:left;"'; } ?>> <a <?php if($isTopMenu) ?> href="/book-mote/">Book demo</a></li>
		     <!-- <li <?php if($isTopMenu) { echo 'style="text-align:left;"'; } ?>   <?php if($profileShouldBeActive) {echo ' current-page-ancestor';} ?>><a <?php if($isTopMenu) ?>  href="/logg-inn"> </i>  Logg inn </a></li> -->
               <li><?php echo do_shortcode('[language-switcher]');?></li>
		</ul>
	</div>

     <style>
          /* #header-new .trp-language-switcher > div > a:hover {
               padding: 7px 12px !important;
          }
          #header-new .trp-ls-shortcode-disabled-language.trp-ls-disabled-language {
          width: 100% !important;
          }
          .trp-ls-shortcode-language.trp-ls-clicked {
               min-width: 147px !important;
               top: 10px !important;
          } */

          /* Dropdown container */
          .outlogged-menu li{
               position: relative;
          }
          .custom-dropdown {
               position: absolute;
               top: 70%;
               left: 0;
               background: #fff;
               box-shadow: 0 8px 32px rgba(16, 24, 40, 0.18);
               padding: 17px 50px;
               min-width: 298px;
               z-index: 1000;
               margin-top: 16px;
          }

          /* Dropdown list */
          .custom-dropdown ul {
               list-style: none;
               margin: 0;
               padding: 0;
          }

          /* Dropdown item */
          .custom-dropdown li {
               display: flex;
               align-items: flex-start;
               gap: 7px;
               padding: 8px 0;
               /* border-bottom: 1px solid #f0f0f0; */
               width: 100%;
               cursor: pointer;
          }

          .custom-dropdown li:last-child {
               border-bottom: none;
          }

          /* Icon styling */
          .custom-dropdown .icon {
               font-size: 24px;
               color: #1B8474; 
               margin-top: 2px;
          }

          /* Title and description */
          .custom-dropdown strong {
               font-weight: 700;
               font-size: 16px;
               color: #101828;
               display: block;
          }

          .custom-dropdown .menu-desc {
               font-size: 14px;
               color: #667085;
               margin-top: 2px;
          }

          /* Show dropdown on hover */
          .outlogged-menu li > .custom-dropdown {
               display: none;
               opacity: 0;
               pointer-events: none;
               transition: opacity 0.2s;
          }
          .outlogged-menu li:hover > .custom-dropdown {
               display: block;
               opacity: 1;
               pointer-events: auto;
          }
          .custom-dropdown ul {
               flex-direction: row;
               flex-wrap: wrap;
               width: 100%;
          }

          /* Dropdown arrow styling */
          .menu-item-has-children > a:after {
               content: '\f107';
               font-family: 'Font Awesome 6 Pro';
               font-weight: 900;
               margin-left: 8px;
               display: inline-block;
               transition: all 0.3s ease;
          }
          .fa-star {
               content: '\f005';
               font-family: 'Font Awesome 6 Pro';
               font-weight: 900;
               margin-left: 8px;
               display: inline-block;
               transition: all 0.3s ease;
          }

          .menu-item-has-children.active-cl > a:after {
               transform: rotate(180deg);
               -webkit-transform: rotate(180deg);
               -moz-transform: rotate(180deg);
               -ms-transform: rotate(180deg);
               -o-transform: rotate(180deg);
          }

          /* Ensure parent menu items have proper spacing */
          .outlogged-menu > li > a {
               display: flex;
               align-items: center;
               justify-content: space-between;
          }
         
          .outlogged-menu li a {
               display: flex;
               justify-content: center;
               align-items: center;
               gap: 16px;
               width: 100%;
               color: #000;
          }
          .outlogged-menu .custom-dropdown li a, .outlogged-menu .custom-dropdown li a:hover, .outlogged-menu .custom-dropdown li a.active {
               color: #000;
               background: #fff;
          }

          /* Mobile specific styles */
          @media (max-width: 768px) {
               .outlogged-menu li > .custom-dropdown {
                    position: relative;
                    top: 0;
                    left: 0;
                    width: 100%;
                    margin-top: 0px;
                    box-shadow: none;
                    padding: 10px 20px;
                    background: transparent;
                    
               }
               .outlogged-menu ul {
                    margin-top: 0px !important;
                    padding: 0px !important;
               }
               

               .custom-dropdown li {
                    padding: 14px 32px;
               }
                .custom-dropdown li {
                    padding: 14px 32px;
               }
               .custom-dropdown li strong{
                    color: #fff;
               }
               .outlogged-menu li.active-cl > .custom-dropdown {
                    display: block !important;
                    opacity: 1 !important;
                    pointer-events: auto !important;
                    transition: opacity 0.3s ease, transform 0.3s ease;
               }
               .outlogged-menu li:hover > .custom-dropdown {
                    display: none;
                    opacity: 0;
                    pointer-events: none;
               }
               .outlogged-menu  li a:hover,  .outlogged-menu li a.active {
                    color: #fff !important;  
                    background: transparent !important;
               }
               .outlogged-menu .custom-dropdown li a, .outlogged-menu .custom-dropdown li a:hover, .outlogged-menu .custom-dropdown li a.active {
                    color: #fff !important;
                    background: transparent !important;
               }
               .outlogged-menu .custom-dropdown li a {
                    text-align: left;
                    border-bottom: none !important;
               }
              
          }

         
     </style>

<script>
jQuery(document).ready(function(){
     jQuery(document).on("click",'.close-tgl',function(){
          jQuery(".mobile-header").find('nav').removeClass('active');
     });

     // Handle mobile dropdown clicks
     jQuery(document).on('click', '.outlogged-menu > li.menu-item-has-children > a', function(e) {
          if (window.innerWidth <= 768) {
               e.preventDefault();
               var $parent = jQuery(this).parent();

               // Toggle this dropdown
               var $dropdown = $parent.find('.custom-dropdown').first();
               if ($parent.hasClass('active-cl')) {
                    $dropdown.stop(true, true).slideUp(400);
                    $parent.removeClass('active-cl');
               } else {
                    // Close others
                    $parent.siblings('.menu-item-has-children').removeClass('active-cl').find('.custom-dropdown').stop(true, true).slideUp(300);

                    // Open this one
                    $dropdown.stop(true, true).slideDown(400);
                    $parent.addClass('active-cl');
               }
          }
     });

})
</script>
<?php } ?>

<script>
jQuery(document).ready(function(){
     jQuery(document).on("click",'.close-tgl',function(){
          jQuery(".mobile-header").find('nav').removeClass('active');
     })
})
</script>
<?php
class Custom_Dropdown_Walker extends Walker_Nav_Menu {
    function start_lvl( &$output, $depth = 0, $args = array() ) {
        $output .= '<div class="custom-dropdown"><ul>';
    }
    function end_lvl( &$output, $depth = 0, $args = array() ) {
        $output .= '</ul></div>';
    }
    function start_el( &$output, $item, $depth = 0, $args = array(), $id = 0 ) {
        $icon_class = get_post_meta($item->ID, '_menu-item-icon', true);
        $icon_class = !empty($icon_class) ? esc_attr($icon_class) : '';
        $description = !empty($item->description) ? '<div class="menu-desc">'.esc_html($item->description).'</div>' : '';
        
        $classes = empty($item->classes) ? array() : (array) $item->classes;
        $has_children = in_array('menu-item-has-children', $classes);
        
        $output .= '<li class="' . implode(' ', $classes) . '">';
     //    if ($depth === 1) {
     //        //$output .= '<span class="icon fa fa-'.$icon_class.'"></span>';
     //        $output .= '<a href="'.esc_url($item->url).'"><span>'.$item->title.'</span>'.$description.'</a>';
     //    } else {
     //        $output .= '<a href="'.esc_url($item->url).'">'.esc_html($item->title).'</a>';
     //    }
        $output .= '<a href="'.esc_url($item->url).'"><span>'.$item->title.'</span>'.$description.'</a>';
    }
    function end_el( &$output, $item, $depth = 0, $args = array() ) {
        $output .= '</li>';
    }
}