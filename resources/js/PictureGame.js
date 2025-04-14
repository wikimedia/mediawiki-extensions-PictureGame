/**
 * JavaScript file for the PictureGame extension
 *
 * @file
 * @ingroup Extensions
 */

var PictureGame = window.PictureGame = {
	currImg: 0, // from editpanel.js

	/**
	 * Unflags an image
	 *
	 * @param {number} id
	 */
	unflag: function ( id ) {
		jQuery( '.admin-container #' + id ).fadeOut();
		jQuery.get(
			mw.config.get( 'wgScript' ),
			{
				title: 'Special:PictureGameHome',
				picGameAction: 'adminPanelUnflag',
				chain: __admin_panel_now__,
				key: __admin_panel_key__,
				id: id
			},
			function ( data ) {
				OO.ui.alert( data );
			}
		);
	},

	/**
	 * Deletes the images
	 *
	 * @param {number} id
	 * @param {string} imageName1 MediaWiki image name
	 * @param {string} imageName2 MediaWiki image name
	 */
	deleteimg: function ( id, imageName1, imageName2 ) {
		jQuery( '.admin-container #' + id ).fadeOut();
		jQuery.get(
			mw.config.get( 'wgScript' ),
			{
				title: 'Special:PictureGameHome',
				picGameAction: 'adminPanelDelete',
				chain: __admin_panel_now__,
				key: __admin_panel_key__,
				id: id,
				img1: imageName1,
				img2: imageName2
			},
			function ( data ) {
				OO.ui.alert( data );
			}
		);
	},

	/**
	 * Unprotects an image
	 *
	 * @param {number} id
	 */
	unprotect: function ( id ) {
		jQuery( '.admin-container #' + id ).fadeOut();
		jQuery.get(
			mw.config.get( 'wgScript' ),
			{
				title: 'Special:PictureGameHome',
				picGameAction: 'unprotectImages',
				chain: __admin_panel_now__,
				key: __admin_panel_key__,
				id: id
			},
			function ( data ) {
				alert( data );
			}
		);
	},

	/* Shows the upload frame */
	loadUploadFrame: function ( filename, img ) {
		PictureGame.currImg = img;

		if ( img == 1 ) {
			document.getElementById( 'edit-image-text' ).innerHTML =
				'<h2> ' + mw.msg( 'picturegame-js-editing-imgone' ) + ' </h2>';
		} else {
			document.getElementById( 'edit-image-text' ).innerHTML =
				'<h2> ' + mw.msg( 'picturegame-js-editing-imgtwo' ) + ' </h2>';
		}

		// Show the "use an existing file" button if the element exists
		if ( document.getElementsByClassName( 'mw-picturegame-image-picker-widget-edit-mode' ).length === 1 ) {
			document.getElementsByClassName( 'mw-picturegame-image-picker-widget-edit-mode' )[ 0 ].style.display = 'block';
		}

		document.getElementById( 'upload-frame' ).src = mw.config.get( 'wgScript' ) +
			'?title=Special:PictureGameAjaxUpload&wpOverwriteFile=true&wpDestFile=' +
			filename;
		document.getElementById( 'edit-image-frame' ).style.display = 'block';
		document.getElementById( 'edit-image-frame' ).style.visibility = 'visible';
	},

	/**
	 * Display an error message, either as an alert() or merely inside an element.
	 *
	 * @param {string} message Message to be shown to the user
	 * @param {string} prefix Either "imageOne_", "imageTwo_" or not set; affects what IDs this method uses
	 */
	uploadError: function ( message, prefix ) {
		var editFrameId, frameId, loaderId;

		if ( !prefix ) {
			loaderId = 'loadingImg';
			editFrameId = 'edit-image-frame';
			frameId = 'upload-frame';
		} else {
			// Strip out the dangling underscore to form proper IDs
			// (proper as in what the code expects, that is)
			prefix = prefix.replace( '_', '' );

			// imageOneLoadingImg, imageTwoLoadingImg
			loaderId = prefix + 'LoadingImg';
			// imageOneUpload-frame, imageTwoUpload-frame
			editFrameId = prefix + 'Upload-frame';
			// This is apparently intentionally the same as editFrameId in this case. Fascinating!
			frameId = editFrameId;
		}

		document.getElementById( loaderId ).style.display = 'none';
		document.getElementById( loaderId ).style.visibility = 'hidden';

		if ( !prefix ) {
			alert( message );
		} else {
			// imageOneUploadError, imageTwoUploadError
			document.getElementById( prefix + 'UploadError' ).innerHTML = '<h1>' + message + '</h1>';
		}

		document.getElementById( editFrameId ).style.display = 'block';
		document.getElementById( editFrameId ).style.visibility = 'visible';
		document.getElementById( frameId ).src = document.getElementById( frameId ).src;
	},

	/**
	 * Called when the upload starts.
	 *
	 * @param {string} prefix "imageOne_", "imageTwo_" or not set
	 */
	completeImageUpload: function ( prefix ) {
		var frame, frameId, loadingId, loadingImg;

		if ( !prefix ) {
			frameId = 'edit-image-frame';
			loadingId = 'loadingImg';
		} else {
			// Strip out the dangling underscore to form proper IDs
			// (proper as in what the code expects, that is)
			prefix = prefix.replace( '_', '' );

			// imageOneUpload-frame, imageTwoUpload-frame
			frameId = prefix + 'Upload-frame';
			// imageOneLoadingImg, imageTwoLoadingImg
			loadingId = prefix + 'LoadingImg';
		}

		frame = document.getElementById( frameId );
		loadingImg = document.getElementById( loadingId );

		if ( frame ) {
			frame.style.display = 'none';
			frame.style.visibility = 'hidden';
		}

		if ( loadingImg ) {
			loadingImg.style.display = 'block';
			loadingImg.style.visibility = 'visible';
		}
	},

	/**
	 * Called when the upload is complete if wpCallbackPrefix is empty; otherwise
	 * imageOne_uploadComplete or imageTwo_uploadComplete are called (assuming
	 * that wpCallbackPrefix=imageOne_ or wpCallbackPrefix=imageTwo_)
	 *
	 * @param {string} imgSrc The HTML for the image thumbnail
	 * @param {string} imgName The MediaWiki image name
	 * @param {string} imgDesc The MediaWiki image description [unused]
	 */
	uploadComplete: function ( imgSrc, imgName, imgDesc ) {
		document.getElementById( 'loadingImg' ).style.display = 'none';
		document.getElementById( 'loadingImg' ).style.visibility = 'hidden';

		if ( PictureGame.currImg == 1 ) {
			document.getElementById( 'image-one-tag' ).innerHTML = imgSrc;
		} else {
			document.getElementById( 'image-two-tag' ).innerHTML = imgSrc;
		}
	},

	/**
	 * Flags an image set
	 *
	 * @see https://phabricator.wikimedia.org/T156304
	 * @see https://phabricator.wikimedia.org/T155451
	 */
	flagImg: function () {
		var options = {
			actions: [
				{ label: mw.msg( 'cancel' ) },
				{ label: mw.msg( 'picturegame-reportimages' ), action: 'accept', flags: [ 'destructive', 'primary' ] }
			],
			textInput: { placeholder: mw.msg( 'picturegame-adminpanelreason' ) }
		};
		OO.ui.prompt( mw.msg( 'picturegame-flagimgconfirm' ), options ).then( function ( reason ) {
			if ( reason !== null ) {
				jQuery.get(
					mw.config.get( 'wgScript' ),
					{
						title: 'Special:PictureGameHome',
						picGameAction: 'flagImage',
						key: document.getElementById( 'key' ).value,
						id: document.getElementById( 'id' ).value,
						comment: reason
					},
					function ( data ) {
						document.getElementById( 'serverMessages' ).innerHTML =
						'<strong>' + data + '</strong>';
					}
				);
			}
		} );
	},

	editPanel: function () {
		document.location = '?title=Special:PictureGameHome&picGameAction=editPanel&id=' +
			document.getElementById( 'id' ).value;
	},

	protectImages: function ( msg ) {
		var ask = confirm( msg );
		if ( ask ) {
			jQuery.get(
				mw.config.get( 'wgScript' ),
				{
					title: 'Special:PictureGameHome',
					picGameAction: 'protectImages',
					key: document.getElementById( 'key' ).value,
					id: document.getElementById( 'id' ).value
				},
				function ( data ) {
					document.getElementById( 'serverMessages' ).innerHTML =
						'<strong>' + data + '</strong>';
				}
			);
		}
	},

	castVote: function ( picID ) {
		LightBox.init(); // creates #lightboxText & friends
		if ( document.getElementById( 'lightboxText' ) !== null ) {
			// pop up the lightbox
			var objLink = {};
			objLink.href = '#';
			objLink.title = '';

			LightBox.show( objLink );

			LightBox.setText(
				'<img src="' + mw.config.get( 'wgExtensionAssetsPath' ) + '/SocialProfile/images/ajax-loader-white.gif" alt="" />'
			);

			document.picGameVote.lastid.value = document.getElementById( 'id' ).value;
			document.picGameVote.img.value = picID;
			jQuery.post(
				mw.config.get( 'wgScript' ),
				{
					title: 'Special:PictureGameHome',
					picGameAction: 'castVote',
					key: document.getElementById( 'key' ).value,
					id: document.getElementById( 'id' ).value,
					img: picID,
					nextid: document.getElementById( 'nextid' ).value
				},
				function ( data ) {
					window.location =
						'?title=Special:PictureGameHome&picGameAction=startGame&lastid=' +
						document.getElementById( 'id' ).value + '&id=' +
						document.getElementById( 'nextid' ).value;
				}
			);
		}
	},

	reupload: function ( id ) {
		if ( id == 1 ) {
			document.getElementById( 'imageOne' ).style.display = 'none';
			document.getElementById( 'imageOne' ).style.visibility = 'hidden';
			document.getElementById( 'imageOneLoadingImg' ).style.display = 'block';
			document.getElementById( 'imageOneLoadingImg' ).style.visibility = 'visible';

			document.getElementById( 'imageOneUpload-frame' ).onload = function handleResponse( st, doc ) {
				document.getElementById( 'imageOneLoadingImg' ).style.display = 'none';
				document.getElementById( 'imageOneLoadingImg' ).style.visibility = 'hidden';
				document.getElementById( 'imageOneUpload-frame' ).style.display = 'block';
				document.getElementById( 'imageOneUpload-frame' ).style.visibility = 'visible';
				this.onload = function ( st, doc ) {
					return;
				};
			};

			// passes in the description of the image
			/* @note It seems that this is either a half-baked feature or something
			that was never fully implemented. Either way, all the elements (etc.)
			related are commented out in the HTML source, so obviously we can't
			refer to 'em either! --ashley, 23 July 2013
			document.getElementById( 'imageOneUpload-frame' ).src =
				document.getElementById( 'imageOneUpload-frame' ).src +
				'&wpUploadDescription=' + document.getElementById( 'picOneDesc' ).value;
			*/
		} else {
			document.getElementById( 'imageTwo' ).style.display = 'none';
			document.getElementById( 'imageTwo' ).style.visibility = 'hidden';
			document.getElementById( 'imageTwoLoadingImg' ).style.display = 'block';
			document.getElementById( 'imageTwoLoadingImg' ).style.visibility = 'visible';

			document.getElementById( 'imageTwoUpload-frame' ).onload = function handleResponse( st, doc ) {
				document.getElementById( 'imageTwoLoadingImg' ).style.display = 'none';
				document.getElementById( 'imageTwoLoadingImg' ).style.visibility = 'hidden';
				document.getElementById( 'imageTwoUpload-frame' ).style.display = 'block';
				document.getElementById( 'imageTwoUpload-frame' ).style.visibility = 'visible';
				this.onload = function ( st, doc ) {
					return;
				};
			};
			// passes in the description of the image
			/*
			document.getElementById( 'imageTwoUpload-frame' ).src =
				document.getElementById( 'imageTwoUpload-frame' ).src +
				'&wpUploadDescription=' +
				document.getElementById( 'picTwoDesc' ).value;
			*/
		}
	},

	imageOne_uploadComplete: function ( imgSrc, imgName, imgDesc ) {
		document.getElementById( 'imageOneLoadingImg' ).style.display = 'none';
		document.getElementById( 'imageOneLoadingImg' ).style.visibility = 'hidden';
		document.getElementById( 'imageOneUpload-frame' ).style.display = 'none';
		document.getElementById( 'imageOneUpload-frame' ).style.visibility = 'hidden';

		jQuery( '#imageOne' ).html( '<p><b>' + imgDesc + '</b></p>' + imgSrc );
		jQuery( '#imageOne' ).append(
			jQuery( '<a>' )
				.attr( 'href', '#' )
				.on( 'click', function () {
					PictureGame.reupload( 1 );
				} )
				.text( window.parent.mw.msg( 'picturegame-js-edit' ) )
				// Words of wisdom (from /extensions/PollNY/Poll.js):
				// <Vulpix> oh, yeah, I know what's happening. Since you're appending the element created with $('<a>'), it appends only it, not the wrapped one... You may need to add a .parent() at the end to get the <p> also...
				// (the <p> tag is a minor cosmetic improvement, nothing else)
				.wrap( '<p/>' )
				.parent()
		);

		document.picGamePlay.picOneURL.value = imgName;
		// document.picGamePlay.picOneDesc.value = imgDesc;

		// as per https://www.mediawiki.org/wiki/Special:Code/MediaWiki/68271
		var imgOne = jQuery( '#imageOne' );
		imgOne.fadeIn( 2000 );

		// Show the start button only when both images have been uploaded
		if (
			document.picGamePlay.picTwoURL.value !== '' &&
			document.picGamePlay.picOneURL.value !== ''
		) {
			// as per https://www.mediawiki.org/wiki/Special:Code/MediaWiki/68271
			var button = jQuery( '#startButton' );
			button.fadeIn( 2000 );
		}
	},

	imageTwo_uploadComplete: function ( imgSrc, imgName, imgDesc ) {
		document.getElementById( 'imageTwoLoadingImg' ).style.display = 'none';
		document.getElementById( 'imageTwoLoadingImg' ).style.visibility = 'hidden';
		document.getElementById( 'imageTwoUpload-frame' ).style.display = 'none';
		document.getElementById( 'imageTwoUpload-frame' ).style.visibility = 'hidden';

		jQuery( '#imageTwo' ).html( '<p><b>' + imgDesc + '</b></p>' + imgSrc );
		jQuery( '#imageTwo' ).append(
			jQuery( '<a>' )
				.attr( 'href', '#' )
				.on( 'click', function () {
					PictureGame.reupload( 2 );
				} )
				.text( window.parent.mw.msg( 'picturegame-js-edit' ) )
				// Words of wisdom (from /extensions/PollNY/Poll.js):
				// <Vulpix> oh, yeah, I know what's happening. Since you're appending the element created with $('<a>'), it appends only it, not the wrapped one... You may need to add a .parent() at the end to get the <p> also...
				// (the <p> tag is a minor cosmetic improvement, nothing else)
				.wrap( '<p/>' )
				.parent()
		);

		document.picGamePlay.picTwoURL.value = imgName;
		// document.picGamePlay.picTwoDesc.value = imgDesc;

		// as per https://www.mediawiki.org/wiki/Special:Code/MediaWiki/68271
		var imgTwo = jQuery( '#imageTwo' );
		imgTwo.fadeIn( 2000 );

		if ( document.picGamePlay.picOneURL.value !== '' ) {
			// as per https://www.mediawiki.org/wiki/Special:Code/MediaWiki/68271
			var button = jQuery( '#startButton' );
			button.fadeIn( 2000 );
		}
	},

	startGame: function () {
		var isError = false,
			gameTitle = document.getElementById( 'picGameTitle' ).value,
			imgOneURL = document.getElementById( 'picOneURL' ).value,
			imgTwoURL = document.getElementById( 'picTwoURL' ).value,
			errorText = '';

		if ( gameTitle.length === 0 ) {
			isError = true;
			document.getElementById( 'picGameTitle' ).style.borderStyle = 'solid';
			document.getElementById( 'picGameTitle' ).style.borderColor = 'red';
			document.getElementById( 'picGameTitle' ).style.borderWidth = '2px';
			errorText = errorText + mw.msg( 'picturegame-js-error-title' ) + '<br />';
		}

		if ( imgOneURL.length === 0 ) {
			isError = true;
			document.getElementById( 'imageOneUpload' ).style.borderStyle = 'solid';
			document.getElementById( 'imageOneUpload' ).style.borderColor = 'red';
			document.getElementById( 'imageOneUpload' ).style.borderWidth = '2px';
			errorText = errorText + mw.msg( 'picturegame-js-error-upload-imgone' ) + '<br />';
		}

		if ( imgTwoURL.length === 0 ) {
			isError = true;
			document.getElementById( 'imageTwoUpload' ).style.borderStyle = 'solid';
			document.getElementById( 'imageTwoUpload' ).style.borderColor = 'red';
			document.getElementById( 'imageTwoUpload' ).style.borderWidth = '2px';
			errorText = errorText + mw.msg( 'picturegame-js-error-upload-imgtwo' ) + '<br />';
		}

		if ( !isError ) {
			document.picGamePlay.submit();
		} else {
			document.getElementById( 'picgame-errors' ).innerHTML = errorText;
		}
	},

	skipToGame: function () {
		document.location = 'index.php?title=Special:PictureGameHome&picGameAction=startGame';
	}
};

jQuery( function () {
	// Handle clicks on "Un-flag" links on the admin panel
	jQuery( 'div.admin-controls a.picgame-unflag-link' ).on( 'click', function ( event ) {
		event.preventDefault();
		var options = {
				actions: [
					{ label: mw.msg( 'cancel' ) },
					{ label: mw.msg( 'picturegame-adminpanelunflag' ), action: 'accept', flags: [ 'constructive' ] }
				]
			}, id = jQuery( this ).parent().parent().attr( 'id' );
		OO.ui.confirm( mw.msg( 'picturegame-adminpanelunflag-confirm' ), options ).done( function ( confirmed ) {
			if ( confirmed ) {
				PictureGame.unflag( id );
			}
		} );
	} );

	// Handle clicks on "Delete" links on the admin panel
	jQuery( 'div.admin-controls a.picgame-delete-link' ).on( 'click', function ( event ) {
		event.preventDefault();
		var options = {
				actions: [
					{ label: mw.msg( 'cancel' ) },
					{ label: mw.msg( 'picturegame-adminpaneldelete' ), action: 'accept', flags: [ 'destructive' ] }
				]
			}, id = jQuery( this ).parent().parent().attr( 'id' );
		OO.ui.confirm( mw.msg( 'picturegame-adminpaneldelete-confirm' ), options ).done( function ( confirmed ) {
			if ( confirmed ) {
				PictureGame.deleteimg(
					id,
					jQuery( this ).data( 'row-img1' ),
					jQuery( this ).data( 'row-img2' )
				);
			}
		} );
	} );

	// Handle clicks on "Unprotect" links on the admin panel
	jQuery( 'div.admin-controls a.picgame-unprotect-link' ).on( 'click', function ( event ) {
		event.preventDefault();
		PictureGame.unprotect( jQuery( this ).parent().parent().attr( 'id' ) );
	} );

	// Handle clicks on "Protect" links on the admin panel
	jQuery( 'a.picgame-protect-link' ).on( 'click', function ( event ) {
		event.preventDefault();
		PictureGame.protectImages( mw.msg( 'picturegame-protectimgconfirm' ) );
	} );

	jQuery( 'div.edit-button-pic-game a.picgame-edit-link' ).on( 'click', function ( event ) {
		event.preventDefault();
		PictureGame.editPanel();
	} );

	// Permalink
	jQuery( 'div#utilityButtons a.picgame-permalink' ).on( 'click', function ( event ) {
		event.preventDefault();
		window.parent.document.location = window.location.href.replace( 'startGame', 'renderPermalink' );
	} );

	// "Flag" link
	jQuery( 'div#utilityButtons a.picgame-flag-link' ).on( 'click', function ( event ) {
		event.preventDefault();
		PictureGame.flagImg();
	} );

	// "Skip to game" button
	jQuery( 'input#skip-button' ).on( 'click', function ( event ) {
		event.preventDefault();
		PictureGame.skipToGame();
	} );

	// Image editing links when editing a pic game
	jQuery( 'div#edit-image-one p a.picgame-upload-link-1' ).on( 'click', function ( event ) {
		event.preventDefault();
		PictureGame.loadUploadFrame( jQuery( this ).data( 'img-one-name' ), 1 );
	} );

	jQuery( 'div#edit-image-two p a.picgame-upload-link-2' ).on( 'click', function ( event ) {
		event.preventDefault();
		PictureGame.loadUploadFrame( jQuery( this ).data( 'img-two-name' ), 2 );
	} );

	// Monitor changes on the hidden image name inputs
	// (This is needed to support using a local image for both images at least.)
	// If they change, check the value of the other field as well, and if both fields
	// have some content, then show the "Create & Play!" button.
	jQuery( '#picOneURL, #picTwoURL' ).on( 'change', function () {
		var ID = jQuery( this ).attr( 'id' );
		if ( jQuery( this ).val() !== '' ) {
			var otherID = ( ID === 'picOneURL' ) ? 'picTwoURL' : 'picOneURL';
			if ( jQuery( '#' + otherID ).val() !== '' ) {
				jQuery( '#startButton' ).fadeIn( 2000 );
			}
		}
	} );

	// "Create and Play!" button on picture game creation form
	jQuery( 'div#startButton input' ).on( 'click', function ( event ) {
		event.preventDefault();
		PictureGame.startGame();
	} );

	// Handlers for registering the vote upon clicking an image
	jQuery( 'div.imgContainer div#imageOne' ).on( {
		click: function () {
			PictureGame.castVote( 0 );
		}
	} );

	jQuery( 'div.imgContainer div#imageTwo' ).on( {
		click: function () {
			PictureGame.castVote( 1 );
		}
	} );
} );
