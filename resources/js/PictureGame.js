/**
 * JavaScript file for the PictureGame extension
 *
 * @file
 * @ingroup Extensions
 */

 /*
	* That needs to be removed, when we drop support to MW 1.28. Modified copy pasta from OOjsUI windows.js
	* @see https://gerrit.wikimedia.org/r/#/c/336008/
	*/
reasonPrompt = OO.ui.prompt || function ( text, options ) {
	manager = new OO.ui.WindowManager();
	textInput = new OO.ui.TextInputWidget( ( options && options.textInput ) || {} );
	textField = new OO.ui.FieldLayout( textInput, {
		align: 'top',
		label: text
	} );
	$( 'body' ).append( manager.$element );
	manager.addWindows( [ new OO.ui.MessageDialog() ] );

	// TODO: This is a little hacky, and could be done by extending MessageDialog instead.

	return manager.openWindow( 'message', $.extend( {
		message: textField.$element
	}, options ) ).then( function ( opened ) {
		// After ready
		textInput.on( 'enter', function () {
			manager.getCurrentWindow().close( { action: 'accept' } );
		} );
		textInput.focus();
		return opened.then( function ( closing ) {
			return closing.then( function ( data ) {
				return $.Deferred().resolve( data && data.action === 'accept' ? textInput.getValue() : null );
			} );
		} );
	} );
};

var PictureGame = window.PictureGame = {
	currImg: 0, // from editpanel.js

	/**
	 * Unflags an image
	 *
	 * @param id Integer:
	 */
	unflag: function( id ) {
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
			function( data ) {
				OO.ui.alert( data );
			}
		);
	},

	/**
	 * Deletes the images
	 *
	 * @param id Integer
	 * @param img1 String: MediaWiki image name
	 * @param img2 String: MediaWiki image name
	 */
	deleteimg: function( id, imageName1, imageName2 ) {
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
			function( data ) {
				OO.ui.alert( data );
			}
		);
	},

	/**
	 * Unprotects an image
	 *
	 * @param id Integer:
	 */
	unprotect: function( id ) {
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
			function( data ) {
				alert( data );
			}
		);
	},

	/* Shows the upload frame */
	loadUploadFrame: function( filename, img ) {
		PictureGame.currImg = img;

		if( img == 1 ) {
			document.getElementById( 'edit-image-text' ).innerHTML =
				'<h2> ' + mw.msg( 'picturegame-js-editing-imgone' ) + ' </h2>';
		} else {
			document.getElementById( 'edit-image-text' ).innerHTML =
				'<h2> ' + mw.msg( 'picturegame-js-editing-imgtwo' ) + ' </h2>';
		}

		document.getElementById( 'upload-frame' ).src = mw.config.get( 'wgScript' ) +
			'?title=Special:PictureGameAjaxUpload&wpOverwriteFile=true&wpDestFile=' +
			filename;
		document.getElementById( 'edit-image-frame' ).style.display = 'block';
		document.getElementById( 'edit-image-frame' ).style.visibility = 'visible';
	},

	uploadError: function( message ) {
		document.getElementById( 'loadingImg' ).style.display = 'none';
		document.getElementById( 'loadingImg' ).style.visibility = 'hidden';
		alert( message );
		document.getElementById( 'edit-image-frame' ).style.display = 'block';
		document.getElementById( 'edit-image-frame' ).style.visibility = 'visible';
		document.getElementById( 'upload-frame' ).src = document.getElementById( 'upload-frame' ).src;
	},

	/* Called when the upload starts */
	completeImageUpload: function() {
		var frame = document.getElementById( 'edit-image-frame' );
		var loadingImg = document.getElementById( 'loadingImg' );
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
	 * Called when the upload is complete
	 *
	 * @param imgSrc String: the HTML for the image thumbnail
	 * @param imgName String: the MediaWiki image name
	 * @param imgDesc String: the MediaWiki image description [unused]
	 */
	uploadComplete: function( imgSrc, imgName, imgDesc ) {
		document.getElementById( 'loadingImg' ).style.display = 'none';
		document.getElementById( 'loadingImg' ).style.visibility = 'hidden';

		if( PictureGame.currImg == 1 ) {
			document.getElementById( 'image-one-tag' ).innerHTML = imgSrc;
		} else {
			document.getElementById( 'image-two-tag' ).innerHTML = imgSrc;
		}
	},

	/**
	 * Flags an image set
	 * @see https://phabricator.wikimedia.org/T156304
	 * @see https://phabricator.wikimedia.org/T155451
	 */
	flagImg: function() {
		var options = {
			actions: [
				{ label: mw.msg( 'cancel' ) },
				{ label: mw.msg( 'picturegame-reportimages' ), action: 'accept', flags: ['destructive', 'primary'] },
			],
			textInput: { placeholder: mw.msg( 'picturegame-adminpanelreason' ) }
		};
		reasonPrompt( mw.msg( 'picturegame-flagimgconfirm' ), options ).then( function ( reason ) {
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
					function( data ) {
						document.getElementById( 'serverMessages' ).innerHTML =
						'<strong>' + data + '</strong>';
					}
				);
			}
		} );
	},

	doHover: function( divID ) {
		if( divID == 'imageOne' ) {
			document.getElementById( divID ).style.backgroundColor = '#4B9AF6';
		} else {
			document.getElementById( divID ).style.backgroundColor = '#FF1800';
		}
	},

	endHover: function( divID ) {
		document.getElementById( divID ).style.backgroundColor = '';
	},

	editPanel: function() {
		document.location = '?title=Special:PictureGameHome&picGameAction=editPanel&id=' +
			document.getElementById( 'id' ).value;
	},

	protectImages: function( msg ) {
		var ask = confirm( msg );
		if( ask ) {
			jQuery.get(
				mw.config.get( 'wgScript' ),
				{
					title: 'Special:PictureGameHome',
					picGameAction: 'protectImages',
					key: document.getElementById( 'key' ).value,
					id: document.getElementById( 'id' ).value
				},
				function( data ) {
					document.getElementById( 'serverMessages' ).innerHTML =
						'<strong>' + data + '</strong>';
				}
			);
		}
	},

	castVote: function( picID ) {
		LightBox.init(); // creates #lightboxText & friends
		if( document.getElementById( 'lightboxText' ) !== null ) {
			// pop up the lightbox
			var objLink = {};
			objLink.href = '#';
			objLink.title = '';

			LightBox.show( objLink );

			if ( window.isFlashSupported() ) {
				LightBox.setText(
					'<embed src="' + mw.config.get( 'wgExtensionAssetsPath' ) + '/SocialProfile/images/ajax-loading.swf" quality="high" wmode="transparent" bgcolor="#ffffff"' +
					'pluginspage="http://www.adobe.com/shockwave/download/download.cgi?P1_Prod_Version=ShockwaveFlash"' +
					'type="application/x-shockwave-flash" width="100" height="100">' +
					'</embed>'
				);
			} else {
				LightBox.setText(
					'<img src="' + mw.config.get( 'wgExtensionAssetsPath' ) + '/SocialProfile/images/ajax-loader-white.gif" alt="" />'
				);
			}

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
				function( data ) {
					window.location =
						'?title=Special:PictureGameHome&picGameAction=startGame&lastid=' +
						document.getElementById( 'id' ).value + '&id=' +
						document.getElementById( 'nextid' ).value;
				}
			);
		}
	},

	reupload: function( id ) {
		if( id == 1 ) {
			document.getElementById( 'imageOne' ).style.display = 'none';
			document.getElementById( 'imageOne' ).style.visibility = 'hidden';
			document.getElementById( 'imageOneLoadingImg' ).style.display = 'block';
			document.getElementById( 'imageOneLoadingImg' ).style.visibility = 'visible';

			document.getElementById( 'imageOneUpload-frame' ).onload = function handleResponse( st, doc ) {
				document.getElementById( 'imageOneLoadingImg' ).style.display = 'none';
				document.getElementById( 'imageOneLoadingImg' ).style.visibility = 'hidden';
				document.getElementById( 'imageOneUpload-frame' ).style.display = 'block';
				document.getElementById( 'imageOneUpload-frame' ).style.visibility = 'visible';
				this.onload = function( st, doc ) { return; };
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
				this.onload = function( st, doc ) { return; };
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

	/**
	 * Eh, there should be a smarter way of doing this instead of epic code
	 * duplication, really...
	 */
	imageOne_uploadError: function( message ) {
		document.getElementById( 'imageOneLoadingImg' ).style.display = 'none';
		document.getElementById( 'imageOneLoadingImg' ).style.visibility = 'hidden';

		document.getElementById( 'imageOneUploadError' ).innerHTML = '<h1>' + message + '</h1>';
		document.getElementById( 'imageOneUpload-frame' ).src =
			document.getElementById( 'imageOneUpload-frame' ).src;

		document.getElementById( 'imageOneUpload-frame' ).style.display = 'block';
		document.getElementById( 'imageOneUpload-frame' ).style.visibility = 'visible';
	},

	imageTwo_uploadError: function( message ) {
		document.getElementById( 'imageTwoLoadingImg' ).style.display = 'none';
		document.getElementById( 'imageTwoLoadingImg' ).style.visibility = 'hidden';

		document.getElementById( 'imageTwoUploadError' ).innerHTML = '<h1>' + message + '</h1>';
		document.getElementById( 'imageTwoUpload-frame' ).src =
			document.getElementById( 'imageTwoUpload-frame' ).src;
		document.getElementById( 'imageTwoUpload-frame' ).style.display = 'block';
		document.getElementById( 'imageTwoUpload-frame' ).style.visibility = 'visible';
	},

	imageOne_completeImageUpload: function() {
		document.getElementById( 'imageOneUpload-frame' ).style.display = 'none';
		document.getElementById( 'imageOneUpload-frame' ).style.visibility = 'hidden';
		document.getElementById( 'imageOneLoadingImg' ).style.display = 'block';
		document.getElementById( 'imageOneLoadingImg' ).style.visibility = 'visible';
	},

	imageTwo_completeImageUpload: function() {
		document.getElementById( 'imageTwoUpload-frame' ).style.display = 'none';
		document.getElementById( 'imageTwoUpload-frame' ).style.visibility = 'hidden';
		document.getElementById( 'imageTwoLoadingImg' ).style.display = 'block';
		document.getElementById( 'imageTwoLoadingImg' ).style.visibility = 'visible';
	},

	imageOne_uploadComplete: function( imgSrc, imgName, imgDesc ) {
		document.getElementById( 'imageOneLoadingImg' ).style.display = 'none';
		document.getElementById( 'imageOneLoadingImg' ).style.visibility = 'hidden';
		document.getElementById( 'imageOneUpload-frame' ).style.display = 'none';
		document.getElementById( 'imageOneUpload-frame' ).style.visibility = 'hidden';

		jQuery( '#imageOne' ).html( '<p><b>' + imgDesc + '</b></p>' + imgSrc );
		jQuery( '#imageOne' ).append(
			jQuery( '<a>' )
				.attr( 'href', '#' )
				.on( 'click', function() { PictureGame.reupload( 1 ); } )
				.text( window.parent.mw.msg( 'picturegame-js-edit' ) )
				// Words of wisdom (from /extensions/PollNY/Poll.js):
				// <Vulpix> oh, yeah, I know what's happening. Since you're appending the element created with $('<a>'), it appends only it, not the wrapped one... You may need to add a .parent() at the end to get the <p> also...
				// (the <p> tag is a minor cosmetic improvement, nothing else)
				.wrap( '<p/>' )
				.parent()
		);

		document.picGamePlay.picOneURL.value = imgName;
		//document.picGamePlay.picOneDesc.value = imgDesc;

		// as per http://www.mediawiki.org/wiki/Special:Code/MediaWiki/68271
		var imgOne = jQuery( '#imageOne' );
		imgOne.fadeIn( 2000 );

		// Show the start button only when both images have been uploaded
		if(
			document.picGamePlay.picTwoURL.value !== '' &&
			document.picGamePlay.picOneURL.value !== ''
		)
		{
			// as per http://www.mediawiki.org/wiki/Special:Code/MediaWiki/68271
			var button = jQuery( '#startButton' );
			button.fadeIn( 2000 );
		}
	},

	imageTwo_uploadComplete: function( imgSrc, imgName, imgDesc ) {
		document.getElementById( 'imageTwoLoadingImg' ).style.display = 'none';
		document.getElementById( 'imageTwoLoadingImg' ).style.visibility = 'hidden';
		document.getElementById( 'imageTwoUpload-frame' ).style.display = 'none';
		document.getElementById( 'imageTwoUpload-frame' ).style.visibility = 'hidden';

		jQuery( '#imageTwo' ).html( '<p><b>' + imgDesc + '</b></p>' + imgSrc );
		jQuery( '#imageTwo' ).append(
			jQuery( '<a>' )
				.attr( 'href', '#' )
				.on( 'click', function() { PictureGame.reupload( 2 ); } )
				.text( window.parent.mw.msg( 'picturegame-js-edit' ) )
				// Words of wisdom (from /extensions/PollNY/Poll.js):
				// <Vulpix> oh, yeah, I know what's happening. Since you're appending the element created with $('<a>'), it appends only it, not the wrapped one... You may need to add a .parent() at the end to get the <p> also...
				// (the <p> tag is a minor cosmetic improvement, nothing else)
				.wrap( '<p/>' )
				.parent()
		);

		document.picGamePlay.picTwoURL.value = imgName;
		//document.picGamePlay.picTwoDesc.value = imgDesc;

		// as per http://www.mediawiki.org/wiki/Special:Code/MediaWiki/68271
		var imgTwo = jQuery( '#imageTwo' );
		imgTwo.fadeIn( 2000 );

		if( document.picGamePlay.picOneURL.value !== '' ) {
			// as per http://www.mediawiki.org/wiki/Special:Code/MediaWiki/68271
			var button = jQuery( '#startButton' );
			button.fadeIn( 2000 );
		}
	},

	startGame: function() {
		var isError = false;
		var gameTitle = document.getElementById( 'picGameTitle' ).value;
		var imgOneURL = document.getElementById( 'picOneURL' ).value;
		var imgTwoURL = document.getElementById( 'picTwoURL' ).value;
		var errorText = '';

		if( gameTitle.length === 0 ) {
			isError = true;
			document.getElementById( 'picGameTitle' ).style.borderStyle = 'solid';
			document.getElementById( 'picGameTitle' ).style.borderColor = 'red';
			document.getElementById( 'picGameTitle' ).style.borderWidth = '2px';
			errorText = errorText + mw.msg( 'picturegame-js-error-title' ) + '<br />';
		}

		if( imgOneURL.length === 0 ) {
			isError = true;
			document.getElementById( 'imageOneUpload' ).style.borderStyle = 'solid';
			document.getElementById( 'imageOneUpload' ).style.borderColor = 'red';
			document.getElementById( 'imageOneUpload' ).style.borderWidth = '2px';
			errorText = errorText + mw.msg( 'picturegame-js-error-upload-imgone' ) + '<br />';
		}

		if( imgTwoURL.length === 0 ) {
			isError = true;
			document.getElementById( 'imageTwoUpload' ).style.borderStyle = 'solid';
			document.getElementById( 'imageTwoUpload' ).style.borderColor = 'red';
			document.getElementById( 'imageTwoUpload' ).style.borderWidth = '2px';
			errorText = errorText + mw.msg( 'picturegame-js-error-upload-imgtwo' ) + '<br />';
		}

		if( !isError ) {
			document.picGamePlay.submit();
		} else {
			document.getElementById( 'picgame-errors' ).innerHTML = errorText;
		}
	},

	skipToGame: function() {
		document.location = 'index.php?title=Special:PictureGameHome&picGameAction=startGame';
	}
};

jQuery( function() {
	// Handle clicks on "Un-flag" links on the admin panel
	jQuery( 'div.admin-controls a.picgame-unflag-link' ).on( 'click', function( event ) {
		event.preventDefault();
		var options = {
			actions: [
				{ label: mw.msg( 'cancel' ) },
				{ label: mw.msg( 'picturegame-adminpanelunflag' ), action: 'accept', flags: ['constructive'] }
			]
		}, id = jQuery( this ).parent().parent().attr( 'id' );
		OO.ui.confirm( mw.msg( 'picturegame-adminpanelunflag-confirm' ), options ).done( function ( confirmed ) {
			if ( confirmed ) {
				PictureGame.unflag( id );
			}
		} );
	} );

	// Handle clicks on "Delete" links on the admin panel
	jQuery( 'div.admin-controls a.picgame-delete-link' ).on( 'click', function( event ) {
		event.preventDefault();
		var options = {
			actions: [
				{ label: mw.msg( 'cancel' ) },
				{ label: mw.msg( 'picturegame-adminpaneldelete' ), action: 'accept', flags: ['destructive'] }
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
	jQuery( 'div.admin-controls a.picgame-unprotect-link' ).on( 'click', function( event ) {
		event.preventDefault();
		PictureGame.unprotect( jQuery( this ).parent().parent().attr( 'id' ) );
	} );

	// Handle clicks on "Protect" links on the admin panel
	jQuery( 'a.picgame-protect-link' ).on( 'click', function( event ) {
		event.preventDefault();
		PictureGame.protectImages( mw.msg( 'picturegame-protectimgconfirm' ) );
	} );

	jQuery( 'div.edit-button-pic-game a.picgame-edit-link' ).on( 'click', function( event ) {
		event.preventDefault();
		PictureGame.editPanel();
	} );

	// Permalink
	jQuery( 'div#utilityButtons a.picgame-permalink' ).on( 'click', function( event ) {
		event.preventDefault();
		window.parent.document.location = window.location.href.replace( 'startGame', 'renderPermalink' );
	} );

	// "Flag" link
	jQuery( 'div#utilityButtons a.picgame-flag-link' ).on( 'click', function( event ) {
		event.preventDefault();
		PictureGame.flagImg();
	} );

	// "Skip to game" button
	jQuery( 'input#skip-button' ).on( 'click', function( event ) {
		event.preventDefault();
		PictureGame.skipToGame();
	} );

	jQuery( 'div#edit-image-one p a.picgame-upload-link-1' ).on( 'click', function( event ) {
		event.preventDefault();
		PictureGame.loadUploadFrame( jQuery( this ).data( 'img-one-name' ), 1 );
	} );

	jQuery( 'div#edit-image-two p a.picgame-upload-link-2' ).on( 'click', function( event ) {
		event.preventDefault();
		PictureGame.loadUploadFrame( jQuery( this ).data( 'img-two-name' ), 2 );
	} );

	// "Create and Play!" button on picture game creation form
	jQuery( 'div#startButton input' ).on( 'click', function( event ) {
		event.preventDefault();
		PictureGame.startGame();
	} );

	// Hovers on the gallery
	jQuery( 'div.picgame-gallery-thumbnail' ).on({
		'mouseout': function() {
			PictureGame.endHover( jQuery( this ).attr( 'id' ) );
		},
		'mouseover': function() {
			PictureGame.doHover( jQuery( this ).attr( 'id' ) );
		},
	} );

	jQuery( 'div.imgContainer div#imageOne' ).on({
		'click': function() {
			PictureGame.castVote( 0 );
		},
		'mouseout': function() {
			PictureGame.endHover( 'imageOne' );
		},
		'mouseover': function() {
			PictureGame.doHover( 'imageOne' );
		}
	} );

	jQuery( 'div.imgContainer div#imageTwo' ).on({
		'click': function() {
			PictureGame.castVote( 1 );
		},
		'mouseout': function() {
			PictureGame.endHover( 'imageTwo' );
		},
		'mouseover': function() {
			PictureGame.doHover( 'imageTwo' );
		}
	} );
} );
