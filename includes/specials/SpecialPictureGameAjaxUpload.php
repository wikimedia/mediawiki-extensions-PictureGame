<?php
/**
 * New version of that fucking AJAX upload form.
 * Originally written as 1.16-compatible; this one's built against and tested
 * with MW 1.21.1.
 *
 * wpThumbWidth is the width of the thumbnail that will be returned
 * Also, to prevent overwriting uploads of files with popular names i.e.
 * Image.jpg all the uploaded files are prepended with the current timestamp.
 * This class, unlike PollNY's and QuizGame's upload classes, also respects
 * callbackPrefix because we need it for PictureGame as we must upload *two*
 * images instead of one.
 *
 * @file
 * @ingroup SpecialPage
 * @ingroup Upload
 * @author Jack Phoenix
 * @date 22 July 2013
 * @note Based on 1.16 core SpecialUpload.php (GPL-licensed) by Bryan et al.
 * @see http://bugzilla.shoutwiki.com/show_bug.cgi?id=22
 */
class SpecialPictureGameAjaxUpload extends SpecialUpload {
	/**
	 * Constructor: initialise object
	 * Get data POSTed through the form and assign them to the object
	 *
	 * @param $request WebRequest: Data posted.
	 */
	public function __construct() {
		parent::__construct();
		$this->mName = 'PictureGameAjaxUpload';
	}

	public function isListed() {
		return false;
	}

	/**
	 * apparently you don't need to (re)declare the protected/public class
	 * member variables here, so I removed them.
	 */

	/**
	 * Initialize instance variables from request and create an Upload handler
	 *
	 * What was changed here: $this->mIgnoreWarning is now unconditionally true
	 * and mUpload uses PictureGameUpload instead of UploadBase
	 */
	protected function loadRequest() {
		$this->mRequest = $request = $this->getRequest();
		$this->mSourceType        = $request->getVal( 'wpSourceType', 'file' );
		$this->mUpload            = PictureGameUpload::createFromRequest( $request );
		$this->mUploadClicked     = $request->wasPosted()
			&& ( $request->getCheck( 'wpUpload' )
				|| $request->getCheck( 'wpUploadIgnoreWarning' ) );

		// Guess the desired name from the filename if not provided
		$this->mDesiredDestName   = $request->getText( 'wpDestFile' );
		if ( !$this->mDesiredDestName && $request->getFileName( 'wpUploadFile' ) !== null ) {
			$this->mDesiredDestName = $request->getFileName( 'wpUploadFile' );
		}
		$this->mComment           = $request->getText( 'wpUploadDescription' );
		$this->mLicense           = $request->getText( 'wpLicense' );

		$this->mDestWarningAck    = $request->getText( 'wpDestFileWarningAck' );
		$this->mIgnoreWarning     = true;//$request->getCheck( 'wpIgnoreWarning' ) || $request->getCheck( 'wpUploadIgnoreWarning' );
		$this->mWatchthis         = $request->getBool( 'wpWatchthis' ) && $this->getUser()->isLoggedIn();
		$this->mCopyrightStatus   = $request->getText( 'wpUploadCopyStatus' );
		$this->mCopyrightSource   = $request->getText( 'wpUploadSource' );

		$this->mForReUpload       = $request->getBool( 'wpForReUpload' ); // updating a file
		$this->mCancelUpload      = $request->getCheck( 'wpCancelUpload' )
		                         || $request->getCheck( 'wpReUpload' ); // b/w compat

		// If it was posted check for the token (no remote POST'ing with user credentials)
		$token = $request->getVal( 'wpEditToken' );
		if ( $this->mSourceType == 'file' && $token == null ) {
			// Skip token check for file uploads as that can't be faked via JS...
			// Some client-side tools don't expect to need to send wpEditToken
			// with their submissions, as that's new in 1.16.
			$this->mTokenOk = true;
		} else {
			$this->mTokenOk = $this->getUser()->matchEditToken( $token );
		}
	}

	/**
	 * Special page entry point
	 *
	 * What was changed here: the setArticleBodyOnly() line below was added,
	 * and some bits of code were entirely removed.
	 */
	public function execute( $par ) {
		// Disable the skin etc.
		$this->getOutput()->setArticleBodyOnly( true );

		// Allow framing so that after uploading an image, we can actually show
		// it to the user :)
		$this->getOutput()->allowClickjacking();

		# Check that uploading is enabled
		if ( !UploadBase::isEnabled() ) {
			throw new ErrorPageError( 'uploaddisabled', 'uploaddisabledtext' );
		}

		# Check permissions
		$user = $this->getUser();
		$permissionRequired = UploadBase::isAllowed( $user );
		if ( $permissionRequired !== true ) {
			throw new PermissionsError( $permissionRequired );
		}

		# Check blocks
		if ( $user->isBlocked() ) {
			throw new UserBlockedError( $user->getBlock() );
		}

		# Check whether we actually want to allow changing stuff
		$this->checkReadOnly();

		$this->loadRequest();

		# Unsave the temporary file in case this was a cancelled upload
		if ( $this->mCancelUpload ) {
			if ( !$this->unsaveUploadedFile() ) {
				# Something went wrong, so unsaveUploadedFile showed a warning
				return;
			}
		}

		# Process upload or show a form
		if ( $this->mTokenOk && !$this->mCancelUpload && ( $this->mUpload && $this->mUploadClicked ) ) {
			$this->processUpload();
		} else {
			$this->showUploadForm( $this->getUploadForm() );
		}

		# Cleanup
		if ( $this->mUpload ) {
			$this->mUpload->cleanupTempFile();
		}
	}

	/**
	 * Get a PictureGameAjaxUploadForm instance with title and text properly set.
	 *
	 * @param $message String: HTML string to add to the form
	 * @param $sessionKey String: session key in case this is a stashed upload
	 * @return PictureGameAjaxUploadForm
	 */
	protected function getUploadForm( $message = '', $sessionKey = '', $hideIgnoreWarning = false ) {
		# Initialize form
		$form = new PictureGameAjaxUploadForm( [
			'watch' => $this->getWatchCheck(),
			'forreupload' => $this->mForReUpload,
			'sessionkey' => $sessionKey,
			'hideignorewarning' => $hideIgnoreWarning,
			'destwarningack' => (bool)$this->mDestWarningAck,
			'destfile' => $this->mDesiredDestName,
		] );
		$form->setTitle( $this->getPageTitle() );

		# Check the token, but only if necessary
		if ( !$this->mTokenOk && !$this->mCancelUpload
				&& ( $this->mUpload && $this->mUploadClicked ) ) {
			$form->addPreText( $this->msg( 'session_fail_preview' )->parse() );
		}

		# Add upload error message
		$form->addPreText( $message );

		return $form;
	}

	/**
	 * Stashes the upload and shows the main upload form.
	 *
	 * Note: only errors that can be handled by changing the name or
	 * description should be redirected here. It should be assumed that the
	 * file itself is sane and has passed UploadBase::verifyFile. This
	 * essentially means that UploadBase::VERIFICATION_ERROR and
	 * UploadBase::EMPTY_FILE should not be passed here.
	 *
	 * @param $message String: HTML message to be passed to mainUploadForm
	 */
	protected function showRecoverableUploadError( $message ) {
		$sessionKey = $this->mUpload->stashSession();
		$message = '<h2>' . $this->msg( 'uploaderror' )->escaped() . "</h2>\n" .
			'<div class="error">' . $message . "</div>\n";

		$form = $this->getUploadForm( $message, $sessionKey );
		$form->setSubmitText( $this->msg( 'upload-tryagain' )->escaped() );
		$this->showUploadForm( $form );
	}

	/**
	 * Show the upload form with error message, but do not stash the file.
	 *
	 * @param $message String: error message to show
	 */
	protected function showUploadError( $message ) {
		$prefix = self::getCallbackPrefix( $this->getRequest() );
		$message = Xml::encodeJsVar( $message );
		$output = "<script language=\"javascript\">
			/*<![CDATA[*/
				// If PictureGame class isn't loaded, then load it via ResourceLoader
				// Yes, this is somewhat of a cheap hack since it seems that at
				// this point, PictureGame class is never loaded, but hey, it
				// works...
				if ( typeof PictureGame !== 'object' ) {
					window.parent.mw.loader.using( 'ext.pictureGame', function() {
						window.parent.PictureGame.{$prefix}uploadError( {$message} );
					} );
				} else {
					window.parent.PictureGame.{$prefix}uploadError( {$message} );
				}
			/*]]>*/</script>";
		$this->showUploadForm( $this->getUploadForm( $output ) );
	}

	/**
	 * Do the upload.
	 * Checks are made in SpecialPictureGameAjaxUpload::execute()
	 *
	 * What was changed here: one hook and the post-upload redirect were removed
	 * in favor of the code below the $this->mUploadSuccessful = true; line
	 */
	protected function processUpload() {
		// Fetch the file if required
		$status = $this->mUpload->fetchFile();
		if ( !$status->isOK() ) {
			$this->showUploadError( $this->getOutput()->parseAsInterface( $status->getWikiText() ) );
			return;
		}

		// Upload verification
		$details = $this->mUpload->verifyUpload();
		if ( $details['status'] != UploadBase::OK ) {
			$this->processVerificationError( $details );
			return;
		}

		// Verify permissions for this title
		$permErrors = $this->mUpload->verifyTitlePermissions( $this->getUser() );
		if ( $permErrors !== true ) {
			$code = array_shift( $permErrors[0] );
			$this->showRecoverableUploadError( $this->msg( $code, $permErrors[0] )->parse() );
			return;
		}

		$this->mLocalFile = $this->mUpload->getLocalFile();

		// Check warnings if necessary
		if ( !$this->mIgnoreWarning ) {
			$warnings = $this->mUpload->checkWarnings();
			if ( $this->showUploadWarning( $warnings ) ) {
				return;
			}
		}

		$contLang = MediaWiki\MediaWikiServices::getInstance()->getContentLanguage();
		$categoryText = '[[' . $contLang->getNsText( NS_CATEGORY ) . ':' .
			$this->msg( 'picturegame-images-category' )->inContentLanguage()->plain() . ']]';
		// Get the page text if this is not a reupload
		//if( !$this->mForReUpload ) {
			$pageText = self::getInitialPageText(
				$categoryText, //$this->mComment,
				$this->mLicense,
				$this->mCopyrightStatus,
				$this->mCopyrightSource
			);
		//} else {
		//	$pageText = false;
		//}

		$status = $this->mUpload->performUpload(
			$categoryText/*$this->mComment*/, $pageText, $this->mWatchthis, $this->getUser()
		);

		if ( !$status->isGood() ) {
			$this->showUploadError( $this->getOutput()->parseAsInterface( $status->getWikiText() ) );
			return;
		}

		// Success, redirect to description page
		$this->mUploadSuccessful = true;

		$this->getOutput()->setArticleBodyOnly( true );
		$this->getOutput()->clearHTML();

		$thumbWidth = $this->getRequest()->getInt( 'wpThumbWidth', 75 );

		// The old version below, which initially used $this->mDesiredDestName
		// instead of that getTitle() caused plenty o' fatals...the new version
		// seems to be OK...I think.
		//$img = MediaWikiServices::getInstance()->getRepoGroup()->findFile( $this->mUpload->getTitle() );
		$img = $this->mLocalFile;

		if ( !$img ) {
			// This should NOT be happening...the transform() call below
			// will cause a fatal error if $img is not an object
			error_log(
				'PictureGame/MiniAjaxUpload FATAL! $this->mUpload is: ' .
				print_r( $this->mUpload, true )
			);
		}

		$thumb = $img->transform( [ 'width' => $thumbWidth ] );
		$img_tag = $thumb->toHtml();
		$slashedImgTag = Xml::encodeJSVar( $img_tag );

		$prefix = self::getCallbackPrefix( $this->getRequest() );

		// $this->mDesiredDestName doesn't include the timestamp so we can't
		// use it as the second param to the JS function...
		// @see extensions/QuizGame/QuestionGameUpload.php,
		// SpecialQuestionGameUpload::processUpload() for a detailed
		// description of this fucked up logic
		$imgName = Xml::encodeJSVar( $img->getTitle()->getDBkey() );
		echo "<script language=\"javascript\">
			/*<![CDATA[*/
			if ( typeof PictureGame !== 'object' ) {
				window.parent.mw.loader.using( 'ext.pictureGame', function() {
					window.parent.PictureGame.{$prefix}uploadComplete({$slashedImgTag}, {$imgName}, '');
				} );
			} else {
				window.parent.PictureGame.{$prefix}uploadComplete({$slashedImgTag}, {$imgName}, '');
			}
			/*]]>*/</script>";
	}

	/**
	 * @param WebRequest $req
	 * @return string Validated prefix. Should be safe for inclusion in js
	 * @return-taint none
	 */
	public static function getCallbackPrefix( WebRequest $req ) {
		$req = $req->getVal( 'wpCallbackPrefix', '' );
		if ( preg_match( '/^[a-zA-Z_][a-zA-Z_0-9]*$/D', $req ) ) {
			return $req;
		}
		return '';
	}
}
