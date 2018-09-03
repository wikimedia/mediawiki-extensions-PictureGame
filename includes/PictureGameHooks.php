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
	 *
	 * @param SkinTemplate $skinTemplate
	 * @param array $links
	 */
	public static function onSkinTemplateNavigationSpecialPage( &$skinTemplate, &$links ) {
		global $wgPictureGameID;

		$title = $skinTemplate->getTitle();
		$user = $skinTemplate->getUser();
		$request = $skinTemplate->getRequest();

		$action = $request->getVal( 'picGameAction' );
		$game = SpecialPage::getTitleFor( 'PictureGameHome' );

		// Add edit page to content actions but only for Special:PictureGameHome
		// and only when $wgPictureGameID is set so that we don't show the "edit"
		// tab when there is no data in the database
		if (
			$action != 'startCreate' &&
			$user->isAllowed( 'picturegameadmin' ) &&
			$title->isSpecial( 'PictureGameHome' ) && !empty( $wgPictureGameID )
		)
		{
			$links['views']['edit'] = [
				'class' => ( $action == 'editItem' ) ? 'selected' : false,
				'text' => $skinTemplate->msg( 'edit' )->plain(),
				// @see https://phabricator.wikimedia.org/T4457
				// @see https://phabricator.wikimedia.org/T4510
				'href' => $game->getFullURL( [
					'picGameAction' => 'editPanel',
					'id' => $wgPictureGameID
				] ),
			];
		}

		// If editing, make special page go back to quiz question
		if ( $action == 'editItem' ) {
			$links['views'][$title->getNamespaceKey()] = [
				'class' => 'selected',
				'text' => $skinTemplate->msg( 'nstab-special' )->plain(),
				'href' => $game->getFullURL( [
					'picGameAction' => 'renderPermalink',
					'id' => $wgPictureGameID
				] ),
			];
		}
	}

	/**
	 * Clear the real (displayed usually as the <h1> element by most skins) page
	 * title on Special:PictureGameHome because:
	 * 1) the [[MediaWiki:Picturegamehome]] i18n msg doesn't exist and
	 * 2) we want to show the picture game title as the page title
	 *
	 * @param SkinTemplate $skinTpl
	 * @param BaseTemplate $template
	 */
	public static function onSkinTemplateOutputPageBeforeExec( &$skinTpl, &$template ) {
		$title = $skinTpl->getTitle();
		if ( $title->isSpecial( 'PictureGameHome' ) ) {
			$template->data['title'] = '';
		}
	}
	/**
	 * For the Renameuser extension
	 *
	 * @param array $renameUserSQL
	 */
	public static function onRenameUserSQL( $renameUserSQL ) {
		$renameUserSQL->tables['picturegame_images'] = [ 'username', 'userid' ];
		$renameUserSQL->tables['picturegame_votes'] = [ 'username', 'userid' ];
	}

	/**
	 * Adds the new, required database tables when the user runs the core
	 * MediaWiki update script, /maintenance/update.php.
	 *
	 * @param DatabaseUpdater $updater
	 */
	public static function onLoadExtensionSchemaUpdates( $updater ) {
		$updater->addExtensionTable( 'picturegame_images', __DIR__ . '/../sql/picturegame.sql' );
		$updater->addExtensionTable( 'picturegame_votes', __DIR__ . '/../sql/picturegame.sql' );
		$updater->addExtensionField( 'picturegame_images', 'comment', __DIR__ . '/../sql/picturegame-add-comment.sql' );
	}
}
