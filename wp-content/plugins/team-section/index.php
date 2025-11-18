<?php
/**
 * Plugin Name: Team Section - Block
 * Description: Makes background element scrolls slower than foreground content.
 * Version: 1.1.0
 * Author: bPlugins
 * Author URI: http://bplugins.com
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.txt
 * Text Domain: team-section
 */

// ABS PATH
if ( !defined( 'ABSPATH' ) ) { exit; }

// Constant
define( 'TSB_VERSION', isset( $_SERVER['HTTP_HOST'] ) && 'localhost' === $_SERVER['HTTP_HOST'] ? time() : '1.1.0' );
define( 'TSB_DIR_URL', plugin_dir_url( __FILE__ ) );
define( 'TSB_DIR_PATH', plugin_dir_path( __FILE__ ) );

if( !class_exists( 'TSBPlugin' ) ){
	class TSBPlugin{
		function __construct(){
			add_action( 'enqueue_block_assets', [$this, 'enqueueBockAssets'] );
			add_action( 'init', [$this, 'onInit'] );
		}
	
		function enqueueBockAssets(){
			wp_register_style( 'fontAwesome', TSB_DIR_URL . 'assets/css/font-awesome.min.css', [], '6.4.2' );
		}

		function onInit(){
			register_block_type( __DIR__ . '/build' );
		}
	}

	new TSBPlugin();
}

// require_once TSB_DIR_PATH . 'inc/block.php';