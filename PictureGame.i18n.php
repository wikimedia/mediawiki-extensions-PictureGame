<?php
/**
 * Internationalization file for PictureGame extension.
 *
 * @file
 * @ingroup Extensions
 */

$messages = array();

/** English
 * @author Aaron Wright <aaron.wright@gmail.com>
 * @author David Pean <david.pean@gmail.com>
 */
$messages['en'] = array(
	'picturegame-signup' => 'Sign Up',
	'picturegame-login' => 'Log in',
	'picturegame-submittedby' => 'Submitted By',
	'picturegame-reportimages' => 'Report Images',
	'picturegame-permalink' => 'Permalink',
	'picturegame-adminpanel' => 'Admin Panel',
	'picturegame-protectimages' => 'Protect Images',
	'picturegame-createlink' => 'Create a Picture Game',
	'picturegame-skipbutton' => 'Skip',
	'picturegame-backbutton' => 'Go Back',
	'picturegame-previousgame' => 'Previous Game',
	'picturegame-adminpaneltitle' => 'Picture Game Admin Panel',
	'picturegame-adminpanelflagged' => 'Flagged Images:',
	'picturegame-adminpanelprotected' => 'Protected Images:',
	'picturegame-adminpanelreinstate' => 'Reinstate',
	'picturegame-adminpaneldelete' => 'Delete',
	'picturegame-adminpanelunprotect' => 'Unprotect',
	'picturegame-adminpanelbacktogame' => '&lt; Back to the Picture Game',
	'picturegame-creategametitle' => 'Create a Picture Game',
	'picturegame-creategamenotloggedin' => 'You must log-in to create a picture game.',
	'picturegame-creategamewelcome' => "Upload two pictures, add some captions, and then go crazy rating everyone's pictures. It's that easy.",
	'picturegame-creategameplayinstead' => 'Play Game Instead',
	'picturegame-creategamegametitle' => 'Picture Game Title',
	'picturegame-creategamecaptiontext' => 'Caption',
	'picturegame-creategamefiletext' => 'File',
	'picturegame-creategamecreateplay' => 'Create and Play!',
	'picturegame-createeditfirstimage' => 'First Image',
	'picturegame-createeditsecondimage' => 'Second Image',
	'picturegame-editgameediting' => 'Editing',
	'picturegame-editgame-editing-title' => 'Editing $1',
	'picturegame-editgamegametitle' => 'Title',
	'picturegame-editgameuploadtext' => 'Upload New Image',
	'picturegame-flagimgconfirm' => 'Are you sure you want to report these images?',
	'picturegame-protectimgconfirm' => 'Are you sure you want to protect these images?',
	'picturegame-buttonupload' => 'Upload',
	'picturegame-buttonsubmit' => 'Submit',
	'picturegame-buttoncancel' => 'Cancel',
	'picturegame-buttonplaygame' => 'Play the Picture Game',
	'picturegame-permalinkflagged' => 'These pictures have been flagged because of inappropriate content or copyrighted material. To play the picture game, click the button below.',
	'picturegame-sysmsg-unauthorized' => "You aren't authorized to do that.",
	'picturegame-sysmsg-blocked' => 'You are currently blocked and cannot add picture games.',
	'picturegame-sysmsg-badkey' => 'Fatal Error: Your key is bad.',
	'picturegame-sysmsg-successfuldelete' => 'You have successfully deleted this picture game!',
	'picturegame-sysmsg-unsuccessfuldelete' => 'Deleting $1 from MediaWiki failed!',
	'picturegame-sysmsg-unflag' => 'You have placed these images back into circulation.',
	'picturegame-sysmsg-flag' => 'The images have been reported!',
	'picturegame-sysmsg-unprotect' => 'The images have been un-protected!',
	'picturegame-sysmsg-protect' => 'The images have been protected!',
	'picturegame-least' => 'Least',
	'picturegame-leastheat' => 'Least Heat',
	'picturegame-leastvotes' => 'Least Votes',
	'picturegame-minifeed-nomore' => 'There are no new picture games to play.',
	'picturegame-minifeed-nomorecreatelink' => 'Create one!',
	'picturegame-most' => 'Most',
	'picturegame-mostheat' => 'Most Heat',
	'picturegame-mostvotes' => 'Most Votes',
	'picturegame-nomoretitle' => 'No More Picture Games!',
	'picturegame-no-more' => 'There are no new picture games to play.<br />
Don\'t get sad, [{{fullurl:Special:PictureGameHome|picGameAction=startCreate}} create your very own] or [[Special:RandomPoll|take some polls!]]',
	'picturegame-create-threshold-title' => 'Create Picture Game',
	'picturegame-create-threshold-reason' => 'Sorry, you cannot create a picture game until you have at least $1',
	'picturegame-next' => 'next',
	'picturegame-prev' => 'prev',
	'picturegame-sorted-most-heat' => 'Picture Games Sorted By Most Heat',
	'picturegame-sorted-most-votes' => 'Picture Games Sorted By Most Votes',
	'picturegame-sorted-least-heat' => 'Picture Games Sorted By Least Heat',
	'picturegame-sorted-least-votes' => 'Picture Games Sorted By Least Votes',
	'picturegame-gallery' => 'Gallery',
	'picturegame-gallery-empty' => 'There are no picture game images yet.',
	'picturegame-empty' => 'There are no picture games in the database. You can [{{fullurl:Special:PictureGameHome|picGameAction=startCreate}} create a new picture game!]',
	'picturegame-nothing-to-edit' => 'Nothing to edit over here.', // displayed when the user triggers PictureGameHome::editPanel() and for some reason the DB returns no data
	'picturegame-images-category' => 'Picturegames', // category where images uploaded via PictureGame's upload form are stored into
	'picturegame-none' => 'none', // shown on the admin panel, after picturegame-adminpanelflagged/picturegame-adminpanelprotected if there are no flagged/protected images
	// messages used in PictureGame.js
	'picturegame-js-edit' => 'Edit',
	'picturegame-js-error-title' => 'Please enter a title!',
	'picturegame-js-error-upload-imgone' => 'Please upload image one!',
	'picturegame-js-error-upload-imgtwo' => 'Please upload image two!',
	'picturegame-js-editing-imgone' => 'Editing Image 1',
	'picturegame-js-editing-imgtwo' => 'Editing Image 2',
	'right-picturegameadmin' => 'Administrate picture games',
);

/** Finnish (Suomi)
 * @author Jack Phoenix <jack@countervandalism.net>
 */
$messages['fi'] = array(
	'picturegame-signup' => 'Rekisteröidy',
	'picturegame-login' => 'Kirjaudu sisään',
	'picturegame-submittedby' => 'Lähettänyt',
	'picturegame-reportimages' => 'Ilmoita kuvista',
	'picturegame-permalink' => 'Ikilinkki',
	'picturegame-adminpanel' => 'Ylläpitäjän paneeli',
	'picturegame-protectimages' => 'Suojaa kuvat',
	'picturegame-createlink' => 'Luo kuvapeli',
	'picturegame-skipbutton' => 'Ohita',
	'picturegame-backbutton' => 'Palaa takaisin',
	'picturegame-previousgame' => 'Aiempi peli',
	'picturegame-adminpaneltitle' => 'Kuvapelin ylläpitäjän paneeli',
	'picturegame-adminpanelflagged' => 'Merkityt kuvat:',
	'picturegame-adminpanelprotected' => 'Suojatut kuvat:',
	'picturegame-adminpanelreinstate' => 'Palauta kiertoon',
	'picturegame-adminpaneldelete' => 'Poista',
	'picturegame-adminpanelunprotect' => 'Poista suojaus',
	'picturegame-adminpanelbacktogame' => '&lt; Takaisin kuvapeliin',
	'picturegame-creategametitle' => 'Luo kuvapeli',
	'picturegame-creategamenotloggedin' => 'Sinun tulee olla kirjautunut sisään luodaksesi kuvapelin.',
	'picturegame-creategamewelcome' => 'Tallenna kaksi kuvaa, lisää joitakin kuvatekstejä ja sen jälkeen arvostele hulluna toisten kuvia. Se on niin helppoa.',
	'picturegame-creategameplayinstead' => 'Pelaa peliä sen sijaan',
	'picturegame-creategamegametitle' => 'Kuvapelin otsikko',
	'picturegame-creategamecaptiontext' => 'Kuvateksti',
	'picturegame-creategamefiletext' => 'Tiedosto',
	'picturegame-creategamecreateplay' => 'Luo ja pelaa!',
	'picturegame-createeditfirstimage' => 'Ensimmäinen kuva',
	'picturegame-createeditsecondimage' => 'Toinen kuva',
	'picturegame-editgameediting' => 'Muokataan',
	'picturegame-editgame-editing-title' => 'Muokataan kuvapeliä $1',
	'picturegame-editgamegametitle' => 'Otsikko',
	'picturegame-editgameuploadtext' => 'Tallenna uusi kuva',
	'picturegame-flagimgconfirm' => 'Oletko varma, että haluat ilmoittaa näistä kuvista?',
	'picturegame-protectimgconfirm' => 'Oletko varma, että haluat suojata nämä kuvat?',
	'picturegame-buttonupload' => 'Tallenna',
	'picturegame-buttonsubmit' => 'Lähetä',
	'picturegame-buttoncancel' => 'Peruuta',
	'picturegame-buttonplaygame' => 'Pelaa kuvapeliä',
	'picturegame-permalinkflagged' => 'Nämä kuvat ovat merkittyjä, joko sopimattoman sisällön tai tekijänoikeuslain suojaaman materiaalin tähden. Pelataksesi kuvapeliä, napsauta painiketta alapuolella.',
	'picturegame-sysmsg-unauthorized' => 'Sinulla ei ole lupaa tehdä tätä.',
	'picturegame-sysmsg-blocked' => 'Olet tällä hetkellä estetty etkä voi lisätä kuvapelejä.',
	'picturegame-sysmsg-badkey' => 'Kohtalokas virhe: Avaimesi on huono.',
	'picturegame-sysmsg-successfuldelete' => 'Olet onnistuneesti poistanut tämän kuvapelin!',
	'picturegame-sysmsg-unsuccessfuldelete' => 'Kuvan $1 poistaminen MediaWikistä epäonnistui!',
	'picturegame-sysmsg-unflag' => 'Olet palauttanut nämä kuvat kiertoon.',
	'picturegame-sysmsg-flag' => 'Kuvista on ilmoitettu!',
	'picturegame-sysmsg-unprotect' => 'Kuvien suojaus on poistettu!',
	'picturegame-sysmsg-protect' => 'Kuvat on suojattu!',
	'picturegame-least' => 'Vähiten',
	'picturegame-leastheat' => 'Vähiten kuumuutta',
	'picturegame-leastvotes' => 'Vähiten ääniä',
	'picturegame-minifeed-nomore' => 'Enää ei ole enempää kuvapelejä pelattavaksi.',
	'picturegame-minifeed-nomorecreatelink' => 'Luo sellainen!',
	'picturegame-most' => 'Eniten',
	'picturegame-mostheat' => 'Eniten kuumuutta',
	'picturegame-mostvotes' => 'Eniten ääniä',
	'picturegame-nomoretitle' => 'Ei enää kuvapelejä!',
	'picturegame-no-more' => 'Enää ei ole uusia kuvapelejä pelattavaksi.<br />
Älä murehdi, [{{fullurl:Special:PictureGameHome|picGameAction=startCreate}} luo omasi] tai [[Special:RandomPoll|ota osaa äänestyksiin!]]',
	'picturegame-create-threshold-title' => 'Luo kuvapeli',
	'picturegame-create-threshold-reason' => 'Pahoittelut, et voi luoda kuvapeliä ennen kuin sinulla on ainakin $1',
	'picturegame-next' => 'seur.',
	'picturegame-prev' => 'edell.',
	'picturegame-gallery' => 'Galleria',
	'picturegame-gallery-empty' => 'Kuvapelin kuvia ei ole vielä yhtään.',
	'picturegame-empty' => 'Tietokannassa ei ole kuvapelejä. Voit [{{fullurl:Special:PictureGameHome|picGameAction=startCreate}} luoda uuden kuvapelin!]',
	'picturegame-nothing-to-edit' => 'Täällä ei ole mitään muokattavaa.',
	'picturegame-images-category' => 'Kuvapelit',
	'picturegame-none' => 'ei ole',
	'picturegame-js-edit' => 'Muokkaa',
	'picturegame-js-error-title' => 'Anna otsikko!',
	'picturegame-js-error-upload-imgone' => 'Tallenna ensimmäinen kuva!',
	'picturegame-js-error-upload-imgtwo' => 'Tallenna toinen kuva!',
	'picturegame-js-editing-imgone' => 'Muokataan ensimmäistä kuvaa',
	'picturegame-js-editing-imgtwo' => 'Muokataan toista kuvaa',
	'right-picturegameadmin' => 'Ylläpitää kuvapelejä',
);

/** Dutch (Nederlands)
 * @author Mark van Alphen
 * @author Mitchel Corstjens
 */
$messages['nl'] = array(
	'picturegame-signup' => 'Word lid',
	'picturegame-login' => 'inloggen',
	'picturegame-submittedby' => 'Gemaakt door',
	'picturegame-reportimages' => 'Meld afbeeldingen',
	'picturegame-permalink' => 'Permalink',
	'picturegame-adminpanel' => 'Beheerderspanel',
	'picturegame-protectimages' => 'Beveilig afbeeldingen',
	'picturegame-createlink' => 'Maak een afbeelding spel',
	'picturegame-skipbutton' => 'Sla over',
	'picturegame-backbutton' => 'Ga terug',
	'picturegame-previousgame' => 'Vorige spel',
	'picturegame-adminpaneltitle' => 'Afbeelding spel beheerder paneel',
	'picturegame-adminpanelflagged' => 'Gemarkeerde afbeeldingen:',
	'picturegame-adminpanelprotected' => 'Beveiligde afbeeldingen:',
	'picturegame-adminpanelreinstate' => 'Opnieuw',
	'picturegame-adminpaneldelete' => 'Verwijder',
	'picturegame-adminpanelunprotect' => 'Beveiliging opheffen',
	'picturegame-adminpanelbacktogame' => '&lt; Terug naar afbeelding spel',
	'picturegame-creategametitle' => 'Maak een afbeelding spel',
	'picturegame-creategamenotloggedin' => 'Je moet ingelogd zijn om een afbeelding spel te maken.',
	'picturegame-creategamewelcome' => 'Upload twee afbeeldingen, voeg wat onderschriften toe, en doe gek en ga op andermans afbeeldingen stemmen. Zo gemakkelijk is het.',
	'picturegame-creategameplayinstead' => 'Speel in plaats van dit het spel',
	'picturegame-creategamegametitle' => 'Afbeelding spel titel',
	'picturegame-creategamecaptiontext' => 'Onderschrift',
	'picturegame-creategamefiletext' => 'Bestand',
	'picturegame-creategamecreateplay' => 'Maak en speel!',
	'picturegame-createeditfirstimage' => 'Eerste afbeelding',
	'picturegame-createeditsecondimage' => 'Tweede afbeelding',
	'picturegame-editgameediting' => 'Bewerken',
	'picturegame-editgame-editing-title' => 'Bezig met bewerken van $1',
	'picturegame-editgamegametitle' => 'Titel',
	'picturegame-editgameuploadtext' => 'Upload nieuwe afbeelding',
	'picturegame-flagimgconfirm' => 'Weet je zeker dat je deze afbeeldingen wilt melden?',
	'picturegame-protectimgconfirm' => 'Weet je zeker dat je deze afbeeldingen wil beveiligen?',
	'picturegame-buttonupload' => 'Upload',
	'picturegame-buttonsubmit' => 'Sla op',
	'picturegame-buttoncancel' => 'Annuleer',
	'picturegame-buttonplaygame' => 'Speel het afbeelding spel',
	'picturegame-permalinkflagged' => 'Deze afbeeldingen zijn gemarkeerd, in verband met ongepaste inhoud of copyright schending. Om het afbeelding spel te spelen, klik op onderstaande knop.',
	'picturegame-sysmsg-unauthorized' => 'Je bent niet geautoriseerd om dat te doen.',
	'picturegame-sysmsg-blocked' => 'Je bent momenteel geblokkeerd en kan geen afbeelding spel toevoegen.',
	'picturegame-sysmsg-badkey' => 'Fatale fout: Je sleutel is fout.',
	'picturegame-sysmsg-successfuldelete' => 'Je hebt succesvol dit afbeelding spel verwijderd!',
	'picturegame-sysmsg-unsuccessfuldelete' => 'Verwijderen $1 van MediaWiki mislukt!',
	'picturegame-sysmsg-unflag' => 'Je hebt deze afbeeldingen terug in circulatie geplaatst!',
	'picturegame-sysmsg-flag' => 'De afbeeldingen zijn gemeld!',
	'picturegame-sysmsg-unprotect' => 'De beveiliging van de afbeeldingen is opgeheven!',
	'picturegame-sysmsg-protect' => 'De afbeeldingen zijn beveiligd!',
	'picturegame-least' => 'Minste',
	'picturegame-leastheat' => 'Minste hitte',
	'picturegame-leastvotes' => 'Minste stemmen',
	'picturegame-minifeed-nomore' => 'Er zijn geen nieuwe afbeelding spellen om te spelen.',
	'picturegame-minifeed-nomorecreatelink' => 'Maak er een!',
	'picturegame-most' => 'Meeste',
	'picturegame-mostheat' => 'Meeste hitte',
	'picturegame-mostvotes' => 'Meeste stemmen',
	'picturegame-nomoretitle' => 'Geen afbeelding spelen meer!',
	'picturegame-no-more' => 'Er zijn vandaag geen afbeelding spellen om te spelen.<br />
Word niet treurig, [{{fullurl:Special:PictureGameHome|picGameAction=startCreate}} maak je geheel eigen] of [[Special:RandomPoll|neem wat polls!]]',
	'picturegame-create-threshold-title' => 'Maak afbeelding spel',
	'picturegame-create-threshold-reason' => 'Sorry, je kan geen afbeelding spel maken tot je tenminste $1 hebt',
	'picturegame-next' => 'volgende',
	'picturegame-prev' => 'vorige',
	'picturegame-sorted-most-heat' => 'Afbeelding spellen sorteren op meest populair',
	'picturegame-sorted-most-votes' => 'Afbeelding spellen sorteren op meeste stemmen',
	'picturegame-sorted-least-heat' => 'Afbeelding spellen sorteren op minst populair',
	'picturegame-sorted-least-votes' => 'Afbeelding spellen sorteren op minste stemmen',
	'picturegame-gallery' => 'galerij',
	'picturegame-gallery-empty' => 'Er zijn nog geen afbeelding voor het spel.',
	'picturegame-empty' => 'Er zijn nog geen afbeelding spellen in de database, u kunt [{{fullurl:Special:PictureGameHome|picGameAction=startCreate}} hier een afbeelding spel maken!]',
	'picturegame-nothing-to-edit' => 'Er is hier niets te bewerken.',
	'picturegame-images-category' => 'Afbeelding spellen',
	'picturegame-none' => 'geen',
	'picturegame-js-edit' => 'Bewerken',
	'picturegame-js-error-title' => 'Voer aub een titel in.',
	'picturegame-js-error-upload-imgone' => 'U moet afbeelding 1 uploaden',
	'picturegame-js-error-upload-imgtwo' => 'U moet afbeelding 2 uploaden',
	'picturegame-js-editing-imgone' => 'Afbeelding 1 bewerken',
	'picturegame-js-editing-imgtwo' => 'Afbeelding 2 bewerken',
	'right-picturegameadmin' => 'Beheer afbeelding spellen',
);