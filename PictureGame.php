<?php
/**
 * PictureGame extension - allows making picture games
 *
 * @file
 * @ingroup Extensions
 * @author Aaron Wright <aaron.wright@gmail.com>
 * @author Ashish Datta <ashish@setfive.com>
 * @author David Pean <david.pean@gmail.com>
 * @author Jack Phoenix <jack@countervandalism.net>
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 * @link https://www.mediawiki.org/wiki/Extension:PictureGame Documentation
 */

/**
 * Protect against register_globals vulnerabilities.
 * This line must be present before any global variable is referenced.
 */
if ( !defined( 'MEDIAWIKI' ) ) {
	die( "This is not a valid entry point.\n" );
}

// Extension credits that will show up on Special:Version
$wgExtensionCredits['other'][] = array(
	'name' => 'PictureGame',
	'version' => '3.1.0',
	'author' => array( 'Aaron Wright', 'Ashish Datta', 'David Pean', 'Jack Phoenix' ),
	'description' => 'Allows making [[Special:PictureGameHome|picture games]]',
	'url' => 'https://www.mediawiki.org/wiki/Extension:PictureGame'
);

// ResourceLoader support for MediaWiki 1.17+
$pictureGameResourceTemplate = array(
	'localBasePath' => dirname( __FILE__ ) . '/picturegame',
	'remoteExtPath' => 'PictureGame/picturegame',
	'position' => 'top' // available since r85616
);

$wgResourceModules['ext.pictureGame'] = $pictureGameResourceTemplate + array(
	'scripts' => 'PictureGame.js',
	'messages' => array(
		'picturegame-js-edit', 'picturegame-js-error-title',
		'picturegame-js-error-upload-imgone',
		'picturegame-js-error-upload-imgtwo', 'picturegame-js-editing-imgone',
		'picturegame-js-editing-imgtwo', 'picturegame-protectimgconfirm',
		'picturegame-flagimgconfirm'
	)
);

$wgResourceModules['ext.pictureGame.lightBox'] = $pictureGameResourceTemplate + array(
	'scripts' => 'LightBox.js'
);

$wgResourceModules['ext.pictureGame.adminPanel'] = $pictureGameResourceTemplate + array(
	'styles' => 'adminpanel.css'
);

$wgResourceModules['ext.pictureGame.editPanel'] = $pictureGameResourceTemplate + array(
	'styles' => 'editpanel.css'
);

$wgResourceModules['ext.pictureGame.gallery'] = $pictureGameResourceTemplate + array(
	'styles' => 'gallery.css'
);

$wgResourceModules['ext.pictureGame.mainGame'] = $pictureGameResourceTemplate + array(
	'styles' => 'maingame.css'
);

$wgResourceModules['ext.pictureGame.startGame'] = $pictureGameResourceTemplate + array(
	'styles' => 'startgame.css'
);

// picturegame_images.flag used to be an enum() and that sucked, big time
define( 'PICTUREGAME_FLAG_NONE', 0 );
define( 'PICTUREGAME_FLAG_FLAGGED', 1 );
define( 'PICTUREGAME_FLAG_PROTECT', 2 );

// Set up the new special page and autoload classes
$dir = dirname( __FILE__ ) . '/';
$wgMessagesDirs['PictureGame'] = __DIR__ . '/i18n';
$wgExtensionMessagesFiles['PictureGame'] = $dir . 'PictureGame.i18n.php';
$wgExtensionMessagesFiles['PictureGameAlias'] = $dir . 'PictureGame.alias.php';
$wgAutoloadClasses['PictureGameHome'] = $dir . 'PictureGameHome.body.php';
$wgSpecialPages['PictureGameHome'] = 'PictureGameHome';

// Upload form
$wgAutoloadClasses['SpecialPictureGameAjaxUpload'] = $dir . 'AjaxUploadForm.php';
$wgAutoloadClasses['PictureGameAjaxUploadForm'] = $dir . 'AjaxUploadForm.php';
$wgAutoloadClasses['PictureGameUpload'] = $dir . 'AjaxUploadForm.php';
$wgSpecialPages['PictureGameAjaxUpload'] = 'SpecialPictureGameAjaxUpload';

// For example: 'edits' => 5 if you want to require users to have at least 5
// edits before they can create new picture games.
$wgCreatePictureGameThresholds = array();

// New user right, required to delete/protect picture games
$wgAvailableRights[] = 'picturegameadmin';
$wgGroupPermissions['sysop']['picturegameadmin'] = true;
$wgGroupPermissions['staff']['picturegameadmin'] = true;

// Hooked functions
$wgAutoloadClasses['PictureGameHooks'] = $dir . 'PictureGameHooks.class.php';

$wgHooks['SkinTemplateNavigation::SpecialPage'][] = 'PictureGameHooks::addContentActions';
$wgHooks['RenameUserSQL'][] = 'PictureGameHooks::onUserRename';
$wgHooks['LoadExtensionSchemaUpdates'][] = 'PictureGameHooks::onLoadExtensionSchemaUpdates';
