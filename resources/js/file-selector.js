/**
 * Allows users to search for existing media (images) and use them on Special:PictureGameHome instead of
 * being forced to upload images for the picture game they're creating (or editing).
 *
 * Originally bastardized from CollaborationKit's ext.CollaborationKit.hubtheme.js by bawolff, hare & Isarra
 * (for FanBoxes, from where I copied it to PictureGame). Kudos!
 *
 * @note Compared to FanBoxes, QuizGame or PollNY, this code is somewhat more complex because unlike those,
 *   PictureGame has *two* images, and those three other extensions only support one image (per user box/quiz/poll).
 *
 * @date 13 May 2022
 */
/**
 * @param $
 * @param mw
 * @param OO
 */
// eslint-disable-next-line wrap-iife
( function ( $, mw, OO ) {
	'use strict';

	var getThumbnail, ImageProcessDialog, openImageBrowser, setupPage;

	/**
	 * Get an image thumbnail with the given width or 75px if not supplied
	 *
	 * @param {string} filename
	 * @param {number} width Image width in pixels
	 * @return {jQuery} promise
	 */
	getThumbnail = function ( filename, width ) {
		if ( !width ) {
			width = 75;
		}
		return new mw.Api().get( {
			action: 'query',
			titles: filename,
			prop: 'imageinfo',
			iiprop: 'url',
			formatversion: 2,
			iiurlwidth: width
		} );
	};

	/**
	 * Subclass ProcessDialog.
	 *
	 * @class ImageProcessDialog
	 * @extends OO.ui.ProcessDialog
	 *
	 * @constructor
	 * @param {Object} config
	 */
	ImageProcessDialog = function ( config ) {
		ImageProcessDialog.super.call( this, config );
	};
	OO.inheritClass( ImageProcessDialog, OO.ui.ProcessDialog );

	// Specify a static title and actions.
	ImageProcessDialog.static.title = mw.msg( 'picturegame-image-picker' );
	ImageProcessDialog.static.name = 'picturegame-image-picker';
	ImageProcessDialog.static.actions = [
		{ action: 'save', label: mw.msg( 'picturegame-image-picker-select' ), flags: 'primary' },
		{ label: mw.msg( 'cancel' ), flags: 'safe' }
	];

	/**
	 * Use the initialize() method to add content to the dialog's $body,
	 * to initialize widgets, and to set up event handlers.
	 */
	ImageProcessDialog.prototype.initialize = function () {
		var defaultSearchTerm;

		ImageProcessDialog.super.prototype.initialize.apply( this, arguments );

		defaultSearchTerm = '';

		this.content = new mw.widgets.MediaSearchWidget();
		this.content.getQuery().setValue( defaultSearchTerm );
		this.$body.append( this.content.$element );
	};

	/**
	 * Set the targetID.
	 *
	 * @param {object} data
	 */
	ImageProcessDialog.prototype.getSetupProcess = function ( data ) {
		this.targetID = data.targetID;
		return ImageProcessDialog.super.prototype.getSetupProcess.apply( this, data );
	};

	/**
	 * In the event "Select" is pressed
	 *
	 * @param action
	 */
	ImageProcessDialog.prototype.getActionProcess = function ( action ) {
		var dialog, fileTitle;

		dialog = this;
		dialog.pushPending();

		if ( action ) {
			return new OO.ui.Process( function () {
				var buttonElementSelector, fileHeight, fileObj, fileUrl, fileTitleObj, previewElement, targetFieldID, width;

				fileObj = dialog.content.getResults().findSelectedItem();
				if ( fileObj === null ) {
					return dialog.close().closed;
				}

				// Hard-coding this to 128px because it simply looks a LOT better to me...
				// width = ( dialog.targetID === '#imageOneUpload' || dialog.targetID === '#imageTwoUpload' ) ? 75 : 128;
				width = 128;

				getThumbnail( fileObj.getData().title, width )
					.done( function ( data ) {
						fileUrl = data.query.pages[ 0 ].imageinfo[ 0 ].thumburl;
						fileHeight = data.query.pages[ 0 ].imageinfo[ 0 ].thumbheight;
						fileTitleObj = new mw.Title( fileObj.getData().title );
						// I was seeing super weird results w/ this original code,
						// namely the stored file name would be Valid_file_name.ext.undefined,
						// "undefined" being fileTitleObj.ext here.
						// So, uh, let's check that it's something else before proceeding?
						if ( fileTitleObj.ext !== undefined ) {
							fileTitle = fileTitleObj.title + '.' + fileTitleObj.ext;
						} else {
							fileTitle = fileTitleObj.title;
						}

						// Hide the local upload form
						if ( dialog.targetID === '#imageOneUpload' || dialog.targetID === '#imageTwoUpload' ) {
							// Creating a new game
							$( dialog.targetID + '-frame' ).hide();
						} else {
							// Editing an existing game
							// Actually, don't do this. The logic here is super annoying, weird and messed up,
							// but either way, when editing a picture game, we do want to allow the existing
							// image to be replaced by either another existing image OR a brand new upload.
							// Hiding the local upload form would literally interfere w/ the latter.
							// $( '#upload-frame' ).hide();
						}

						// Clear out any and all error messages, if any
						// There can be an error message displayed if the user clicks on the "Upload" button
						// on the regular form w/o choosing a file to be uploaded
						// Choosing a file here, via the file picker, renders any and all such errors void.
						if ( $( dialog.targetID + 'Error' ).length > 0 && $( dialog.targetID + 'Error' ).html() !== '' ) {
							$( dialog.targetID + 'Error' ).html( '' );
						}

						// Generate preview
						if ( dialog.targetID === '#imageOneUpload' || dialog.targetID === '#imageTwoUpload' ) {
							previewElement = dialog.targetID.replace( /Upload/, '' );
						} else {
							previewElement = '#' + ( window.PictureGame.currImg === 2 ? 'image-two-tag' : 'image-one-tag' );
						}
						$( previewElement ).html(
							'<img src="' + fileUrl + '" width="' + width + '" height="' + fileHeight + '" />'
						);

						// Change the "use local image" button's label to visually indicate to the user
						// that they can just press it again should they wish to use a different, already uploaded
						// image instead
						if ( dialog.targetID === '#imageOneUpload' ) {
							buttonElementSelector = '.mw-picturegame-image-picker-widget-one';
						} else if ( dialog.targetID === '#imageTwoUpload' ) {
							buttonElementSelector = '.mw-picturegame-image-picker-widget-two';
						} else {
							buttonElementSelector = '.mw-picturegame-image-picker-widget-edit-mode';
						}

						// Change the button text to visually indicate to the user that their image choice
						// is not set in stone
						$( buttonElementSelector + ' .oo-ui-labelElement-label' )
							.text( mw.msg( 'picturegame-js-change-image' ) );

						if ( dialog.targetID === '#imageOneUpload' || dialog.targetID === '#imageTwoUpload' ) {
							// Need to make the element visible!
							$( dialog.targetID.replace( /Upload/, '' ) ).show();
						}

						// Set form value
						if ( dialog.targetID === '#imageOneUpload' ) {
							targetFieldID = 'picOneURL';
						} else if ( dialog.targetID === '#imageTwoUpload' ) {
							targetFieldID = 'picTwoURL';
						} else {
							// for grep: img1, img2
							targetFieldID = 'img' + window.PictureGame.currImg;
						}

						$( '#' + targetFieldID ).val( fileTitle );

						// Manually trigger the change event so that code in PictureGame.js
						// can take care of showing the "Create & Play!" button
						// (only relevant when creating a new pic game, but it doesn't hurt
						// to run this for the "editing an existing one" case, so I'm not
						// bothering to wrap this in an if() of any kind)
						$( '#' + targetFieldID ).trigger( 'change' );

						dialog.close( { action: action } );
					} );
			} );
		}

		// Fallback to parent handler.
		return ImageProcessDialog.super.prototype.getActionProcess.call( this, action );
	};

	/**
	 * Get dialog height.
	 */
	ImageProcessDialog.prototype.getBodyHeight = function () {
		return 600;
	};

	/**
	 * Create and append the window manager.
	 *
	 * @param {String} #imageOneUpload or #imageTwoUpload when creating a new game,
	 *  #image-one-tag or #image-two-tag when editing an existing game
	 */
	openImageBrowser = function ( selector ) {
		var windowManager, processDialog;

		windowManager = new OO.ui.WindowManager();
		$( 'body' ).append( windowManager.$element );

		// Create a new dialog window.
		processDialog = new ImageProcessDialog( {
			size: 'large'
		} );

		// Add windows to window manager using the addWindows() method.
		windowManager.addWindows( [ processDialog ] );

		// Open the window.
		windowManager.openWindow( processDialog, $.extend( {
			targetID: selector
		} ) );
	};

	/**
	 * Initial setup function run when DOM loaded.
	 */
	setupPage = function () {
		var imageBrowserButton, imageBrowserButtonTwo, imageBrowserButtonEditMode, $selectorWidget,
			$selectorWidgetTwo, $selectorWidgetEditMode, which;

		// Defining the button
		imageBrowserButton = new OO.ui.ButtonWidget( {
			icon: 'imageAdd',
			classes: [ 'mw-picturegame-image-picker-widget-inlinebutton' ],
			label: mw.msg( 'picturegame-image-picker-launch-button' )
		} );

		imageBrowserButton.on( 'click', openImageBrowser, [ '#imageOneUpload' ] );

		// Create a slightly modified copy for the 2nd uploading form...
		imageBrowserButtonTwo = new OO.ui.ButtonWidget( {
			icon: 'imageAdd',
			classes: [ 'mw-picturegame-image-picker-widget-inlinebutton' ],
			label: mw.msg( 'picturegame-image-picker-launch-button' )
		} );

		imageBrowserButtonTwo.on( 'click', openImageBrowser, [ '#imageTwoUpload' ] );

		// eslint-disable-next-line no-jquery/no-parse-html-literal
		$selectorWidget = $( '<div class="mw-picturegame-image-picker-widget-one"></div>' )
			.append(
				$( '<div>' ).append( imageBrowserButton.$element )
			);

		// eslint-disable-next-line no-jquery/no-parse-html-literal
		$selectorWidgetTwo = $( '<div class="mw-picturegame-image-picker-widget-two"></div>' )
			.append(
				$( '<div>' ).append( imageBrowserButtonTwo.$element )
			);

		// Inject it above the uploading forms
		// (but after the "First Image"/"Second Image" texts when creating a new game)
		if ( mw.util.getParamValue( 'picGameAction' ) === 'editPanel' ) {
			imageBrowserButtonEditMode = new OO.ui.ButtonWidget( {
				icon: 'imageAdd',
				classes: [ 'mw-picturegame-image-picker-widget-inlinebutton' ],
				label: mw.msg( 'picturegame-image-picker-launch-button' )
			} );

			which = ( window.PictureGame.currImg === 2 ? 'image-two-tag' : 'image-one-tag' );
			imageBrowserButtonEditMode.on( 'click', openImageBrowser, [ '#' + which ] );

			// eslint-disable-next-line no-jquery/no-parse-html-literal
			$selectorWidgetEditMode = $( '<div class="mw-picturegame-image-picker-widget-edit-mode"></div>' )
				.append(
					$( '<div>' ).append( imageBrowserButtonEditMode.$element )
				);

			// Editing an existing game
			// In the edit mode, we have only *one* upload area, which nevertheless handles /both/
			// images, depending on which image's "Upload New Image" (sic!) link was clicked
			$( '#upload-frame' ).before( $selectorWidgetEditMode );
		} else {
			// Creating a brand new game
			$( '#imageOneUpload h1' ).after( $selectorWidget );
			$( '#imageTwoUpload h1' ).after( $selectorWidgetTwo );
		}
	};

	$( setupPage );

} )( jQuery, mediaWiki, OO );
