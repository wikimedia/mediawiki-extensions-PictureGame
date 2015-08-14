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

// Extension credits that will show up on Special:Version
$wgExtensionCredits['other'][] = array(
	'name' => 'PictureGame',
	'version' => '3.2',
	'author' => array( 'Aaron Wright', 'Ashish Datta', 'David Pean', 'Jack Phoenix' ),
	'description' => 'Allows making [[Special:PictureGameHome|picture games]]',
	'url' => 'https://www.mediawiki.org/wiki/Extension:PictureGame'
);

// ResourceLoader support for MediaWiki 1.17+
$wgResourceModules['ext.pictureGame'] = array(
	'scripts' => 'PictureGame.js',
	'messages' => array(
		'picturegame-js-edit', 'picturegame-js-error-title',
		'picturegame-js-error-upload-imgone',
		'picturegame-js-error-upload-imgtwo', 'picturegame-js-editing-imgone',
		'picturegame-js-editing-imgtwo', 'picturegame-protectimgconfirm',
		'picturegame-flagimgconfirm'
	),
	'dependencies' => array(
		'ext.socialprofile.flash',
		'ext.socialprofile.LightBox'
	),
	'localBasePath' => __DIR__ . '/picturegame',
	'remoteExtPath' => 'PictureGame/picturegame',
);

$wgResourceModules['ext.pictureGame.adminPanel'] = array(
	'styles' => 'adminpanel.css',
	'localBasePath' => __DIR__ . '/picturegame',
	'remoteExtPath' => 'PictureGame/picturegame',
	'position' => 'top'
);

$wgResourceModules['ext.pictureGame.editPanel'] = array(
	'styles' => 'editpanel.css',
	'localBasePath' => __DIR__ . '/picturegame',
	'remoteExtPath' => 'PictureGame/picturegame',
	'position' => 'top'
);

$wgResourceModules['ext.pictureGame.gallery'] = array(
	'styles' => 'gallery.css',
	'localBasePath' => __DIR__ . '/picturegame',
	'remoteExtPath' => 'PictureGame/picturegame',
	'position' => 'top'
);

$wgResourceModules['ext.pictureGame.mainGame'] = array(
	'styles' => 'maingame.css',
	'localBasePath' => __DIR__ . '/picturegame',
	'remoteExtPath' => 'PictureGame/picturegame',
	'position' => 'top'
);

$wgResourceModules['ext.pictureGame.startGame'] = array(
	'styles' => 'startgame.css',
	'localBasePath' => __DIR__ . '/picturegame',
	'remoteExtPath' => 'PictureGame/picturegame',
	'position' => 'top'
);

// picturegame_images.flag used to be an enum() and that sucked, big time
define( 'PICTUREGAME_FLAG_NONE', 0 );
define( 'PICTUREGAME_FLAG_FLAGGED', 1 );
define( 'PICTUREGAME_FLAG_PROTECT', 2 );

// Set up the new special page and autoload classes
$wgMessagesDirs['PictureGame'] = __DIR__ . '/i18n';
$wgExtensionMessagesFiles['PictureGameAlias'] = __DIR__ . '/PictureGame.alias.php';
$wgAutoloadClasses['PictureGameHome'] = __DIR__ . '/PictureGameHome.body.php';
$wgSpecialPages['PictureGameHome'] = 'PictureGameHome';

// Upload form
$wgAutoloadClasses['SpecialPictureGameAjaxUpload'] = __DIR__ . '/AjaxUploadForm.php';
$wgAutoloadClasses['PictureGameAjaxUploadForm'] = __DIR__ . '/AjaxUploadForm.php';
$wgAutoloadClasses['PictureGameUpload'] = __DIR__ . '/AjaxUploadForm.php';
$wgSpecialPages['PictureGameAjaxUpload'] = 'SpecialPictureGameAjaxUpload';

// For example: 'edits' => 5 if you want to require users to have at least 5
// edits before they can create new picture games.
$wgCreatePictureGameThresholds = array();

// New user right, required to delete/protect picture games
$wgAvailableRights[] = 'picturegameadmin';
$wgGroupPermissions['sysop']['picturegameadmin'] = true;
$wgGroupPermissions['staff']['picturegameadmin'] = true;

// Hooked functions
$wgAutoloadClasses['PictureGameHooks'] = __DIR__ . '/PictureGameHooks.class.php';

$wgHooks['SkinTemplateNavigation::SpecialPage'][] = 'PictureGameHooks::addContentActions';
$wgHooks['RenameUserSQL'][] = 'PictureGameHooks::onUserRename';
$wgHooks['LoadExtensionSchemaUpdates'][] = 'PictureGameHooks::onLoadExtensionSchemaUpdates';
