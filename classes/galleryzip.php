<?php
namespace GalleryZip;

use GalleryZip\Zipper\Zipper;

/**
 * ToDo
 *
 * - make image sizes as option
 * - make link text and classes for link as option
 * - add option to cache zip-files or not (and if, how long)
 *
 */
class GalleryZip
{
	/**
	 * DataContainer
	 * @var object
	 */
	private static $dc = null;

	/**
	 * One of a predefined sizes (thumbnail, medium, large or full) or a
	 * custom size registered with add_image_size
	 * Will be removed in a future version with an option
	 *
	 * @var string
	 */
	const IMAGE_SIZE  = 'full';

	/**
	 * Instance of this class
	 * @var object
	 */
	private static $instance = null;

	/**
	 * Array with pathes to images
	 * @var array
	 */
	public static $images = array();

	/**
	 * Returns an instance of this class
	 * @param	object	$dc		Optional instance of a DataContainer
	 */
	public static function get_instance( $dc = null ) {
		self::get_datacontainer( $dc );

		if ( null === self::$instance )
			self::$instance = new self();

		return self::$instance;
	}

	/**
	 * Constructor
	 * - removes the original shortcode
	 * - add the custom shortcode
	 */
	private final function __construct() {
		remove_shortcode( 'gallery' );
		add_shortcode( 'gallery', array( __CLASS__, 'gallery_zip_shortcode' ) );
	}

	/**
	 * Returns the DataContainer or create a new one if none is set
	 * @param	object	$dc		DataContainer
	 */
	private static function get_datacontainer( $dc = null ) {
		if ( null === self::$dc ) {
			if ( null !== $dc && ( $dc instanceof GalleryZip_DataContainer ) )
				self::$dc = $dc;
			else
				self::$dc = new GalleryZip_DataContainer();
		}
	}

	/**
	 * The custom shortcode for the gallery
	 * This shortcode fetch the arguments for the shortcode and passes them to the original
	 * shortcode. After this, it appends a link to download the zip-file.
	 * @param	array	$atts	Shortcode attributes
	 * @return	string			HTML of the gallery
	 */
	public static function gallery_zip_shortcode( $atts ) {
		require_once ABSPATH . 'wp-includes/media.php';
		$output = gallery_shortcode( $atts );

		$post  = get_post();
		self::get_gallery_images_from_shortcode( $post->ID, $atts );

		$gallery_id = ( isset( self::$images[$post->ID] ) ) ?
			count( self::$images[$post->ID] ) - 1 : 0;

		$output .= sprintf( '<div><a href="#" gallery-id="%d" post-id="%d" class="gallery-zip">%s</a></div>', $gallery_id, $post->ID, __( 'Get as Zip' ) );

		return $output;
	}

	/**
	 * Get the list of images from a gallery.
	 * @param	integer	$post_id	ID of the post with gallery(s)
	 * @param	array	$atts		Shortcode attributes
	 * @return	array				Array with pathes to the gallery-images
	 */
	protected static function get_gallery_images_from_shortcode( $post_id, $atts ) {
		// use the post ID if the attribute 'ids' is not set or empty
		$id = ( ! isset( $atts['ids'] ) || empty( $atts['ids'] ) ) ?
			(int) $post_id : $atts['ids'];

		$exclude = ( isset( $atts['exclude'] ) && ! empty( $atts['exclude'] ) ) ?
			$atts['exclude'] : '';

		if ( ! isset( self::$images[$post_id] ) || ! is_array( self::$images[$post_id] ) )
			self::$images[$post_id] = array();

		$images = self::get_gallery_images( $id, $exclude );

		array_push( self::$images[$post_id], $images );

		self::$dc->images = self::$images;

		return $images;
	}

	/**
	 * Get the image-pathes from a gallery. Handles galleries after WP 3.5 and before
	 * @param	string	$id			Comma seperated list of attachment-IDs (>WP3.5) or the post ID (<WP3.5)
	 * @param	string	$exclude	Comma seperated list of images to exclude from the gallery
	 * @return	array				Array with image-apthes
	 */
	protected static function get_gallery_images( $id, $exclude ) {
		$images     = array();
		$query_args = array(
				'post_status'    => 'inherit',
				'post_type'      => 'attachment',
				'post_mime_type' => 'image',
				'numberposts'    => -1,
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

	/**
	 * Ceates the zip-file and send the url of the zip-file to the browser for download
	 * @param	integer	$post_id		Post ID
	 * @param	integer	$gallery_id		The n-th gallery in the post
	 * @return	string					URL to the zip-file
	 */
	public static function get_images_ajax_callback( $post_id = 0, $gallery_id = 0 ) {
		self::get_datacontainer();

		if ( empty( self::$images ) )
			self::$images = self::$dc->images;

		$zipfile = '';
		$images = ( isset( self::$images[$post_id][$gallery_id] ) ) ?
			self::$images[$post_id][$gallery_id] : array();

		if ( ! empty( $images ) ) {

			$post_title = sanitize_title_with_dashes( get_the_title( $post_id ) );
			$zipname = sprintf( 'gallery-%d-from-%s.zip', ( $gallery_id+1 ), $post_title );
			$zipper  = new Zipper();
			$zipfile = $zipper->zip_images( $zipname, $images );
		}

		$response = ( ! empty( $zipfile ) ) ? $zipfile : '';

		return $response;
	}

}