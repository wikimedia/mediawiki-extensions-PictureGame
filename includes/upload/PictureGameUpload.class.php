<?php
/**
 * Quick helper class for SpecialPictureGameAjaxUpload::loadRequest; this prefixes the
 * filename with the timestamp. Yes, another class is needed for it. *sigh*
 */
class PictureGameUpload extends UploadFromFile {
	/**
	 * Create a form of UploadBase depending on wpSourceType and initializes it
	 */
	public static function createFromRequest( &$request, $type = null ) {
		$handler = new self;
		$handler->initializeFromRequest( $request );
		return $handler;
	}

	/**
	 * @param $request WebRequest
	 */
	function initializeFromRequest( &$request ) {
		$upload = $request->getUpload( 'wpUploadFile' );

		$desiredDestName = $request->getText( 'wpDestFile' );
		if ( !$desiredDestName ) {
			$desiredDestName = $request->getFileName( 'wpUploadFile' );
		}
		// Added for PictureGame
		$prefix = SpecialPictureGameAjaxUpload::getCallbackPrefix( $request );
		$desiredDestName = time() . '-' . $prefix . $desiredDestName;

		$this->initialize( $desiredDestName, $upload );
	}
}
