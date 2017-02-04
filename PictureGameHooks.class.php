<?php
/**
 * PictureGame hooks class
 * All class methods are public and static.
 *
 * @file
 */
class PictureGameHooks {

	/**
	 * Custom content actions for picture game
	 */
	public static function addContentActions( &$skinTemplate, &$links ) {
		global $wgUser, $wgRequest, $wgPictureGameID;

		$title = $skinTemplate->getTitle();
		// Add edit page to content actions but only for Special:PictureGameHome
		// and only when $wgPictureGameID is set so that we don't show the "edit"
		// tab when there is no data in the database
		if (
			$wgRequest->getVal( 'picGameAction' ) != 'startCreate' &&
			$wgUser->isAllowed( 'picturegameadmin' ) &&
			$title->isSpecial( 'PictureGameHome' ) && !empty( $wgPictureGameID )
		)
		{
			$pic = SpecialPage::getTitleFor( 'PictureGameHome' );
			$links['views']['edit'] = array(
				'class' => ( $wgRequest->getVal( 'picGameAction' ) == 'editItem' ) ? 'selected' : false,
				'text' => wfMessage( 'edit' )->plain(),
				'href' => $pic->getFullURL( 'picGameAction=editPanel&id=' . $wgPictureGameID ), // @bug 2457, 2510
			);
		}

		// If editing, make special page go back to quiz question
		if ( $wgRequest->getVal( 'picGameAction' ) == 'editItem' ) {
			$pic = SpecialPage::getTitleFor( 'QuizGameHome' );
			$links['views'][$title->getNamespaceKey()] = array(
				'class' => 'selected',
				'text' => wfMessage( 'nstab-special' )->plain(),
				'href' => $pic->getFullURL( 'picGameAction=renderPermalink&id=' . $wgPictureGameID ),
			);
		}

		return true;
	}

	/**
	 * For the Renameuser extension
	 *
	 * @param $renameUserSQL Array
	 * @return Boolean
	 */
	public static function onUserRename( $renameUserSQL ) {
		$renameUserSQL->tables['picturegame_images'] = array( 'username', 'userid' );
		$renameUserSQL->tables['picturegame_votes'] = array( 'username', 'userid' );
		return true;
	}

	/**
	 * Adds the new, required database tables when the user runs the core
	 * MediaWiki update script, /maintenance/update.php.
	 *
	 * @param $updater DatabaseUpdater
	 * @return Boolean
	 */
	public static function onLoadExtensionSchemaUpdates( $updater ) {
		$updater->addExtensionUpdate( array( 'addTable', 'picturegame_images', __DIR__ . '/sql/picturegame.sql', true ) );
		$updater->addExtensionUpdate( array( 'addTable', 'picturegame_votes', __DIR__ . '/sql/picturegame.sql', true ) );
		$updater->addExtensionField( 'picturegame_images', 'comment', __DIR__ . '/sql/picturegame-add-comment.sql' );

		return true;
	}
}
