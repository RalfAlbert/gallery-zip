/**
 * jQuery for plugin GalleryZip
 * @version 2013-05-14
 */
jQuery(document).ready(
	function($){
		$( '.gallery-zip' ).click(
			function(e) {
				e.preventDefault();
				if ( undefined !== GalleryZip.ajaxurl ) {
					$.post(
						GalleryZip.ajaxurl,
						{ 'action' : 'get_galleryzip', 'post_id' : $(this).attr( 'post-id' ), 'gallery_id' : $(this).attr( 'gallery-id' ) },
						function(respond){
							if( undefined !== respond.result ){
								console.log( respond.result );
							}
						}
					);
				}
			}
		);
	}
);