<?php
/**
 * PictureGame extension - allows making picture games
 *
 * @file
 * @ingroup Extensions
 * @author Aaron Wright <aaron.wright@gmail.com>
 * @author Ashish Datta <ashish@setfive.com>
 * @author David Pean <david.pean@gmail.com>
 * @author Jack Phoenix
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 * @link https://www.mediawiki.org/wiki/Extension:PictureGame Documentation
 */

use MediaWiki\MediaWikiServices;

class PictureGameHome extends UnlistedSpecialPage {
	// picturegame_images.flag used to be an enum() and that sucked, big time
	static $FLAG_NONE = 0;
	static $FLAG_FLAGGED = 1;
	static $FLAG_PROTECT = 2;

	/**
	 * @var String: MD5 hash of the current user's username; used to salt admin panel requests
	 */
	private $SALT;

	/**
	 * Construct the MediaWiki special page
	 */
	public function __construct() {
		parent::__construct( 'PictureGameHome' );
	}

	/**
	 * Show the special page
	 *
	 * @param mixed|null $par Parameter passed to the page, if any
	 */
	public function execute( $par ) {
		global $wgSecretKey;

		$out = $this->getOutput();
		$request = $this->getRequest();
		$user = $this->getUser();

		// Is the database locked?
		$this->checkReadOnly();

		// https://phabricator.wikimedia.org/T155405
		// Throws error message when SocialProfile extension is not installed
		if ( !class_exists( 'UserStats' ) ) {
			throw new ErrorPageError( 'picturegame-error-socialprofile-title', 'picturegame-error-socialprofile' );
		}

		// Blocked through Special:Block? No access for you either!
		if ( $user->isBlocked() ) {
			throw new UserBlockedError( $user->getBlock() );
		}

		// Set the correct robot policies, ensure that skins don't render a link to
		// Special:WhatLinksHere on their toolboxes, etc.
		$this->setHeaders();

		// Salt as you like
		// FIXME replace with MediaWiki's edit token system.
		$this->SALT = MWCryptHash::hmac( $user->getName(), $wgSecretKey, false );

		// Add the main JS file
		$out->addModules( 'ext.pictureGame' );

		// What should we do?
		$action = $request->getVal( 'picGameAction' );

		switch ( $action ) {
			case 'startGame':
				$this->renderPictureGame();
				break;
			case 'createGame':
				$this->createGame();
				break;
			case 'castVote':
				$this->voteAndForward();
				break;
			case 'flagImage':
				$this->flagImage();
				break;
			case 'renderPermalink':
				$this->renderPictureGame();
				break;
			case 'gallery':
				$this->displayGallery();
				break;
			case 'editPanel':
				if ( $user->isLoggedIn() && $user->isAllowed( 'picturegameadmin' ) ) {
					$this->editPanel();
				} else {
					$this->showHomePage();
				}
				break;
			case 'completeEdit':
				if ( $user->isLoggedIn() && $user->isAllowed( 'picturegameadmin' ) ) {
					$this->completeEdit();
				} else {
					$this->showHomePage();
				}
				break;
			case 'adminPanel':
				if ( $user->isLoggedIn() && $user->isAllowed( 'picturegameadmin' ) ) {
					$this->adminPanel();
				} else {
					$this->showHomePage();
				}
				break;
			case 'adminPanelUnflag':
				if ( $user->isLoggedIn() && $user->isAllowed( 'picturegameadmin' ) ) {
					$this->adminPanelUnflag();
				} else {
					$this->showHomePage();
				}
				break;
			case 'adminPanelDelete':
				if ( $user->isLoggedIn() && $user->isAllowed( 'picturegameadmin' ) ) {
					$this->adminPanelDelete();
				} else {
					$this->showHomePage();
				}
				break;
			case 'protectImages':
				if ( $user->isLoggedIn() && $user->isAllowed( 'picturegameadmin' ) ) {
					$this->protectImages();
				} else {
					echo $this->msg( 'picturegame-sysmsg-unauthorized' )->escaped();
				}
				break;
			case 'unprotectImages':
				if ( $user->isLoggedIn() && $user->isAllowed( 'picturegameadmin' ) ) {
					$this->unprotectImages();
				} else {
					$this->showHomePage();
				}
				break;
			case 'startCreate':
				if ( $user->isBlocked() ) {
					throw new UserBlockedError( $user->getBlock() );
				} else {
					$this->showHomePage();
				}
				break;
			default:
				$this->renderPictureGame();
				break;
		}
	}

	/**
	 * Called via AJAX to delete an image out of the game.
	 */
	function adminPanelDelete() {
		$out = $this->getOutput();
		$request = $this->getRequest();
		$user = $this->getUser();

		$out->setArticleBodyOnly( true );

		$id = $request->getInt( 'id' );
		$image1 = addslashes( $request->getVal( 'img1' ) );
		$image2 = addslashes( $request->getVal( 'img2' ) );

		$key = $request->getVal( 'key' );
		$now = $request->getVal( 'chain' );

		if (
			$key != md5( $now . $this->SALT ) ||
			( !$user->isLoggedIn() || !$user->isAllowed( 'picturegameadmin' ) )
		)
		{
			//echo $this->msg( 'picturegame-sysmsg-badkey' )->text();
			//return;
		}

		$dbw = wfGetDB( DB_MASTER );
		$dbw->delete( 'picturegame_images', [ 'id' => $id ], __METHOD__ );

		global $wgMemc;
		$key = $wgMemc->makeKey( 'user', 'profile', 'picgame', $user->getId() );
		$wgMemc->delete( $key );

		/* Pop the images out of MediaWiki also */
		//$img_one = MediaWikiServices::getInstance()->getRepoGroup()->findFile( $image1 );
		$oneResult = $twoResult = false;
		if ( $image1 ) {
			$img_one = Title::makeTitle( NS_FILE, $image1 );
			$reason = 'Picture Game image 1 Delete';
			$wikipage = WikiPage::factory( $img_one );
			if ( version_compare( MW_VERSION, '1.35', '<' ) ) {
				$status = $wikipage->doDeleteArticleReal( $reason );
			} else {
				// Different signature in 1.35 and above
				$status = $wikipage->doDeleteArticleReal( $reason, $user );
			}
			$oneResult = $status->isOK();
		}

		if ( $image2 ) {
			$img_two = Title::makeTitle( NS_FILE, $image2 );
			$reason = 'Picture Game image 2 Delete';
			$wikipage = WikiPage::factory( $img_two );
			if ( version_compare( MW_VERSION, '1.35', '<' ) ) {
				$status = $wikipage->doDeleteArticleReal( $reason );
			} else {
				// Different signature in 1.35 and above
				$status = $wikipage->doDeleteArticleReal( $reason, $user );
			}
			$twoResult = $status->isOK();
		}

		if ( $oneResult && $twoResult ) {
			$this->addLogEntry( 'delete', $id ); // @todo FIXME: Do we want more precise logging? What about the failure cases?
			echo $this->msg( 'picturegame-sysmsg-successfuldelete' )->escaped();
			return;
		}

		if ( $oneResult ) {
			echo $this->msg( 'picturegame-sysmsg-unsuccessfuldelete', $image1 )->escaped();
		}
		if ( $twoResult ) {
			echo $this->msg( 'picturegame-sysmsg-unsuccessfuldelete', $image2 )->escaped();
		}
	}

	/**
	 * Called over AJAX to unflag an image
	 */
	function adminPanelUnflag() {
		$out = $this->getOutput();
		$request = $this->getRequest();
		$user = $this->getUser();

		$out->setArticleBodyOnly( true );

		$id = $request->getInt( 'id' );

		$key = $request->getVal( 'key' );
		$now = $request->getVal( 'chain' );

		if (
			$key != md5( $now . $this->SALT ) ||
			( !$user->isLoggedIn() || !$user->isAllowed( 'picturegameadmin' ) )
		) {
			echo $this->msg( 'picturegame-sysmsg-badkey' )->escaped();
			return;
		}

		$dbw = wfGetDB( DB_MASTER );
		$dbw->update(
			'picturegame_images',
			[ 'flag' => PictureGameHome::$FLAG_NONE, 'comment' => '' ],
			[ 'id' => $id ],
			__METHOD__
		);

		$this->addLogEntry( 'unflag', $id );

		$out->clearHTML();
		echo $this->msg( 'picturegame-sysmsg-unflag' )->escaped();
	}

	/**
	 * Updates a record in the picture game table.
	 */
	function completeEdit() {
		$out = $this->getOutput();
		$request = $this->getRequest();

		$id = $request->getInt( 'id' );
		$key = addslashes( $request->getVal( 'key' ) );

		$title = $request->getVal( 'newTitle' );
		$imgOneCaption = $request->getVal( 'imgOneCaption' );
		$imgTwoCaption = $request->getVal( 'imgTwoCaption' );

		if ( $key != md5( $id . $this->SALT ) ) {
			$out->addHTML( '<h3>' . $this->msg( 'picturegame-sysmsg-badkey' )->escaped() . '</h3>' );
			return;
		}

		$dbw = wfGetDB( DB_MASTER );
		$dbw->update(
			'picturegame_images',
			[
				'title' => $title,
				'img1_caption' => $imgOneCaption,
				'img2_caption' => $imgTwoCaption
			],
			[ 'id' => $id ],
			__METHOD__
		);

		// @todo FIXME: also log $title, $imgOneCaption, $imgTwoCaption (tho latter are basically
		// always empty b/c they are not exposed in the UI)
		$this->addLogEntry( 'edit', $id );

		/* When it's done, redirect to a permalink of these images */
		$out->setArticleBodyOnly( true );
		header( 'Location: ?title=Special:PictureGameHome&picGameAction=renderPermalink&id=' . $id );
	}

	/**
	 * Displays the edit panel.
	 */
	function editPanel() {
		global $wgUploadPath, $wgExtensionAssetsPath, $wgRightsText;

		$lang = $this->getLanguage();
		$out = $this->getOutput();
		$request = $this->getRequest();

		$id = $request->getInt( 'id' );

		$dbw = wfGetDB( DB_MASTER );
		$res = $dbw->select(
			'picturegame_images',
			'*',
			[ 'id' => $id ],
			__METHOD__
		);

		$row = $dbw->fetchObject( $res );
		if ( empty( $row ) ) {
			$out->addHTML( $this->msg( 'picturegame-nothing-to-edit' )->escaped() );
			return;
		}

		$imgID = $row->id;
		$actor = User::newFromActorId( $row->actor );
		$user_name = $lang->truncateForVisual( $actor->getName(), 20 );

		$title_text = $row->title;
		$img1_caption_text = $row->img1_caption;
		$img2_caption_text = $row->img2_caption;
		$repoGroup = MediaWikiServices::getInstance()->getRepoGroup();

		// I assume MediaWiki does some caching with these functions?
		$img_one = $repoGroup->findFile( $row->img1 );
		$imgOneWidth = 0;
		$thumb_one_url = $thumb_two_url = '';
		if ( is_object( $img_one ) ) {
			$thumb_one_url = $img_one->createThumb( 128 );
			if ( $img_one->getWidth() >= 128 ) {
				$imgOneWidth = 128;
			} else {
				$imgOneWidth = $img_one->getWidth();
			}
		}
		$imgOne = '<img width="' . $imgOneWidth . '" alt="" src="' .
			$thumb_one_url . '?' . time() . '"/>';
		$imgOneName = $row->img1;

		$img_two = $repoGroup->findFile( $row->img2 );
		$imgTwoWidth = 0;
		if ( is_object( $img_two ) ) {
			$thumb_two_url = $img_two->createThumb( 128 );
			if ( $img_one->getWidth() >= 128 ) {
				$imgTwoWidth = 128;
			} else {
				$imgTwoWidth = $img_one->getWidth();
			}
		}
		$imgTwo = '<img width="' . $imgTwoWidth . '" alt="" src="' .
			$thumb_two_url . '?' . time() . '"/>';
		$imgTwoName = $row->img2;

		$output = '';

		$out->addModuleStyles( 'ext.pictureGame.editPanel' );

		$out->setPageTitle( $this->msg( 'picturegame-editgame-editing-title', $title_text )->text() );

		$id = $actor->getId();
		$avatar = new wAvatar( $id, 'l' );
		$avatarID = $avatar->getAvatarImage();
		$stats = new UserStats( $id, $actor->getName() );
		$stats_data = $stats->getUserStats();

		if ( $wgRightsText ) {
			$copywarnMsg = 'copyrightwarning';
			$copywarnMsgParams = [
				'[[' . $this->msg( 'copyrightpage' )->inContentLanguage()->plain() . ']]',
				$wgRightsText
			];
		} else {
			$copywarnMsg = 'copyrightwarning2';
			$copywarnMsgParams = [
				'[[' . $this->msg( 'copyrightpage' )->inContentLanguage()->plain() . ']]'
			];
		}

		$usrTitleObj = $actor->getUserPage();
		$imgPath = htmlspecialchars( $wgExtensionAssetsPath . '/SocialProfile/images' );

		$formattedVoteCount = htmlspecialchars( $lang->formatNum( $stats_data['votes'] ) );
		$formattedEditCount = htmlspecialchars( $lang->formatNum( $stats_data['edits'] ) );
		$formattedCommentCount = htmlspecialchars( $lang->formatNum( $stats_data['comments'] ) );
		$output .= '<div id="edit-container" class="edit-container">
			<form id="picGameVote" name="picGameVote" method="post" action="' .
			htmlspecialchars( $this->getPageTitle()->getFullURL( 'picGameAction=completeEdit' ) ) . '">
			<div id="edit-textboxes" class="edit-textboxes">

				<div class="credit-box-edit" id="creditBox">
					<h1>' . $this->msg( 'picturegame-submittedby' )->escaped() . '</h1>
					<div class="submitted-by-image">
						<a href="' . htmlspecialchars( $usrTitleObj->getFullURL() ) . "\">
							<img src=\"{$wgUploadPath}/avatars/{$avatarID}\" style=\"border:1px solid #d7dee8; width:50px; height:50px;\" alt=\"\" border=\"0\"/>
						</a>
					</div>
					<div class=\"submitted-by-user\">
						<a href=\"" . htmlspecialchars( $usrTitleObj->getFullURL() ) . "\">{$user_name}</a>
						<ul>
							<li>
								<img src=\"{$imgPath}/voteIcon.gif\" border=\"0\" alt=\"\" />
								{$formattedVoteCount}
							</li>
							<li>
								<img src=\"{$imgPath}/editIcon.gif\" border=\"0\" alt=\"\" />
								{$formattedEditCount}
							</li>
							<li>
								<img src=\"{$imgPath}/commentsIcon.gif\" border=\"0\" alt=\"\" />
								{$formattedCommentCount}
							</li>
						</ul>
					</div>
					<div class=\"visualClear\"></div>
				</div>


				<h1>" . $this->msg( 'picturegame-editgamegametitle' )->escaped() . "</h1>
				<p><input name=\"newTitle\" id=\"newTitle\" type=\"text\" value=\"{$title_text}\" size=\"40\"/></p>
					<input id=\"key\" name=\"key\" type=\"hidden\" value=\"" . md5( $imgID . $this->SALT ) . "\" />
					<input id=\"id\" name=\"id\" type=\"hidden\" value=\"{$imgID}\" />

			</div>
			<div class=\"edit-images-container\">
				<div id=\"edit-images\" class=\"edit-images\">
					<div id=\"edit-image-one\" class=\"edit-image-one\">
						<h1>" . $this->msg( 'picturegame-createeditfirstimage' )->escaped() . "</h1>
						<p><input name=\"imgOneCaption\" id=\"imgOneCaption\" type=\"text\" value=\"{$img1_caption_text}\" /></p>
						<p id=\"image-one-tag\">{$imgOne}</p>
						<p><a class=\"picgame-upload-link-1\" href=\"#\" data-img-one-name=\"{$imgOneName}\">" .
							$this->msg( 'picturegame-editgameuploadtext' )->escaped() . '</a></p>
					</div>

					<div id="edit-image-two" class="edit-image-one">
						<h1>' . $this->msg( 'picturegame-createeditsecondimage' )->escaped() . "</h1>
						<p><input name=\"imgTwoCaption\" id=\"imgTwoCaption\" type=\"text\" value=\"{$img2_caption_text}\" /></p>
						<p id=\"image-two-tag\">{$imgTwo}</p>
						<p><a class=\"picgame-upload-link-2\" href=\"#\" data-img-two-name=\"{$imgTwoName}\">" .
							$this->msg( 'picturegame-editgameuploadtext' )->escaped() . "</a></p>
					</div>

					<div id=\"loadingImg\" class=\"loadingImg\" style=\"display:none\">
						<img src=\"{$imgPath}/ajax-loader-white.gif\" alt=\"\" />
					</div>

					<div class=\"visualClear\"></div>

				</div>

				<div class=\"edit-image-frame\" id=\"edit-image-frame\" style=\"display:hidden\">
					<div class=\"edit-image-text\" id=\"edit-image-text\"></div>
					<iframe frameborder=\"0\" scrollbar=\"no\" class=\"upload-frame\" id=\"upload-frame\" src=\"\"></iframe>
				</div>

				<div class=\"visualClear\"></div>
			</div>

			<div class=\"copyright-warning\">" .
				$this->msg( $copywarnMsg, $copywarnMsgParams )->parse() .
			'</div>

			<div id="complete-buttons" class="complete-buttons">
				<input type="button" onclick="document.picGameVote.submit()" value="' . $this->msg( 'picturegame-buttonsubmit' )->escaped() . "\"/>
				<input type=\"button\" onclick=\"window.location='" .
					htmlspecialchars( $this->getPageTitle()->getFullURL( "picGameAction=renderPermalink&id={$imgID}" ) ) . "'\" value=\"" .
					$this->msg( 'cancel' )->escaped() . "\"/>
			</div>
		</form>
		</div>";

		$out->addHTML( $output );
	}

	/**
	 * Displays the admin panel.
	 */
	function adminPanel() {
		$lang = $this->getLanguage();
		$out = $this->getOutput();
		$request = $this->getRequest();
		$user = $this->getUser();

		$now = Xml::encodeJsVar( time() );
		$key = Xml::encodeJsVar( md5( $now . $this->SALT ) );

		$output = '<script type="text/javascript">
			var __admin_panel_now__ = ' . $now . ';
			var __admin_panel_key__ = ' . $key . ';
		</script>';

		$out->addModuleStyles( 'ext.pictureGame.adminPanel' );

		$out->setPageTitle( $this->msg( 'picturegame-adminpaneltitle' )->text() );
		$output .= '
		<div class="back-link">
			<a href="' . htmlspecialchars( $this->getPageTitle()->getFullURL( 'picGameAction=startGame' ) ) . '"> ' .
				$this->msg( 'picturegame-adminpanelbacktogame' )->escaped() . '</a>
		</div>

		<div id="admin-container" class="admin-container">
			<p><strong>' . $this->msg( 'picturegame-adminpanelflagged' )->escaped() . '</strong></p>';

		$dbw = wfGetDB( DB_MASTER );
		$res = $dbw->select(
			'picturegame_images',
			[ 'id', 'img1', 'img2', 'comment' ],
			[
				'flag' => PictureGameHome::$FLAG_FLAGGED,
				"img1 <> ''",
				"img2 <> ''"
			],
			__METHOD__
		);

		// If we have nothing, indicate that in the UI instead of showing...
		// well, nothing
		if ( $dbw->numRows( $res ) <= 0 ) {
			$output .= $this->msg( 'picturegame-none' )->escaped();
		}
		$repoGroup = MediaWikiServices::getInstance()->getRepoGroup();

		foreach ( $res as $row ) {
			$img_one_tag = $img_two_tag = '';
			$img_one = $repoGroup->findFile( $row->img1 );
			if ( is_object( $img_one ) ) {
				$thumb_one = $img_one->transform( [ 'width' => 128 ] );
				$img_one_tag = $thumb_one->toHtml();
			}

			$img_two = $repoGroup->findFile( $row->img2 );
			if ( is_object( $img_two ) ) {
				$thumb_two = $img_two->transform( [ 'width' => 128 ] );
				$img_two_tag = $thumb_two->toHtml();
			}

			$img_one_description = htmlspecialchars( $lang->truncateForVisual( $row->img1, 12 ) );
			$img_two_description = htmlspecialchars( $lang->truncateForVisual( $row->img2, 12 ) );
			$img1Name = htmlspecialchars( $row->img1 );
			$img2Name = htmlspecialchars( $row->img2 );

			$reason = '';
			$comment = htmlspecialchars( $row->comment );
			$id = (int)$row->id;
			if ( !empty( $row->comment ) ) {
				$reason .= "<div class=\"picturegame-adminpanelflag\" id=\"picturegame-adminpanelflagreason-$id\">
				<b>" . $this->msg( 'picturegame-adminpanelreason' )->escaped() . "</b>: $comment
				</div><p>";
			}

			$output .= '<div id="' . $id . "\" class=\"admin-row\">
				<div class=\"admin-image\">
					<p>{$img_one_tag}</p>
					<p><b>{$img_one_description}</b></p>
				</div>
				<div class=\"admin-image\">
					<p>{$img_two_tag}</p>
					<p><b>{$img_two_description}</b></p>
				</div>
				<div class=\"admin-controls\">
					<a class=\"picgame-unflag-link\" href=\"#\">" .
						$this->msg( 'picturegame-adminpanelunflag' )->escaped() .
					"</a> |
					<a class=\"picgame-delete-link\" href=\"#\" data-row-img1=\"$img1Name\" data-row-img2=\"$img2Name\">"
						. $this->msg( 'picturegame-adminpaneldelete' )->escaped() .
					"</a>
					{$reason}
				</div>
				<div class=\"visualClear\"></div>
			</div>";
		}

		$output .= '</div>
		<div id="admin-container" class="admin-container">
			<p><strong>' . $this->msg( 'picturegame-adminpanelprotected' )->escaped() . '</strong></p>';
		$dbw = wfGetDB( DB_MASTER );
		$res = $dbw->select(
			'picturegame_images',
			[ 'id', 'img1', 'img2' ],
			[
				'flag' => PictureGameHome::$FLAG_PROTECT,
				"img1 <> ''",
				"img2 <> ''"
			],
			__METHOD__
		);

		// If we have nothing, indicate that in the UI instead of showing...
		// well, nothing
		if ( $dbw->numRows( $res ) <= 0 ) {
			$output .= $this->msg( 'picturegame-none' )->escaped();
		}

		foreach ( $res as $row ) {
			$img_one_tag = $img_two_tag = '';
			$img_one = $repoGroup->findFile( $row->img1 );
			if ( is_object( $img_one ) ) {
				$thumb_one = $img_one->transform( [ 'width' => 128 ] );
				$img_one_tag = $thumb_one->toHtml();
			}

			$img_two = $repoGroup->findFile( $row->img2 );
			if ( is_object( $img_two ) ) {
				$thumb_two = $img_two->transform( [ 'width' => 128 ] );
				$img_two_tag = $thumb_two->toHtml();
			}

			$img_one_description = htmlspecialchars( $lang->truncateForVisual( $row->img1, 12 ) );
			$img_two_description = htmlspecialchars( $lang->truncateForVisual( $row->img2, 12 ) );

			$img1Name = htmlspecialchars( $row->img1 );
			$img2Name = htmlspecialchars( $row->img2 );
			$id = (int)$row->id;
			$output .= '<div id="' . $id . "\" class=\"admin-row\">

				<div class=\"admin-image\">
					<p>{$img_one_tag}</p>
					<p><b>{$img_one_description}</b></p>
				</div>
				<div class=\"admin-image\">
					<p>{$img_two_tag}</p>
					<p><b>{$img_two_description}</b></p>
				</div>
				<div class=\"admin-controls\">
					<a class=\"picgame-unprotect-link\" href=\"#\">" .
						$this->msg( 'picturegame-adminpanelunprotect' )->escaped() .
					"</a> |
					<a class=\"picgame-delete-link\" href=\"#\" data-row-img1=\"$img1Name\" data-row-img2=\"$img2Name\">"
						. $this->msg( 'picturegame-adminpaneldelete' )->escaped() .
						'</a>
					</div>
					<div class="visualClear"></div>
				</div>';
		}

		$output .= '</div>';

		$out->addHTML( $output );
	}

	/**
	 * Called with AJAX to flag an image.
	 */
	function flagImage() {
		$out = $this->getOutput();
		$request = $this->getRequest();

		$out->setArticleBodyOnly( true );

		$id = $request->getInt( 'id' );
		$key = $request->getVal( 'key' );
		$comment = $request->getVal( 'comment' ) ? $request->getVal( 'comment' ) : ''; // reason for flagging
		if ( $key != md5( $id . $this->SALT ) ) {
			echo $this->msg( 'picturegame-sysmsg-badkey' )->escaped();
			return;
		}

		$dbw = wfGetDB( DB_MASTER );
		$dbw->update(
			'picturegame_images',
			[ 'flag' => PictureGameHome::$FLAG_FLAGGED, 'comment' => $comment ],
			[ 'id' => $id, 'flag' => PictureGameHome::$FLAG_NONE ],
			__METHOD__
		);

		$this->addLogEntry( 'flag', $id, $comment );

		$out->clearHTML();
		echo '<div style="color:red; font-weight:bold; font-size:16px; margin:-5px 0px 20px 0px;">' .
			$this->msg( 'picturegame-sysmsg-flag' )->escaped() .
		'</div>';
	}

	/**
	 * Called with AJAX to unprotect an image set.
	 */
	function unprotectImages() {
		$out = $this->getOutput();
		$request = $this->getRequest();

		$out->setArticleBodyOnly( true );

		$id = $request->getInt( 'id' );
		$key = $request->getVal( 'key' );
		$chain = $request->getVal( 'chain' );

		if ( $key != md5( $chain . $this->SALT ) ) {
			echo $this->msg( 'picturegame-sysmsg-badkey' )->escaped();
			return;
		}

		$dbw = wfGetDB( DB_MASTER );
		$dbw->update(
			'picturegame_images',
			[ 'flag' => PictureGameHome::$FLAG_NONE ],
			[ 'id' => $id ],
			__METHOD__
		);

		$this->addLogEntry( 'unprotect', $id );

		$out->clearHTML();
		echo $this->msg( 'picturegame-sysmsg-unprotect' )->escaped();
	}

	/**
	 * Protects an image set.
	 */
	function protectImages() {
		$out = $this->getOutput();
		$request = $this->getRequest();

		$out->setArticleBodyOnly( true );

		$id = $request->getInt( 'id' );
		$key = $request->getVal( 'key' );

		if ( $key != md5( $id . $this->SALT ) ) {
			echo $this->msg( 'picturegame-sysmsg-badkey' )->escaped();
			return;
		}

		$dbw = wfGetDB( DB_MASTER );
		$dbw->update(
			'picturegame_images',
			[ 'flag' => PictureGameHome::$FLAG_PROTECT ],
			[ 'id' => $id ],
			__METHOD__
		);

		$this->addLogEntry( 'protect', $id );

		$out->clearHTML();
		echo $this->msg( 'picturegame-sysmsg-protect' )->escaped();
	}

	function displayGallery() {
		$lang = $this->getLanguage();
		$out = $this->getOutput();
		$request = $this->getRequest();
		$user = $this->getUser();
		$linkRenderer = $this->getLinkRenderer();
		$thisTitle = $this->getPageTitle();

		$out->setHTMLTitle( $this->msg( 'pagetitle', $this->msg( 'picturegame-gallery' )->text() )->text() );

		$type = $request->getVal( 'type' );
		$direction = $request->getVal( 'direction' );

		if ( ( $type == 'heat' ) && ( $direction == 'most' ) ) {
			$crit = 'Heat';
			$order = 'ASC';
			$sortheader = $this->msg( 'picturegame-sorted-most-heat' )->text();
		} elseif ( ( $type == 'heat' ) && ( $direction == 'least' ) ) {
			$crit = 'Heat';
			$order = 'DESC';
			$sortheader = $this->msg( 'picturegame-sorted-least-heat' )->text();
		} elseif ( ( $type == 'votes' ) && ( $direction == 'most' ) ) {
			$crit = '(img0_votes + img1_votes)';
			$order = 'DESC';
			$sortheader = $this->msg( 'picturegame-sorted-most-votes' )->text();
		} elseif ( ( $type == 'votes' ) && ( $direction == 'least' ) ) {
			$crit = '(img0_votes + img1_votes)';
			$order = 'ASC';
			$sortheader = $this->msg( 'picturegame-sorted-least-votes' )->text();
		} else {
			$type = 'heat';
			$direction = 'most';
			$crit = 'Heat';
			$order = 'ASC';
			$sortheader = $this->msg( 'picturegame-sorted-most-heat' )->text();
		}

		if ( isset( $sortheader ) ) {
			$out->setPageTitle( $sortheader );
		}

		// Add CSS
		$out->addModuleStyles( 'ext.pictureGame.gallery' );

		$output = '<div class="picgame-gallery-navigation">';

		if ( $type == 'votes' && $direction == 'most' ) {
			$output .= '<h1>' . $this->msg( 'picturegame-most' )->escaped() . '</h1>
					<p><b>' . $this->msg( 'picturegame-mostvotes' )->escaped() . '</b></p>
					<p><a href="' . htmlspecialchars( $thisTitle->getFullURL( [
						'picGameAction' => 'gallery',
						'type' => 'heat',
						'direction' => 'most'
					] ) ) . '">' . $this->msg( 'picturegame-mostheat' )->escaped() . '</a></p>

					<h1 style="margin:10px 0px !important;">' . $this->msg( 'picturegame-least' )->escaped() . '</h1>
					<p><a href="' . htmlspecialchars( $thisTitle->getFullURL( [
						'picGameAction' => 'gallery',
						'type' => 'votes',
						'direction' => 'least'
					] ) ) . '">' . $this->msg( 'picturegame-leastvotes' )->escaped() . '</a></p>
					<p><a href="' . htmlspecialchars( $thisTitle->getFullURL( [
						'picGameAction' => 'gallery',
						'type' => 'heat',
						'direction' => 'least'
					] ) ) . '">' . $this->msg( 'picturegame-leastheat' )->escaped() . '</a></p>';
		}

		if ( $type == 'votes' && $direction == 'least' ) {
			$output .= '<h1>' . $this->msg( 'picturegame-most' )->escaped() . '</h1>
					<p><a href="' . htmlspecialchars( $thisTitle->getFullURL( [
						'picGameAction' => 'gallery',
						'type' => 'votes',
						'direction' => 'most'
					] ) ) . '">' . $this->msg( 'picturegame-mostvotes' )->escaped() . '</a></p>
					<p><a href="' . htmlspecialchars( $thisTitle->getFullURL( [
						'picGameAction' => 'gallery',
						'type' => 'heat',
						'direction' => 'most'
					] ) ) . '">' . $this->msg( 'picturegame-mostheat' )->escaped() . '</a></p>

					<h1 style="margin:10px 0px !important;">' . $this->msg( 'picturegame-least' )->escaped() . '</h1>
					<p><b>' . $this->msg( 'picturegame-leastvotes' )->escaped() . '</b></p>
					<p><a href="' . htmlspecialchars( $thisTitle->getFullURL( [
						'picGameAction' => 'gallery',
						'type' => 'heat',
						'direction' => 'least'
					] ) ) . '">' . $this->msg( 'picturegame-leastheat' )->escaped() . '</a></p>';
		}

		if ( $type == 'heat' && $direction == 'most' ) {
			$output .= '<h1>' . $this->msg( 'picturegame-most' )->escaped() . '</h1>
					<p><a href="' . htmlspecialchars( $thisTitle->getFullURL( [
						'picGameAction' => 'gallery',
						'type' => 'votes',
						'direction' => 'most'
					] ) ) . '">' . $this->msg( 'picturegame-mostvotes' )->escaped() . '</a></p>
					<p><b>' . $this->msg( 'picturegame-mostheat' )->escaped() . '</b></p>

					<h1 style="margin:10px 0px !important;">' . $this->msg( 'picturegame-least' )->escaped() . '</h1>
					<p><a href="' . htmlspecialchars( $thisTitle->getFullURL( [
						'picGameAction' => 'gallery',
						'type' => 'votes',
						'direction' => 'least'
					] ) ) . '">' . $this->msg( 'picturegame-leastvotes' )->escaped() . '</a></p>
					<p><a href="' . htmlspecialchars( $thisTitle->getFullURL( [
						'picGameAction' => 'gallery',
						'type' => 'heat',
						'direction' => 'least'
					] ) ) . '">' . $this->msg( 'picturegame-leastheat' )->escaped() . '</a></p>';
		}

		if ( $type == 'heat' && $direction == 'least' ) {
			$output .= '<h1>' . $this->msg( 'picturegame-most' )->escaped() . '</h1>
					<p><a href="' . htmlspecialchars( $thisTitle->getFullURL( [
						'picGameAction' => 'gallery',
						'type' => 'votes',
						'direction' => 'most'
					] ) ) . '">' . $this->msg( 'picturegame-mostvotes' )->escaped() . '</a></p>
					<p><a href="' . htmlspecialchars( $thisTitle->getFullURL( [
						'picGameAction' => 'gallery',
						'type' => 'heat',
						'direction' => 'most'
					] ) ) . '">' . $this->msg( 'picturegame-mostheat' )->escaped() . '</a></p>

					<h1 style="margin:10px 0px !important;">' . $this->msg( 'picturegame-least' )->escaped() . '</h1>
					<p><a href="' . htmlspecialchars( $thisTitle->getFullURL( [
						'picGameAction' => 'gallery',
						'type' => 'votes',
						'direction' => 'least'
					] ) ) . '">' . $this->msg( 'picturegame-leastvotes' )->escaped() . '</a></p>
					<p><b>' . $this->msg( 'picturegame-leastheat' )->escaped() . '</b></p>';
		}

		$output .= '</div>';

		$output .= '<div class="picgame-gallery-container" id="picgame-gallery-thumbnails">';

		$per_row = 3;
		$x = 1;

		$dbr = wfGetDB( DB_REPLICA );
		$total = (int)$dbr->selectField(
			'picturegame_images',
			[ 'COUNT(*) AS mycount' ],
			[],
			__METHOD__
		);

		// We have nothing? If so, inform the user about it...
		if ( $total == 0 ) {
			$output .= $this->msg( 'picturegame-gallery-empty' )->parse();
		}

		$page = $request->getInt( 'page', 1 );

		// Add limit to SQL
		$per_page = 9;
		$limit = $per_page;

		$limitvalue = 0;
		if ( $limit > 0 && $page ) {
			$limitvalue = $page * $limit - ( $limit );
		}

		$dbw = wfGetDB( DB_MASTER );
		$res = $dbw->select(
			'picturegame_images',
			'*',
			[
				'flag <> ' . PictureGameHome::$FLAG_FLAGGED,
				"img1 <> ''",
				"img2 <> ''"
			],
			__METHOD__,
			[
				'ORDER BY' => "{$crit} {$order}",
				'OFFSET' => $limitvalue,
				'LIMIT' => $limit
			]
		);

		$preloadImages = [];
		$repoGroup = MediaWikiServices::getInstance()->getRepoGroup();

		foreach ( $res as $row ) {
			$gameid = (int)$row->id;

			$title_text = htmlspecialchars( $lang->truncateForVisual( $row->title, 23 ) );

			$imgOneCount = (int)$row->img0_votes;
			$imgTwoCount = (int)$row->img1_votes;
			$totalVotes = $imgOneCount + $imgTwoCount;

			if ( $imgOneCount == 0 ) {
				$imgOnePercent = 0;
			} else {
				$imgOnePercent = floor( $imgOneCount / $totalVotes  * 100 );
			}

			if ( $imgTwoCount == 0 ) {
				$imgTwoPercent = 0;
			} else {
				$imgTwoPercent = 100 - $imgOnePercent;
			}

			$gallery_thumbnail_one = $gallery_thumbnail_two = '';
			$img_one = $repoGroup->findFile( $row->img1 );
			if ( is_object( $img_one ) ) {
				$gallery_thumb_image_one = $img_one->transform( [ 'width' => 80 ] );
				$gallery_thumbnail_one = $gallery_thumb_image_one->toHtml();
			}

			$img_two = $repoGroup->findFile( $row->img2 );
			if ( is_object( $img_two ) ) {
				$gallery_thumb_image_two = $img_two->transform( [ 'width' => 80 ] );
				$gallery_thumbnail_two = $gallery_thumb_image_two->toHtml();
			}

			$output .= "
			<div class=\"picgame-gallery-thumbnail\" id=\"picgame-gallery-thumbnail-{$x}\" onclick=\"javascript:document.location=mw.config.get('wgScriptPath')+'/index.php?title=Special:PictureGameHome&picGameAction=renderPermalink&id={$gameid}'\">
			<h1>{$title_text} ({$totalVotes})</h1>

				<div class=\"picgame-gallery-thumbnailimg\">
					{$gallery_thumbnail_one}
					<p>{$imgOnePercent}%</p>
				</div>

				<div class=\"picgame-gallery-thumbnailimg\">
					{$gallery_thumbnail_two}
					<p>{$imgTwoPercent}%</p>
				</div>

				<div class=\"visualClear\"></div>
			</div>";

			if ( $x != 1 && $x % $per_row == 0 ) {
				$output .= '<div class="visualClear"></div>';
			}
			$x++;
		}

		$output .= '</div>';

		// Page Nav
		$numofpages = ceil( $total / $per_page );

		if ( $numofpages > 1 ) {
			$output .= '<div class="page-nav">';

			if ( $page > 1 ) {
				$output .= $linkRenderer->makeLink(
					$thisTitle,
					$this->msg( 'picturegame-prev' )->text(),
					[],
					[
						'picGameAction' => 'gallery',
						'page' => ( $page - 1 ),
						'type' => $type,
						'direction' => $direction
					]
				) . $this->msg( 'word-separator' )->escaped();
			}

			for ( $i = 1; $i <= $numofpages; $i++ ) {
				if ( $i == $page ) {
					$output .= ( $i . ' ' );
				} else {
					$output .= $linkRenderer->makeLink(
						$thisTitle,
						$i,
						[],
						[
							'picGameAction' => 'gallery',
							'page' => $i,
							'type' => $type,
							'direction' => $direction
						]
					) . $this->msg( 'word-separator' )->escaped();
				}
			}

			if ( $page < $numofpages ) {
				$output .= $this->msg( 'word-separator' )->escaped() . $linkRenderer->makeLink(
					$thisTitle,
					$this->msg( 'picturegame-next' )->text(),
					[],
					[
						'picGameAction' => 'gallery',
						'page' => ( $page + 1 ),
						'type' => $type,
						'direction' => $direction
					]
				);
			}

			$output .= '</div>';
		}

		$out->addHTML( $output );
	}

	/**
	 * Cast a user vote.
	 * The JS takes care of redirecting the page.
	 */
	function voteAndForward() {
		$out = $this->getOutput();
		$request = $this->getRequest();
		$user = $this->getUser();

		$out->setArticleBodyOnly( true );

		$key = $request->getVal( 'key' );
		$next_id = $request->getVal( 'nextid' );
		$id = $request->getInt( 'id' );
		$img = addslashes( $request->getVal( 'img' ) );

		$imgnum = ( $img == 0 ) ? 0 : 1;

		if ( $key != md5( $id . $this->SALT ) ) {
			$out->addHTML( $this->msg( 'picturegame-sysmsg-badkey' )->escaped() );
			return;
		}

		if ( strlen( $id ) > 0 && strlen( $img ) > 0 ) {
			$dbw = wfGetDB( DB_MASTER );

			// check if the user has voted on this already
			// @todo FIXME: in both cases we can just use selectField(), I think
			$res = $dbw->select(
				'picturegame_votes',
				[ 'COUNT(*) AS mycount' ],
				[
					'actor' => $user->getActorId(),
					'picid' => $id
				],
				__METHOD__
			);
			$row = $dbw->fetchObject( $res );

			// if they haven't, then check if the id exists and then insert the
			// vote
			if ( $row->mycount == 0 ) {
				$res = $dbw->select(
					'picturegame_images',
					[ 'COUNT(*) AS mycount' ],
					[ 'id' => $id ],
					__METHOD__
				);
				$row = $dbw->fetchObject( $res );

				if ( $row->mycount == 1 ) {
					$dbw->insert(
						'picturegame_votes',
						[
							'picid' => $id,
							'actor' => $user->getActorId(),
							'imgpicked' => $imgnum,
							'vote_date' => date( 'Y-m-d H:i:s' )
						],
						__METHOD__
					);

					$res = $dbw->update(
						'picturegame_images',
						[
							"img{$imgnum}_votes = img{$imgnum}_votes + 1",
							"heat = ABS( ( img0_votes / ( img0_votes+img1_votes) ) - ( img1_votes / ( img0_votes+img1_votes ) ) )"
						],
						[ 'id' => $id ],
						__METHOD__
					);

					// Increase social statistics
					$stats = new UserStatsTrack( $user->getId(), $user->getName() );
					$stats->incStatField( 'picturegame_vote' );
				}
			}
		}

		$out->addHTML( 'OK' );
	}

	/**
	 * Fetches the two images to be voted on
	 *
	 * @param $isPermalink Boolean: false by default
	 * @param $imgID Integer: is present if rendering a permalink
	 * @param $lastID Integer: optional; the last image ID the user saw
	 */
	function getImageDivs( $isPermalink = false, $imgID = -1, $lastID = -1 ) {
		global $wgExtensionAssetsPath, $wgUseEditButtonFloat, $wgUploadPath;

		$lang = $this->getLanguage();
		$out = $this->getOutput();
		$request = $this->getRequest();
		$user = $this->getUser();

		$totalVotes = 0;

		$dbr = wfGetDB( DB_REPLICA );

		// if imgID is -1 then we need some random IDs
		if ( $imgID == -1 ) {
			$query = $dbr->select(
				'picturegame_votes',
				'picid',
				[ 'actor' => $user->getActorId() ],
				__METHOD__
			);
			$picIds = [];
			foreach ( $query as $resultRow ) {
				$picIds[] = $resultRow->picid;
			}

			// If there are no picture games or only one game in the database,
			// the above query won't add anything to the picIds array.
			// Trying to implode an empty array...well, you'll get "id NOT IN ()"
			// which in turn is invalid SQL.
			$whereConds = [
				'flag <> ' . PictureGameHome::$FLAG_FLAGGED,
				"img1 <> ''",
				"img2 <> ''"
			];
			if ( !empty( $picIds ) ) {
				$whereConds[] = 'id NOT IN (' . implode( ',', $picIds ) . ')';
			}
			$res = $dbr->select(
				'picturegame_images',
				'*',
				$whereConds,
				__METHOD__,
				[ 'LIMIT' => 1 ]
			);
			$row = $dbr->fetchObject( $res );
			$imgID = isset( $row->id ) ? $row->id : 0;
		} else {
			$res = $dbr->select(
				'picturegame_images',
				'*',
				[
					'flag <> ' . PictureGameHome::$FLAG_FLAGGED,
					"img1 <> ''",
					"img2 <> ''",
					'id' => $imgID
				],
				__METHOD__
			);
			$row = $dbr->fetchObject( $res );
		}

		// Early return here in case if we have *nothing* in the database to
		// prevent fatals etc.
		if ( empty( $row ) ) {
			$out->setPageTitle( $this->msg( 'picturegame-nomoretitle' )->text() );
			// Wrap it in plainlinks to hide the external link icon since a
			// link to this wiki is not really an external link
			$out->wrapWikiMsg(
				"<div class=\"plainlinks\">$1</div>",
				// There is a slight difference between "no more games *for you* to play"
				// (=you've played 'em all) and "nothing at all in the database" (=there
				// is nothing to play)...
				( $lastID > -1 ? 'picturegame-empty-no-more' : 'picturegame-empty' )
			);
			return;
		}

		$actor = User::newFromActorId( $row->actor );
		$user_title = $actor->getUserPage();
		$repoGroup = MediaWikiServices::getInstance()->getRepoGroup();

		if ( $imgID ) {
			global $wgPictureGameID;
			$wgPictureGameID = $imgID;
			$toExclude = $dbr->select(
				'picturegame_votes',
				'picid',
				[ 'actor' => $user->getActorId() ],
				__METHOD__
			);

			$excludedImgIds = [];
			foreach ( $toExclude as $excludedRow ) {
				$excludedImgIds[] = $excludedRow->picid;
			}

			$next_id = 0;

			$whereConds = [
				"id <> {$imgID}",
				'flag != ' . PictureGameHome::$FLAG_FLAGGED,
				"img1 <> ''",
				"img2 <> ''"
			];
			if ( !empty( $excludedImgIds ) ) {
				// @todo Can we improve this further? i.e. push $imgID into $excludedImgIds
				// if this condition is hit and unset( $whereConds[0] ) or somesuch...
				// 'cause right now the SQL this ends up generating is not only funny but
				// also somewhat redundant because it doesn't make sense to have two separate
				// "NOT IN" clauses in the same query.
				$whereConds[] = 'id NOT IN (' . implode( ',', $excludedImgIds ) . ')';
			}

			$nextres = $dbr->select(
				'picturegame_images',
				'*',
				$whereConds,
				__METHOD__,
				[ 'LIMIT' => 1 ]
			);
			$nextrow = $dbr->fetchObject( $nextres );
			$next_id = ( isset( $nextrow->id ) ? $nextrow->id : 0 );

			if ( $next_id ) {
				$img_one = $repoGroup->findFile( $nextrow->img1 );
				if ( is_object( $img_one ) ) {
					$preload_thumb = $img_one->transform( [ 'width' => 256 ] );
				}
				if ( is_object( $preload_thumb ) ) {
					$preload_one_tag = $preload_thumb->toHtml();
				}

				$img_two = $repoGroup->findFile( $nextrow->img2 );
				if ( is_object( $img_two ) ) {
					$preload_thumb = $img_two->transform( [ 'width' => 256 ] );
				}
				if ( is_object( $preload_thumb ) ) {
					$preload_two_tag = $preload_thumb->toHtml();
				}

				$preload = $preload_one_tag . $preload_two_tag;
			}
		}

		if ( ( $imgID < 0 ) || !is_numeric( $imgID ) || is_null( $row ) ) {
			$out->setPageTitle( $this->msg( 'picturegame-nomoretitle' )->plain() );

			// Wrap it in plainlinks to hide the external link icon since a
			// link to this wiki is not really an external link
			$output = '<div class="plainlinks">' .
				$this->msg( 'picturegame-no-more' )->parse() .
			'</div>';

			return $output;
		}

		// snag the images to vote on and grab some thumbnails
		// modify this query so that if the current user has voted on this
		// image pair don't show it again
		$imgOneCount = $row->img0_votes;
		$imgTwoCount = $row->img1_votes;

		$user_name = $lang->truncateForVisual( $actor->getName(), 20 );

		$title_text_length = strlen( $row->title );
		$title_text_space = stripos( $row->title, ' ' );

		if ( ( $title_text_space == false || $title_text_space >= '48' ) && $title_text_length > 48 ) {
			$title_text = substr( $row->title, 0, 48 ) . '<br />' .
				substr( $row->title, 48, 48 );
		} elseif ( $title_text_length > 48 && substr( $row->title, 48, 1 ) == ' ' ) {
			$title_text = substr( $row->title, 0, 48 ) . '<br />' .
				substr( $row->title, 48, 48 );
		} elseif ( $title_text_length > 48 && substr( $row->title, 48, 1 ) != ' ' ) {
			$title_text_lastspace = strrpos( substr( $row->title, 0, 48 ), ' ' );
			$title_text = substr( $row->title, 0, $title_text_lastspace ) .
				'<br />' . substr( $row->title, $title_text_lastspace, 30 );
		} else {
			$title_text = $row->title;
		}

		$x = 1;
		$img1_caption_text = '';
		$img1caption_array = str_split( $row->img1_caption );
		foreach ( $img1caption_array as $img1_character ) {
			if ( $x % 30 == 0 ) {
				$img1_caption_text .= $img1_character . '<br />';
			} else {
				$img1_caption_text .= $img1_character;
			}
			$x++;
		}

		$x = 1;
		$img2_caption_text = '';
		$img1caption_array = str_split( $row->img2_caption );
		foreach( $img1caption_array as $img2_character ) {
			if( $x % 30 == 0 ) {
				$img2_caption_text .= $img2_character . '<br />';
			} else {
				$img2_caption_text .= $img2_character;
			}
			$x++;
		}

		// I assume MediaWiki does some caching with these functions
		$img_one = $repoGroup->findFile( $row->img1 );
		$thumb_one_url = '';
		if ( is_object( $img_one ) ) {
			$thumb_one_url = $img_one->createThumb( 256 );
			$imageOneWidth = $img_one->getWidth();
		}
		//$imgOne = '<img width="' . ( $imageOneWidth >= 256 ? 256 : $imageOneWidth ) . '" alt="" src="' . $thumb_one_url . ' "/>';
		//$imageOneWidth = ( $imageOneWidth >= 256 ? 256 : $imageOneWidth );
		//$imageOneWidth += 10;
		$imgOne = '<img style="width:100%;" alt="" src="' . $thumb_one_url . ' "/>';

		$img_two = $repoGroup->findFile( $row->img2 );
		$thumb_two_url = '';
		if ( is_object( $img_two ) ) {
			$thumb_two_url = $img_two->createThumb( 256 );
			$imageTwoWidth = $img_two->getWidth();
		}
		//$imgTwo = '<img width="' . ( $imageTwoWidth >= 256 ? 256 : $imageTwoWidth ) . '" alt="" src="' . $thumb_two_url . ' "/>';
		//$imageTwoWidth = ( $imageTwoWidth >= 256 ? 256 : $imageTwoWidth );
		//$imageTwoWidth += 10;
		$imgTwo = '<img style="width:100%;" alt="" src="' . $thumb_two_url . ' " />';

		$title = $title_text;
		$img1_caption = $img1_caption_text;
		$img2_caption = $img2_caption_text;

		$vote_one_tag = '';
		$vote_two_tag = '';
		$imgOnePercent = '';
		$barOneWidth = '';
		$imgTwoPercent = '';
		$barTwoWidth = '';
		$permalinkJS = '';

		$isShowVotes = false;
		if ( $lastID > 0 ) {
			$res = $dbr->select(
				'picturegame_images',
				'*',
				[
					'flag <> ' . PictureGameHome::$FLAG_FLAGGED,
					'id' => $lastID
				],
				__METHOD__
			);
			$row = $dbr->fetchObject( $res );

			if ( $row ) {
				$img_one = $repoGroup->findFile( $row->img1 );
				$img_two = $repoGroup->findFile( $row->img2 );
				$imgOneCount = $row->img0_votes;
				$imgTwoCount = $row->img1_votes;
				$isShowVotes = true;
			}
		}

		if ( $isPermalink || $isShowVotes ) {
			if ( is_object( $img_one ) ) {
				$vote_one_thumb = $img_one->transform( [ 'width' => 40 ] );
			}
			if ( is_object( $vote_one_thumb ) ) {
				$vote_one_tag = $vote_one_thumb->toHtml();
			}

			if ( is_object( $img_two ) ) {
				$vote_two_thumb = $img_two->transform( [ 'width' => 40 ] );
			}
			if ( is_object( $vote_two_thumb ) ) {
				$vote_two_tag = $vote_two_thumb->toHtml();
			}

			$totalVotes = $imgOneCount + $imgTwoCount;

			if ( $imgOneCount == 0 ) {
				$imgOnePercent = 0;
				$barOneWidth = 0;
			} else {
				$imgOnePercent = floor( $imgOneCount / $totalVotes  * 100 );
				$barOneWidth = floor( 200 * ( $imgOneCount / $totalVotes ) );
			}

			if ( $imgTwoCount == 0 ) {
				$imgTwoPercent = 0;
				$barTwoWidth = 0;
			} else {
				$imgTwoPercent = 100 - $imgOnePercent;
				$barTwoWidth = floor( 200 * ( $imgTwoCount / $totalVotes ) );
			}

			$permalinkJS = "document.getElementById( 'voteStats' ).style.display = 'inline';
			document.getElementById( 'voteStats' ).style.visibility = 'visible';";
		}

		$output = '';
		// set the page title
		//$out->setPageTitle( $title_text );

		// figure out if the user is an admin / the creator
		$editlinks = '';
		if ( $user->isAllowed( 'picturegameadmin' ) ) {
			// If the user can edit, throw in some links
			$editlinks = ' - <a href="' . htmlspecialchars( $this->getPageTitle()->getFullURL(
				'picGameAction=adminPanel' ) ) . '"> ' .
				$this->msg( 'picturegame-adminpanel' )->escaped() .
			'</a> - <a class="picgame-protect-link" href="javascript:void(0);"> '
				. $this->msg( 'picturegame-protectimages' )->escaped() . '</a>';
		}

		$createLink = '';
		// Only registered users can create new picture games
		if ( $user->isLoggedIn() ) {
			$createLink = '
			<div class="create-link">
				<a href="' . htmlspecialchars( $this->getPageTitle()->getFullURL( 'picGameAction=startCreate' ) ) . '">
					<img src="' . $wgExtensionAssetsPath . '/SocialProfile/images/addIcon.gif" border="0" alt="" />'
					. $this->msg( 'picturegame-createlink' )->escaped() .
				'</a>
			</div>';
		}
		$editLink = $flagLink = '';
		if ( $user->isLoggedIn() ) {
			if ( $user->isAllowed( 'picturegameadmin' ) && $wgUseEditButtonFloat == true ) {
				$editLink .= '<div class="edit-menu-pic-game">
					<div class="edit-button-pic-game">
						<img src="' . $wgExtensionAssetsPath . '/SocialProfile/images/editIcon.gif" alt="" />
						<a class="picgame-edit-link" href="#">' . $this->msg( 'edit' )->escaped() . '</a>
					</div>
				</div>';
			}
			$flagLink .= "<a class=\"picgame-flag-link\" href=\"#\">"
				. $this->msg( 'picturegame-reportimages' )->escaped() . " </a> - ";
		}

		$id = User::idFromName( $user_title->getText() );
		$avatar = new wAvatar( $id, 'l' );
		$avatarID = $avatar->getAvatarImage();
		$stats = new UserStats( $id, $user_title->getText() );
		$stats_data = $stats->getUserStats();
		$preload = '';

		$out->setHTMLTitle( $this->msg( 'pagetitle', $title )->text() );

		$next_id = ( isset( $next_id ) ? $next_id : 0 );

		$formattedVoteCount = $lang->formatNum( $stats_data['votes'] );
		$formattedEditCount = $lang->formatNum( $stats_data['edits'] );
		$formattedCommentCount = $lang->formatNum( $stats_data['comments'] );

		$output .= "
		<script type=\"text/javascript\">var next_id = \"{$next_id}\";</script>
		{$editLink}
		<div class=\"editDiv\" id=\"editDiv\" style=\"display: none\"> </div>


				<div class=\"serverMessages\" id=\"serverMessages\"></div>

				<div class=\"imgContent\" id=\"imgContent\">
					<div class=\"imgTitle\" id=\"imgTitle\">" . $title . "</div>
					<div class=\"imgContainer\" id=\"imgContainerOne\" style=\"width:45%;\">
						<div class=\"imgCaption\" id=\"imgOneCaption\">" . $img1_caption . "</div>
						<div class=\"imageOne\" id=\"imageOne\" style=\"padding:5px;\">
							" . $imgOne . "	</div>
					</div>

					<div class=\"imgContainer\" id=\"imgContainerTwo\" style=\"width:45%;\">
						<div class=\"imgCaption\" id=\"imgTwoCaption\">" . $img2_caption . "</div>
						<div class=\"imageTwo\" id=\"imageTwo\" style=\"padding:5px;\">
						" . $imgTwo . "	</div>
					</div>
					<div class=\"visualClear\"></div>

					<div class=\"pic-game-navigation\">
						<ul>
							<li id=\"backButton\" style=\"display:" . ( $lastID > 0 ? 'block' : 'none' ) . "\">
								<a href=\"javascript:window.parent.document.location='" .
									htmlspecialchars( $this->getPageTitle()->getFullURL( 'picGameAction=renderPermalink' ) ) .
									"&id=' + document.getElementById('lastid').value\">"
									. $this->msg( 'picturegame-backbutton' )->escaped() .
								"</a>
							</li>
							<li id=\"skipButton\" style=\"display:" . ( $next_id > 0 ? 'block' : 'none' ) . "\">
								<a href=\"" . htmlspecialchars( $this->getPageTitle()->getFullURL( 'picGameAction=startGame' ) ) . '">'
									. $this->msg( 'picturegame-skipbutton' )->escaped() .
								'</a>
							</li>
						</ul>
					</div>

					<form id="picGameVote" name="picGameVote" method="post" action="' .
						htmlspecialchars( $this->getPageTitle()->getFullURL( 'picGameAction=castVote' ) ) . "\">
						<input id=\"key\" name=\"key\" type=\"hidden\" value=\"" . md5( $imgID . $this->SALT ) . "\" />
						<input id=\"id\" name=\"id\" type=\"hidden\" value=\"" . $imgID . "\" />
						<input id=\"lastid\" name=\"lastid\" type=\"hidden\" value=\"" . $lastID . "\" />
						<input id=\"nextid\" name=\"nextid\" type=\"hidden\" value=\"" . $next_id . "\" />

						<input id=\"img\" name=\"img\" type=\"hidden\" value=\"\" />
					</form>
			</div>
			<div class=\"other-info\">
				{$createLink}
				<div class=\"credit-box\" id=\"creditBox\">
					<h1>" . $this->msg( 'picturegame-submittedby' )->escaped() . "</h1>
					<div class=\"submitted-by-image\">
						<a href=\"{$user_title->getFullURL()}\">
							<img src=\"{$wgUploadPath}/avatars/{$avatarID}\" style=\"border:1px solid #d7dee8; width:50px; height:50px;\" alt=\"\" />
						</a>
					</div>
					<div class=\"submitted-by-user\">
						<a href=\"{$user_title->getFullURL()}\">{$user_name}</a>
						<ul>
							<li>
								<img src=\"{$wgExtensionAssetsPath}/SocialProfile/images/voteIcon.gif\" border=\"0\" alt=\"\" />
								{$formattedVoteCount}
							</li>
							<li>
								<img src=\"{$wgExtensionAssetsPath}/SocialProfile/images/editIcon.gif\" border=\"0\" alt=\"\" />
								{$formattedEditCount}
							</li>
							<li>
								<img src=\"{$wgExtensionAssetsPath}/SocialProfile/images/commentsIcon.gif\" border=\"0\" alt=\"\" />
								{$formattedCommentCount}
							</li>
						</ul>
					</div>
					<div class=\"visualClear\"></div>
				</div>

				<div class=\"voteStats\" id=\"voteStats\" style=\"display:none\">
					<div id=\"vote-stats-text\">
						<h1>" . $this->msg( 'picturegame-previousgame' )->escaped() . " ({$totalVotes})</h1></div>
					<div class=\"vote-bar\">
						<span class=\"vote-thumbnail\" id=\"one-vote-thumbnail\">{$vote_one_tag}</span>
						<span class=\"vote-percent\" id=\"one-vote-percent\">{$imgOnePercent}%</span>
						<span class=\"vote-blue\">
							<img src=\"{$wgExtensionAssetsPath}/SocialProfile/images/vote-bar-1.gif\" id=\"one-vote-width\" border=\"0\" style=\"width:{$barOneWidth}px;height:11px;\" alt=\"\" />
						</span>
					</div>
					<div class=\"vote-bar\">
						<span class=\"vote-thumbnail\" id=\"two-vote-thumbnail\">{$vote_two_tag}</span>
						<span class=\"vote-percent\" id=\"two-vote-percent\">{$imgTwoPercent}%</span>
						<span class=\"vote-red\">
							<img src=\"{$wgExtensionAssetsPath}/SocialProfile/images/vote-bar-2.gif\" id=\"two-vote-width\" border=\"0\" style=\"width:{$barTwoWidth}px;height:11px;\" alt=\"\" />
						</span>
					</div>
				</div>
				<div class=\"utilityButtons\" id=\"utilityButtons\">" . $flagLink .
					"<a class=\"picgame-permalink\" href=\"#\">"
						. $this->msg( 'picturegame-permalink' )->escaped() .
					'</a>'
				. $editlinks . "
				</div>

			</div>

			<div class=\"visualClear\"></div>

			<script language=\"javascript\">{$permalinkJS}</script>

		<div id=\"preload\" style=\"display:none\">
			{$preload}
			<object classid=\"clsid:D27CDB6E-AE6D-11cf-96B8-444553540000\" codebase=\"http://download.macromedia.com/pub/shockwave/cabs/flash/swflash.cab#version=9,0,28,0\" width=\"75\" height=\"75\" title=\"hourglass\">
				<param name=\"movie\" value=\"" . $wgExtensionAssetsPath . "/SocialProfile/images/ajax-loading.swf\" />
				<param name=\"quality\" value=\"high\" />
				<param name=\"wmode\" value=\"transparent\" />
				<param name=\"bgcolor\" value=\"#ffffff\" />
				<embed src=\"" . $wgExtensionAssetsPath . "/SocialProfile/images/ajax-loading.swf\" quality=\"high\" wmode=\"transparent\" bgcolor=\"#ffffff\" pluginspage=\"http://www.adobe.com/shockwave/download/download.cgi?P1_Prod_Version=ShockwaveFlash\" type=\"application/x-shockwave-flash\" width=\"100\" height=\"100\"></embed>
			 </object>
		</div>";

		// fix syntax coloring
		return $output;
	}

	/**
	 * Insert information about the new picture game into the database,
	 * increase social statistics, purge memcached entries and redirect the
	 * user to the newly-created picture game.
	 */
	function createGame() {
		$request = $this->getRequest();
		$user = $this->getUser();

		// @todo FIXME: as per Tim: https://www.mediawiki.org/wiki/Special:Code/MediaWiki/59183#c4709
		$title = addslashes( $request->getVal( 'picGameTitle' ) );

		$img1 = addslashes( $request->getVal( 'picOneURL' ) );
		$img2 = addslashes( $request->getVal( 'picTwoURL' ) );
		$img1_caption = addslashes( $request->getVal( 'picOneDesc' ) );
		$img2_caption = addslashes( $request->getVal( 'picTwoDesc' ) );

		$key = $request->getVal( 'key' );
		$chain = $request->getVal( 'chain' );
		$id = -1;

		$dbr = wfGetDB( DB_MASTER );

		// make sure no one is trying to do bad things
		if ( $key == md5( $chain . $this->SALT ) ) {
			$res = $dbr->select(
				'picturegame_images',
				'COUNT(*) AS mycount',
				// Resulting SQL looks like ...WHERE (img1 = $img1 OR img2 = $img1) AND
				// (img1 = $img2 OR $img2 = $img2)
				[
					$dbr->makeList( [ 'img1' => $img1, 'img2' => $img1 ], LIST_OR ),
					$dbr->makeList( [ 'img1' => $img2, 'img2' => $img1 ], LIST_OR ),
				],
				__METHOD__,
				[ 'GROUP BY' => 'id' ]
			);
			$row = $dbr->fetchObject( $res );

			// if these image pairs don't exist, insert them
			if ( isset( $row ) && isset( $row->mycount ) && $row->mycount == 0 ) {
				$dbr->insert(
					'picturegame_images',
					[
						'actor' => $user->getActorId(),
						'img1' => $img1,
						'img2' => $img2,
						'title' => $title,
						'img1_caption' => $img1_caption,
						'img2_caption' => $img2_caption,
						'pg_date' => date( 'Y-m-d H:i:s' )
					],
					__METHOD__
				);

				// @todo FIXME: also log at least $title
				$this->addLogEntry( 'create', $id );

				$id = $dbr->selectField(
					'picturegame_images',
					'MAX(id) AS maxid',
					[],
					__METHOD__
				);

				// Increase social statistics
				$stats = new UserStatsTrack( $user->getId(), $user->getName() );
				$stats->incStatField( 'picturegame_created' );

				// Purge memcached
				global $wgMemc;
				$key = $wgMemc->makeKey( 'user', 'profile', 'picgame', $user->getId() );
				$wgMemc->delete( $key );
			}
		}

		header( "Location: ?title=Special:PictureGameHome&picGameAction=startGame&id={$id}" );
	}

	// Renders the initial page of the game
	function renderPictureGame() {
		$out = $this->getOutput();
		$request = $this->getRequest();

		$permalinkID = $request->getInt( 'id', -1 );
		$lastId = $request->getInt( 'lastid', -1 );

		$isPermalink = false;
		$permalinkError = false;

		$dbr = wfGetDB( DB_MASTER );
		if ( $permalinkID > 0 ) {
			$isPermalink = true;

			$mycount = (int)$dbr->selectField(
				'picturegame_images',
				'COUNT(*) AS mycount',
				[
					'flag = ' . PictureGameHome::$FLAG_NONE .
						' OR flag = ' . PictureGameHome::$FLAG_PROTECT,
					'id' => $permalinkID
				],
				__METHOD__
			);

			if ( $mycount == 0 ) {
				$out->addModuleStyles( 'ext.pictureGame.mainGame' );
				$output = '
					<div class="picgame-container" id="picgame-container">
						<p>' . $this->msg( 'picturegame-permalinkflagged' )->escaped() . '</p>
						<p><input type="button" class="site-button" value="' .
							$this->msg( 'picturegame-buttonplaygame' )->escaped() .
							'" onclick="window.location=\'' .
							htmlspecialchars( $this->getPageTitle()->getFullURL( 'picGameAction=startGame' ) ) . '\'" />
						</p>
					</div>';
				$out->addHTML( $output );
				return;
			}
		}

		$out->addModuleStyles( 'ext.pictureGame.mainGame' );

		$output = '<div class="picgame-container" id="picgame-container">' .
			$this->getImageDivs( $isPermalink, $permalinkID, $lastId ) .
		'</div>';

		$out->addHTML( $output );
	}

	/**
	 * Shows the initial page that prompts the image upload.
	 */
	function showHomePage() {
		global $wgExtensionAssetsPath;

		$out = $this->getOutput();
		$request = $this->getRequest();
		$user = $this->getUser();

		// You need to be logged in to create a new picture game (because
		// usually only registered users can upload files)
		if ( !$user->isLoggedIn() ) {
			$out->setPageTitle( $this->msg( 'picturegame-creategametitle' ) );
			$output = $this->msg( 'picturegame-creategamenotloggedin' )->escaped();
			$output .= "<p>
				<input type=\"button\" class=\"site-button\" onclick=\"window.location='" .
					htmlspecialchars( SpecialPage::getTitleFor( 'Userlogin', 'signup' )->getFullURL() ) .
					"'\" value=\"" . $this->msg( 'picturegame-signup' )->escaped() . "\" />
				<input type=\"button\" class=\"site-button\" onclick=\"window.location='" .
					htmlspecialchars( SpecialPage::getTitleFor( 'Userlogin' )->getFullURL() ) .
					"'\" value=\"" . $this->msg( 'picturegame-login' )->escaped() . "\" />
			</p>";
			$out->addHTML( $output );
			return;
		}

		/**
		 * Create Picture Game Thresholds based on User Stats
		 */
		global $wgCreatePictureGameThresholds;
		if ( is_array( $wgCreatePictureGameThresholds ) && count( $wgCreatePictureGameThresholds ) > 0 ) {
			$can_create = true;

			$stats = new UserStats( $user->getId(), $user->getName() );
			$stats_data = $stats->getUserStats();

			$threshold_reason = '';
			foreach ( $wgCreatePictureGameThresholds as $field => $threshold ) {
				if ( $stats_data[$field] < $threshold ) {
					$can_create = false;
					$threshold_reason .= ( ( $threshold_reason ) ? ', ' : '' ) . "$threshold $field";
				}
			}

			if ( $can_create == false ) {
				$out->setPageTitle( $this->msg( 'picturegame-create-threshold-title' )->plain() );
				$out->addHTML( $this->msg( 'picturegame-create-threshold-reason', $threshold_reason )->escaped() );
				return '';
			}
		}

		// Show a link to the admin panel for picture game admins
		if ( $user->isAllowed( 'picturegameadmin' ) ) {
			$adminlink = '<a href="' . htmlspecialchars( $this->getPageTitle()->getFullURL( 'picGameAction=adminPanel' ) ) . '"> ' .
				$this->msg( 'picturegame-adminpanel' )->escaped() . ' </a>';
		}

		$dbr = wfGetDB( DB_MASTER );

		$excludedIds = $dbr->select(
			'picturegame_votes',
			'picid',
			[ 'actor' => $user->getActorId() ],
			__METHOD__
		);

		$excluded = [];
		foreach ( $excludedIds as $excludedId ) {
			$excluded[] = $excludedId->picid;
		}

		$canSkip = false;
		if ( !empty( $excluded ) ) {
			$myCount = (int)$dbr->selectField(
				'picturegame_images',
				'COUNT(*) AS mycount',
				[
					'id NOT IN(' . implode( ',', $excluded  ) . ')',
					'flag != ' . PictureGameHome::$FLAG_FLAGGED,
					"img1 <> ''",
					"img2 <> ''"
				],
				__METHOD__
			);
			if ( $myCount > 0 ) {
				$canSkip = true;
			}
		}

		// used for the key
		$now = time();

		$out->setHTMLTitle( $this->msg( 'pagetitle', $this->msg( 'picturegame-creategametitle' )->text() )->text() );
		$out->setPageTitle( $this->msg( 'picturegame-creategametitle' )->text() );
		$out->addModuleStyles( 'ext.pictureGame.startGame' );

		$output = "\t\t" . '<div class="pick-game-welcome-message">';
		$output .= $this->msg( 'picturegame-creategamewelcome' )->escaped();
		$output .= '<br />

			<div id="skipButton" class="startButton">';
		$play_button_text = $this->msg( 'picturegame-creategameplayinstead' )->escaped();
		$skipButton = '';
		if ( $canSkip ) {
			$skipButton = "<input class=\"site-button\" type=\"button\" id=\"skip-button\" value=\"{$play_button_text}\"/>";
		}
		$output .= $skipButton .
				'</div>
			</div>

			<div class="uploadLeft">
				<div id="uploadTitle" class="uploadTitle">
					<form id="picGamePlay" name="picGamePlay" method="post" action="' .
						htmlspecialchars( $this->getPageTitle()->getFullURL( 'picGameAction=createGame' ) ) . '">
						<h1>' . $this->msg( 'picturegame-creategamegametitle' )->escaped() . '</h1>
						<div class="picgame-errors" id="picgame-errors"></div>
						<p>
							<input name="picGameTitle" id="picGameTitle" type="text" value="" size="40" />
						</p>
						<input name="picOneURL" id="picOneURL" type="hidden" value="" />
						<input name="picTwoURL" id="picTwoURL" type="hidden" value="" />';
						/*<input name=\"picOneDesc\" id=\"picOneDesc\" type=\"hidden\" value=\"\" />
						<input name=\"picTwoDesc\" id=\"picTwoDesc\" type=\"hidden\" value=\"\" />*/
		$uploadObj = SpecialPage::getTitleFor( 'PictureGameAjaxUpload' );
		$output .= '<input name="key" type="hidden" value="' . md5( $now . $this->SALT ) . '" />
						<input name="chain" type="hidden" value="' . $now . '" />
					</form>
				</div>

				<div class="content">
					<div id="uploadImageForms" class="uploadImage">

						<div id="imageOneUpload" class="imageOneUpload">
							<h1>' . $this->msg( 'picturegame-createeditfirstimage' )->escaped() . '</h1>
							<!--Caption:<br /><input name="picOneDesc" id="picOneDesc" type="text" value="" /><br />-->
							<div id="imageOneUploadError"></div>
							<div id="imageOneLoadingImg" class="loadingImg" style="display:none">
								<img src="' . $wgExtensionAssetsPath . '/SocialProfile/images/ajax-loader-white.gif" alt="" />
							</div>
							<div id="imageOne" class="imageOne" style="display:none;"></div>
							<iframe class="imageOneUpload-frame" scrolling="no" frameborder="0" width="400" id="imageOneUpload-frame" src="' .
								htmlspecialchars( $uploadObj->getFullURL( 'wpCallbackPrefix=imageOne_' ) ) . '"></iframe>
						</div>

						<div id="imageTwoUpload" class="imageTwoUpload">
							<h1>' . $this->msg( 'picturegame-createeditsecondimage' )->escaped() . '</h1>
							<!--Caption:<br /><input name="picTwoDesc" id="picTwoDesc" type="text" value="" /><br />-->
							<div id="imageTwoUploadError"></div>
							<div id="imageTwoLoadingImg" class="loadingImg" style="display:none">
								<img src="' . $wgExtensionAssetsPath . '/SocialProfile/images/ajax-loader-white.gif" alt="" />
							</div>
							<div id="imageTwo" class="imageTwo" style="display:none;"></div>
							<iframe id="imageTwoUpload-frame" scrolling="no" frameborder="0" width="510" src="' .
								htmlspecialchars( $uploadObj->getFullURL( 'wpCallbackPrefix=imageTwo_' ) ) . '"></iframe>
						</div>

						<div class="visualClear"></div>
					</div>
				</div>
			</div>

			<div id="startButton" class="startButton" style="display: none;">
				<input type="button" class="site-button" value="' . $this->msg( 'picturegame-creategamecreateplay' )->escaped() . '" />
			</div>';

		$out->addHTML( $output );
	}

	/**
	 * Adds a log entry to Special:Log/picturegame.
	 *
	 * @param string $action Log action, i.e. flag, unflag, create, delete, ...
	 * @param int $id Picture game ID
	 * @param string $reason Reason for flagging (only when $action = 'flag')
	 */
	private function addLogEntry( $action, $id, $reason = '' ) {
		$logEntry = new ManualLogEntry( 'picturegame', $action );
		$logEntry->setPerformer( $this->getUser() );
		$logEntry->setTarget( $this->getPageTitle() );
		if ( isset( $reason ) && $reason ) {
			$logEntry->setComment( $reason );
		}
		$logEntry->setParameters( [
			'4::id' => $id
		] );
		$logId = $logEntry->insert();
		$logEntry->publish( $logId );
	}
}
