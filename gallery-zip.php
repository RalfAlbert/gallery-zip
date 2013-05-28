<?php
/**
 * WordPress-Plugin Allows the user to zip all images in a gallery
 *
 * PHP version 5.3
 *
 * @category   PHP
 * @package    WordPress
 * @subpackage Gallery Zip
 * @author     Ralf Albert <me@neun12.de>
 * @license    GPLv3 http://www.gnu.org/licenses/gpl-3.0.txt
 * @version    0.1
 * @link       http://wordpress.com
 */

/**
 * Plugin Name:	Gallery Zip
 * Plugin URI:	http://yoda.neun12.de
 * Description:	Allows the user to zip all images in a gallery
 * Version: 	0.1
 * Author: 		Ralf Albert
 * Author URI: 	http://yoda.neun12.de
 * Network:     true
 * License:		GPLv3
 */
namespace GalleryZip;

add_action( 'plugins_loaded', __NAMESPACE__ . '\gallery_zip_start', 10, 0 );

function gallery_zip_start() {
	// simple autoloader
	$classes = glob( dirname( __FILE__ ) . '/classes/*.php' );

	if ( ! empty( $classes ) ) {
		foreach ( $classes as $class )
			require_once $class;


		add_action( 'init', __NAMESPACE__ . '\add_hooks', 10, 0 );
		add_action( 'init', __NAMESPACE__ . '\enqueue_scripts', 10, 0 );

		if ( is_admin() )
			return;

		// this is only needed on the frontend
		GalleryZip::get_instance( new GalleryZip_DataContainer() );

	}
}

function add_hooks() {
	add_action( 'wp_ajax_get_galleryzip',        __NAMESPACE__ . '\get_gallery_zip', 10, 0 );
	add_action( 'wp_ajax_nopriv_get_galleryzip', __NAMESPACE__ . '\get_gallery_zip', 10, 0 );
}

function enqueue_scripts() {
	// load minified version if SCRIPT_DEBUG is true
	$min = ( defined( 'SCRIPT_DEBUG' ) && true == SCRIPT_DEBUG ) ? '' : '.min';
	wp_enqueue_script(
		'gallery-zip',
		plugins_url(
			sprintf( 'js/gallery_zip%s.js', $min ),
			__FILE__
		),
		array( 'jquery' ),
		false,
		true
	);

	// set JS object with params
	wp_localize_script( 'gallery-zip', 'GalleryZip', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ) ) );
}

function get_gallery_zip() {
	$send_result = function( $result = '' ) {
		if ( is_array( $result ) )
			$result = var_export( $result, true );

		header( 'Content-type: application/json' );
		die( json_encode( array( 'result' => $result ) ) );
	};

	$post_id    = (int) filter_input( INPUT_POST, 'post_id', FILTER_SANITIZE_NUMBER_INT );
	$gallery_id = (int) filter_input( INPUT_POST, 'gallery_id', FILTER_SANITIZE_NUMBER_INT );

	if ( 0 >= $post_id )
		$send_result( var_export( $_POST, true ) );

	$images = GalleryZip::get_images_ajax_callback( $post_id, $gallery_id );
	$send_result( $images );
}
