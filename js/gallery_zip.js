/**
 * jQuery for plugin GalleryZip
 * @version 2013-05-30
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
						function(response){
							if( undefined !== response.result ){
								if ( false !== response.result )
									document.location = response.result;
								else
									alert( 'Could not create zip' );
							} else {
								alert( 'Sorry, but something went wrong while creating the zip file.' );
							}
							
						}
					);
				}
			}
		);
	}
);