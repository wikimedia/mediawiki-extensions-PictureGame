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
	 * @param $par Mixed: parameter passed to the page or null
	 */
	public function execute( $par ) {
		$out = $this->getOutput();
		$request = $this->getRequest();
		$user = $this->getUser();

		// Is the database locked?
		if( wfReadOnly() ) {
			$out->readOnlyPage();
			return false;
		}

		// https://phabricator.wikimedia.org/T155405
		// Throws error message when SocialProfile extension is not installed
		if( !class_exists( 'UserStats' ) ) {
			throw new ErrorPageError( 'picturegame-error-socialprofile-title', 'picturegame-error-socialprofile' );
		}

		// Blocked through Special:Block? No access for you either!
		if( $user->isBlocked() ) {
			throw new UserBlockedError( $user->getBlock() );
		}

		// Salt as you like
		$this->SALT = md5( $user->getName() );

		// Add the main JS file
		$out->addModules( 'ext.pictureGame' );

		// What should we do?
		$action = $request->getVal( 'picGameAction' );

		switch( $action ) {
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
				if( $user->isLoggedIn() && $user->isAllowed( 'picturegameadmin' ) ) {
					$this->editPanel();
				} else {
					$this->showHomePage();
				}
				break;
			case 'completeEdit':
				if( $user->isLoggedIn() && $user->isAllowed( 'picturegameadmin' ) ) {
					$this->completeEdit();
				} else {
					$this->showHomePage();
				}
				break;
			case 'adminPanel':
				if( $user->isLoggedIn() && $user->isAllowed( 'picturegameadmin' ) ) {
					$this->adminPanel();
				} else {
					$this->showHomePage();
				}
				break;
			case 'adminPanelUnflag':
				if( $user->isLoggedIn() && $user->isAllowed( 'picturegameadmin' ) ) {
					$this->adminPanelUnflag();
				} else {
					$this->showHomePage();
				}
				break;
			case 'adminPanelDelete':
				if( $user->isLoggedIn() && $user->isAllowed( 'picturegameadmin' ) ) {
					$this->adminPanelDelete();
				} else {
					$this->showHomePage();
				}
				break;
			case 'protectImages':
				if( $user->isLoggedIn() && $user->isAllowed( 'picturegameadmin' ) ) {
					$this->protectImages();
				} else {
					echo $this->msg( 'picturegame-sysmsg-unauthorized' )->text();
				}
				break;
			case 'unprotectImages':
				if( $user->isLoggedIn() && $user->isAllowed( 'picturegameadmin' ) ) {
					$this->unprotectImages();
				} else {
					$this->showHomePage();
				}
				break;
			case 'startCreate':
				if( $user->isBlocked() ) {
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

		if(
			$key != md5( $now . $this->SALT ) ||
			( !$user->isLoggedIn() || !$user->isAllowed( 'picturegameadmin' ) )
		)
		{
			//echo $this->msg( 'picturegame-sysmsg-badkey' )->text();
			//return;
		}

		$dbw = wfGetDB( DB_MASTER );
		$dbw->delete( 'picturegame_images', array( 'id' => $id ), __METHOD__ );
		$dbw->commit( __METHOD__ );

		global $wgMemc;
		$key = wfMemcKey( 'user', 'profile', 'picgame', $user->getID() );
		$wgMemc->delete( $key );

		/* Pop the images out of MediaWiki also */
		//$img_one = wfFindFile( $image1 );
		$oneResult = $twoResult = false;
		if( $image1 ) {
			$img_one = Title::makeTitle( NS_FILE, $image1 );
			$article = new Article( $img_one );
			$oneResult = $article->doDeleteArticle( 'Picture Game image 1 Delete' );
		}

		if( $image2 ) {
			$img_two = Title::makeTitle( NS_FILE, $image2 );
			$article = new Article( $img_two );
			$twoResult = $article->doDeleteArticle( 'Picture Game image 2 Delete' );
		}

		if( $oneResult && $twoResult ) {
			echo $this->msg( 'picturegame-sysmsg-successfuldelete' )->text();
			return;
		}

		if( $oneResult ) {
			echo $this->msg( 'picturegame-sysmsg-unsuccessfuldelete', $image1 )->text();
		}
		if( $twoResult ) {
			echo $this->msg( 'picturegame-sysmsg-unsuccessfuldelete', $image2 )->text();
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

		if(
			$key != md5( $now . $this->SALT ) ||
			( !$user->isLoggedIn() || !$user->isAllowed( 'picturegameadmin' ) )
		) {
			echo $this->msg( 'picturegame-sysmsg-badkey' )->text();
			return;
		}

		$dbw = wfGetDB( DB_MASTER );
		$dbw->update(
			'picturegame_images',
			array( 'flag' => PictureGameHome::$FLAG_NONE ),
			array( 'id' => $id ),
			__METHOD__
		);

		$out->clearHTML();
		echo $this->msg( 'picturegame-sysmsg-unflag' )->text();
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

		if( $key != md5( $id . $this->SALT ) ) {
			$out->addHTML( '<h3>' . $this->msg( 'picturegame-sysmsg-badkey' )->plain() . '</h3>' );
			return;
		}

		$dbw = wfGetDB( DB_MASTER );
		$dbw->update(
			'picturegame_images',
			array(
				'title' => $title,
				'img1_caption' => $imgOneCaption,
				'img2_caption' => $imgTwoCaption
			),
			array( 'id' => $id ),
			__METHOD__
		);

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
			array( 'id' => $id ),
			__METHOD__
		);

		$row = $dbw->fetchObject( $res );
		if ( empty( $row ) ) {
			$out->addHTML( $this->msg( 'picturegame-nothing-to-edit' )->text() );
			return;
		}

		$imgID = $row->id;
		$user_name = $lang->truncate( $row->username, 20 );

		$title_text = $row->title;
		$img1_caption_text = $row->img1_caption;
		$img2_caption_text = $row->img2_caption;

		// I assume MediaWiki does some caching with these functions?
		$img_one = wfFindFile( $row->img1 );
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

		$img_two = wfFindFile( $row->img2 );
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

		$id = User::idFromName( $row->username );
		$avatar = new wAvatar( $id, 'l' );
		$avatarID = $avatar->getAvatarImage();
		$stats = new UserStats( $id, $row->username );
		$stats_data = $stats->getUserStats();

		if ( $wgRightsText ) {
			$copywarnMsg = 'copyrightwarning';
			$copywarnMsgParams = array(
				'[[' . $this->msg( 'copyrightpage' )->inContentLanguage()->plain() . ']]',
				$wgRightsText
			);
		} else {
			$copywarnMsg = 'copyrightwarning2';
			$copywarnMsgParams = array(
				'[[' . $this->msg( 'copyrightpage' )->inContentLanguage()->plain() . ']]'
			);
		}

		$usrTitleObj = Title::makeTitle( NS_USER, $row->username );
		$imgPath = $wgExtensionAssetsPath . '/SocialProfile/images';

		$formattedVoteCount = $lang->formatNum( $stats_data['votes'] );
		$formattedEditCount = $lang->formatNum( $stats_data['edits'] );
		$formattedCommentCount = $lang->formatNum( $stats_data['comments'] );
		$output .= '<div id="edit-container" class="edit-container">
			<form id="picGameVote" name="picGameVote" method="post" action="' .
			htmlspecialchars( $this->getPageTitle()->getFullURL( 'picGameAction=completeEdit' ) ) . '">
			<div id="edit-textboxes" class="edit-textboxes">

				<div class="credit-box-edit" id="creditBox">
					<h1>' . $this->msg( 'picturegame-submittedby' )->plain() . '</h1>
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


				<h1>" . $this->msg( 'picturegame-editgamegametitle' )->plain() . "</h1>
				<p><input name=\"newTitle\" id=\"newTitle\" type=\"text\" value=\"{$title_text}\" size=\"40\"/></p>
					<input id=\"key\" name=\"key\" type=\"hidden\" value=\"" . md5( $imgID . $this->SALT ) . "\" />
					<input id=\"id\" name=\"id\" type=\"hidden\" value=\"{$imgID}\" />

			</div>
			<div class=\"edit-images-container\">
				<div id=\"edit-images\" class=\"edit-images\">
					<div id=\"edit-image-one\" class=\"edit-image-one\">
						<h1>" . $this->msg( 'picturegame-createeditfirstimage' )->plain() . "</h1>
						<p><input name=\"imgOneCaption\" id=\"imgOneCaption\" type=\"text\" value=\"{$img1_caption_text}\" /></p>
						<p id=\"image-one-tag\">{$imgOne}</p>
						<p><a class=\"picgame-upload-link-1\" href=\"javascript:void(0);\" data-img-one-name=\"{$imgOneName}\">" .
							$this->msg( 'picturegame-editgameuploadtext' )->plain() . '</a></p>
					</div>

					<div id="edit-image-two" class="edit-image-one">
						<h1>' . $this->msg( 'picturegame-createeditsecondimage' )->plain() . "</h1>
						<p><input name=\"imgTwoCaption\" id=\"imgTwoCaption\" type=\"text\" value=\"{$img2_caption_text}\" /></p>
						<p id=\"image-two-tag\">{$imgTwo}</p>
						<p><a class=\"picgame-upload-link-2\" href=\"javascript:void(0);\" data-img-two-name=\"{$imgTwoName}\">" .
							$this->msg( 'picturegame-editgameuploadtext' )->plain() . "</a></p>
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
				<input type="button" onclick="document.picGameVote.submit()" value="' . $this->msg( 'picturegame-buttonsubmit' )->plain() . "\"/>
				<input type=\"button\" onclick=\"window.location='" .
					htmlspecialchars( $this->getPageTitle()->getFullURL( "picGameAction=renderPermalink&id={$imgID}" ) ) . "'\" value=\"" .
					$this->msg( 'picturegame-buttoncancel' )->plain() . "\"/>
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

		$now = time();
		$key = md5( $now . $this->SALT );

		$output = '<script type="text/javascript">
			var __admin_panel_now__ = "' . $now . '";
			var __admin_panel_key__ = "' . $key . '";
		</script>';

		$out->addModuleStyles( 'ext.pictureGame.adminPanel' );

		$out->setPageTitle( $this->msg( 'picturegame-adminpaneltitle' )->text() );
		$output .= '
		<div class="back-link">
			<a href="' . htmlspecialchars( $this->getPageTitle()->getFullURL( 'picGameAction=startGame' ) ) . '"> ' .
				$this->msg( 'picturegame-adminpanelbacktogame' )->text() . '</a>
		</div>

		<div id="admin-container" class="admin-container">
			<p><strong>' . $this->msg( 'picturegame-adminpanelflagged' )->text() . '</strong></p>';

		$dbw = wfGetDB( DB_MASTER );
		$res = $dbw->select(
			'picturegame_images',
			array( 'id', 'img1', 'img2' ),
			array(
				'flag' => PictureGameHome::$FLAG_FLAGGED,
				"img1 <> ''",
				"img2 <> ''"
			),
			__METHOD__
		);

		// If we have nothing, indicate that in the UI instead of showing...
		// well, nothing
		if ( $dbw->numRows( $res ) <= 0 ) {
			$output .= $this->msg( 'picturegame-none' )->text();
		}

		foreach ( $res as $row ) {
			$img_one_tag = $img_two_tag = '';
			$img_one = wfFindFile( $row->img1 );
			if ( is_object( $img_one ) ) {
				$thumb_one = $img_one->transform( array( 'width' => 128 ) );
				$img_one_tag = $thumb_one->toHtml();
			}

			$img_two = wfFindFile( $row->img2 );
			if ( is_object( $img_two ) ) {
				$thumb_two = $img_two->transform( array( 'width' => 128 ) );
				$img_two_tag = $thumb_two->toHtml();
			}

			$img_one_description = $lang->truncate( $row->img1, 12 );
			$img_two_description = $lang->truncate( $row->img2, 12 );

			$output .= '<div id="' . $row->id . "\" class=\"admin-row\">

				<div class=\"admin-image\">
					<p>{$img_one_tag}</p>
					<p><b>{$img_one_description}</b></p>
				</div>
				<div class=\"admin-image\">
					<p>{$img_two_tag}</p>
					<p><b>{$img_two_description}</b></p>
				</div>
				<div class=\"admin-controls\">
					<a class=\"picgame-unflag-link\" href=\"javascript:void(0)\">" .
						$this->msg( 'picturegame-adminpanelunflag' )->text() .
					"</a> |
					<a class=\"picgame-delete-link\" href=\"javascript:void(0);\" data-row-img1=\"{$row->img1}\" data-row-img2=\"{$row->img2}\">"
						. $this->msg( 'picturegame-adminpaneldelete' )->text() .
					'</a>
				</div>
				<div class="visualClear"></div>

			</div>';
		}

		$output .= '</div>
		<div id="admin-container" class="admin-container">
			<p><strong>' . $this->msg( 'picturegame-adminpanelprotected' )->text() . '</strong></p>';
		$dbw = wfGetDB( DB_MASTER );
		$res = $dbw->select(
			'picturegame_images',
			array( 'id', 'img1', 'img2' ),
			array(
				'flag' => PictureGameHome::$FLAG_PROTECT,
				"img1 <> ''", "img2 <> ''"
			),
			__METHOD__
		);

		// If we have nothing, indicate that in the UI instead of showing...
		// well, nothing
		if ( $dbw->numRows( $res ) <= 0 ) {
			$output .= $this->msg( 'picturegame-none' )->text();
		}

		foreach ( $res as $row ) {
			$img_one_tag = $img_two_tag = '';
			$img_one = wfFindFile( $row->img1 );
			if ( is_object( $img_one ) ) {
				$thumb_one = $img_one->transform( array( 'width' => 128 ) );
				$img_one_tag = $thumb_one->toHtml();
			}

			$img_two = wfFindFile( $row->img2 );
			if ( is_object( $img_two ) ) {
				$thumb_two = $img_two->transform( array( 'width' => 128 ) );
				$img_two_tag = $thumb_two->toHtml();
			}

			$img_one_description = $lang->truncate( $row->img1, 12 );
			$img_two_description = $lang->truncate( $row->img2, 12 );

			$output .= '<div id="' . $row->id . "\" class=\"admin-row\">

				<div class=\"admin-image\">
					<p>{$img_one_tag}</p>
					<p><b>{$img_one_description}</b></p>
				</div>
				<div class=\"admin-image\">
					<p>{$img_two_tag}</p>
					<p><b>{$img_two_description}</b></p>
				</div>
				<div class=\"admin-controls\">
					<a class=\"picgame-unprotect-link\" href=\"javascript:void(0)\">" .
						$this->msg( 'picturegame-adminpanelunprotect' )->text() .
					"</a> |
					<a class=\"picgame-delete-link\" href=\"javascript:void(0);\" data-row-img1=\"{$row->img1}\" data-row-img2=\"{$row->img2}\">"
						. $this->msg( 'picturegame-adminpaneldelete' )->text() .
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

		if( $key != md5( $id . $this->SALT ) ) {
			echo $this->msg( 'picturegame-sysmsg-badkey' )->plain();
			return;
		}

		$dbw = wfGetDB( DB_MASTER );
		$dbw->update(
			'picturegame_images',
			array( 'flag' => PictureGameHome::$FLAG_FLAGGED ),
			array( 'id' => $id, 'flag' => PictureGameHome::$FLAG_NONE ),
			__METHOD__
		);

		$out->clearHTML();
		echo '<div style="color:red; font-weight:bold; font-size:16px; margin:-5px 0px 20px 0px;">' .
			$this->msg( 'picturegame-sysmsg-flag' )->plain() .
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

		if( $key != md5( $chain . $this->SALT ) ) {
			echo $this->msg( 'picturegame-sysmsg-badkey' )->plain();
			return;
		}

		$dbw = wfGetDB( DB_MASTER );
		$dbw->update(
			'picturegame_images',
			array( 'flag' => PictureGameHome::$FLAG_NONE ),
			array( 'id' => $id ),
			__METHOD__
		);

		$out->clearHTML();
		echo $this->msg( 'picturegame-sysmsg-unprotect' )->plain();
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

		if( $key != md5( $id . $this->SALT ) ) {
			echo $this->msg( 'picturegame-sysmsg-badkey' )->plain();
			return;
		}

		$dbw = wfGetDB( DB_MASTER );
		$dbw->update(
			'picturegame_images',
			array( 'flag' => PictureGameHome::$FLAG_PROTECT ),
			array( 'id' => $id ),
			__METHOD__
		);

		$out->clearHTML();
		echo $this->msg( 'picturegame-sysmsg-protect' )->plain();
	}

	function displayGallery() {
		$lang = $this->getLanguage();
		$out = $this->getOutput();
		$request = $this->getRequest();
		$user = $this->getUser();
		$thisTitle = $this->getPageTitle();

		$out->setHTMLTitle( $this->msg( 'pagetitle', $this->msg( 'picturegame-gallery' )->text() )->text() );

		$type = $request->getVal( 'type' );
		$direction = $request->getVal( 'direction' );

		if( ( $type == 'heat' ) && ( $direction == 'most' ) ) {
			$crit = 'Heat';
			$order = 'ASC';
			$sortheader = $this->msg( 'picturegame-sorted-most-heat' )->text();
		} elseif( ( $type == 'heat' ) && ( $direction == 'least' ) ) {
			$crit = 'Heat';
			$order = 'DESC';
			$sortheader = $this->msg( 'picturegame-sorted-least-heat' )->text();
		} elseif( ( $type == 'votes' ) && ( $direction == 'most' ) ) {
			$crit = '(img0_votes + img1_votes)';
			$order = 'DESC';
			$sortheader = $this->msg( 'picturegame-sorted-most-votes' )->text();
		} elseif( ( $type == 'votes' ) && ( $direction == 'least' ) ) {
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

		if( $type == 'votes' && $direction == 'most' ) {
			$output .= '<h1>' . $this->msg( 'picturegame-most' )->text() . '</h1>
					<p><b>' . $this->msg( 'picturegame-mostvotes' )->text() . '</b></p>
					<p><a href="' . htmlspecialchars( $thisTitle->getFullURL( array(
						'picGameAction' => 'gallery',
						'type' => 'heat',
						'direction' => 'most'
					) ) ) . '">' . $this->msg( 'picturegame-mostheat' )->text() . '</a></p>

					<h1 style="margin:10px 0px !important;">' . $this->msg( 'picturegame-least' )->text() . '</h1>
					<p><a href="' . htmlspecialchars( $thisTitle->getFullURL( array(
						'picGameAction' => 'gallery',
						'type' => 'votes',
						'direction' => 'least'
					) ) ) . '">' . $this->msg( 'picturegame-leastvotes' )->text() . '</a></p>
					<p><a href="' . htmlspecialchars( $thisTitle->getFullURL( array(
						'picGameAction' => 'gallery',
						'type' => 'heat',
						'direction' => 'least'
					) ) ) . '">' . $this->msg( 'picturegame-leastheat' )->text() . '</a></p>';
		}

		if( $type == 'votes' && $direction == 'least' ) {
			$output .= '<h1>' . $this->msg( 'picturegame-most' )->text() . '</h1>
					<p><a href="' . htmlspecialchars( $thisTitle->getFullURL( array(
						'picGameAction' => 'gallery',
						'type' => 'votes',
						'direction' => 'most'
					) ) ) . '">' . $this->msg( 'picturegame-mostvotes' )->text() . '</a></p>
					<p><a href="' . htmlspecialchars( $thisTitle->getFullURL( array(
						'picGameAction' => 'gallery',
						'type' => 'heat',
						'direction' => 'most'
					) ) ) . '">' . $this->msg( 'picturegame-mostheat' )->text() . '</a></p>

					<h1 style="margin:10px 0px !important;">' . $this->msg( 'picturegame-least' )->text() . '</h1>
					<p><b>' . $this->msg( 'picturegame-leastvotes' )->text() . '</b></p>
					<p><a href="' . htmlspecialchars( $thisTitle->getFullURL( array(
						'picGameAction' => 'gallery',
						'type' => 'heat',
						'direction' => 'least'
					) ) ) . '">' . $this->msg( 'picturegame-leastheat' )->text() . '</a></p>';
		}

		if( $type == 'heat' && $direction == 'most' ) {
			$output .= '<h1>' . $this->msg( 'picturegame-most' )->text() . '</h1>
					<p><a href="' . htmlspecialchars( $thisTitle->getFullURL( array(
						'picGameAction' => 'gallery',
						'type' => 'votes',
						'direction' => 'most'
					) ) ) . '">' . $this->msg( 'picturegame-mostvotes' )->text() . '</a></p>
					<p><b>' . $this->msg( 'picturegame-mostheat' )->text() . '</b></p>

					<h1 style="margin:10px 0px !important;">' . $this->msg( 'picturegame-least' )->text() . '</h1>
					<p><a href="' . htmlspecialchars( $thisTitle->getFullURL( array(
						'picGameAction' => 'gallery',
						'type' => 'votes',
						'direction' => 'least'
					) ) ) . '">' . $this->msg( 'picturegame-leastvotes' )->text() . '</a></p>
					<p><a href="' . htmlspecialchars( $thisTitle->getFullURL( array(
						'picGameAction' => 'gallery',
						'type' => 'heat',
						'direction' => 'least'
					) ) ) . '">' . $this->msg( 'picturegame-leastheat' )->text() . '</a></p>';
		}

		if( $type == 'heat' && $direction == 'least' ) {
			$output .= '<h1>' . $this->msg( 'picturegame-most' )->text() . '</h1>
					<p><a href="' . htmlspecialchars( $thisTitle->getFullURL( array(
						'picGameAction' => 'gallery',
						'type' => 'votes',
						'direction' => 'most'
					) ) ) . '">' . $this->msg( 'picturegame-mostvotes' )->text() . '</a></p>
					<p><a href="' . htmlspecialchars( $thisTitle->getFullURL( array(
						'picGameAction' => 'gallery',
						'type' => 'heat',
						'direction' => 'most'
					) ) ) . '">' . $this->msg( 'picturegame-mostheat' )->text() . '</a></p>

					<h1 style="margin:10px 0px !important;">' . $this->msg( 'picturegame-least' )->text() . '</h1>
					<p><a href="' . htmlspecialchars( $thisTitle->getFullURL( array(
						'picGameAction' => 'gallery',
						'type' => 'votes',
						'direction' => 'least'
					) ) ) . '">' . $this->msg( 'picturegame-leastvotes' )->text() . '</a></p>
					<p><b>' . $this->msg( 'picturegame-leastheat' )->text() . '</b></p>';
		}

		$output .= '</div>';

		$output .= '<div class="picgame-gallery-container" id="picgame-gallery-thumbnails">';

		$per_row = 3;
		$x = 1;

		$dbr = wfGetDB( DB_SLAVE );
		$total = (int)$dbr->selectField(
			'picturegame_images',
			array( 'COUNT(*) AS mycount' ),
			array(),
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
		if( $limit > 0 && $page ) {
			$limitvalue = $page * $limit - ( $limit );
		}

		$dbw = wfGetDB( DB_MASTER );
		$res = $dbw->select(
			'picturegame_images',
			'*',
			array(
				'flag <> ' . PictureGameHome::$FLAG_FLAGGED,
				"img1 <> ''",
				"img2 <> ''"
			),
			__METHOD__,
			array(
				'ORDER BY' => "{$crit} {$order}",
				'OFFSET' => $limitvalue,
				'LIMIT' => $limit
			)
		);

		$preloadImages = array();

		foreach( $res as $row ) {
			$gameid = $row->id;

			$title_text = $lang->truncate( $row->title, 23 );

			$imgOneCount = $row->img0_votes;
			$imgTwoCount = $row->img1_votes;
			$totalVotes = $imgOneCount + $imgTwoCount;

			if( $imgOneCount == 0 ) {
				$imgOnePercent = 0;
			} else {
				$imgOnePercent = floor( $imgOneCount / $totalVotes  * 100 );
			}

			if( $imgTwoCount == 0 ) {
				$imgTwoPercent = 0;
			} else {
				$imgTwoPercent = 100 - $imgOnePercent;
			}

			$gallery_thumbnail_one = $gallery_thumbnail_two = '';
			$img_one = wfFindFile( $row->img1 );
			if ( is_object( $img_one ) ) {
				$gallery_thumb_image_one = $img_one->transform( array( 'width' => 80 ) );
				$gallery_thumbnail_one = $gallery_thumb_image_one->toHtml();
			}

			$img_two = wfFindFile( $row->img2 );
			if ( is_object( $img_two ) ) {
				$gallery_thumb_image_two = $img_two->transform( array( 'width' => 80 ) );
				$gallery_thumbnail_two = $gallery_thumb_image_two->toHtml();
			}

			$output .= "
			<div class=\"picgame-gallery-thumbnail\" id=\"picgame-gallery-thumbnail-{$x}\" onclick=\"javascript:document.location=wgScriptPath+'/index.php?title=Special:PictureGameHome&picGameAction=renderPermalink&id={$gameid}'\">
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

			if( $x != 1 && $x % $per_row == 0 ) {
				$output .= '<div class="visualClear"></div>';
			}
			$x++;
		}

		$output .= '</div>';

		// Page Nav
		$numofpages = ceil( $total / $per_page );

		if( $numofpages > 1 ) {
			$output .= '<div class="page-nav">';

			if( $page > 1 ) {
				$output .= Linker::link(
					$thisTitle,
					$this->msg( 'picturegame-prev' )->text(),
					array(),
					array(
						'picGameAction' => 'gallery',
						'page' => ( $page - 1 ),
						'type' => $type,
						'direction' => $direction
					)
				) . $this->msg( 'word-separator' )->plain();
			}

			for( $i = 1; $i <= $numofpages; $i++ ) {
				if( $i == $page ) {
					$output .= ( $i . ' ' );
				} else {
					$output .= Linker::link(
						$thisTitle,
						$i,
						array(),
						array(
							'picGameAction' => 'gallery',
							'page' => $i,
							'type' => $type,
							'direction' => $direction
						)
					) . $this->msg( 'word-separator' )->plain();
				}
			}

			if( $page < $numofpages ) {
				$output .= $this->msg( 'word-separator' )->plain() . Linker::link(
					$thisTitle,
					$this->msg( 'picturegame-next' )->text(),
					array(),
					array(
						'picGameAction' => 'gallery',
						'page' => ( $page + 1 ),
						'type' => $type,
						'direction' => $direction
					)
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

		if( $key != md5( $id . $this->SALT ) ) {
			$out->addHTML( $this->msg( 'picturegame-sysmsg-badkey' )->plain() );
			return;
		}

		if( strlen( $id ) > 0 && strlen( $img ) > 0 ) {
			$dbw = wfGetDB( DB_MASTER );

			// check if the user has voted on this already
			// @todo FIXME: in both cases we can just use selectField(), I think
			$res = $dbw->select(
				'picturegame_votes',
				array( 'COUNT(*) AS mycount' ),
				array(
					'username' => $user->getName(),
					'picid' => $id
				),
				__METHOD__
			);
			$row = $dbw->fetchObject( $res );

			// if they haven't, then check if the id exists and then insert the
			// vote
			if( $row->mycount == 0 ) {
				$res = $dbw->select(
					'picturegame_images',
					array( 'COUNT(*) AS mycount' ),
					array( 'id' => $id ),
					__METHOD__
				);
				$row = $dbw->fetchObject( $res );

				if( $row->mycount == 1 ) {
					$dbw->insert(
						'picturegame_votes',
						array(
							'picid' => $id,
							'userid' => $user->getID(),
							'username' => $user->getName(),
							'imgpicked' => $imgnum,
							'vote_date' => date( 'Y-m-d H:i:s' )
						),
						__METHOD__
					);

					$sql = "UPDATE picturegame_images SET img" . $imgnum . "_votes=img" . $imgnum . "_votes+1,
						heat=ABS( ( img0_votes / ( img0_votes+img1_votes) ) - ( img1_votes / ( img0_votes+img1_votes ) ) )
						WHERE id=" . $id . ";";
					$res = $dbw->query( $sql, __METHOD__ );
					/*$res = $dbw->update(
						'picturegame_images',
						array(
							"img{$imgnum}_votes = img{$imgnum}_votes + 1",
							"heat = ABS( ( img0_votes / ( img0_votes+img1_votes) ) - ( img1_votes / ( img0_votes+img1_votes ) ) )"
						),
						array( 'id' => $id ),
						__METHOD__
					);*/

					// Increase social statistics
					$stats = new UserStatsTrack( $user->getID(), $user->getName() );
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

		$dbr = wfGetDB( DB_SLAVE );

		// if imgID is -1 then we need some random IDs
		if( $imgID == -1 ) {
			$query = $dbr->select(
				'picturegame_votes',
				'picid',
				array( 'username' => $user->getName() ),
				__METHOD__
			);
			$picIds = array();
			foreach ( $query as $resultRow ) {
				$picIds[] = $resultRow->picid;
			}

			// If there are no picture games or only one game in the database,
			// the above query won't add anything to the picIds array.
			// Trying to implode an empty array...well, you'll get "id NOT IN ()"
			// which in turn is invalid SQL.
			$whereConds = array(
				'flag <> ' . PictureGameHome::$FLAG_FLAGGED,
				"img1 <> ''",
				"img2 <> ''"
			);
			if ( !empty( $picIds ) ) {
				$whereConds[] = 'id NOT IN (' . implode( ',', $picIds ) . ')';
			}
			$res = $dbr->select(
				'picturegame_images',
				'*',
				$whereConds,
				__METHOD__,
				array( 'LIMIT' => 1 )
			);
			$row = $dbr->fetchObject( $res );
			$imgID = isset( $row->id ) ? $row->id : 0;
		} else {
			$res = $dbr->select(
				'picturegame_images',
				'*',
				array(
					'flag <> ' . PictureGameHome::$FLAG_FLAGGED,
					"img1 <> ''",
					"img2 <> ''",
					'id' => $imgID
				),
				__METHOD__
			);
			$row = $dbr->fetchObject( $res );
		}

		// Early return here in case if we have *nothing* in the database to
		// prevent fatals etc.
		if( empty( $row ) ) {
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

		$user_title = Title::makeTitle( NS_USER, $row->username );

		if( $imgID ) {
			global $wgPictureGameID;
			$wgPictureGameID = $imgID;
			$toExclude = $dbr->select(
				'picturegame_votes',
				'picid',
				array( 'username' => $user->getName() ),
				__METHOD__
			);

			$excludedImgIds = array();
			foreach ( $toExclude as $excludedRow ) {
				$excludedImgIds[] = $excludedRow->picid;
			}

			$next_id = 0;

			if ( !empty( $excludedImgIds ) ) {
				$nextres = $dbr->select(
					'picturegame_images',
					'*',
					array(
						"id <> {$imgID}",
						'id NOT IN (' . implode( ',', $excludedImgIds ) . ')',
						'flag != ' . PictureGameHome::$FLAG_FLAGGED,
						"img1 <> ''",
						"img2 <> ''"
					),
					__METHOD__,
					array( 'LIMIT' => 1 )
				);
				$nextrow = $dbr->fetchObject( $nextres );
				$next_id = ( isset( $nextrow->id ) ? $nextrow->id : 0 );
			}

			if( $next_id ) {
				$img_one = wfFindFile( $nextrow->img1 );
				if( is_object( $img_one ) ) {
					$preload_thumb = $img_one->transform( array( 'width' => 256 ) );
				}
				if( is_object( $preload_thumb ) ) {
					$preload_one_tag = $preload_thumb->toHtml();
				}

				$img_two = wfFindFile( $nextrow->img2 );
				if( is_object( $img_two ) ) {
					$preload_thumb = $img_two->transform( array( 'width' => 256 ) );
				}
				if( is_object( $preload_thumb ) ) {
					$preload_two_tag = $preload_thumb->toHtml();
				}

				$preload = $preload_one_tag . $preload_two_tag;
			}
		}

		if( ( $imgID < 0 ) || !is_numeric( $imgID ) || is_null( $row ) ) {
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

		$user_name = $lang->truncate( $row->username, 20 );

		$title_text_length = strlen( $row->title );
		$title_text_space = stripos( $row->title, ' ' );

		if( ( $title_text_space == false || $title_text_space >= '48' ) && $title_text_length > 48 ) {
			$title_text = substr( $row->title, 0, 48 ) . '<br />' .
				substr( $row->title, 48, 48 );
		} elseif( $title_text_length > 48 && substr( $row->title, 48, 1 ) == ' ' ) {
			$title_text = substr( $row->title, 0, 48 ) . '<br />' .
				substr( $row->title, 48, 48 );
		} elseif( $title_text_length > 48 && substr( $row->title, 48, 1 ) != ' ' ) {
			$title_text_lastspace = strrpos( substr( $row->title, 0, 48 ), ' ' );
			$title_text = substr( $row->title, 0, $title_text_lastspace ) .
				'<br />' . substr( $row->title, $title_text_lastspace, 30 );
		} else {
			$title_text = $row->title;
		}

		$x = 1;
		$img1_caption_text = '';
		$img1caption_array = str_split( $row->img1_caption );
		foreach( $img1caption_array as $img1_character ) {
			if( $x % 30 == 0 ) {
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
		$img_one = wfFindFile( $row->img1 );
		$thumb_one_url = '';
		if( is_object( $img_one ) ) {
			$thumb_one_url = $img_one->createThumb( 256 );
			$imageOneWidth = $img_one->getWidth();
		}
		//$imgOne = '<img width="' . ( $imageOneWidth >= 256 ? 256 : $imageOneWidth ) . '" alt="" src="' . $thumb_one_url . ' "/>';
		//$imageOneWidth = ( $imageOneWidth >= 256 ? 256 : $imageOneWidth );
		//$imageOneWidth += 10;
		$imgOne = '<img style="width:100%;" alt="" src="' . $thumb_one_url . ' "/>';

		$img_two = wfFindFile( $row->img2 );
		$thumb_two_url = '';
		if( is_object( $img_two ) ) {
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
		if( $lastID > 0 ) {
			$res = $dbr->select(
				'picturegame_images',
				'*',
				array(
					'flag <> ' . PictureGameHome::$FLAG_FLAGGED,
					'id' => $lastID
				),
				__METHOD__
			);
			$row = $dbr->fetchObject( $res );

			if( $row ) {
				$img_one = wfFindFile( $row->img1 );
				$img_two = wfFindFile( $row->img2 );
				$imgOneCount = $row->img0_votes;
				$imgTwoCount = $row->img1_votes;
				$isShowVotes = true;
			}
		}

		if( $isPermalink || $isShowVotes ) {
			if( is_object( $img_one ) ) {
				$vote_one_thumb = $img_one->transform( array( 'width' => 40 ) );
			}
			if( is_object( $vote_one_thumb ) ) {
				$vote_one_tag = $vote_one_thumb->toHtml();
			}

			if( is_object( $img_two ) ) {
				$vote_two_thumb = $img_two->transform( array( 'width' => 40 ) );
			}
			if( is_object( $vote_two_thumb ) ) {
				$vote_two_tag = $vote_two_thumb->toHtml();
			}

			$totalVotes = $imgOneCount + $imgTwoCount;

			if( $imgOneCount == 0 ) {
				$imgOnePercent = 0;
				$barOneWidth = 0;
			} else {
				$imgOnePercent = floor( $imgOneCount / $totalVotes  * 100 );
				$barOneWidth = floor( 200 * ( $imgOneCount / $totalVotes ) );
			}

			if( $imgTwoCount == 0 ) {
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
		if( $user->isAllowed( 'picturegameadmin' ) ) {
			// If the user can edit, throw in some links
			$editlinks = ' - <a href="' . htmlspecialchars( $this->getPageTitle()->getFullURL(
				'picGameAction=adminPanel' ) ) . '"> ' .
				$this->msg( 'picturegame-adminpanel' )->text() .
			'</a> - <a class="picgame-protect-link" href="javascript:void(0);"> '
				. $this->msg( 'picturegame-protectimages' )->text() . '</a>';
		}

		$createLink = '';
		// Only registered users can create new picture games
		if( $user->isLoggedIn() ) {
			$createLink = '
			<div class="create-link">
				<a href="' . htmlspecialchars( $this->getPageTitle()->getFullURL( 'picGameAction=startCreate' ) ) . '">
					<img src="' . $wgExtensionAssetsPath . '/SocialProfile/images/addIcon.gif" border="0" alt="" />'
					. $this->msg( 'picturegame-createlink' )->text() .
				'</a>
			</div>';
		}

		$editLink = '';
		if( $user->isLoggedIn() && $user->isAllowed( 'picturegameadmin' ) && $wgUseEditButtonFloat == true ) {
			$editLink .= '<div class="edit-menu-pic-game">
				<div class="edit-button-pic-game">
					<img src="' . $wgExtensionAssetsPath . '/SocialProfile/images/editIcon.gif" alt="" />
					<a class="picgame-edit-link" href="javascript:void(0)">' . $this->msg( 'edit' )->text() . '</a>
				</div>
			</div>';
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
									. $this->msg( 'picturegame-backbutton' )->text() .
								"</a>
							</li>
							<li id=\"skipButton\" style=\"display:" . ( $next_id > 0 ? 'block' : 'none' ) . "\">
								<a href=\"" . htmlspecialchars( $this->getPageTitle()->getFullURL( 'picGameAction=startGame' ) ) . '">'
									. $this->msg( 'picturegame-skipbutton' )->text() .
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
					<h1>" . $this->msg( 'picturegame-submittedby' )->text() . "</h1>
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
						<h1>" . $this->msg( 'picturegame-previousgame' )->text() . " ({$totalVotes})</h1></div>
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

				<div class=\"utilityButtons\" id=\"utilityButtons\">
					<a class=\"picgame-flag-link\" href=\"javascript:void(0);\">"
						. $this->msg( 'picturegame-reportimages' )->text() .
					" </a> -
					<a href=\"javascript:window.parent.document.location='" . htmlspecialchars( $this->getPageTitle()->getFullURL( 'picGameAction=renderPermalink' ) ) . "&id=' + document.getElementById('id').value\">"
						. $this->msg( 'picturegame-permalink' )->text() .
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

		// @todo FIXME: as per Tim: http://www.mediawiki.org/wiki/Special:Code/MediaWiki/59183#c4709
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
		if( $key == md5( $chain . $this->SALT ) ) {
			$sql = "SELECT COUNT(*) AS mycount FROM {$dbr->tableName( 'picturegame_images' )} WHERE
				( img1 = \"" . $img1 . "\" OR img2 = \"" . $img1 . "\" ) AND
				( img1 = \"" . $img2 . "\" OR img2 = \"" . $img2 . "\" ) GROUP BY id;";

			$res = $dbr->query( $sql, __METHOD__ );
			$row = $dbr->fetchObject( $res );

			// if these image pairs don't exist, insert them
			if( isset( $row ) && $row->mycount == 0 ) {
				$dbr->insert(
					'picturegame_images',
					array(
						'userid' => $user->getID(),
						'username' => $user->getName(),
						'img1' => $img1,
						'img2' => $img2,
						'title' => $title,
						'img1_caption' => $img1_caption,
						'img2_caption' => $img2_caption,
						'pg_date' => date( 'Y-m-d H:i:s' )
					),
					__METHOD__
				);

				$id = $dbr->selectField(
					'picturegame_images',
					'MAX(id) AS maxid',
					array(),
					__METHOD__
				);

				// Increase social statistics
				$stats = new UserStatsTrack( $user->getID(), $user->getName() );
				$stats->incStatField( 'picturegame_created' );

				// Purge memcached
				global $wgMemc;
				$key = wfMemcKey( 'user', 'profile', 'picgame', $user->getID() );
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
		if( $permalinkID > 0 ) {
			$isPermalink = true;

			$mycount = (int)$dbr->selectField(
				'picturegame_images',
				'COUNT(*) AS mycount',
				array(
					'flag = ' . PictureGameHome::$FLAG_NONE .
						' OR flag = ' . PictureGameHome::$FLAG_PROTECT,
					'id' => $permalinkID
				),
				__METHOD__
			);

			if( $mycount == 0 ) {
				$out->addModuleStyles( 'ext.pictureGame.mainGame' );
				$output = '
					<div class="picgame-container" id="picgame-container">
						<p>' . $this->msg( 'picturegame-permalinkflagged' )->text() . '</p>
						<p><input type="button" class="site-button" value="' .
							$this->msg( 'picturegame-buttonplaygame' )->text() .
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
		if( !$user->isLoggedIn() ) {
			$out->setPageTitle( $this->msg( 'picturegame-creategametitle' ) );
			$output = $this->msg( 'picturegame-creategamenotloggedin' )->text();
			$output .= "<p>
				<input type=\"button\" class=\"site-button\" onclick=\"window.location='" .
					htmlspecialchars( SpecialPage::getTitleFor( 'Userlogin', 'signup' )->getFullURL() ) .
					"'\" value=\"" . $this->msg( 'picturegame-signup' )->text() . "\" />
				<input type=\"button\" class=\"site-button\" onclick=\"window.location='" .
					htmlspecialchars( SpecialPage::getTitleFor( 'Userlogin' )->getFullURL() ) .
					"'\" value=\"" . $this->msg( 'picturegame-login' )->text() . "\" />
			</p>";
			$out->addHTML( $output );
			return;
		}

		/**
		 * Create Picture Game Thresholds based on User Stats
		 */
		global $wgCreatePictureGameThresholds;
		if( is_array( $wgCreatePictureGameThresholds ) && count( $wgCreatePictureGameThresholds ) > 0 ) {
			$can_create = true;

			$stats = new UserStats( $user->getID(), $user->getName() );
			$stats_data = $stats->getUserStats();

			$threshold_reason = '';
			foreach( $wgCreatePictureGameThresholds as $field => $threshold ) {
				if ( $stats_data[$field] < $threshold ) {
					$can_create = false;
					$threshold_reason .= ( ( $threshold_reason ) ? ', ' : '' ) . "$threshold $field";
				}
			}

			if( $can_create == false ) {
				$out->setPageTitle( $this->msg( 'picturegame-create-threshold-title' )->plain() );
				$out->addHTML( $this->msg( 'picturegame-create-threshold-reason', $threshold_reason )->text() );
				return '';
			}
		}

		// Show a link to the admin panel for picture game admins
		if( $user->isAllowed( 'picturegameadmin' ) ) {
			$adminlink = '<a href="' . htmlspecialchars( $this->getPageTitle()->getFullURL( 'picGameAction=adminPanel' ) ) . '"> ' .
				$this->msg( 'picturegame-adminpanel' )->text() . ' </a>';
		}

		$dbr = wfGetDB( DB_MASTER );

		$excludedIds = $dbr->select(
			'picturegame_votes',
			'picid',
			array( 'username' => $user->getName() ),
			__METHOD__
		);

		$excluded = array();
		foreach ( $excludedIds as $excludedId ) {
			$excluded[] = $excludedId->picid;
		}

		$canSkip = false;
		if ( !empty( $excluded ) ) {
			$myCount = (int)$dbr->selectField(
				'picturegame_images',
				'COUNT(*) AS mycount',
				array(
					'id NOT IN(' . implode( ',', $excluded  ) . ')',
					'flag != ' . PictureGameHome::$FLAG_FLAGGED,
					"img1 <> ''",
					"img2 <> ''"
				),
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
		$output .= $this->msg( 'picturegame-creategamewelcome' )->text();
		$output .= '<br />

			<div id="skipButton" class="startButton">';
		$play_button_text = $this->msg( 'picturegame-creategameplayinstead' )->text();
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
						<h1>' . $this->msg( 'picturegame-creategamegametitle' )->text() . '</h1>
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
							<h1>' . $this->msg( 'picturegame-createeditfirstimage' )->text() . '</h1>
							<!--Caption:<br /><input name="picOneDesc" id="picOneDesc" type="text" value="" /><br />-->
							<div id="imageOneUploadError"></div>
							<div id="imageOneLoadingImg" class="loadingImg" style="display:none">
								<img src="' . $wgExtensionAssetsPath . '/SocialProfile/images/ajax-loader-white.gif" alt="" />
							</div>
							<div id="imageOne" class="imageOne" style="display:none;"></div>
							<iframe class="imageOneUpload-frame" scrolling="no" frameborder="0" width="400" id="imageOneUpload-frame" src="' .
								htmlspecialchars( $uploadObj->getFullURL( 'callbackPrefix=imageOne_' ) ) . '"></iframe>
						</div>

						<div id="imageTwoUpload" class="imageTwoUpload">
							<h1>' . $this->msg( 'picturegame-createeditsecondimage' )->text() . '</h1>
							<!--Caption:<br /><input name="picTwoDesc" id="picTwoDesc" type="text" value="" /><br />-->
							<div id="imageTwoUploadError"></div>
							<div id="imageTwoLoadingImg" class="loadingImg" style="display:none">
								<img src="' . $wgExtensionAssetsPath . '/SocialProfile/images/ajax-loader-white.gif" alt="" />
							</div>
							<div id="imageTwo" class="imageTwo" style="display:none;"></div>
							<iframe id="imageTwoUpload-frame" scrolling="no" frameborder="0" width="510" src="' .
								htmlspecialchars( $uploadObj->getFullURL( 'callbackPrefix=imageTwo_' ) ) . '"></iframe>
						</div>

						<div class="visualClear"></div>
					</div>
				</div>
			</div>

			<div id="startButton" class="startButton" style="display: none;">
				<input type="button" class="site-button" value="' . $this->msg( 'picturegame-creategamecreateplay' )->text() . '" />
			</div>';

		$out->addHTML( $output );
	}
}
