<?php
/**
 * @file include/text.php
 */

use \Zotlabs\Lib as Zlib;
use \Michelf\MarkdownExtra;

require_once("include/bbcode.php");

// random string, there are 86 characters max in text mode, 128 for hex
// output is urlsafe

define('RANDOM_STRING_HEX',  0x00 );
define('RANDOM_STRING_TEXT', 0x01 );

/**
 * @brief This is our template processor.
 *
 * @param string|SmartyEngine $s the string requiring macro substitution,
 *   or an instance of SmartyEngine
 * @param array $r key value pairs (search => replace)
 *
 * @return string substituted string
 */
function replace_macros($s, $r) {
	$arr = [
			'template' => $s,
			'params' => $r
	];

	/**
	 * @hooks replace_macros
	 *   * \e string \b template
	 *   * \e array \b params
	 */
	call_hooks('replace_macros', $arr);

	$t = App::template_engine();
	$output = $t->replace_macros($arr['template'], $arr['params']);

	return $output;
}

/**
 * @brief Generates a random string.
 *
 * @param number $size
 * @param int $type
 *
 * @return string
 */
function random_string($size = 64, $type = RANDOM_STRING_HEX) {
	// generate a bit of entropy and run it through the whirlpool
	$s = hash('whirlpool', (string) rand() . uniqid(rand(),true) . (string) rand(),(($type == RANDOM_STRING_TEXT) ? true : false));
	$s = (($type == RANDOM_STRING_TEXT) ? str_replace("\n","",base64url_encode($s,true)) : $s);

	return(substr($s, 0, $size));
}

/**
 * @brief This is our primary input filter.
 *
 * The high bit hack only involved some old IE browser, forget which (IE5/Mac?)
 * that had an XSS attack vector due to stripping the high-bit on an 8-bit character
 * after cleansing, and angle chars with the high bit set could get through as markup.
 *
 * This is now disabled because it was interfering with some legitimate unicode sequences
 * and hopefully there aren't a lot of those browsers left.
 *
 * Use this on any text input where angle chars are not valid or permitted
 * They will be replaced with safer brackets. This may be filtered further
 * if these are not allowed either.
 *
 * @param string $string Input string
 *
 * @return string Filtered string
 */
function notags($string) {

	return(str_replace(array("<",">"), array('[',']'), $string));

//  High-bit filter no longer used
//	return(str_replace(array("<",">","\xBA","\xBC","\xBE"), array('[',']','','',''), $string));
}


/**
 * use this on "body" or "content" input where angle chars shouldn't be removed,
 * and allow them to be safely displayed.
 *
 * @param string $string
 *
 * @return string
 */
function escape_tags($string) {
	return(htmlspecialchars($string, ENT_COMPAT, 'UTF-8', false));
}


function z_input_filter($s,$type = 'text/bbcode',$allow_code = false) {

	if($type === 'text/bbcode')
		return escape_tags($s);
	if($type == 'text/plain')
		return escape_tags($s);
	if($type == 'application/x-pdl')
		return escape_tags($s);

	if(App::$is_sys) {
		return $s;
	}

	if($allow_code) {
		if($type === 'text/markdown')
			return htmlspecialchars($s,ENT_QUOTES);
		return $s;
	}

	if($type === 'text/markdown') {
		$x = new Zlib\MarkdownSoap($s);
		return $x->clean();
	}

	if($type === 'text/html')
		return purify_html($s);

	return escape_tags($s);
}


/**
 * @brief Use HTMLPurifier to get standards compliant HTML.
 *
 * Use the <a href="http://htmlpurifier.org/" target="_blank">HTMLPurifier</a>
 * library to get filtered and standards compliant HTML.
 *
 * @see HTMLPurifier
 *
 * @param string $s raw HTML
 * @param boolean $allow_position allow CSS position
 * @return string standards compliant filtered HTML
 */
function purify_html($s, $allow_position = false) {

/**
 * @FIXME this function has html output, not bbcode - so safely purify these
 * require_once('include/html2bbcode.php');
 * $s = html2bb_video($s);
 * $s = oembed_html2bbcode($s);
 */

	$config = HTMLPurifier_Config::createDefault();
	$config->set('Cache.DefinitionImpl', null);
	$config->set('Attr.EnableID', true);

	// If enabled, target=blank attributes are added to all links.
	//$config->set('HTML.TargetBlank', true);
	//$config->set('Attr.AllowedFrameTargets', ['_blank', '_self', '_parent', '_top']);
	// restore old behavior of HTMLPurifier < 4.8, only used when targets allowed at all
	// do not add rel="noreferrer" to all links with target attributes
	//$config->set('HTML.TargetNoreferrer', false);
	// do not add noopener rel attributes to links which have a target attribute associated with them
	//$config->set('HTML.TargetNoopener', false);

	//Allow some custom data- attributes used by built-in libs.
	//In this way members which do not have allowcode set can still use the built-in js libs in webpages to some extent.

	$def = $config->getHTMLDefinition(true);

	//data- attributes used by the foundation library

	// f6 navigation

	//dropdown menu
	$def->info_global_attr['data-dropdown-menu'] = new HTMLPurifier_AttrDef_Text;
	//drilldown menu
	$def->info_global_attr['data-drilldown'] = new HTMLPurifier_AttrDef_Text;
	//accordion menu
	$def->info_global_attr['data-accordion-menu'] = new HTMLPurifier_AttrDef_Text;
	//responsive navigation
	$def->info_global_attr['data-responsive-menu'] = new HTMLPurifier_AttrDef_Text;
	$def->info_global_attr['data-responsive-toggle'] = new HTMLPurifier_AttrDef_Text;
	//magellan
	$def->info_global_attr['data-magellan'] = new HTMLPurifier_AttrDef_Text;
	$def->info_global_attr['data-magellan-target'] = new HTMLPurifier_AttrDef_Text;

	// f6 containers

	//accordion
	$def->info_global_attr['data-accordion'] = new HTMLPurifier_AttrDef_Text;
	$def->info_global_attr['data-accordion-item'] = new HTMLPurifier_AttrDef_Text;
	$def->info_global_attr['data-tab-content'] = new HTMLPurifier_AttrDef_Text;
	//dropdown
	$def->info_global_attr['data-dropdown'] = new HTMLPurifier_AttrDef_Text;
	//off-canvas
	$def->info_global_attr['data-off-canvas-wrapper'] = new HTMLPurifier_AttrDef_Text;
	$def->info_global_attr['data-off-canvas'] = new HTMLPurifier_AttrDef_Text;
	$def->info_global_attr['data-off-canvas-content'] = new HTMLPurifier_AttrDef_Text;
	//reveal
	$def->info_global_attr['data-reveal'] = new HTMLPurifier_AttrDef_Text;
	//tabs
	$def->info_global_attr['data-tabs'] = new HTMLPurifier_AttrDef_Text;
	$def->info_global_attr['data-tabs-content'] = new HTMLPurifier_AttrDef_Text;

	// f6 media

	//orbit
	$def->info_global_attr['data-orbit'] = new HTMLPurifier_AttrDef_Text;
	$def->info_global_attr['data-slide'] = new HTMLPurifier_AttrDef_Text;
	//tooltip
	$def->info_global_attr['data-tooltip'] = new HTMLPurifier_AttrDef_Text;

	// f6 plugins

	//abide - the use is pointless since we can't do anything with forms

	//equalizer
	$def->info_global_attr['data-equalizer'] = new HTMLPurifier_AttrDef_Text;
	$def->info_global_attr['data-equalizer-watch'] = new HTMLPurifier_AttrDef_Text;

	//interchange - potentially dangerous since it can load content

	//toggler
	$def->info_global_attr['data-toggler'] = new HTMLPurifier_AttrDef_Text;

	//sticky
	$def->info_global_attr['data-sticky'] = new HTMLPurifier_AttrDef_Text;
	$def->info_global_attr['data-sticky-container'] = new HTMLPurifier_AttrDef_Text;

	// f6 common

	$def->info_global_attr['data-options'] = new HTMLPurifier_AttrDef_Text;
	$def->info_global_attr['data-toggle'] = new HTMLPurifier_AttrDef_Text;
	$def->info_global_attr['data-close'] = new HTMLPurifier_AttrDef_Text;
	$def->info_global_attr['data-open'] = new HTMLPurifier_AttrDef_Text;
	$def->info_global_attr['data-position'] = new HTMLPurifier_AttrDef_Text;


	//data- attributes used by the bootstrap library
	$def->info_global_attr['data-dismiss'] = new HTMLPurifier_AttrDef_Text;
	$def->info_global_attr['data-target'] = new HTMLPurifier_AttrDef_Text;
	$def->info_global_attr['data-toggle'] = new HTMLPurifier_AttrDef_Text;
	$def->info_global_attr['data-backdrop'] = new HTMLPurifier_AttrDef_Text;
	$def->info_global_attr['data-keyboard'] = new HTMLPurifier_AttrDef_Text;
	$def->info_global_attr['data-show'] = new HTMLPurifier_AttrDef_Text;
	$def->info_global_attr['data-spy'] = new HTMLPurifier_AttrDef_Text;
	$def->info_global_attr['data-offset'] = new HTMLPurifier_AttrDef_Text;
	$def->info_global_attr['data-animation'] = new HTMLPurifier_AttrDef_Text;
	$def->info_global_attr['data-container'] = new HTMLPurifier_AttrDef_Text;
	$def->info_global_attr['data-delay'] = new HTMLPurifier_AttrDef_Text;
	$def->info_global_attr['data-placement'] = new HTMLPurifier_AttrDef_Text;
	$def->info_global_attr['data-title'] = new HTMLPurifier_AttrDef_Text;
	$def->info_global_attr['data-trigger'] = new HTMLPurifier_AttrDef_Text;
	$def->info_global_attr['data-content'] = new HTMLPurifier_AttrDef_Text;
	$def->info_global_attr['data-trigger'] = new HTMLPurifier_AttrDef_Text;
	$def->info_global_attr['data-parent'] = new HTMLPurifier_AttrDef_Text;
	$def->info_global_attr['data-ride'] = new HTMLPurifier_AttrDef_Text;
	$def->info_global_attr['data-slide-to'] = new HTMLPurifier_AttrDef_Text;
	$def->info_global_attr['data-slide'] = new HTMLPurifier_AttrDef_Text;
	$def->info_global_attr['data-interval'] = new HTMLPurifier_AttrDef_Text;
	$def->info_global_attr['data-pause'] = new HTMLPurifier_AttrDef_Text;
	$def->info_global_attr['data-wrap'] = new HTMLPurifier_AttrDef_Text;
	$def->info_global_attr['data-offset-top'] = new HTMLPurifier_AttrDef_Text;
	$def->info_global_attr['data-offset-bottom'] = new HTMLPurifier_AttrDef_Text;

	//some html5 elements
	//Block
	$def->addElement('section', 'Block', 'Flow', 'Common');
	$def->addElement('nav',     'Block', 'Flow', 'Common');
	$def->addElement('article', 'Block', 'Flow', 'Common');
	$def->addElement('aside',   'Block', 'Flow', 'Common');
	$def->addElement('header',  'Block', 'Flow', 'Common');
	$def->addElement('footer',  'Block', 'Flow', 'Common');
	//Inline
	$def->addElement('button',  'Inline', 'Inline', 'Common');


	if($allow_position) {
		$cssDefinition = $config->getCSSDefinition();

		$cssDefinition->info['position'] = new HTMLPurifier_AttrDef_Enum(array('absolute', 'fixed', 'relative', 'static', 'inherit'), false);

		$cssDefinition->info['left'] = new HTMLPurifier_AttrDef_CSS_Composite(array(
			new HTMLPurifier_AttrDef_CSS_Length(),
			new HTMLPurifier_AttrDef_CSS_Percentage()
		));

		$cssDefinition->info['right'] = new HTMLPurifier_AttrDef_CSS_Composite(array(
			new HTMLPurifier_AttrDef_CSS_Length(),
			new HTMLPurifier_AttrDef_CSS_Percentage()
		));

		$cssDefinition->info['top'] = new HTMLPurifier_AttrDef_CSS_Composite(array(
			new HTMLPurifier_AttrDef_CSS_Length(),
			new HTMLPurifier_AttrDef_CSS_Percentage()
		));

		$cssDefinition->info['bottom'] = new HTMLPurifier_AttrDef_CSS_Composite(array(
			new HTMLPurifier_AttrDef_CSS_Length(),
			new HTMLPurifier_AttrDef_CSS_Percentage()
		));
	}

	$purifier = new HTMLPurifier($config);

	return $purifier->purify($s);
}


/**
 * @brief Generate a string that's random, but usually pronounceable.
 *
 * Used to generate initial passwords.
 *
 * @note In order to create "pronounceable" strings some consonant pairs or
 * letters that does not make a very good word ending are chopped off, so that
 * the returned string length can be lower than $len.
 *
 * @param int $len max length of generated string
 * @return string Genereated random, but usually pronounceable string
 */
function autoname($len) {

	if ($len <= 0)
		return '';

	$vowels = array('a','a','ai','au','e','e','e','ee','ea','i','ie','o','ou','u');
	if (mt_rand(0, 5) == 4)
		$vowels[] = 'y';

	$cons = array(
			'b','bl','br',
			'c','ch','cl','cr',
			'd','dr',
			'f','fl','fr',
			'g','gh','gl','gr',
			'h',
			'j',
			'k','kh','kl','kr',
			'l',
			'm',
			'n',
			'p','ph','pl','pr',
			'qu',
			'r','rh',
			's','sc','sh','sm','sp','st',
			't','th','tr',
			'v',
			'w','wh',
			'x',
			'z','zh'
			);

	$midcons = array('ck','ct','gn','ld','lf','lm','lt','mb','mm', 'mn','mp',
				'nd','ng','nk','nt','rn','rp','rt');

	// avoid these consonant pairs at the end of the string
	$noend = array('bl', 'br', 'cl','cr','dr','fl','fr','gl','gr',
				'kh', 'kl','kr','mn','pl','pr','rh','tr','qu','wh');

	$start = mt_rand(0, 2);
	if ($start == 0)
		$table = $vowels;
	else
		$table = $cons;

	$word = '';

	for ($x = 0; $x < $len; $x ++) {
		$r = mt_rand(0, count($table) - 1);
		$word .= $table[$r];

		if ($table == $vowels)
			$table = array_merge($cons, $midcons);
		else
			$table = $vowels;
	}

	$word = substr($word, 0, $len);

	foreach ($noend as $noe) {
		if ((strlen($word) > 2) && (substr($word, -2) == $noe)) {
			$word = substr($word, 0, -1);
			break;
		}
	}
	// avoid the letter 'q' as it does not make a very good word ending
	if (substr($word, -1) == 'q')
		$word = substr($word, 0, -1);

	return $word;
}


/**
 * @brief escape text ($str) for XML transport
 *
 * @param string $str
 * @return string Escaped text.
 */
function xmlify($str) {
	$buffer = '';

	if(is_array($str)) {

		// allow to fall through so we ge a PHP error, as the log statement will
		// probably get lost in the noise unless we're specifically looking for it.

		btlogger('xmlify called with array: ' . print_r($str,true), LOGGER_NORMAL, LOG_WARNING);
	}

	$len = mb_strlen($str);
	for($x = 0; $x < $len; $x ++) {
		$char = mb_substr($str,$x,1);

		switch( $char ) {
			case "\r" :
				break;
			case "&" :
				$buffer .= '&amp;';
				break;
			case "'" :
				$buffer .= '&apos;';
				break;
			case "\"" :
				$buffer .= '&quot;';
				break;
			case '<' :
				$buffer .= '&lt;';
				break;
			case '>' :
				$buffer .= '&gt;';
				break;
			case "\n" :
				$buffer .= "\n";
				break;
			default :
				$buffer .= $char;
				break;
		}
	}
	$buffer = trim($buffer);

	return($buffer);
}

/**
 * @brief Undo an xmlify.
 *
 * Pass xml escaped text ($s), returns unescaped text.
 *
 * @param string $s
 *
 * @return string
 */
function unxmlify($s) {
	$ret = str_replace('&amp;', '&', $s);
	$ret = str_replace(array('&lt;', '&gt;', '&quot;', '&apos;'), array('<', '>', '"', "'"), $ret);

	return $ret;
}

/**
 * @brief Automatic pagination.
 *
 * To use, get the count of total items.
 * Then call App::set_pager_total($number_items);
 * Optionally call App::set_pager_itemspage($n) to the number of items to display on each page
 * Then call paginate($a) after the end of the display loop to insert the pager block on the page
 * (assuming there are enough items to paginate).
 * When using with SQL, the setting LIMIT %d, %d => App::$pager['start'],App::$pager['itemspage']
 * will limit the results to the correct items for the current page.
 * The actual page handling is then accomplished at the application layer.
 *
 * @param App &$a
 */
function paginate(&$a) {
	$o = '';
	$stripped = preg_replace('/(&page=[0-9]*)/','',App::$query_string);

//	$stripped = preg_replace('/&zid=(.*?)([\?&]|$)/ism','',$stripped);

	$stripped = str_replace('q=','',$stripped);
	$stripped = trim($stripped,'/');
	$pagenum = App::$pager['page'];
	$url = z_root() . '/' . $stripped;

	if(App::$pager['total'] > App::$pager['itemspage']) {
		$o .= '<div class="pager">';
		if(App::$pager['page'] != 1)
			$o .= '<span class="pager_prev">'."<a href=\"$url".'&page='.(App::$pager['page'] - 1).'">' . t('prev') . '</a></span> ';

		$o .=  "<span class=\"pager_first\"><a href=\"$url"."&page=1\">" . t('first') . "</a></span> ";

		$numpages = App::$pager['total'] / App::$pager['itemspage'];

		$numstart = 1;
		$numstop = $numpages;

		if($numpages > 14) {
			$numstart = (($pagenum > 7) ? ($pagenum - 7) : 1);
			$numstop = (($pagenum > ($numpages - 7)) ? $numpages : ($numstart + 14));
		}

		for($i = $numstart; $i <= $numstop; $i++){
			if($i == App::$pager['page'])
				$o .= '<span class="pager_current">'.(($i < 10) ? '&nbsp;'.$i : $i);
			else
				$o .= "<span class=\"pager_n\"><a href=\"$url"."&page=$i\">".(($i < 10) ? '&nbsp;'.$i : $i)."</a>";
			$o .= '</span> ';
		}

		if((App::$pager['total'] % App::$pager['itemspage']) != 0) {
			if($i == App::$pager['page'])
				$o .= '<span class="pager_current">'.(($i < 10) ? '&nbsp;'.$i : $i);
			else
				$o .= "<span class=\"pager_n\"><a href=\"$url"."&page=$i\">".(($i < 10) ? '&nbsp;'.$i : $i)."</a>";
			$o .= '</span> ';
		}

		$lastpage = (($numpages > intval($numpages)) ? intval($numpages)+1 : $numpages);
		$o .= "<span class=\"pager_last\"><a href=\"$url"."&page=$lastpage\">" . t('last') . "</a></span> ";

		if((App::$pager['total'] - (App::$pager['itemspage'] * App::$pager['page'])) > 0)
			$o .= '<span class="pager_next">'."<a href=\"$url"."&page=".(App::$pager['page'] + 1).'">' . t('next') . '</a></span>';
		$o .= '</div>'."\r\n";
	}

	return $o;
}


function alt_pager(&$a, $i, $more = '', $less = '') {

	if(! $more)
		$more = t('older');
	if(! $less)
		$less = t('newer');

	$stripped = preg_replace('/(&page=[0-9]*)/','',App::$query_string);
	$stripped = str_replace('q=','',$stripped);
	$stripped = trim($stripped,'/');
	//$pagenum = App::$pager['page'];
	$url = z_root() . '/' . $stripped;

	return replace_macros(get_markup_template('alt_pager.tpl'), array(
		'$has_less' => ((App::$pager['page'] > 1) ? true : false),
		'$has_more' => (($i > 0 && $i >= App::$pager['itemspage']) ? true : false),
		'$less' => $less,
		'$more' => $more,
		'$url' => $url,
		'$prevpage' => App::$pager['page'] - 1,
		'$nextpage' => App::$pager['page'] + 1,
	));

}


/**
 * @brief Generate a guaranteed unique (for this domain) item ID for ATOM.
 *
 * Safe from birthday paradox.
 *
 * @return string a unique id
 */
function item_message_id() {
	do {
		$dups = false;
		$hash = random_string();
		$mid = $hash . '@' . App::get_hostname();

		$r = q("SELECT id FROM item WHERE mid = '%s' LIMIT 1",
			dbesc($mid));
		if(count($r))
			$dups = true;
	} while($dups == true);

	return $mid;
}

/**
 * @brief Generate a guaranteed unique photo ID.
 *
 * Safe from birthday paradox.
 *
 * @return string a uniqe hash
 */
function photo_new_resource() {
	do {
		$found = false;
		$resource = hash('md5', uniqid(mt_rand(), true));

		$r = q("SELECT id FROM photo WHERE resource_id = '%s' LIMIT 1",
			dbesc($resource));
		if(count($r))
			$found = true;
	} while($found === true);

	return $resource;
}

/**
 * @brief
 *
 * for html,xml parsing - let's say you've got
 * an attribute foobar="class1 class2 class3"
 * and you want to find out if it contains 'class3'.
 * you can't use a normal sub string search because you
 * might match 'notclass3' and a regex to do the job is
 * possible but a bit complicated.
 *
 * pass the attribute string as $attr and the attribute you
 * are looking for as $s - returns true if found, otherwise false
 *
 * @param string $attr attribute string
 * @param string $s attribute you are looking for
 * @return boolean true if found
 */
function attribute_contains($attr, $s) {
	// remove quotes
	$attr = str_replace([ '"',"'" ],['',''],$attr);
	$a = explode(' ', $attr);
	if($a && in_array($s, $a))
		return true;

	return false;
}

/**
 * @brief Logging function for Hubzilla.
 *
 * Logging output is configured through Hubzilla's system config. The log file
 * is set in system logfile, log level in system loglevel and to enable logging
 * set system debugging.
 *
 * Available constants for log level are LOGGER_NORMAL, LOGGER_TRACE, LOGGER_DEBUG,
 * LOGGER_DATA and LOGGER_ALL.
 *
 * Since PHP5.4 we get the file, function and line automatically where the logger
 * was called, so no need to add it to the message anymore.
 *
 * @param string $msg Message to log
 * @param int $level A log level
 * @param int $priority - compatible with syslog
 */
function logger($msg, $level = LOGGER_NORMAL, $priority = LOG_INFO) {

	if(App::$module == 'setup' && is_writable('install.log')) {
		$debugging = true;
		$logfile = 'install.log';
		$loglevel = LOGGER_ALL;
	}
	else {
		$debugging = get_config('system', 'debugging');
		$loglevel  = intval(get_config('system', 'loglevel'));
		$logfile   = get_config('system', 'logfile');
	}

	if((! $debugging) || (! $logfile) || ($level > $loglevel))
		return;

	$where = '';

	$stack = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
	$where = basename($stack[0]['file']) . ':' . $stack[0]['line'] . ':' . $stack[1]['function'] . ': ';

	$s = datetime_convert('UTC','UTC', 'now', ATOM_TIME) . ':' . log_priority_str($priority) . ':' . session_id() . ':' . $where . $msg . PHP_EOL;
	$pluginfo = array('filename' => $logfile, 'loglevel' => $level, 'message' => $s,'priority' => $priority, 'logged' => false);

	if(! (App::$module == 'setup'))
		call_hooks('logger',$pluginfo);

	if(! $pluginfo['logged'])
		@file_put_contents($pluginfo['filename'], $pluginfo['message'], FILE_APPEND);
}

/**
 * @brief like logger() but with a function backtrace to pinpoint certain classes
 * of problems which show up deep in the calling stack.
 *
 * @param string $msg Message to log
 * @param int $level A log level
 * @param int $priority - compatible with syslog
 */
function btlogger($msg, $level = LOGGER_NORMAL, $priority = LOG_INFO) {

	if(! defined('BTLOGGER_DEBUG_FILE'))
		define('BTLOGGER_DEBUG_FILE','btlogger.out');

	logger($msg, $level, $priority);

	if(file_exists(BTLOGGER_DEBUG_FILE) && is_writable(BTLOGGER_DEBUG_FILE)) {
		$stack = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
		$where = basename($stack[0]['file']) . ':' . $stack[0]['line'] . ':' . $stack[1]['function'] . ': ';
		$s = datetime_convert('UTC','UTC', 'now', ATOM_TIME) . ':' . log_priority_str($priority) . ':' . session_id() . ':' . $where . $msg . PHP_EOL;
		@file_put_contents(BTLOGGER_DEBUG_FILE, $s, FILE_APPEND);
	}

	$stack = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
	if($stack) {
		for($x = 1; $x < count($stack); $x ++) {
			$s = 'stack: ' . basename($stack[$x]['file']) . ':' . $stack[$x]['line'] . ':' . $stack[$x]['function'] . '()';
			logger($s,$level, $priority);

			if(file_exists(BTLOGGER_DEBUG_FILE) && is_writable(BTLOGGER_DEBUG_FILE)) {
				@file_put_contents(BTLOGGER_DEBUG_FILE, $s . PHP_EOL, FILE_APPEND);
			}
		}
	}
}



function log_priority_str($priority) {
	$parr = array(
		LOG_EMERG   => 'LOG_EMERG',
		LOG_ALERT   => 'LOG_ALERT',
		LOG_CRIT    => 'LOG_CRIT',
		LOG_ERR     => 'LOG_ERR',
		LOG_WARNING => 'LOG_WARNING',
		LOG_NOTICE  => 'LOG_NOTICE',
		LOG_INFO    => 'LOG_INFO',
		LOG_DEBUG   => 'LOG_DEBUG'
	);

	if($parr[$priority])
		return $parr[$priority];
	return 'LOG_UNDEFINED';
}

/**
 * @brief This is a special logging facility for developers.
 *
 * It allows one to target specific things to trace/debug and is identical to
 * logger() with the exception of the log filename. This allows one to isolate
 * specific calls while allowing logger() to paint a bigger picture of overall
 * activity and capture more detail.
 *
 * If you find dlogger() calls in checked in code, you are free to remove them -
 * so as to provide a noise-free development environment which responds to events
 * you are targetting personally.
 *
 * @param string $msg Message to log
 * @param int $level A log level.
 */
function dlogger($msg, $level = 0) {

	// turn off logger in install mode

	if(App::$module == 'setup')
		return;

	$debugging = get_config('system','debugging');
	$loglevel  = intval(get_config('system','loglevel'));
	$logfile   = get_config('system','dlogfile');

	if((! $debugging) || (! $logfile) || ($level > $loglevel))
		return;

	$where = '';

	$stack = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
	$where = basename($stack[0]['file']) . ':' . $stack[0]['line'] . ':' . $stack[1]['function'] . ': ';


	@file_put_contents($logfile, datetime_convert('UTC','UTC', 'now', ATOM_TIME) . ':' . session_id() . ' ' . $where . $msg . PHP_EOL, FILE_APPEND);
}


function profiler($t1,$t2,$label) {
	if(file_exists('profiler.out') && $t1 && t2)
		@file_put_contents('profiler.out', sprintf('%01.4f %s',$t2 - $t1,$label) . PHP_EOL, FILE_APPEND);
}


function activity_match($haystack,$needle) {

	if(! is_array($needle))
		$needle = [ $needle ];

	if($needle) {
		foreach($needle as $n) {
			if(($haystack === $n) || (strtolower(basename($n)) === strtolower(basename($haystack)))) {
				return true;
			}
		}
	}
	return false;
}

/**
 * @brief Pull out all \#hashtags and \@person tags from $s.
 *
 * We also get \@person\@domain.com - which would make
 * the regex quite complicated as tags can also
 * end a sentence. So we'll run through our results
 * and strip the period from any tags which end with one.
 *
 * @param string $s
 * @return Returns array of tags found, or empty array.
 */
function get_tags($s) {
	$ret = array();
	$match = array();

	// ignore anything in a code block

	$s = preg_replace('/\[code(.*?)\](.*?)\[\/code\]/sm','',$s);

	// ignore anything in [style= ]
	$s = preg_replace('/\[style=(.*?)\]/sm','',$s);

	// ignore anything in [color= ], because it may contain color codes which are mistaken for tags
	$s = preg_replace('/\[color=(.*?)\]/sm','',$s);

	// match any double quoted tags

	if(preg_match_all('/([@#!]\&quot\;.*?\&quot\;)/',$s,$match)) {
		foreach($match[1] as $mtch) {
			$ret[] = $mtch;
		}
	}

	// Match full names against @tags including the space between first and last
	// We will look these up afterward to see if they are full names or not recognisable.

	// The lookbehind is used to prevent a match in the middle of a word
	// '=' needs to be avoided because when the replacement is made (in handle_tag()) it has to be ignored there
	// Feel free to allow '=' if the issue with '=' is solved in handle_tag()
	// added / ? and [ to avoid issues with hashchars in url paths

	// added ; to single word tags to allow emojis and other unicode character constructs in bbcode
	// (this would actually be &#xnnnnn; but the ampersand will have been escaped to &amp; by the time we see it.)

	if(preg_match_all('/(?<![a-zA-Z0-9=\pL\/\?])(@[^ \x0D\x0A,:?\[]+ [^ \x0D\x0A@,:?\[]+)/u',$s,$match)) {
		foreach($match[1] as $mtch) {
			if(substr($mtch,-1,1) === '.')
				$ret[] = substr($mtch,0,-1);
			else
				$ret[] = $mtch;
		}
	}

	// Otherwise pull out single word tags. These can be @nickname, @first_last
	// and #hash tags.

	if(preg_match_all('/(?<![a-zA-Z0-9=\pL\/\?\;])([@#\!][^ \x0D\x0A,;:?\[]+)/u',$s,$match)) {
		foreach($match[1] as $mtch) {
			if(substr($mtch,-1,1) === '.')
				$mtch = substr($mtch,0,-1);
			// ignore strictly numeric tags like #1 or #^ bookmarks or ## double hash
			if((strpos($mtch,'#') === 0) && ( ctype_digit(substr($mtch,1)) || substr($mtch,1,1) === '^') || substr($mtch,1,1) === '#')
				continue;
			// or quote remnants from the quoted strings we already picked out earlier
			if(strpos($mtch,'&quot'))
				continue;

			$ret[] = $mtch;
		}
	}

	// bookmarks

	if(preg_match_all('/#\^\[(url|zrl)(.*?)\](.*?)\[\/(url|zrl)\]/',$s,$match,PREG_SET_ORDER)) {
		foreach($match as $mtch) {
			$ret[] = $mtch[0];
		}
	}

	// make sure the longer tags are returned first so that if two or more have common substrings
	// we'll replace the longest ones first. Otherwise the common substring would be found in
	// both strings and the string replacement would link both to the shorter strings and
	// fail to link the longer string. Hubzilla github issue #378

	usort($ret,'tag_sort_length');

//	logger('get_tags: ' . print_r($ret,true));

	return $ret;
}

function tag_sort_length($a,$b) {
	if(mb_strlen($a) == mb_strlen($b))
		return 0;

	return((mb_strlen($b) < mb_strlen($a)) ? (-1) : 1);
}

function total_sort($a,$b) {
	if($a['total'] == $b['total'])
		return 0;
	return(($b['total'] > $a['total']) ? 1 : (-1));
}


/**
 * @brief Quick and dirty quoted_printable encoding.
 *
 * @param string $s
 * @return string
 */
function qp($s) {
	return str_replace ("%", "=", rawurlencode($s));
}


function get_mentions($item,$tags) {
	$o = '';

	if(! count($tags))
		return $o;

	foreach($tags as $x) {
		if($x['ttype'] == TERM_MENTION) {
			$o .= "\t\t" . '<link rel="mentioned" href="' . $x['url'] . '" />' . "\r\n";
			$o .= "\t\t" . '<link rel="ostatus:attention" href="' . $x['url'] . '" />' . "\r\n";
		}
	}
	return $o;
}


function contact_block() {
	$o = '';

	if(! App::$profile['uid'])
		return;

	if(! perm_is_allowed(App::$profile['uid'],get_observer_hash(),'view_contacts'))
		return;

	$shown = get_pconfig(App::$profile['uid'],'system','display_friend_count');

	if($shown === false)
		$shown = 25;
	if($shown == 0)
		return;

	$is_owner = ((local_channel() && local_channel() == App::$profile['uid']) ? true : false);
	$sql_extra = '';

	$abook_flags = " and abook_pending = 0 and abook_self = 0 ";

	if(! $is_owner) {
		$abook_flags .= " and abook_hidden = 0 ";
		$sql_extra = " and xchan_hidden = 0 ";
	}

	if((! is_array(App::$profile)) || (App::$profile['hide_friends']))
		return $o;

	$r = q("SELECT COUNT(abook_id) AS total FROM abook left join xchan on abook_xchan = xchan_hash WHERE abook_channel = %d
		$abook_flags and xchan_orphan = 0 and xchan_deleted = 0 $sql_extra",
		intval(App::$profile['uid'])
	);
	if(count($r)) {
		$total = intval($r[0]['total']);
	}
	if(! $total) {
		$contacts = t('No connections');
		$micropro = null;
	} else {

		$randfunc = db_getfunc('RAND');

		$r = q("SELECT abook.*, xchan.* FROM abook left join xchan on abook.abook_xchan = xchan.xchan_hash WHERE abook_channel = %d $abook_flags and abook_archived = 0 and xchan_orphan = 0 and xchan_deleted = 0 $sql_extra ORDER BY $randfunc LIMIT %d",
			intval(App::$profile['uid']),
			intval($shown)
		);

		if(count($r)) {
			$contacts = t('Connections');
			$micropro = Array();
			foreach($r as $rr) {

				// There is no setting to discover if you are bi-directionally connected
				// Use the ability to post comments as an indication that this relationship is more
				// than wishful thinking; even though soapbox channels and feeds will disable it. 

				if(! intval(get_abconfig(App::$profile['uid'],$rr['xchan_hash'],'their_perms','post_comments'))) {
					$rr['oneway'] = true;
				}
				$micropro[] = micropro($rr,true,'mpfriend');
			}
		}
	}

	$tpl = get_markup_template('contact_block.tpl');
	$o = replace_macros($tpl, array(
		'$contacts' => $contacts,
		'$nickname' => App::$profile['channel_address'],
		'$viewconnections' => (($total > $shown) ? sprintf(t('View all %s connections'),$total) : ''),
		'$micropro' => $micropro,
	));

	$arr = array('contacts' => $r, 'output' => $o);

	call_hooks('contact_block_end', $arr);
	return $o;
}


function chanlink_hash($s) {
	return z_root() . '/chanview?f=&hash=' . urlencode($s);
}

function chanlink_url($s) {
	return z_root() . '/chanview?f=&url=' . urlencode($s);
}


function chanlink_cid($d) {
	return z_root() . '/chanview?f=&cid=' . intval($d);
}

function magiclink_url($observer,$myaddr,$url) {
	return (($observer)
		? z_root() . '/magic?f=&owa=1&dest=' . $url . '&addr=' . $myaddr
		: $url
	);
}



function micropro($contact, $redirect = false, $class = '', $textmode = false) {

	if($contact['click'])
		$url = '#';
	else
		$url = chanlink_hash($contact['xchan_hash']);

	return replace_macros(get_markup_template(($textmode)?'micropro_txt.tpl':'micropro_img.tpl'),array(
		'$click' => (($contact['click']) ? $contact['click'] : ''),
		'$class' => $class . (($contact['archived']) ? ' archived' : ''),
		'$oneway' => (($contact['oneway']) ? true : false),
		'$url' => $url,
		'$photo' => $contact['xchan_photo_s'],
		'$name' => $contact['xchan_name'],
		'$title' => $contact['xchan_name'] . ' [' . $contact['xchan_addr'] . ']',
	));
}


function search($s,$id='search-box',$url='/search',$save = false) {

	return replace_macros(get_markup_template('searchbox.tpl'),array(
		'$s' => $s,
		'$id' => $id,
		'$action_url' => z_root() . $url,
		'$search_label' => t('Search'),
		'$save_label' => t('Save'),
		'$savedsearch' => feature_enabled(local_channel(),'savedsearch')
	));
}


function searchbox($s,$id='search-box',$url='/search',$save = false) {
	return replace_macros(get_markup_template('searchbox.tpl'),array(
		'$s' => $s,
		'$id' => $id,
		'$action_url' => z_root() . '/' . $url,
		'$search_label' => t('Search'),
		'$save_label' => t('Save'),
		'$savedsearch' => ($save && feature_enabled(local_channel(),'savedsearch'))
	));
}

/**
 * @brief Replace naked text hyperlink with HTML formatted hyperlink.
 *
 * @param string $s
 * @param boolean $me (optional) default false
 * @return string
 */
function linkify($s, $me = false) {
	$s = preg_replace("/(https?\:\/\/[a-zA-Z0-9\pL\:\/\-\?\&\;\.\=\_\@\~\#\'\%\$\!\+\,\@]*)/u", (($me) ? ' <a href="$1" rel="me" >$1</a>' : ' <a href="$1" >$1</a>'), $s);
	$s = preg_replace("/\<(.*?)(src|href)=(.*?)\&amp\;(.*?)\>/ism",'<$1$2=$3&$4>',$s);

	return($s);
}

/**
 * @brief Replace media element using http url with https to a local redirector
 *  if using https locally.
 *
 * Looks for HTML tags containing src elements that are http when we're viewing an https page
 * Typically this throws an insecure content violation in the browser. So we redirect them
 * to a local redirector which uses https and which redirects to the selected content
 *
 * @param string $s
 * @returns string
 */
function sslify($s) {
	if (strpos(z_root(),'https:') === false)
		return $s;

	// By default we'll only sslify img tags because media files will probably choke.
	// You can set sslify_everything if you want - but it will likely white-screen if it hits your php memory limit.
	// The downside is that http: media files will likely be blocked by your browser
	// Complain to your browser maker

	$allow = get_config('system','sslify_everything');

	$pattern = (($allow) ? "/\<(.*?)src=\"(http\:.*?)\"(.*?)\>/" : "/\<img(.*?)src=\"(http\:.*?)\"(.*?)\>/" );

	$matches = null;
	$cnt = preg_match_all($pattern,$s,$matches,PREG_SET_ORDER);
	if ($cnt) {
		foreach ($matches as $match) {
			$filename = basename( parse_url($match[2], PHP_URL_PATH) );
			$s = str_replace($match[2],z_root() . '/sslify/' . $filename . '?f=&url=' . urlencode($match[2]),$s);
		}
	}

	return $s;
}

/**
 * @brief Get an array of poke verbs.
 *
 * @return array
 *  * \e index is present tense verb
 *  * \e value is array containing past tense verb, translation of present, translation of past
 */
function get_poke_verbs() {
	if (get_config('system', 'poke_basic')) {
		$arr = array(
			'poke' => array('poked', t('poke'), t('poked')),
		);
	} else {
		$arr = array(
			'poke' => array( 'poked', t('poke'), t('poked')),
			'ping' => array( 'pinged', t('ping'), t('pinged')),
			'prod' => array( 'prodded', t('prod'), t('prodded')),
			'slap' => array( 'slapped', t('slap'), t('slapped')),
			'finger' => array( 'fingered', t('finger'), t('fingered')),
			'rebuff' => array( 'rebuffed', t('rebuff'), t('rebuffed')),
		);

		/**
		 * @hooks poke_verbs
		 *   * \e array associative array with another array as value
		 */
		call_hooks('poke_verbs', $arr);
	}

	return $arr;
}

/**
 * @brief Get an array of mood verbs.
 *
 * @return array
 *   * \e index is the verb
 *   * \e value is the translated verb
 */
function get_mood_verbs() {

	$arr = [
		'happy'      => t('happy'),
		'sad'        => t('sad'),
		'mellow'     => t('mellow'),
		'tired'      => t('tired'),
		'perky'      => t('perky'),
		'angry'      => t('angry'),
		'stupefied'  => t('stupefied'),
		'puzzled'    => t('puzzled'),
		'interested' => t('interested'),
		'bitter'     => t('bitter'),
		'cheerful'   => t('cheerful'),
		'alive'      => t('alive'),
		'annoyed'    => t('annoyed'),
		'anxious'    => t('anxious'),
		'cranky'     => t('cranky'),
		'disturbed'  => t('disturbed'),
		'frustrated' => t('frustrated'),
		'depressed'  => t('depressed'),
		'motivated'  => t('motivated'),
		'relaxed'    => t('relaxed'),
		'surprised'  => t('surprised'),
	];

	/**
	 * @hooks mood_verbs
	 *   * \e array associative array with mood verbs
	 */
	call_hooks('mood_verbs', $arr);

	return $arr;
}

/**
 * @brief Function to list all smilies, both internal and from addons.
 *
 * @return Returns array with keys 'texts' and 'icons'
 */
function list_smilies($default_only = false) {

	$texts =  array(
		'&lt;3',
		'&lt;/3',
		':-)',
		';-)',
		':-(',
		':-P',
		':-p',
		':-"',
		':-&quot;',
		':-x',
		':-X',
		':-D',
		'8-|',
		'8-O',
		':-O',
		'\\o/',
		'o.O',
		'O.o',
		'o_O',
		'O_o',
		":'(",
		":-!",
		":-/",
		":-[",
		"8-)",
		':beer',
		':homebrew',
		':coffee',
		':facepalm',
		':like',
		':dislike'
	);

	$icons = array(
		'<img class="smiley" src="' . z_root() . '/images/emoticons/smiley-heart.gif" alt="&lt;3" />',
		'<img class="smiley" src="' . z_root() . '/images/emoticons/smiley-brokenheart.gif" alt="&lt;/3" />',
		'<img class="smiley" src="' . z_root() . '/images/emoticons/smiley-smile.gif" alt=":-)" />',
		'<img class="smiley" src="' . z_root() . '/images/emoticons/smiley-wink.gif" alt=";-)" />',
		'<img class="smiley" src="' . z_root() . '/images/emoticons/smiley-frown.gif" alt=":-(" />',
		'<img class="smiley" src="' . z_root() . '/images/emoticons/smiley-tongue-out.gif" alt=":-P" />',
		'<img class="smiley" src="' . z_root() . '/images/emoticons/smiley-tongue-out.gif" alt=":-p" />',
		'<img class="smiley" src="' . z_root() . '/images/emoticons/smiley-kiss.gif" alt=":-\"" />',
		'<img class="smiley" src="' . z_root() . '/images/emoticons/smiley-kiss.gif" alt=":-\"" />',
		'<img class="smiley" src="' . z_root() . '/images/emoticons/smiley-kiss.gif" alt=":-x" />',
		'<img class="smiley" src="' . z_root() . '/images/emoticons/smiley-kiss.gif" alt=":-X" />',
		'<img class="smiley" src="' . z_root() . '/images/emoticons/smiley-laughing.gif" alt=":-D" />',
		'<img class="smiley" src="' . z_root() . '/images/emoticons/smiley-surprised.gif" alt="8-|" />',
		'<img class="smiley" src="' . z_root() . '/images/emoticons/smiley-surprised.gif" alt="8-O" />',
		'<img class="smiley" src="' . z_root() . '/images/emoticons/smiley-surprised.gif" alt=":-O" />',
		'<img class="smiley" src="' . z_root() . '/images/emoticons/smiley-thumbsup.gif" alt="\\o/" />',
		'<img class="smiley" src="' . z_root() . '/images/emoticons/smiley-Oo.gif" alt="o.O" />',
		'<img class="smiley" src="' . z_root() . '/images/emoticons/smiley-Oo.gif" alt="O.o" />',
		'<img class="smiley" src="' . z_root() . '/images/emoticons/smiley-Oo.gif" alt="o_O" />',
		'<img class="smiley" src="' . z_root() . '/images/emoticons/smiley-Oo.gif" alt="O_o" />',
		'<img class="smiley" src="' . z_root() . '/images/emoticons/smiley-cry.gif" alt=":\'(" />',
		'<img class="smiley" src="' . z_root() . '/images/emoticons/smiley-foot-in-mouth.gif" alt=":-!" />',
		'<img class="smiley" src="' . z_root() . '/images/emoticons/smiley-undecided.gif" alt=":-/" />',
		'<img class="smiley" src="' . z_root() . '/images/emoticons/smiley-embarassed.gif" alt=":-[" />',
		'<img class="smiley" src="' . z_root() . '/images/emoticons/smiley-cool.gif" alt="8-)" />',
		'<img class="smiley" src="' . z_root() . '/images/emoticons/beer_mug.gif" alt=":beer" />',
		'<img class="smiley" src="' . z_root() . '/images/emoticons/beer_mug.gif" alt=":homebrew" />',
		'<img class="smiley" src="' . z_root() . '/images/emoticons/coffee.gif" alt=":coffee" />',
		'<img class="smiley" src="' . z_root() . '/images/emoticons/smiley-facepalm.gif" alt=":facepalm" />',
		'<img class="smiley" src="' . z_root() . '/images/emoticons/like.gif" alt=":like" />',
		'<img class="smiley" src="' . z_root() . '/images/emoticons/dislike.gif" alt=":dislike" />'

	);

	$params = array('texts' => $texts, 'icons' => $icons);

	if($default_only)
		return $params;

	call_hooks('smilie', $params);

	return $params;
}

/**
 * @brief Replaces text emoticons with graphical images.
 *
 * It is expected that this function will be called using HTML text.
 * We will escape text between HTML pre and code blocks, and HTML attributes
 * (such as urls) from being processed.
 *
 * At a higher level, the bbcode [nosmile] tag can be used to prevent this
 * function from being executed by the prepare_text() routine when preparing
 * bbcode source for HTML display.
 *
 * @param string $s
 * @param boolean $sample (optional) default false
 * @return string
 */
function smilies($s, $sample = false) {

	if(intval(get_config('system', 'no_smilies'))
		|| (local_channel() && intval(get_pconfig(local_channel(), 'system', 'no_smilies'))))
		return $s;

	$s = preg_replace_callback('{<(pre|code)>.*?</\1>}ism', 'smile_shield', $s);
	$s = preg_replace_callback('/<[a-z]+ .*?>/ism', 'smile_shield', $s);


	$params = list_smilies();
	$params['string'] = $s;

	if ($sample) {
		$s = '<div class="smiley-sample">';
		for ($x = 0; $x < count($params['texts']); $x ++) {
			$s .= '<dl><dt>' . $params['texts'][$x] . '</dt><dd>' . $params['icons'][$x] . '</dd></dl>';
		}
	} else {
		$params['string'] = preg_replace_callback('/&lt;(3+)/','preg_heart',$params['string']);
		$s = str_replace($params['texts'],$params['icons'],$params['string']);
	}


	$s = preg_replace_callback('/<!--base64:(.*?)-->/ism', 'smile_unshield', $s);

	return $s;
}

/**
 * @brief
 *
 * @param array $m
 * @return string
 */
function smile_shield($m) {
	return '<!--base64:' . base64special_encode($m[0]) . '-->';
}

function smile_unshield($m) {
	return base64special_decode($m[1]);
}

/**
 * @brief Expand <3333 to the correct number of hearts.
 *
 * @param array $x
 */
function preg_heart($x) {

	if (strlen($x[1]) == 1)
		return $x[0];

	$t = '';
	for($cnt = 0; $cnt < strlen($x[1]); $cnt ++)
		$t .= '<img class="smiley" src="' . z_root() . '/images/emoticons/smiley-heart.gif" alt="&lt;3" />';

	$r =  str_replace($x[0],$t,$x[0]);

	return $r;
}



function day_translate($s) {
	$ret = str_replace(array('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'),
		array( t('Monday'), t('Tuesday'), t('Wednesday'), t('Thursday'), t('Friday'), t('Saturday'), t('Sunday')),
		$s);

	$ret = str_replace(array('January','February','March','April','May','June','July','August','September','October','November','December'),
		array( t('January'), t('February'), t('March'), t('April'), t('May'), t('June'), t('July'), t('August'), t('September'), t('October'), t('November'), t('December')),
		$ret);

	return $ret;
}

/**
 * @brief normalises a string.
 *
 * @param string $url
 * @return string
 */
function normalise_link($url) {
	$ret = str_replace(array('https:', '//www.'), array('http:', '//'), $url);

	return(rtrim($ret, '/'));
}

/**
 * @brief Compare two URLs to see if they are the same.
 *
 * But ignore slight but hopefully insignificant differences such as if one
 * is https and the other isn't, or if one is www.something and the other
 * isn't - and also ignore case differences.
 *
 * @see normalise_link()
 *
 * @param string $a
 * @param string $b
 * @return true if the URLs match, otherwise false
 */
function link_compare($a, $b) {
	if (strcasecmp(normalise_link($a), normalise_link($b)) === 0)
		return true;

	return false;
}

// Given an item array, convert the body element from bbcode to html and add smilie icons.
// If attach is true, also add icons for item attachments


function unobscure(&$item) {
	return;
}

function unobscure_mail(&$item) {
	if(array_key_exists('mail_obscured',$item) && intval($item['mail_obscured'])) {
		if($item['title'])
			$item['title'] = base64url_decode(str_rot47($item['title']));
		if($item['body'])
			$item['body'] = base64url_decode(str_rot47($item['body']));
	}
}


function theme_attachments(&$item) {

	$arr = json_decode($item['attach'],true);
	if(is_array($arr) && count($arr)) {
		$attaches = array();
		foreach($arr as $r) {

			$icon = getIconFromType($r['type']);
			$label = (($r['title']) ? urldecode(htmlspecialchars($r['title'], ENT_COMPAT, 'UTF-8')) : t('Unknown Attachment'));

			//some feeds provide an attachment where title an empty space
			if($label  == ' ')
				$label = t('Unknown Attachment');

			$title = t('Size') . ' ' . (($r['length']) ? userReadableSize($r['length']) : t('unknown'));

			require_once('include/channel.php');
			if(is_foreigner($item['author_xchan']))
				$url = $r['href'];
			else
				$url = z_root() . '/magic?f=&owa=1&hash=' . $item['author_xchan'] . '&dest=' . $r['href'] . '/' . $r['revision'];

			//$s .= '<a href="' . $url . '" title="' . $title . '" class="attachlink"  >' . $icon . '</a>';
			$attaches[] = array('label' => $label, 'url' => $url, 'icon' => $icon, 'title' => $title);
		}

		$s = replace_macros(get_markup_template('item_attach.tpl'), array(
			'$attaches' => $attaches
		));
	}

	return $s;
}


function format_categories(&$item,$writeable) {
	$s = '';

	$terms = get_terms_oftype($item['term'],TERM_CATEGORY);
	if($terms) {
		$categories = array();
		foreach($terms as $t) {
			$term = htmlspecialchars($t['term'],ENT_COMPAT,'UTF-8',false) ;
			if(! trim($term))
				continue;
			$removelink = (($writeable) ?  z_root() . '/filerm/' . $item['id'] . '?f=&cat=' . urlencode($t['term']) : '');
			$categories[] = array('term' => $term, 'writeable' => $writeable, 'removelink' => $removelink, 'url' => zid($t['url']));
		}

		$s = replace_macros(get_markup_template('item_categories.tpl'),array(
			'$remove' => t('remove category'),
			'$categories' => $categories
		));
	}

	return $s;
}

/**
 * @brief Add any hashtags which weren't mentioned in the message body, e.g. community tags
 *
 * @param[in] array &$item
 * @return string HTML link of hashtag
 */

function format_hashtags(&$item) {
	$s = '';

	$terms = get_terms_oftype($item['term'], array(TERM_HASHTAG,TERM_COMMUNITYTAG));
	if($terms) {
		foreach($terms as $t) {
			$term = htmlspecialchars($t['term'], ENT_COMPAT, 'UTF-8', false) ;
			if(! trim($term))
				continue;
			if(strpos($item['body'], $t['url']))
				continue;
			if($s)
				$s .= ' ';

			$s .= '<span class="badge badge-pill badge-info"><i class="fa fa-hashtag"></i>&nbsp;<a class="text-white" href="' . zid($t['url']) . '" >' . $term . '</a></span>';
		}
	}

	return $s;
}



function format_mentions(&$item) {
	$s = '';

	$terms = get_terms_oftype($item['term'],TERM_MENTION);
	if($terms) {
		foreach($terms as $t) {
			$term = htmlspecialchars($t['term'],ENT_COMPAT,'UTF-8',false) ;
			if(! trim($term))
				continue;
			if(strpos($item['body'], $t['url']))
				continue;
			if($s)
				$s .= ' ';
			$s .= '<span class="badge badge-pill badge-success"><i class="fa fa-at"></i>&nbsp;<a class="text-white" href="' . zid($t['url']) . '" >' . $term . '</a></span>';
		}
	}

	return $s;
}


function format_filer(&$item) {
	$s = '';

	$terms = get_terms_oftype($item['term'],TERM_FILE);
	if($terms) {
		$categories = array();
		foreach($terms as $t) {
			$term = htmlspecialchars($t['term'],ENT_COMPAT,'UTF-8',false) ;
			if(! trim($term))
				continue;
			$removelink = z_root() . '/filerm/' . $item['id'] . '?f=&term=' . urlencode($t['term']);
			$categories[] = array('term' => $term, 'removelink' => $removelink);
		}

		$s = replace_macros(get_markup_template('item_filer.tpl'),array(
			'$remove' => t('remove from file'),
			'$categories' => $categories
		));
	}

	return $s;
}


function generate_map($coord) {
	$coord = trim($coord);
	$coord = str_replace(array(',','/','  '),array(' ',' ',' '),$coord);

	$arr = [
			'lat' => trim(substr($coord, 0, strpos($coord, ' '))),
			'lon' => trim(substr($coord, strpos($coord, ' ')+1)),
			'html' => ''
	];

	/**
	 * @hooks generate_map
	 *   * \e string \b lat
	 *   * \e string \b lon
	 *   * \e string \b html the parsed HTML to return
	 */
	call_hooks('generate_map', $arr);

	return (($arr['html']) ? $arr['html'] : $coord);
}

function generate_named_map($location) {
	$arr = [
			'location' => $location,
			'html' => ''
	];

	/**
	 * @hooks generate_named_map
	 *   * \e string \b location
	 *   * \e string \b html the parsed HTML to return
	 */
	call_hooks('generate_named_map', $arr);

	return (($arr['html']) ? $arr['html'] : $location);
}


function prepare_body(&$item,$attach = false) {

	call_hooks('prepare_body_init', $item);

	$s = '';
	$photo = '';
	$is_photo = ((($item['verb'] === ACTIVITY_POST) && ($item['obj_type'] === ACTIVITY_OBJ_PHOTO)) ? true : false);

	if($is_photo) {

		$object = json_decode($item['obj'],true);

		// if original photo width is <= 640px prepend it to item body
		if($object['link'][0]['width'] && $object['link'][0]['width'] <= 640) {
			$s .= '<div class="inline-photo-item-wrapper"><a href="' . zid(rawurldecode($object['id'])) . '" target="_blank" rel="nofollow noopener" ><img class="inline-photo-item" style="max-width:' . $object['link'][0]['width'] . 'px; width:100%; height:auto;" src="' . zid(rawurldecode($object['link'][0]['href'])) . '"></a></div>' . $s;
		}

		// if original photo width is > 640px make it a cover photo
		if($object['link'][0]['width'] && $object['link'][0]['width'] > 640) {
			$scale = ((($object['link'][1]['width'] == 1024) || ($object['link'][1]['height'] == 1024)) ? 1 : 0);
			$photo = '<a href="' . zid(rawurldecode($object['id'])) . '" target="_blank" rel="nofollow noopener"><img style="max-width:' . $object['link'][$scale]['width'] . 'px; width:100%; height:auto;" src="' . zid(rawurldecode($object['link'][$scale]['href'])) . '"></a>';
		}
	}

	if($item['item_obscured']) {
		$s .= prepare_binary($item);
	}
	else {
		$s .= prepare_text($item['body'],$item['mimetype'], false);
	}

	$event = (($item['obj_type'] === ACTIVITY_OBJ_EVENT) ? format_event_obj($item['obj']) : false);

	$prep_arr = array(
		'item' => $item,
		'html' => $event ? $event['content'] : $s,
		'event' => $event['header'],
		'photo' => $photo
	);

	call_hooks('prepare_body', $prep_arr);

	$s = $prep_arr['html'];
	$photo = $prep_arr['photo'];
	$event = $prep_arr['event'];

	if(! $attach) {
		return $s;
	}

	if(strpos($s,'<div class="map">') !== false && $item['coord']) {
		$x = generate_map(trim($item['coord']));
		if($x) {
			$s = preg_replace('/\<div class\=\"map\"\>/','$0' . $x,$s);
		}
	}

	$attachments = theme_attachments($item);

	$writeable = ((get_observer_hash() == $item['owner_xchan']) ? true : false);

	$tags = format_hashtags($item);

	if($item['resource_type'])
		$mentions = format_mentions($item);

	$categories = format_categories($item,$writeable);

	if(local_channel() == $item['uid'])
		$filer = format_filer($item);

	$s = sslify($s);

	$prep_arr = array(
		'item' => $item,
		'photo' => $photo,
		'html' => $s,
		'event' => $event,
		'categories' => $categories,
		'folders' => $filer,
		'tags' => $tags,
		'mentions' => $mentions,
		'attachments' => $attachments
	);

	call_hooks('prepare_body_final', $prep_arr);

	unset($prep_arr['item']);

	return $prep_arr;
}


function prepare_binary($item) {
	return replace_macros(get_markup_template('item_binary.tpl'), [
		'$download'  => t('Download binary/encrypted content'),
		'$url'       => z_root() . '/viewsrc/' . $item['id'] . '/download'
	]);
}


/**
 * @brief Given a text string, convert from bbcode to html and add smilie icons.
 *
 * @param string $text
 * @param string $content_type (optional) default text/bbcode
 * @param boolean $cache (optional) default false
 *
 * @return string
 */
function prepare_text($text, $content_type = 'text/bbcode', $cache = false) {

	switch($content_type) {
		case 'text/plain':
			$s = escape_tags($text);
			break;

		case 'text/html':
			$s = $text;
			break;

		case 'text/markdown':
			$text = Zlib\MarkdownSoap::unescape($text);
			$s = MarkdownExtra::defaultTransform($text);
			break;

		case 'application/x-pdl';
			$s = escape_tags($text);
			break;

		// No security checking is done here at display time - so we need to verify
		// that the author is allowed to use PHP before storing. We also cannot allow
		// importation of PHP text bodies from other sites. Therefore this content
		// type is only valid for web pages (and profile details).

		// It may be possible to provide a PHP message body which is evaluated on the
		// sender's site before sending it elsewhere. In that case we will have a
		// different content-type here.

		case 'application/x-php':
			ob_start();
			eval($text);
			$s = ob_get_contents();
			ob_end_clean();
			break;

		case 'text/bbcode':
		case '':
		default:
			require_once('include/bbcode.php');

			if(stristr($text,'[nosmile]'))
				$s = bbcode($text, [ 'cache' => $cache ]);
			else
				$s = smilies(bbcode($text, [ 'cache' => $cache ]));

			$s = zidify_links($s);

			break;
	}

//logger('prepare_text: ' . $s);

	return $s;
}


function create_export_photo_body(&$item) {
	if(($item['verb'] === ACTIVITY_POST) && ($item['obj_type'] === ACTIVITY_OBJ_PHOTO)) {
		$j = json_decode($item['obj'],true);
		if($j) {
			$item['body'] .= "\n\n" . (($j['body']) ? $j['body'] : $j['bbcode']);
			$item['sig'] = '';
		}
	}
}

/**
 * @brief Return atom link elements for all of our hubs.
 *
 * @return string
 */
function feed_hublinks() {
	$hub = get_config('system', 'huburl');

	$hubxml = '';
	if(strlen($hub)) {
		$hubs = explode(',', $hub);
		if(count($hubs)) {
			foreach($hubs as $h) {
				$h = trim($h);
				if(! strlen($h))
					continue;

				$hubxml .= '<link rel="hub" href="' . xmlify($h) . '" />' . "\n" ;
			}
		}
	}

	return $hubxml;
}

/**
 * @brief Return atom link elements for salmon endpoints
 *
 * @param string $nick
 * @return string
 */
function feed_salmonlinks($nick) {

	$salmon  = '<link rel="salmon" href="' . xmlify(z_root() . '/salmon/' . $nick) . '" />' . "\n" ;

	// old style links that status.net still needed as of 12/2010

	$salmon .= '  <link rel="http://salmon-protocol.org/ns/salmon-replies" href="' . xmlify(z_root() . '/salmon/' . $nick) . '" />' . "\n" ;
	$salmon .= '  <link rel="http://salmon-protocol.org/ns/salmon-mention" href="' . xmlify(z_root() . '/salmon/' . $nick) . '" />' . "\n" ;

	return $salmon;
}


function get_plink($item,$conversation_mode = true) {
	if($conversation_mode)
		$key = 'plink';
	else
		$key = 'llink';

	$zidify = true;

	if(array_key_exists('author',$item) && $item['author']['xchan_network'] !== 'zot')
		$zidify = false;

	if(x($item,$key)) {
		return array(
			'href' => (($zidify) ? zid($item[$key]) : $item[$key]),
			'title' => t('Link to Source'),
		);
	}
	else {
		return false;
	}
}


function unamp($s) {
	return str_replace('&amp;', '&', $s);
}

function layout_select($channel_id, $current = '') {
	$r = q("select mid, v from item left join iconfig on iconfig.iid = item.id
		where iconfig.cat = 'system' and iconfig.k = 'PDL' and item.uid = %d and item_type = %d ",
		intval($channel_id),
		intval(ITEM_TYPE_PDL)
	);

	if($r) {
		$empty_selected = (($current === false) ? ' selected="selected" ' : '');
		$options .= '<option value="" ' . $empty_selected . '>' . t('default') . '</option>';
		foreach($r as $rr) {
			$selected = (($rr['mid'] == $current) ? ' selected="selected" ' : '');
			$options .= '<option value="' . $rr['mid'] . '"' . $selected . '>' . $rr['v'] . '</option>';
		}
	}

	$o = replace_macros(get_markup_template('field_select_raw.tpl'), array(
		'$field'	=> array('layout_mid', t('Page layout'), $selected, t('You can create your own with the layouts tool'), $options)
	));

	return $o;
}


function mimetype_select($channel_id, $current = 'text/bbcode', $choices = null, $element = 'mimetype') {

	$x = (($choices) ? $choices : [
		'text/bbcode'       => t('BBcode'),
		'text/html'         => t('HTML'),
		'text/markdown'     => t('Markdown'),
		'text/plain'        => t('Text'),
		'application/x-pdl' => t('Comanche Layout')
	]);


	if((App::$is_sys) || (channel_codeallowed($channel_id) && $channel_id == local_channel())){
		$x['application/x-php'] = t('PHP');
	}

	foreach($x as $y => $z) {
		$selected = (($y == $current) ? ' selected="selected" ' : '');
		$options .= '<option value="' . $y . '"' . $selected . '>' . $z . '</option>';
	}

	$o = replace_macros(get_markup_template('field_select_raw.tpl'), array(
		'$field'	=> array( $element, t('Page content type'), $selected, '', $options)
	));

	return $o;
}

function engr_units_to_bytes ($size_str) {
	if(! $size_str)
		return $size_str;
	switch (substr(trim($size_str), -1)) {
		case 'M': case 'm': return (int)$size_str * 1048576;
		case 'K': case 'k': return (int)$size_str * 1024;
		case 'G': case 'g': return (int)$size_str * 1073741824;
		default: return $size_str;
	}
}


function base64url_encode($s, $strip_padding = true) {

	$s = strtr(base64_encode($s),'+/','-_');

	if($strip_padding)
		$s = str_replace('=','',$s);

	return $s;
}

function base64url_decode($s) {
	if(is_array($s)) {
		logger('base64url_decode: illegal input: ' . print_r(debug_backtrace(), true));
		return $s;
	}
	return base64_decode(strtr($s,'-_','+/'));
}


function base64special_encode($s, $strip_padding = true) {

	$s = strtr(base64_encode($s),'+/',',.');

	if($strip_padding)
		$s = str_replace('=','',$s);

	return $s;
}

function base64special_decode($s) {
	if(is_array($s)) {
		logger('base64url_decode: illegal input: ' . print_r(debug_backtrace(), true));
		return $s;
	}
	return base64_decode(strtr($s,',.','+/'));
}

/**
 * @brief Return a div to clear floats.
 *
 * @return string
 */
function cleardiv() {
	return '<div class="clear"></div>';
}


function bb_translate_video($s) {
	$arr = array('string' => $s);
	call_hooks('bb_translate_video',$arr);
	return $arr['string'];
}

function html2bb_video($s) {
	$arr = array('string' => $s);
	call_hooks('html2bb_video',$arr);
	return $arr['string'];
}

/**
 * apply xmlify() to all values of array $val, recursively
 */
function array_xmlify($val) {
	if (is_bool($val)) return $val?"true":"false";
	if (is_array($val)) return array_map('array_xmlify', $val);
	return xmlify((string) $val);
}


function reltoabs($text, $base) {
	if (empty($base))
		return $text;

	$base = rtrim($base,'/');

	$base2 = $base . "/";

	// Replace links
	$pattern = "/<a([^>]*) href=\"(?!http|https|\/)([^\"]*)\"/";
	$replace = "<a\${1} href=\"" . $base2 . "\${2}\"";
	$text = preg_replace($pattern, $replace, $text);

	$pattern = "/<a([^>]*) href=\"(?!http|https)([^\"]*)\"/";
	$replace = "<a\${1} href=\"" . $base . "\${2}\"";
	$text = preg_replace($pattern, $replace, $text);

	// Replace images
	$pattern = "/<img([^>]*) src=\"(?!http|https|\/)([^\"]*)\"/";
	$replace = "<img\${1} src=\"" . $base2 . "\${2}\"";
	$text = preg_replace($pattern, $replace, $text);

	$pattern = "/<img([^>]*) src=\"(?!http|https)([^\"]*)\"/";
	$replace = "<img\${1} src=\"" . $base . "\${2}\"";
	$text = preg_replace($pattern, $replace, $text);

	// Done
	return $text;
}

function item_post_type($item) {
	switch($item['resource_type']) {
		case 'photo':
			$post_type = t('photo');
			break;
		case 'event':
			$post_type = t('event');
			break;
		default:
			$post_type = t('status');
			if($item['mid'] != $item['parent_mid'])
				$post_type = t('comment');
			break;
	}

	if(strlen($item['verb']) && (! activity_match($item['verb'],ACTIVITY_POST)))
		$post_type = t('activity');

	return $post_type;
}

// This needs to be fixed to use quoted tag strings

function undo_post_tagging($s) {

	$matches = null;
	// undo tags and mentions
	$cnt = preg_match_all('/([@#])(\!*)\[zrl=(.*?)\](.*?)\[\/zrl\]/ism',$s,$matches,PREG_SET_ORDER);
	if($cnt) {
		foreach($matches as $mtch) {
			$s = str_replace($mtch[0], $mtch[1] . $mtch[2] . quote_tag($mtch[4]),$s);
		}
	}
	// undo forum tags
	$cnt = preg_match_all('/\!\[zrl=(.*?)\](.*?)\[\/zrl\]/ism',$s,$matches,PREG_SET_ORDER);
	if($cnt) {
		foreach($matches as $mtch) {
			$s = str_replace($mtch[0], '!' . quote_tag($mtch[2]),$s);
		}
	}


	return $s;
}

function quote_tag($s) {
	if(strpos($s,' ') !== false)
		return '&quot;' . $s . '&quot;';
	return $s;
}


function fix_mce_lf($s) {
	$s = str_replace("\r\n","\n",$s);
//	$s = str_replace("\n\n","\n",$s);
	return $s;
}


function protect_sprintf($s) {
	return(str_replace('%','%%',$s));
}


function is_a_date_arg($s) {
	$i = intval($s);
	if($i > 1900) {
		$y = date('Y');
		if($i <= $y+1 && strpos($s,'-') == 4) {
			$m = intval(substr($s,5));
			if($m > 0 && $m <= 12)
				return true;
		}
	}

	return false;
}

function legal_webbie($s) {
	if(! $s)
		return '';

	// WARNING: This regex may not work in a federated environment.
	// You will probably want something like 
	// preg_replace('/([^a-z0-9\_])/','',strtolower($s));

	$r = preg_replace('/([^a-z0-9\-\_])/','',strtolower($s));

	$x = [ 'input' => $s, 'output' => $r ];
	call_hooks('legal_webbie',$x);
	return $x['output'];

}

function legal_webbie_text() {

	// WARNING: This will not work in a federated environment.

	$s = t('a-z, 0-9, -, and _ only');

	$x = [ 'text' => $s ];
	call_hooks('legal_webbie_text',$x);
	return $x['text'];

}





function check_webbie($arr) {


	// These names conflict with the CalDAV server
	$taken = [ 'principals', 'addressbooks', 'calendars' ];

	$reservechan = get_config('system','reserved_channels');
	if(strlen($reservechan)) {
		$taken = array_merge($taken,explode(',', $reservechan));
	}

	$str = '';
	if(count($arr)) {
		foreach($arr as $x) {
			$y = legal_webbie($x);
			if(strlen($y)) {
				if($str)
					$str .= ',';
				$str .= "'" . dbesc($y) . "'";
			}
		}

		if(strlen($str)) {
			$r = q("select channel_address from channel where channel_address in ( $str ) ");
			if(count($r)) {
				foreach($r as $rr) {
					$taken[] = $rr['channel_address'];
				}
			}
			foreach($arr as $x) {
				$y = legal_webbie($x);
				if(! in_array($y,$taken)) {
					return $y;
				}
			}
		}
	}

	return '';
}

function ids_to_array($arr,$idx = 'id') {
	$t = array();
	if($arr) {
		foreach($arr as $x) {
			if(array_key_exists($idx,$x) && strlen($x[$idx]) && (! in_array($x[$idx],$t))) {
				$t[] = $x[$idx];
			}
		}
	}
	return($t);
}




function ids_to_querystr($arr,$idx = 'id',$quote = false) {
	$t = array();
	if($arr) {
		foreach($arr as $x) {
			if(! in_array($x[$idx],$t)) {
				if($quote)
					$t[] = "'" . dbesc($x[$idx]) . "'";
				else
					$t[] = $x[$idx];
			}
		}
	}
	return(implode(',', $t));
}

/**
 * @brief array_elm_to_str($arr,$elm,$delim = ',') extract unique individual elements from an array of arrays and return them as a string separated by a delimiter
 * similar to ids_to_querystr, but allows a different delimiter instead of a db-quote option 
 * empty elements (evaluated after trim()) are ignored.
 * @param $arr array
 * @param $elm array key to extract from sub-array
 * @param $delim string default ','
 * @returns string
 */

function array_elm_to_str($arr,$elm,$delim = ',') {

	$tmp = [];
	if($arr && is_array($arr)) {
		foreach($arr as $x) {
			if(is_array($x) && array_key_exists($elm,$x)) {
				$z = trim($x[$elm]);
				if(($z) && (! in_array($z,$tmp))) {
					$tmp[] = $z;
				}
			}
		}
	}
	return implode($delim,$tmp);
}




/**
 * @brief Fetches xchan and hubloc data for an array of items with only an
 * author_xchan and owner_xchan.
 *
 * If $abook is true also include the abook info. This is needed in the API to
 * save extra per item lookups there.
 *
 * @param[in,out] array &$items
 * @param boolean $abook If true also include the abook info
 * @param number $effective_uid
 */
function xchan_query(&$items, $abook = true, $effective_uid = 0) {
	$arr = array();
	if($items && count($items)) {

		if($effective_uid) {
			for($x = 0; $x < count($items); $x ++) {
				$items[$x]['real_uid'] = $items[$x]['uid'];
				$items[$x]['uid'] = $effective_uid;
			}
		}

		foreach($items as $item) {
			if($item['owner_xchan'] && (! in_array("'" . dbesc($item['owner_xchan']) . "'",$arr)))
				$arr[] = "'" . dbesc($item['owner_xchan']) . "'";
			if($item['author_xchan'] && (! in_array("'" . dbesc($item['author_xchan']) . "'",$arr)))
				$arr[] = "'" . dbesc($item['author_xchan']) . "'";
		}
	}
	if(count($arr)) {
		if($abook) {
			$chans = q("select * from xchan left join hubloc on hubloc_hash = xchan_hash left join abook on abook_xchan = xchan_hash and abook_channel = %d
				where xchan_hash in (" . protect_sprintf(implode(',', $arr)) . ") and hubloc_primary = 1",
				intval($item['uid'])
			);
		}
		else {
			$chans = q("select xchan.*,hubloc.* from xchan left join hubloc on hubloc_hash = xchan_hash
				where xchan_hash in (" . protect_sprintf(implode(',', $arr)) . ") and hubloc_primary = 1");
		}
		$xchans = q("select * from xchan where xchan_hash in (" . protect_sprintf(implode(',',$arr)) . ") and xchan_network in ('rss','unknown')");
		if(! $chans)
			$chans = $xchans;
		else
			$chans = array_merge($xchans,$chans);
	}
	if($items && count($items) && $chans && count($chans)) {
		for($x = 0; $x < count($items); $x ++) {
			$items[$x]['owner'] = find_xchan_in_array($items[$x]['owner_xchan'],$chans);
			$items[$x]['author'] = find_xchan_in_array($items[$x]['author_xchan'],$chans);
		}
	}
}

function xchan_mail_query(&$item) {
	$arr = array();
	$chans = null;
	if($item) {
		if($item['from_xchan'] && (! in_array("'" . dbesc($item['from_xchan']) . "'",$arr)))
			$arr[] = "'" . dbesc($item['from_xchan']) . "'";
		if($item['to_xchan'] && (! in_array("'" . dbesc($item['to_xchan']) . "'",$arr)))
			$arr[] = "'" . dbesc($item['to_xchan']) . "'";
	}

	if(count($arr)) {
		$chans = q("select xchan.*,hubloc.* from xchan left join hubloc on hubloc_hash = xchan_hash
			where xchan_hash in (" . protect_sprintf(implode(',', $arr)) . ") and hubloc_primary = 1");
	}
	if($chans) {
		$item['from'] = find_xchan_in_array($item['from_xchan'],$chans);
		$item['to']  = find_xchan_in_array($item['to_xchan'],$chans);
	}
}


function find_xchan_in_array($xchan,$arr) {
	if(count($arr)) {
		foreach($arr as $x) {
			if($x['xchan_hash'] === $xchan) {
				return $x;
			}
		}
	}
	return array();
}

function get_rel_link($j,$rel) {
	if(is_array($j) && ($j))
		foreach($j as $l)
			if(array_key_exists('rel',$l) && $l['rel'] === $rel && array_key_exists('href',$l))
				return $l['href'];

	return '';
}


// Lots of code to write here

function magic_link($s) {
	return $s;
}

/**
 * @brief If $escape is true, dbesc() each element before adding quotes.
 *
 * @param[in,out] array &$arr
 * @param boolean $escape (optional) default false
 */
function stringify_array_elms(&$arr, $escape = false) {
	for($x = 0; $x < count($arr); $x ++)
		$arr[$x] = "'" . (($escape) ? dbesc($arr[$x]) : $arr[$x]) . "'";
}

/**
 * @brief Indents a flat JSON string to make it more human-readable.
 *
 * @param string $json The original JSON string to process.
 * @return string Indented version of the original JSON string.
 */
function jindent($json) {

	$result    = '';
	$pos       = 0;
	$strLen    = strlen($json);
	$indentStr = '  ';
	$newLine   = "\n";
	$prevChar  = '';
	$outOfQuotes = true;

	for ($i=0; $i<=$strLen; $i++) {
		// Grab the next character in the string.
		$char = substr($json, $i, 1);

		// Are we inside a quoted string?
		if ($char == '"' && $prevChar != '\\') {
			$outOfQuotes = !$outOfQuotes;

		// If this character is the end of an element,
		// output a new line and indent the next line.
		} else if(($char == '}' || $char == ']') && $outOfQuotes) {
			$result .= $newLine;
			$pos --;
			for ($j=0; $j<$pos; $j++) {
				$result .= $indentStr;
			}
		}

		// Add the character to the result string.
		$result .= $char;

		// If the last character was the beginning of an element,
		// output a new line and indent the next line.
		if (($char == ',' || $char == '{' || $char == '[') && $outOfQuotes) {
			$result .= $newLine;
			if ($char == '{' || $char == '[') {
				$pos ++;
			}

			for ($j = 0; $j < $pos; $j++) {
				$result .= $indentStr;
			}
		}

		$prevChar = $char;
	}

	return $result;
}

/**
 * @brief Creates navigation menu for webpage, layout, blocks, menu sites.
 *
 * @return string with parsed HTML
 */
function design_tools() {

	$channel  = App::get_channel();
	$sys = false;

	if(App::$is_sys && is_site_admin()) {
		require_once('include/channel.php');
		$channel = get_sys_channel();
		$sys = true;
	}

	$who = $channel['channel_address'];

	return replace_macros(get_markup_template('design_tools.tpl'), array(
		'$title'  => t('Design Tools'),
		'$who'    => $who,
		'$sys'    => $sys,
		'$blocks' => t('Blocks'),
		'$menus'  => t('Menus'),
		'$layout' => t('Layouts'),
		'$pages'  => t('Pages')
	));
}

/**
 * @brief Creates website portation tools menu
 *
 * @return string
 */
function website_portation_tools() {

	$channel  = App::get_channel();
	$sys = false;

	if(App::$is_sys && is_site_admin()) {
		require_once('include/channel.php');
		$channel = get_sys_channel();
		$sys = true;
	}

	return replace_macros(get_markup_template('website_portation_tools.tpl'), array(
		'$title'               => t('Import'),
		'$import_label'        => t('Import website...'),
		'$import_placeholder'  => t('Select folder to import'),
		'$file_upload_text'    => t('Import from a zipped folder:'),
		'$file_import_text'    => t('Import from cloud files:'),
		'$desc'                => t('/cloud/channel/path/to/folder'),
		'$hint'                => t('Enter path to website files'),
		'$select'              => t('Select folder'),
		'$export_label'        => t('Export website...'),
		'$file_download_text'  => t('Export to a zip file'),
		'$filename_desc'       => t('website.zip'),
		'$filename_hint'       => t('Enter a name for the zip file.'),
		'$cloud_export_text'   => t('Export to cloud files'),
		'$cloud_export_desc'   => t('/path/to/export/folder'),
		'$cloud_export_hint'   => t('Enter a path to a cloud files destination.'),
		'$cloud_export_select' => t('Specify folder'),
	));
}

/**
 * @brief case insensitive in_array()
 *
 * @param string $needle
 * @param array $haystack
 * @return boolean
 */
function in_arrayi($needle, $haystack) {
	return in_array(strtolower($needle), array_map('strtolower', $haystack));
}

function normalise_openid($s) {
	return trim(str_replace(array('http://','https://'),array('',''),$s),'/');
}

/**
 * Used in ajax endless scroll request to find out all the args that the master page was viewing.
 * This was using $_REQUEST, but $_REQUEST also contains all your cookies. So we're restricting it
 * to $_GET and $_POST.
 *
 * @return string with additional URL parameters
 */
function extra_query_args() {
	$s = '';
	if(count($_GET)) {
		foreach($_GET as $k => $v) {
			// these are request vars we don't want to duplicate
			if(! in_array($k, array('q','f','zid','page','PHPSESSID'))) {
				$s .= '&' . $k . '=' . urlencode($v);
			}
		}
	}
	if(count($_POST)) {
		foreach($_POST as $k => $v) {
			// these are request vars we don't want to duplicate
			if(! in_array($k, array('q','f','zid','page','PHPSESSID'))) {
				$s .= '&' . $k . '=' . urlencode($v);
			}
		}
	}

	return $s;
}

/**
 * @brief This function removes the tag $tag from the text $body and replaces it
 * with the appropiate link.
 *
 * @param App $a
 * @param[in,out] string &$body the text to replace the tag in
 * @param[in,out] string &$access_tag used to return tag ACL exclusions e.g. @!foo
 * @param[in,out] string &$str_tags string to add the tag to
 * @param int $profile_uid
 * @param string $tag the tag to replace
 * @param boolean $diaspora default false
 * @return boolean true if replaced, false if not replaced
 */
function handle_tag($a, &$body, &$access_tag, &$str_tags, $profile_uid, $tag, $diaspora = false) {

	$replaced = false;
	$r = null;
	$match = array();

	$termtype = ((strpos($tag,'#') === 0)   ? TERM_HASHTAG  : TERM_UNKNOWN);
	$termtype = ((strpos($tag,'@') === 0)   ? TERM_MENTION  : $termtype);
	$termtype = ((strpos($tag,'!') === 0)   ? TERM_FORUM    : $termtype);
	$termtype = ((strpos($tag,'#^[') === 0) ? TERM_BOOKMARK : $termtype);

	//is it a hash tag?
	if(strpos($tag,'#') === 0) {
		if(strpos($tag,'#^[') === 0) {
			if(preg_match('/#\^\[(url|zrl)(.*?)\](.*?)\[\/(url|zrl)\]/',$tag,$match)) {
				$basetag = $match[3];
				$url = ((substr($match[2],0,1) === '=') ? substr($match[2],1) : $match[3]);
				$replaced = true;
			}
		}
		// if the tag is already replaced...
		elseif((strpos($tag,'[zrl=')) || (strpos($tag,'[url='))) {
			//...do nothing
			return $replaced;
		}
		if(! $replaced) {

			// base tag has the tags name only

			if((substr($tag,0,7) === '#&quot;') && (substr($tag,-6,6) === '&quot;')) {
				$basetag = substr($tag,7);
				$basetag = substr($basetag,0,-6);
			}
			else
				$basetag = str_replace('_',' ',substr($tag,1));

			//create text for link
			$url = z_root() . '/search?tag=' . rawurlencode($basetag);
			$newtag = '#[zrl=' . z_root() . '/search?tag=' . rawurlencode($basetag) . ']' . $basetag . '[/zrl]';
			//replace tag by the link. Make sure to not replace something in the middle of a word
			// The '=' is needed to not replace color codes if the code is also used as a tag
			// Much better would be to somehow completely avoiding things in e.g. [color]-tags.
			// This would allow writing things like "my favourite tag=#foobar".
			$body = preg_replace('/(?<![a-zA-Z0-9=])'.preg_quote($tag,'/').'/', $newtag, $body);
			$replaced = true;
		}
		//is the link already in str_tags?
		if(! stristr($str_tags,$newtag)) {
			//append or set str_tags
			if(strlen($str_tags))
				$str_tags .= ',';

			$str_tags .= $newtag;
		}
		return [
			'replaced' => $replaced,
			'termtype' => $termtype,
			'term'     => $basetag,
			'url'      => $url,
			'contact'  => $r[0]
		];
	}

	//is it a person tag?

	$grouptag = false;

	if(strpos($tag,'!') === 0) {
		$grouptag = true;
	}

	if(strpos($tag,'@') === 0 || $grouptag) {

		// The @! tag will alter permissions
		$exclusive = (((! $grouptag) && (strpos($tag,'!') === 1) && (! $diaspora)) ? true : false);

		//is it already replaced?
		if(strpos($tag,'[zrl='))
			return $replaced;

		//get the person's name

		$name = substr($tag,(($exclusive) ? 2 : 1)); // The name or name fragment we are going to replace
		$newname = $name; // a copy that we can mess with
		$tagcid = 0;

		$r = null;

		// is it some generated name?

		$forum = false;
		$trailing_plus_name = false;

		// @channel+ is a forum or network delivery tag

		if(substr($newname,-1,1) === '+') {
			$forum = true;
			$newname = substr($newname,0,-1);
		}

		// Here we're looking for an address book entry as provided by the auto-completer
		// of the form something+nnn where nnn is an abook_id or the first chars of xchan_hash


		// If there's a +nnn in the string make sure there isn't a space preceding it

		$t1 = strpos($newname,' ');
		$t2 = strrpos($newname,'+');

		if($t1 && $t2 && $t1 < $t2)
			$t2 = 0;

		if(($t2) && (! $diaspora)) {
			//get the id

			$tagcid = urldecode(substr($newname,$t2 + 1));

			if(strrpos($tagcid,' '))
				$tagcid = substr($tagcid,0,strrpos($tagcid,' '));

			if(strlen($tagcid) < 16)
				$abook_id = intval($tagcid);
			//remove the next word from tag's name
			if(strpos($name,' ')) {
				$name = substr($name,0,strpos($name,' '));
			}

			if($abook_id) { // if there was an id
				// select channel with that id from the logged in user's address book
				$r = q("SELECT * FROM abook left join xchan on abook_xchan = xchan_hash
					WHERE abook_id = %d AND abook_channel = %d LIMIT 1",
						intval($abook_id),
						intval($profile_uid)
				);
			}
			else {
				$r = q("SELECT * FROM xchan
					WHERE xchan_hash like '%s%%' LIMIT 1",
						dbesc($tagcid)
				);
			}
		}

		if(! $r) {

			// look for matching names in the address book

			// Two ways to deal with spaces - double quote the name or use underscores
			// we see this after input filtering so quotes have been html entity encoded

			if((substr($name,0,6) === '&quot;') && (substr($name,-6,6) === '&quot;')) {
				$newname = substr($name,6);
				$newname = substr($newname,0,-6);
			}
			else
				$newname = str_replace('_',' ',$name);

			// do this bit over since we started over with $name

			if(substr($newname,-1,1) === '+') {
				$forum = true;
				$newname = substr($newname,0,-1);
			}

			//select someone from this user's contacts by name
			$r = q("SELECT * FROM abook left join xchan on abook_xchan = xchan_hash
				WHERE xchan_name = '%s' AND abook_channel = %d LIMIT 1",
					dbesc($newname),
					intval($profile_uid)
			);

			if(! $r) {
				//select someone by attag or nick and the name passed in
				$r = q("SELECT * FROM abook left join xchan on abook_xchan = xchan_hash
					WHERE xchan_addr like ('%s') AND abook_channel = %d LIMIT 1",
						dbesc(((strpos($newname,'@')) ? $newname : $newname . '@%')),
						intval($profile_uid)
				);
			}

			if(! $r) {
				// it's possible somebody has a name ending with '+', which we stripped off as a forum indicator
				// This is very rare but we want to get it right.

				$r = q("SELECT * FROM abook left join xchan on abook_xchan = xchan_hash
					WHERE xchan_name = '%s' AND abook_channel = %d LIMIT 1",
						dbesc($newname . '+'),
						intval($profile_uid)
				);
				if($r)
					$trailing_plus_name = true;
			}
		}

		// $r is set if we found something

		$channel = App::get_channel();

		if($r) {
			$profile = $r[0]['xchan_url'];
			$newname = $r[0]['xchan_name'];
			// add the channel's xchan_hash to $access_tag if exclusive
			if($exclusive) {
				$access_tag .= 'cid:' . $r[0]['xchan_hash'];
			}
		}
		else {

			// check for a group/collection exclusion tag

			// note that we aren't setting $replaced even though we're replacing text.
			// This tag isn't going to get a term attached to it. It's only used for
			// access control. The link points to out own channel just so it doesn't look
			// weird - as all the other tags are linked to something.

			if(local_channel() && local_channel() == $profile_uid) {
				require_once('include/group.php');
				$grp = group_byname($profile_uid,$name);

				if($grp) {
					$g = q("select hash from groups where id = %d and visible = 1 limit 1",
						intval($grp)
					);
					if($g && $exclusive) {
						$access_tag .= 'gid:' . $g[0]['hash'];
					}
					$channel = App::get_channel();
					if($channel) {
						$newtag = '@' . (($exclusive) ? '!' : '') . '[zrl=' . z_root() . '/channel/' . $channel['channel_address'] . ']' . $newname . '[/zrl]';
						$body = str_replace('@' . (($exclusive) ? '!' : '') . $name, $newtag, $body);
					}
				}
			}
		}

		if(($exclusive) && (! $access_tag)) {
			$access_tag .= 'cid:' . $channel['channel_hash'];
		}

		// if there is an url for this channel

		if(isset($profile)) {
			$replaced = true;
			//create profile link
			$profile = str_replace(',','%2c',$profile);
			$url = $profile;
			if($grouptag) {
				$newtag = '!' . '[zrl=' . $profile . ']' . $newname	. '[/zrl]';
				$body = str_replace('!' . $name, $newtag, $body);
			}
			else {
				$newtag = '@' . (($exclusive) ? '!' : '') . '[zrl=' . $profile . ']' . $newname	. (($forum &&  ! $trailing_plus_name) ? '+' : '') . '[/zrl]';
				$body = str_replace('@' . (($exclusive) ? '!' : '') . $name, $newtag, $body);
			}

			//append tag to str_tags
			if(! stristr($str_tags,$newtag)) {
				if(strlen($str_tags))
					$str_tags .= ',';
				$str_tags .= $newtag;
			}
		}
	}

	return [
		'replaced' => $replaced,
		'termtype' => $termtype,
		'term'     => $newname,
		'url'      => $url,
		'contact'  => $r[0]
	];
}

function linkify_tags($a, &$body, $uid, $diaspora = false) {
	$str_tags = '';
	$tagged = array();
	$results = array();

	$tags = get_tags($body);

	if(count($tags)) {
		foreach($tags as $tag) {
			$access_tag = '';

			// If we already tagged 'Robert Johnson', don't try and tag 'Robert'.
			// Robert Johnson should be first in the $tags array

			$fullnametagged = false;
			for($x = 0; $x < count($tagged); $x ++) {
				if(stristr($tagged[$x],$tag . ' ')) {
					$fullnametagged = true;
					break;
				}
			}
			if($fullnametagged)
				continue;

			$success = handle_tag($a, $body, $access_tag, $str_tags, ($uid) ? $uid : App::$profile_uid , $tag, $diaspora);
			$results[] = array('success' => $success, 'access_tag' => $access_tag);
			if($success['replaced']) $tagged[] = $tag;
		}
	}

	return $results;
}

/**
 * @brief returns icon name for use with e.g. font-awesome based on mime-type.
 *
 * These are the the font-awesome names of version 3.2.1. The newer font-awesome
 * 4 has different names.
 *
 * @param string $type mime type
 * @return string
 * @todo rename to get_icon_from_type()
 */
function getIconFromType($type) {
	$iconMap = array(
		//Folder
		t('Collection') => 'fa-folder-o',
		'multipart/mixed' => 'fa-folder-o', //dirs in attach use this mime type
		//Common file
		'application/octet-stream' => 'fa-file-o',
		//Text
		'text/plain' => 'fa-file-text-o',
		'text/markdown' => 'fa-file-text-o',
		'text/bbcode' => 'fa-file-text-o',
		'text/html' => 'fa-file-text-o',
		'application/msword' => 'fa-file-word-o',
		'application/pdf' => 'fa-file-pdf-o',
		'application/vnd.oasis.opendocument.text' => 'fa-file-word-o',
		'application/epub+zip' => 'fa-book',
		//Spreadsheet
		'application/vnd.oasis.opendocument.spreadsheet' => 'fa-file-excel-o',
		'application/vnd.ms-excel' => 'fa-file-excel-o',
		//Image
		'image/jpeg' => 'fa-picture-o',
		'image/png' => 'fa-picture-o',
		'image/gif' => 'fa-picture-o',
		'image/svg+xml' => 'fa-picture-o',
		//Archive
		'application/zip' => 'fa-file-archive-o',
		'application/x-rar-compressed' => 'fa-file-archive-o',
		//Audio
		'audio/mpeg' => 'fa-file-audio-o',
		'audio/wav' => 'fa-file-audio-o',
		'application/ogg' => 'fa-file-audio-o',
		'audio/ogg' => 'fa-file-audio-o',
		'audio/webm' => 'fa-file-audio-o',
		'audio/mp4' => 'fa-file-audio-o',
		//Video
		'video/quicktime' => 'fa-file-video-o',
		'video/webm' => 'fa-file-video-o',
		'video/mp4' => 'fa-file-video-o',
		'video/x-matroska' => 'fa-file-video-o'
	);

	$catMap = [ 
		'application' => 'fa-file-code-o',
		'multipart'   => 'fa-folder',
		'audio'       => 'fa-file-audio-o',
		'video'       => 'fa-file-video-o',
		'text'        => 'fa-file-text-o',
		'image'       => 'fa=file-picture-o',
		'message'     => 'fa-file-text-o'
	]; 


	$iconFromType = '';

	if (array_key_exists($type, $iconMap)) {
		$iconFromType = $iconMap[$type];
	}
	else {
		$parts = explode('/',$type);
		if($parts[0] && $catMap[$parts[0]]) { 
			$iconFromType = $catMap[$parts[0]];
		}
	}

	if(! $iconFromType)	{
		$iconFromType = 'fa-file-o';
	}


	return $iconFromType;
}

/**
 * @brief Returns a human readable formatted string for filesizes.
 *
 * @param int $size filesize in bytes
 * @return string human readable formatted filesize
 * @todo rename to user_readable_size()
 */
function userReadableSize($size) {
	$ret = '';
	if (is_numeric($size)) {
		$incr = 0;
		$k = 1024;
		$unit = array('bytes', 'KB', 'MB', 'GB', 'TB', 'PB');
		while (($size / $k) >= 1){
			$incr++;
			$size = round($size / $k, 2);
		}
		$ret = $size . ' ' . $unit[$incr];
	}

	return $ret;
}

function str_rot47($str) {
	return strtr($str,
		'!"#$%&\'()*+,-./0123456789:;<=>?@ABCDEFGHIJKLMNOPQRSTUVWXYZ[\]^_`abcdefghijklmnopqrstuvwxyz{|}~',
		'PQRSTUVWXYZ[\]^_`abcdefghijklmnopqrstuvwxyz{|}~!"#$%&\'()*+,-./0123456789:;<=>?@ABCDEFGHIJKLMNO');
}


function string_replace($old,$new,&$s) {

	$x = str_replace($old,$new,$s);
	$replaced = false;
	if($x !== $s) {
		$replaced = true;
	}
	$s = $x;
	return $replaced;
}


function json_url_replace($old,$new,&$s) {

	$old = str_replace('/','\\/',$old);
	$new = str_replace('/','\\/',$new);

	$x = str_replace($old,$new,$s);
	$replaced = false;
	if($x !== $s) {
		$replaced = true;
	}
	$s = $x;
	return $replaced;
}


function item_url_replace($channel,&$item,$old,$new,$oldnick = '') {

	if($item['attach']) {
		json_url_replace($old,$new,$item['attach']);
		if($oldnick)
			json_url_replace('/' . $oldnick . '/' ,'/' . $channel['channel_address'] . '/' ,$item['attach']);
	}
	if($item['object']) {
		json_url_replace($old,$new,$item['object']);
		if($oldnick)
			json_url_replace('/' . $oldnick . '/' ,'/' . $channel['channel_address'] . '/' ,$item['object']);
	}
	if($item['target']) {
		json_url_replace($old,$new,$item['target']);
		if($oldnick)
			json_url_replace('/' . $oldnick . '/' ,'/' . $channel['channel_address'] . '/' ,$item['target']);
	}

	if(string_replace($old,$new,$item['body'])) {
		$item['sig'] = base64url_encode(rsa_sign($item['body'],$channel['channel_prvkey']));
		$item['item_verified']  = 1;
	}

	$item['plink'] = str_replace($old,$new,$item['plink']);
	if($oldnick)
		$item['plink'] = str_replace('/' . $oldnick . '/' ,'/' . $channel['channel_address'] . '/' ,$item['plink']);

	$item['llink'] = str_replace($old,$new,$item['llink']);
	if($oldnick)
		$item['llink'] = str_replace('/' . $oldnick . '/' ,'/' . $channel['channel_address'] . '/' ,$item['llink']);

}


/**
 * @brief Used to wrap ACL elements in angle brackets for storage.
 *
 * @param[in,out] array &$item
 */
function sanitise_acl(&$item) {
	if (strlen($item))
		$item = '<' . notags(trim(urldecode($item))) . '>';
	else
		unset($item);
}

/**
 * @brief Convert an ACL array to a storable string.
 *
 * @param array $p
 * @return array
 */
function perms2str($p) {
	$ret = '';

	if (is_array($p))
		$tmp = $p;
	else
		$tmp = explode(',', $p);

	if (is_array($tmp)) {
		array_walk($tmp, 'sanitise_acl');
		$ret = implode('', $tmp);
	}

	return $ret;
}

/**
 * @brief Turn user/group ACLs stored as angle bracketed text into arrays.
 *
 * turn string array of angle-bracketed elements into string array
 * e.g. "<123xyz><246qyo><sxo33e>" => array(123xyz,246qyo,sxo33e);
 *
 * @param string $s
 * @return array
 */
function expand_acl($s) {
	$ret = array();

	if(strlen($s)) {
		$t = str_replace('<','',$s);
		$a = explode('>',$t);
		foreach($a as $aa) {
			if($aa)
				$ret[] = $aa;
		}
	}

	return $ret;
}

function acl2json($s) {
	$s = expand_acl($s);
	$s = json_encode($s);

	return $s;
}

/**
 * @brief When editing a webpage - a dropdown is needed to select a page layout
 *
 * On submit, the pdl_select value (which is the mid of an item with item_type = ITEM_TYPE_PDL)
 * is stored in the webpage's resource_id, with resource_type 'pdl'.
 *
 * Then when displaying a webpage, we can see if it has a pdl attached. If not we'll
 * use the default site/page layout.
 *
 * If it has a pdl we'll load it as we know the mid and pass the body through comanche_parser() which will generate the
 * page layout from the given description
 *
 * @FIXME - there is apparently a very similar function called layout_select; this one should probably take precedence
 * and the other should be checked for compatibility and removed
 *
 * @param int $uid
 * @param string $current
 * @return string HTML code for dropdown
 */
function pdl_selector($uid, $current='') {
	$o = '';

	$sql_extra = item_permissions_sql($uid);

	$r = q("select iconfig.*, mid from iconfig left join item on iconfig.iid = item.id
		where item.uid = %d and iconfig.cat = 'system' and iconfig.k = 'PDL' $sql_extra order by v asc",
		intval($uid)
	);

	$arr = array('channel_id' => $uid, 'current' => $current, 'entries' => $r);
	call_hooks('pdl_selector', $arr);

	$entries = $arr['entries'];
	$current = $arr['current'];

	$o .= '<select name="pdl_select" id="pdl_select" size="1">';
	$entries[] = array('title' => t('Default'), 'mid' => '');
	foreach($entries as $selection) {
		$selected = (($selection == $current) ? ' selected="selected" ' : '');
		$o .= "<option value=\"{$selection['mid']}\" $selected >{$selection['v']}</option>";
	}

	$o .= '</select>';
	return $o;
}

/**
 * @brief returns a one-dimensional array from a multi-dimensional array
 * empty values are discarded
 *
 * example: print_r(flatten_array_recursive(array('foo','bar',array('baz','blip',array('zob','glob')),'','grip')));
 *
 * Array ( [0] => foo [1] => bar [2] => baz [3] => blip [4] => zob [5] => glob [6] => grip )
 *
 * @param array $arr multi-dimensional array
 * @return one-dimensional array
 */
function flatten_array_recursive($arr) {
	$ret = array();

	if(! $arr)
		return $ret;

	foreach($arr as $a) {
		if(is_array($a)) {
			$tmp = flatten_array_recursive($a);
			if($tmp) {
				$ret = array_merge($ret, $tmp);
			}
		}
		elseif($a) {
			$ret[] = $a;
		}
	}

	return($ret);
}

/**
 * @brief Highlight Text.
 *
 * @param string $s Text to highlight
 * @param string $lang Which language should be highlighted
 * @return string
 *     Important: The returned text has the text pattern 'http' translated to '%eY9-!' which should be converted back
 * after further processing. This was done to prevent oembed links from occurring inside code blocks. 
 * See include/bbcode.php
 */
function text_highlight($s, $lang) {

	if($lang === 'js')
		$lang = 'javascript';

	if($lang === 'json') {
		$lang = 'javascript';
		if(! strpos(trim($s), "\n"))
			$s = jindent($s);
	}

	$arr = [
			'text' => $s,
			'language' => $lang,
			'success' => false
	];

	/**
	 * @hooks text_highlight
	 *   * \e string \b text
	 *   * \e string \b language
	 *   * \e boolean \b success default false
	 */
	call_hooks('text_highlight', $arr);

	if($arr['success'])
		$o = $arr['text'];
	else
		$o = $s;

	$o = str_replace('http','%eY9-!',$o);

	return('<code>' . $o . '</code>');
}

// function to convert multi-dimensional array to xml
// create new instance of simplexml

// $xml = new SimpleXMLElement('<root/>');

// function callback
// array2XML($xml, $my_array);

// save as xml file
// echo (($xml->asXML('data.xml')) ? 'Your XML file has been generated successfully!' : 'Error generating XML file!');

function arrtoxml($root_elem,$arr) {
	$xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><' . $root_elem . '></' . $root_elem . '>', null, false);
	array2XML($xml,$arr);

	return $xml->asXML();
}

function array2XML($obj, $array) {
	foreach ($array as $key => $value) {
		if(is_numeric($key))
			$key = 'item' . $key;

		if(is_array($value)) {
			$node = $obj->addChild($key);
			array2XML($node, $value);
		}
		else {
			$obj->addChild($key, htmlspecialchars($value));
		}
	}
}

/**
 * @brief Inserts an array into $table.
 *
 * @TODO Why is this function in include/text.php?
 *
 * @param string $table
 * @param array $arr
 * @return boolean|PDOStatement
 */
function create_table_from_array($table, $arr) {

	if(! ($arr && $table))
		return false;

	if(dbesc_array($arr)) {
		$r = dbq("INSERT INTO " . TQUOT . $table . TQUOT . " (" . TQUOT
			. implode(TQUOT . ', ' . TQUOT, array_keys($arr))
			. TQUOT . ") VALUES ('"
			. implode("', '", array_values($arr))
			. "')"
		);
	}

	return $r;
}

function share_shield($m) {
	return str_replace($m[1],'!=+=+=!' . base64url_encode($m[1]) . '=+!=+!=',$m[0]);
}

function share_unshield($m) {
	$x = str_replace(array('!=+=+=!','=+!=+!='),array('',''),$m[1]);
	return str_replace($m[1], base64url_decode($x), $m[0]);
}


function cleanup_bbcode($body) {

	/**
	 * fix naked links by passing through a callback to see if this is a hubzilla site
	 * (already known to us) which will get a zrl, otherwise link with url, add bookmark tag to both.
	 * First protect any url inside certain bbcode tags so we don't double link it.
	 */

	$body = preg_replace_callback('/\[code(.*?)\[\/(code)\]/ism','\red_escape_codeblock',$body);
	$body = preg_replace_callback('/\[url(.*?)\[\/(url)\]/ism','\red_escape_codeblock',$body);
	$body = preg_replace_callback('/\[zrl(.*?)\[\/(zrl)\]/ism','\red_escape_codeblock',$body);


	$body = preg_replace_callback("/([^\]\='".'"'."\/]|^|\#\^)(https?\:\/\/[a-zA-Z0-9\pL\:\/\-\?\&\;\.\=\@\_\~\#\%\$\!\\
+\,\(\)]+)/ismu", '\nakedoembed', $body);
	$body = preg_replace_callback("/([^\]\='".'"'."\/]|^|\#\^)(https?\:\/\/[a-zA-Z0-9\pL\:\/\-\?\&\;\.\=\@\_\~\#\%\$\!\\
+\,\(\)]+)/ismu", '\red_zrl_callback', $body);

	$body = preg_replace_callback('/\[\$b64zrl(.*?)\[\/(zrl)\]/ism','\red_unescape_codeblock',$body);
	$body = preg_replace_callback('/\[\$b64url(.*?)\[\/(url)\]/ism','\red_unescape_codeblock',$body);
	$body = preg_replace_callback('/\[\$b64code(.*?)\[\/(code)\]/ism','\red_unescape_codeblock',$body);

	// fix any img tags that should be zmg

	$body = preg_replace_callback('/\[img(.*?)\](.*?)\[\/img\]/ism','\red_zrlify_img_callback',$body);


	$body = bb_translate_video($body);

	/**
	 * Fold multi-line [code] sequences
	 */

	$body = preg_replace('/\[\/code\]\s*\[code\]/ism',"\n",$body);

	$body = scale_external_images($body,false);


	return $body;
}

function gen_link_id($mid) {
	if(strpbrk($mid,':/&?<>"\'') !== false)
		return 'b64.' . base64url_encode($mid);
	return $mid;
}


// callback for array_walk

function array_trim(&$v,$k) {
	$v = trim($v);
}

function array_escape_tags(&$v,$k) {
	$v = escape_tags($v);
}

function ellipsify($s,$maxlen) {
	if($maxlen & 1)
		$maxlen --;
	if($maxlen < 4)
		$maxlen = 4;

	if(mb_strlen($s) < $maxlen)
		return $s;

	return mb_substr($s,0,$maxlen / 2) . '...' . mb_substr($s,mb_strlen($s) - ($maxlen / 2));
}

function purify_filename($s) {
	if(($s[0] === '.') || strpos($s,'/') !== false)
		return '';
	return $s;
}


