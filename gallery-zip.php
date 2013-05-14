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
	add_action( 'init', __NAMESPACE__ . '\add_hooks', 10, 0 );
	add_action( 'init', __NAMESPACE__ . '\enqueue_scripts', 10, 0 );

	if ( is_admin() )
		return;

	// this is only needed on the frontend
	GalleryZip::get_instance();
	add_action( 'shutdown', array( __NAMESPACE__ . '\GalleryZip', 'save_in_session' ), 10, 0 );
}

function add_hooks() {
	add_action( 'wp_ajax_get_galleryzip',        __NAMESPACE__ . '\get_images_ajax_callback', 10, 0 );
	add_action( 'wp_ajax_nopriv_get_galleryzip', __NAMESPACE__ . '\get_images_ajax_callback', 10, 0 );
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

	$images = GalleryZip::get_images_zip( $post_id, $gallery_id );
	$send_result( $images );
}

class GalleryZip
{
	const SESSION_KEY = 'gallery-zip';

	// one of a predefined sizes (thumbnail, medium, large or full) or a
	// custom size registered with add_image_size
	const IMAGE_SIZE  = 'full';

	private static $instance = null;

	public static $images = array();

	public static function get_instance() {
		if ( ! session_id() )
			session_start();

		if ( null === self::$instance )
			self::$instance = new self();

		return self::$instance;
	}

	private final function __construct() {
		if ( ! session_id() )
			session_start();

		remove_shortcode( 'gallery' );
		add_shortcode( 'gallery', array( __CLASS__, 'gallery_zip_shortcode' ) );
	}

	public static function gallery_zip_shortcode( $atts ) {
		$post  = get_post();

		require_once ABSPATH . 'wp-includes/media.php';
		self::get_gallery_images_from_shortcode( $atts );
		$output = gallery_shortcode( $post->ID, $atts );

		$gallery_id = count( self::$images[$post->ID] ) - 1;

		$output .= sprintf( '<div><a href="#" gallery-id="%d" post-id="%d" class="gallery-zip">%s</a></div>', $gallery_id, $post->ID, __( 'Get as Zip' ) );

		return $output;
	}

	protected static function get_gallery_images_from_shortcode( $id, $atts ) {
		// use the post ID if the attribute 'ids' is not set or empty
		$id = ( ! isset( $atts['ids'] ) || empty( $atts['ids'] ) ) ?
			(int) $id : $atts['ids'];

		$exclude = ( isset( $atts['exclude'] ) && ! empty( $atts['exclude'] ) ) ?
			$atts['exclude'] : '';

		if ( ! isset( self::$images[$id] ) || ! is_array( self::$images[$id] ) )
			self::$images[$id] = array();

		$images = self::get_gallery_images( $id, $exclude );

		array_push( self::$images[$id], $images );

		return $images;
	}

	protected static function get_gallery_images( $id, $exclude ) {
		$images     = array();
		$query_args = array(
				'post_status'    => 'inherit',
				'post_type'      => 'attachment',
				'post_mime_type' => 'image',
		);

		// handle gallery WP3.5+
		// if $id contains an comma, it is a list of post IDs
		if ( false !== strpos( $id, ',' ) ) {
			$query_args['include'] = $id;
		} elseif ( ! empty( $exclude ) ) {
			// handle excluding posts
			$query_args['post_parent'] = $id;
			$query_args['exclude']     = $exclude;
		} else {
			// handle gallery before WP3.5
			$query_args['post_parent'] = $id;
		}

		$attachments = get_posts( $query_args );

		$img_sizes = array_merge( array( 'full' ), get_intermediate_image_sizes() );

		$img_size = ( in_array( self::IMAGE_SIZE, $img_sizes ) ) ?
				self::IMAGE_SIZE : 'full';

		foreach ( $attachments as $key => $post ) {
			$img = wp_get_attachment_image_src( $post->ID, $img_size, false, false );
			$images[] = sprintf( '%s/%s', dirname( get_attached_file( $post->ID ) ), basename( $img[0] ) );
		}

		return $images;
	}

	public static function save_in_session() {
		$_SESSION[self::SESSION_KEY] = self::$images;
		session_write_close();
	}

	public static function get_images_ajax_callback( $post_id = 0, $gallery_id = 0 ) {
		if ( ! session_id() )
			session_start();

		if ( empty( self::$images ) )
			self::$images = $_SESSION[self::SESSION_KEY];

		return ( isset( self::$images[$post_id][$gallery_id] ) ) ?
			self::$images[$post_id][$gallery_id] : array();
	}

}