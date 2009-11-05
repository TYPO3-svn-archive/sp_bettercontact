<?php

########################################################################
# Extension Manager/Repository config file for ext: "sp_bettercontact"
#
# Auto generated 05-11-2009 09:27
#
# Manual updates:
# Only the data in the array - anything else is removed by next write.
# "version" and "dependencies" must not be touched!
########################################################################

$EM_CONF[$_EXTKEY] = array(
	'title' => 'Better Contact',
	'description' => 'Secure Contact form with solid Spam protection. Input can be checked for length, allowed and disallowed signs and with Regular Expressions. Attackers can be locked if they try to send a lot of mails back-to-back. Admin can get detailed Spam notifications. Captcha support included.',
	'category' => 'plugin',
	'shy' => 0,
	'version' => '2.3.0',
	'dependencies' => '',
	'conflicts' => '',
	'priority' => '',
	'loadOrder' => '',
	'module' => '',
	'state' => 'stable',
	'uploadfolder' => 0,
	'createDirs' => '',
	'modify_tables' => '',
	'clearcacheonload' => 0,
	'lockType' => '',
	'author' => 'Kai Vogel',
	'author_email' => 'kai.vogel ( at ) speedprogs.de',
	'author_company' => 'www.speedprogs.de',
	'CGLcompliance' => '',
	'CGLcompliance_note' => '',
	'constraints' => array(
		'depends' => array(
			'php' => '5.2.0-0.0.0',
			'typo3' => '4.1.0-0.0.0',
		),
		'conflicts' => array(
		),
		'suggests' => array(
		),
	),
	'_md5_values_when_last_written' => 'a:27:{s:9:"ChangeLog";s:4:"348e";s:12:"ext_icon.gif";s:4:"e37d";s:17:"ext_localconf.php";s:4:"83c8";s:14:"ext_tables.php";s:4:"1abe";s:14:"ext_tables.sql";s:4:"936f";s:24:"ext_typoscript_setup.txt";s:4:"46a8";s:13:"locallang.xml";s:4:"7e5d";s:14:"doc/manual.sxw";s:4:"a2ab";s:24:"res/templates/email.html";s:4:"3632";s:23:"res/templates/form.html";s:4:"19fb";s:28:"res/templates/stylesheet.css";s:4:"ec39";s:37:"res/examples/additional_locallang.xml";s:4:"805e";s:45:"res/examples/checkbox_radiobutton_select.html";s:4:"57df";s:29:"res/examples/more_fields.html";s:4:"db1d";s:45:"res/examples/show_messages_as_list_above.html";s:4:"7775";s:25:"res/fallback/flexform.xml";s:4:"b85d";s:19:"res/images/list.gif";s:4:"46c2";s:20:"res/images/popup.gif";s:4:"1ec5";s:21:"res/images/wizard.gif";s:4:"7c0b";s:36:"pi1/class.tx_spbettercontact_pi1.php";s:4:"1d23";s:42:"pi1/class.tx_spbettercontact_pi1_check.php";s:4:"9443";s:42:"pi1/class.tx_spbettercontact_pi1_email.php";s:4:"9177";s:45:"pi1/class.tx_spbettercontact_pi1_flexform.php";s:4:"a792";s:44:"pi1/class.tx_spbettercontact_pi1_session.php";s:4:"de55";s:45:"pi1/class.tx_spbettercontact_pi1_template.php";s:4:"c27f";s:44:"pi1/class.tx_spbettercontact_pi1_wizicon.php";s:4:"3e54";s:17:"pi1/locallang.xml";s:4:"9f0d";}',
);

?>