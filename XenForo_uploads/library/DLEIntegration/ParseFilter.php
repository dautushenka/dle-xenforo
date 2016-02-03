<?php
/*
=====================================================
 DataLife Engine - by SoftNews Media Group 
-----------------------------------------------------
 http://dle-news.ru/
-----------------------------------------------------
 Copyright (c) 2004,2009 SoftNews Media Group
=====================================================
 ������ ��� ������� ���������� �������
=====================================================
 ����: parse.class.php
-----------------------------------------------------
 ����������: ����� ������� ������
=====================================================
*/

class DLEIntegration_ParseFilter {
	var $tagsArray;
	var $attrArray;
	var $tagsMethod;
	var $attrMethod;
	var $xssAuto;
	var $video_config = array ();
	var $code_text = array ();
	var $code_count = 0;
	var $wysiwyg = false;
	var $allow_php = false;
	var $safe_mode = false;
	var $allow_code = true;
	var $leech_mode = false;
	var $filter_mode = true;
	var $allow_url = true;
	var $allow_image = true;
	var $not_allowed_tags = false;
	var $not_allowed_text = false;
	var $tagBlacklist = array ('applet', 'body', 'bgsound', 'base', 'basefont', 'frame', 'frameset', 'head', 'html', 'id', 'iframe', 'ilayer', 'layer', 'link', 'meta', 'name', 'script', 'style', 'title', 'xml' );
	var $attrBlacklist = array ('action', 'background', 'codebase', 'dynsrc', 'lowsrc' );
	
	var $font_sizes = array (1 => '8', 2 => '10', 3 => '12', 4 => '14', 5 => '18', 6 => '24', 7 => '36' );
	
	function DLE_ParseFilter($tagsArray = array(), $attrArray = array(), $tagsMethod = 0, $attrMethod = 0, $xssAuto = 1) {
		for($i = 0; $i < count( $tagsArray ); $i ++)
			$tagsArray[$i] = strtolower( $tagsArray[$i] );
		for($i = 0; $i < count( $attrArray ); $i ++)
			$attrArray[$i] = strtolower( $attrArray[$i] );
		$this->tagsArray = ( array ) $tagsArray;
		$this->attrArray = ( array ) $attrArray;
		$this->tagsMethod = $tagsMethod;
		$this->attrMethod = $attrMethod;
		$this->xssAuto = $xssAuto;
	}
	function process($source) {
			
		if( function_exists( "get_magic_quotes_gpc" ) && get_magic_quotes_gpc() ) $source = stripslashes( $source );  

		$source = $this->remove( $this->decode( $source ) );
			
		if( $this->code_count ) {
			foreach ( $this->code_text as $key_find => $key_replace ) {
				$find[] = $key_find;
				$replace[] = $key_replace;
			}
				
			$source = str_replace( $find, $replace, $source );
		}
			
		$this->code_count = 0;
		$this->code_text = array ();
		$source = preg_replace( "#\{include#i", "&#123;include", $source );

		$source = addslashes( $source );			
		return $source;

	}
	function remove($source) {
		$loopCounter = 0;
		while ( $source != $this->filterTags( $source ) ) {
			$source = $this->filterTags( $source );
			$loopCounter ++;
		}
		return $source;
	}
	function filterTags($source) {
		$preTag = NULL;
		$postTag = $source;
		$tagOpen_start = strpos( $source, '<' );
		while ( $tagOpen_start !== FALSE ) {
			$preTag .= substr( $postTag, 0, $tagOpen_start );
			$postTag = substr( $postTag, $tagOpen_start );
			$fromTagOpen = substr( $postTag, 1 );
			$tagOpen_end = strpos( $fromTagOpen, '>' );
			if( $tagOpen_end === false ) break;
			$tagOpen_nested = strpos( $fromTagOpen, '<' );
			if( ($tagOpen_nested !== false) && ($tagOpen_nested < $tagOpen_end) ) {
				$preTag .= substr( $postTag, 0, ($tagOpen_nested + 1) );
				$postTag = substr( $postTag, ($tagOpen_nested + 1) );
				$tagOpen_start = strpos( $postTag, '<' );
				continue;
			}
			$tagOpen_nested = (strpos( $fromTagOpen, '<' ) + $tagOpen_start + 1);
			$currentTag = substr( $fromTagOpen, 0, $tagOpen_end );
			$tagLength = strlen( $currentTag );
			if( ! $tagOpen_end ) {
				$preTag .= $postTag;
				$tagOpen_start = strpos( $postTag, '<' );
			}
			$tagLeft = $currentTag;
			$attrSet = array ();
			$currentSpace = strpos( $tagLeft, ' ' );
			if( substr( $currentTag, 0, 1 ) == "/" ) {
				$isCloseTag = TRUE;
				list ( $tagName ) = explode( ' ', $currentTag );
				$tagName = substr( $tagName, 1 );
			} else {
				$isCloseTag = FALSE;
				list ( $tagName ) = explode( ' ', $currentTag );
			}
			if( (! preg_match( "/^[a-z][a-z0-9]*$/i", $tagName )) || (! $tagName) || ((in_array( strtolower( $tagName ), $this->tagBlacklist )) && ($this->xssAuto)) ) {
				$postTag = substr( $postTag, ($tagLength + 2) );
				$tagOpen_start = strpos( $postTag, '<' );
				continue;
			}
			while ( $currentSpace !== FALSE ) {
				$fromSpace = substr( $tagLeft, ($currentSpace + 1) );
				$nextSpace = strpos( $fromSpace, ' ' );
				$openQuotes = strpos( $fromSpace, '"' );
				$closeQuotes = strpos( substr( $fromSpace, ($openQuotes + 1) ), '"' ) + $openQuotes + 1;
				if( strpos( $fromSpace, '=' ) !== FALSE ) {
					if( ($openQuotes !== FALSE) && (strpos( substr( $fromSpace, ($openQuotes + 1) ), '"' ) !== FALSE) ) $attr = substr( $fromSpace, 0, ($closeQuotes + 1) );
					else $attr = substr( $fromSpace, 0, $nextSpace );
				} else
					$attr = substr( $fromSpace, 0, $nextSpace );
				if( ! $attr ) $attr = $fromSpace;
				$attrSet[] = $attr;
				$tagLeft = substr( $fromSpace, strlen( $attr ) );
				$currentSpace = strpos( $tagLeft, ' ' );
			}
			$tagFound = in_array( strtolower( $tagName ), $this->tagsArray );
			if( (! $tagFound && $this->tagsMethod) || ($tagFound && ! $this->tagsMethod) ) {
				if( ! $isCloseTag ) {
					$attrSet = $this->filterAttr( $attrSet, strtolower( $tagName ) );
					$preTag .= '<' . $tagName;
					for($i = 0; $i < count( $attrSet ); $i ++)
						$preTag .= ' ' . $attrSet[$i];
					if( strpos( $fromTagOpen, "</" . $tagName ) ) $preTag .= '>';
					else $preTag .= ' />';
				} else
					$preTag .= '</' . $tagName . '>';
			}
			$postTag = substr( $postTag, ($tagLength + 2) );
			$tagOpen_start = strpos( $postTag, '<' );
		}
		$preTag .= $postTag;
		return $preTag;
	}
	
	function filterAttr($attrSet, $tagName) {
		
		global $config;
		
		$newSet = array ();
		for($i = 0; $i < count( $attrSet ); $i ++) {
			if( ! $attrSet[$i] ) continue;
			
			$attrSet[$i] = trim( $attrSet[$i] );
			
			$exp = strpos( $attrSet[$i], '=' );
			if( $exp === false ) $attrSubSet = Array ($attrSet[$i] );
			else {
				$attrSubSet = Array ();
				$attrSubSet[] = substr( $attrSet[$i], 0, $exp );
				$attrSubSet[] = substr( $attrSet[$i], $exp + 1 );
			}
			$attrSubSet[1] = stripslashes( $attrSubSet[1] );
			
			list ( $attrSubSet[0] ) = explode( ' ', $attrSubSet[0] );
			
			$attrSubSet[0] = strtolower( $attrSubSet[0] );
			
			if( (! preg_match( "/^[a-z]*$/i", $attrSubSet[0] )) || (($this->xssAuto) && ((in_array( $attrSubSet[0], $this->attrBlacklist )) || (substr( $attrSubSet[0], 0, 2 ) == 'on'))) ) continue;
			if( $attrSubSet[1] ) {
				$attrSubSet[1] = str_replace( '&#', '', $attrSubSet[1] );
				$attrSubSet[1] = preg_replace( '/\s+/', ' ', $attrSubSet[1] );
				$attrSubSet[1] = str_replace( '"', '', $attrSubSet[1] );
				if( (substr( $attrSubSet[1], 0, 1 ) == "'") && (substr( $attrSubSet[1], (strlen( $attrSubSet[1] ) - 1), 1 ) == "'") ) $attrSubSet[1] = substr( $attrSubSet[1], 1, (strlen( $attrSubSet[1] ) - 2) );
			}
			
			if( ((strpos( strtolower( $attrSubSet[1] ), 'expression' ) !== false) && ($attrSubSet[0] == 'style')) || (strpos( strtolower( $attrSubSet[1] ), 'javascript:' ) !== false) || (strpos( strtolower( $attrSubSet[1] ), 'behaviour:' ) !== false) || (strpos( strtolower( $attrSubSet[1] ), 'vbscript:' ) !== false) || (strpos( strtolower( $attrSubSet[1] ), 'mocha:' ) !== false) || (strpos( strtolower( $attrSubSet[1] ), 'data:' ) !== false and $attrSubSet[0] == "href") || (strpos( strtolower( $attrSubSet[1] ), 'data:' ) !== false and $attrSubSet[0] == "src") || ($attrSubSet[0] == "href" and strpos( strtolower( $attrSubSet[1] ), $config['admin_path'] ) !== false and preg_match( "/[?&%<\[\]]/", $attrSubSet[1] )) || (strpos( strtolower( $attrSubSet[1] ), 'livescript:' ) !== false) ) continue;

			$attrFound = in_array( $attrSubSet[0], $this->attrArray );
			if( (! $attrFound && $this->attrMethod) || ($attrFound && ! $this->attrMethod) ) {
				if( $attrSubSet[1] ) $newSet[] = $attrSubSet[0] . '="' . $attrSubSet[1] . '"';
				elseif( $attrSubSet[1] == "0" ) $newSet[] = $attrSubSet[0] . '="0"';
				else $newSet[] = $attrSubSet[0] . '=""';
			}
		}
		;
		return $newSet;
	}
	function decode($source) {
		
		if( $this->allow_code )
			$source = preg_replace( "#\[code\](.+?)\[/code\]#ies", "\$this->code_tag( '\\1' )", $source );
		
		if( $this->safe_mode and ! $this->wysiwyg ) {
			
			$source = htmlspecialchars( $source, ENT_QUOTES );
			$source = str_replace( '&amp;', '&', $source );
		
		} else {
			
			$source = str_replace( "<>", "&lt;&gt;", str_replace( ">>", "&gt;&gt;", str_replace( "<<", "&lt;&lt;", $source ) ) );
			$source = str_replace( "<!--", "&lt;!--", $source );
		
		}

		return $source;
	}
	
	function BB_Parse($source, $use_html = TRUE) {
		
		global $config, $lang;
		
		$find = array ('/about:/i', '/vbscript:/i', '/onclick/i', '/onload/i', '/onunload/i', '/onabort/i', '/onerror/i', '/onblur/i', '/onchange/i', '/onfocus/i', '/onreset/i', '/onsubmit/i', '/ondblclick/i', '/onkeydown/i', '/onkeypress/i', '/onkeyup/i', '/onmousedown/i', '/onmouseup/i', '/onmouseover/i', '/onmouseout/i', '/onselect/i', '/javascript/i', "'\[quote\]'si", "'\[quote=(.+?)\]'si", "'\[/quote\]'si", "'\[/spoiler\]'si" );
		
		$replace = array ("&#097;bout:", "vbscript<b></b>:", "&#111;nclick", "&#111;nload", "&#111;nunload", "&#111;nabort", "&#111;nerror", "&#111;nblur", "&#111;nchange", "&#111;nfocus", "&#111;nreset", "&#111;nsubmit", "&#111;ndblclick", "&#111;nkeydown", "&#111;nkeypress", "&#111;nkeyup", "&#111;nmousedown", "&#111;nmouseup", "&#111;nmouseover", "&#111;nmouseout", "&#111;nselect", "j&#097;vascript", "<!--QuoteBegin--><div class=\"quote\"><!--QuoteEBegin-->", "<!--QuoteBegin \\1 --><div class=\"title_quote\">{$lang['i_quote']} \\1</div><div class=\"quote\"><!--QuoteEBegin-->", "<!--QuoteEnd--></div><!--QuoteEEnd-->", "<!--spoiler_text_end--></div><!--/dle_spoiler-->" );
		
		if( $use_html == false ) {
			$find[] = "'\r'";
			$replace[] = "";
			$find[] = "'\n'";
			$replace[] = "<br />";
		} else {
			$source = str_replace( "\r\n\r\n", "\n", $source );
		}
		
		$smilies_arr = explode( ",", $config['smilies'] );
		foreach ( $smilies_arr as $smile ) {
			$smile = trim( $smile );
			$find[] = "':$smile:'";
			$replace[] = "<!--smile:{$smile}--><img style=\"vertical-align: middle;border: none;\" alt=\"$smile\" src=\"" . $config['http_home_url'] . "engine/data/emoticons/{$smile}.gif\" /><!--/smile-->";
		}
		
		$source = preg_replace( $find, $replace, $source );
		$source = preg_replace( "#<iframe#i", "&lt;iframe", $source );
		$source = preg_replace( "#<script#i", "&lt;script", $source );
		
		$source = str_replace( "`", "&#96;", $source );
		$source = str_replace( "{THEME}", "&#123;THEME}", $source );
		
		if( ! $this->allow_php ) {
			
			$source = str_replace( "<?", "&lt;?", $source );
			$source = str_replace( "?>", "?&gt;", $source );
		
		}
		
		$source = preg_replace( "#\[code\](.+?)\[/code\]#is", "<!--code1--><div class=\"scriptcode\"><!--ecode1-->\\1<!--code2--></div><!--ecode2-->", $source );
		$source = preg_replace( "#\[(left|right|center)\](.+?)\[/\\1\]#is", "<div align=\"\\1\">\\2</div>", $source );
		
		$source = preg_replace( "#\[b\](.+?)\[/b\]#is", "<b>\\1</b>", $source );
		$source = preg_replace( "#\[i\](.+?)\[/i\]#is", "<i>\\1</i>", $source );
		$source = preg_replace( "#\[u\](.+?)\[/u\]#is", "<u>\\1</u>", $source );
		$source = preg_replace( "#\[s\](.+?)\[/s\]#is", "<s>\\1</s>", $source );
		
		$source = preg_replace( "#\[spoiler\]#ie", "\$this->build_spoiler('')", $source );
		$source = preg_replace( "#\[spoiler=(.+?)\]#ie", "\$this->build_spoiler('\\1')", $source );
		
		if( $this->allow_url ) {
			
			$source = preg_replace( "#\[url\](\S.+?)\[/url\]#ie", "\$this->build_url(array('html' => '\\1', 'show' => '\\1'))", $source );
			$source = preg_replace( "#\[url\s*=\s*\&quot\;\s*(\S+?)\s*\&quot\;\s*\](.*?)\[\/url\]#ie", "\$this->build_url(array('html' => '\\1', 'show' => '\\2'))", $source );
			$source = preg_replace( "#\[url\s*=\s*(\S.+?)\s*\](.*?)\[\/url\]#ie", "\$this->build_url(array('html' => '\\1', 'show' => '\\2'))", $source );
			
			$source = preg_replace( "#\[leech\](\S.+?)\[/leech\]#ie", "\$this->build_url(array('html' => '\\1', 'show' => '\\1', 'leech' => '1'))", $source );
			$source = preg_replace( "#\[leech\s*=\s*\&quot\;\s*(\S+?)\s*\&quot\;\s*\](.*?)\[\/leech\]#ie", "\$this->build_url(array('html' => '\\1', 'show' => '\\2', 'leech' => '1'))", $source );
			$source = preg_replace( "#\[leech\s*=\s*(\S.+?)\s*\](.*?)\[\/leech\]#ie", "\$this->build_url(array('html' => '\\1', 'show' => '\\2', 'leech' => '1'))", $source );
		
		} else {
			
			if( stristr( $source, "[url" ) !== false ) $this->not_allowed_tags = true;
			if( stristr( $source, "[leech" ) !== false ) $this->not_allowed_tags = true;
			if( stristr( $source, "&lt;a" ) !== false ) $this->not_allowed_tags = true;
		
		}
		
		if( $this->allow_image ) {
			
			$source = preg_replace( "#\[img\](.+?)\[/img\]#ie", "\$this->build_image('\\1')", $source );
			$source = preg_replace( "#\[img=(.+?)\](.+?)\[/img\]#ie", "\$this->build_image('\\2', '\\1')", $source );
		
		} else {
			
			if( stristr( $source, "[img" ) !== false ) $this->not_allowed_tags = true;
			if( stristr( $source, "&lt;img" ) !== false ) $this->not_allowed_tags = true;
		
		}
		
		$source = preg_replace( "#\[email\s*=\s*\&quot\;([\.\w\-]+\@[\.\w\-]+\.[\.\w\-]+)\s*\&quot\;\s*\](.*?)\[\/email\]#ie", "\$this->build_email(array('html' => '\\1', 'show' => '\\2'))", $source );
		$source = preg_replace( "#\[email\s*=\s*([\.\w\-]+\@[\.\w\-]+\.[\w\-]+)\s*\](.*?)\[\/email\]#ie", "\$this->build_email(array('html' => '\\1', 'show' => '\\2'))", $source );
		
		if( ! $this->safe_mode ) {
			
			$source = preg_replace( "'\[thumb\]([^\[]*)([/\\\\])(.*?)\[/thumb\]'ie", "\$this->build_thumb('\$1\$2\$3', '\$1\$2thumbs\$2\$3')", $source );
			$source = preg_replace( "'\[thumb=(.*?)\]([^\[]*)([/\\\\])(.*?)\[/thumb\]'ie", "\$this->build_thumb('\$2\$3\$4', '\$2\$3thumbs\$3\$4', '\$1')", $source );
			$source = preg_replace( "#\[video\s*=\s*(\S.+?)\s*\]#ie", "\$this->build_video('\\1')", $source );
			$source = preg_replace( "#\[audio\s*=\s*(\S.+?)\s*\]#ie", "\$this->build_audio('\\1')", $source );
			$source = preg_replace( "#\[flash=([^\]]+)\](.+?)\[/flash\]#ies", "\$this->build_flash('\\1', '\\2')", $source );
			$source = preg_replace( "#\[youtube=([^\]]+)\]#ies", "\$this->build_youtube('\\1')", $source );
			
			while ( preg_match( "#\[size=([^\]]+)\](.+?)\[/size\]#ies", $source ) ) {
                $source = preg_replace( "#\[size=([^\]]+)\](.+?)\[/size\]#ies", "\$this->font_change(array('tag'=>'size','1'=>'\\1','2'=>'\\2'))", $source );
            }
            
            while ( preg_match( "#\[font=\"?([^\"\]]+)\"?\](.+?)\[/font\]#ies", $source ) ) {
                $source = preg_replace( "#\[font=\"?([^\"\]]+)\"?\](.+?)\[/font\]#ies", "\$this->font_change(array('tag'=>'font','1'=>'\\1','2'=>'\\2'))", $source );
		
    		}
    		
    		while ( preg_match( "#\[color=\"?([^\"\]]+)\"?\](.+?)\[/color\]#ies", $source ) ) {
    			$source = preg_replace( "#\[color=\"?([^\"\]]+)\"?\](.+?)\[/color\]#ies", "\$this->font_change(array('tag'=>'color','1'=>'\\1','2'=>'\\2'))", $source );
    		}
		}
		
		if( $this->filter_mode ) $source = $this->word_filter( $source );
		
		return trim( $source );
	
	}
	
	function decodeBBCodes($txt, $use_html = TRUE, $wysiwig = "no") {
		
		global $config;
		
		$find = array ();
		$result = array ();
		$txt = stripslashes( $txt );
		if( $this->filter_mode ) $txt = $this->word_filter( $txt, false );
		
		$txt = preg_replace( "#<!--ThumbBegin-->(.+?)<!--ThumbEnd-->#ie", "\$this->decode_thumb('\\1')", $txt );
		$txt = preg_replace( "#<!--TBegin-->(.+?)<!--TEnd-->#ie", "\$this->decode_newthumb('\\1')", $txt );
		$txt = preg_replace( "#<!--QuoteBegin-->(.+?)<!--QuoteEBegin-->#", '[quote]', $txt );
		$txt = preg_replace( "#<!--QuoteBegin ([^>]+?) -->(.+?)<!--QuoteEBegin-->#", "[quote=\\1]", $txt );
		$txt = preg_replace( "#<!--QuoteEnd-->(.+?)<!--QuoteEEnd-->#", '[/quote]', $txt );
		$txt = preg_replace( "#<!--code1-->(.+?)<!--ecode1-->#", '[code]', $txt );
		$txt = preg_replace( "#<!--code2-->(.+?)<!--ecode2-->#", '[/code]', $txt );
		$txt = preg_replace( "#<!--dle_leech_begin--><a href=[\"'](http://|https://|ftp://|ed2k://|news://|magnet:)?(\S.+?)['\"].*?" . ">(.+?)</a><!--dle_leech_end-->#ie", "\$this->decode_leech('\\1\\2', '\\3')", $txt );
		$txt = preg_replace( "#<!--dle_video_begin-->(.+?)src=\"(.+?)\"(.+?)<!--dle_video_end-->#is", '[video=\\2]', $txt );
		$txt = preg_replace( "#<!--dle_video_begin:(.+?)-->(.+?)<!--dle_video_end-->#is", '[video=\\1]', $txt );
		$txt = preg_replace( "#<!--dle_audio_begin:(.+?)-->(.+?)<!--dle_audio_end-->#is", '[audio=\\1]', $txt );
		$txt = preg_replace( "#<!--dle_image_begin:(.+?)-->(.+?)<!--dle_image_end-->#ies", "\$this->decode_dle_img('\\1')", $txt );
		$txt = preg_replace( "#<!--dle_youtube_begin:(.+?)-->(.+?)<!--dle_youtube_end-->#is", '[youtube=\\1]', $txt );
		$txt = preg_replace( "#<!--dle_flash_begin:(.+?)-->(.+?)<!--dle_flash_end-->#ies", "\$this->decode_flash('\\1')", $txt );
		$txt = preg_replace( "#<!--dle_spoiler-->(.+?)<!--spoiler_text-->#is", '[spoiler]', $txt );
		$txt = preg_replace( "#<!--dle_spoiler (.+?) -->(.+?)<!--spoiler_text-->#is", '[spoiler=\\1]', $txt );
		$txt = str_replace( "<!--spoiler_text_end--></div><!--/dle_spoiler-->", '[/spoiler]', $txt );
		
		if( $wysiwig != "yes" ) {
			$txt = preg_replace( "#<i>(.+?)</i>#is", "[i]\\1[/i]", $txt );
			$txt = preg_replace( "#<b>(.+?)</b>#is", "[b]\\1[/b]", $txt );
			$txt = preg_replace( "#<s>(.+?)</s>#is", "[s]\\1[/s]", $txt );
			$txt = preg_replace( "#<u>(.+?)</u>#is", "[u]\\1[/u]", $txt );
			$txt = preg_replace( "#<center>(.+?)</center>#is", "[center]\\1[/center]", $txt );
			$txt = preg_replace( "#<img src=[\"'](\S+?)['\"](.+?)>#ie", "\$this->decode_img('\\1', '\\2')", $txt );
			
			$txt = preg_replace( "#<a href=[\"']mailto:(.+?)['\"]>(.+?)</a>#", "[email=\\1]\\2[/email]", $txt );
			$txt = preg_replace( "#<noindex><a href=[\"'](http://|https://|ftp://|ed2k://|news://|magnet:)?(\S.+?)['\"].*?" . ">(.+?)</a></noindex>#ie", "\$this->decode_url('\\1\\2', '\\3')", $txt );
			$txt = preg_replace( "#<a href=[\"'](http://|https://|ftp://|ed2k://|news://|magnet:)?(\S.+?)['\"].*?" . ">(.+?)</a>#ie", "\$this->decode_url('\\1\\2', '\\3')", $txt );
			
			$txt = preg_replace( "#<!--sizestart:(.+?)-->(.+?)<!--/sizestart-->#", "[size=\\1]", $txt );
			$txt = preg_replace( "#<!--colorstart:(.+?)-->(.+?)<!--/colorstart-->#", "[color=\\1]", $txt );
			$txt = preg_replace( "#<!--fontstart:(.+?)-->(.+?)<!--/fontstart-->#", "[font=\\1]", $txt );
			
			$txt = str_replace( "<!--sizeend--></span><!--/sizeend-->", "[/size]", $txt );
			$txt = str_replace( "<!--colorend--></span><!--/colorend-->", "[/color]", $txt );
			$txt = str_replace( "<!--fontend--></span><!--/fontend-->", "[/font]", $txt );
			
			while ( preg_match( "#<span style=['\"]color:(.+?)['\"]>(.+?)</span>#is", $txt ) ) {
				$txt = preg_replace( "#<span style=['\"]color:(.+?)['\"]>(.+?)</span>#is", "[color=\\1]\\2[/color]", $txt );
			}
			
			while ( preg_match( "#<div align=['\"]left['\"]>(.+?)</div>#is", $txt ) ) {
				$txt = preg_replace( "#<div align=['\"]left['\"]>(.+?)</div>#is", "[left]\\1[/left]", $txt );
			}
			while ( preg_match( "#<div align=['\"]right['\"]>(.+?)</div>#is", $txt ) ) {
				$txt = preg_replace( "#<div align=['\"]right['\"]>(.+?)</div>#is", "[right]\\1[/right]", $txt );
			}
			while ( preg_match( "#<div align=['\"]center['\"]>(.+?)</div>#is", $txt ) ) {
				$txt = preg_replace( "#<div align=['\"]center['\"]>(.+?)</div>#is", "[center]\\1[/center]", $txt );
			}
		
		} else {
			
			$txt = str_replace( "<!--sizeend--></span><!--/sizeend-->", "</span>", $txt );
			$txt = str_replace( "<!--colorend--></span><!--/colorend-->", "</span>", $txt );
			$txt = str_replace( "<!--fontend--></span><!--/fontend-->", "</span>", $txt );
			$txt = str_replace( "<!--/sizestart-->", "", $txt );
			$txt = str_replace( "<!--/colorstart-->", "", $txt );
			$txt = str_replace( "<!--/fontstart-->", "", $txt );
			$txt = preg_replace( "#<!--sizestart:(.+?)-->#", "", $txt );
			$txt = preg_replace( "#<!--colorstart:(.+?)-->#", "", $txt );
			$txt = preg_replace( "#<!--fontstart:(.+?)-->#", "", $txt );
		
		}

		$txt = preg_replace( "#<!--smile:(.+?)-->(.+?)<!--/smile-->#is", ':\\1:', $txt );

		$smilies_arr = explode( ",", $config['smilies'] );

		foreach ( $smilies_arr as $smile ) {
			$smile = trim( $smile );
			$replace[] = ":$smile:";
			$find[] = "#<img style=['\"]border: none;['\"] alt=['\"]" . $smile . "['\"] align=['\"]absmiddle['\"] src=['\"](.+?)" . $smile . ".gif['\"] />#is";
		}

		$txt = preg_replace( $find, $replace, $txt );
		
		if( ! $use_html ) {
			$txt = str_replace( "<br>", "\n", $txt );
			$txt = str_replace( "<br />", "\n", $txt );
			$txt = str_replace( "<BR>", "\n", $txt );
			$txt = str_replace( "<BR />", "\n", $txt );
		}
		
		if (!$this->safe_mode) $txt = htmlspecialchars( $txt, ENT_QUOTES );
		if( $wysiwig != "yes" ) $txt = preg_replace( "#\[code\](.+?)\[/code\]#ies", "\$this->decode_code('\\1', '{$use_html}')", $txt );

		return trim( $txt );
	
	}
	
	function font_change($tags) {
		
		if( ! is_array( $tags ) ) {
			return;
		}
		
		$style = $tags['1'];
		$text = stripslashes( $tags['2'] );
		$type = $tags['tag'];
		
		$style = str_replace( '&quot;', '', $style );
		$style = preg_replace( "/[&\(\)\.\%\[\]<>\'\"]/", "", preg_replace( "#^(.+?)(?:;|$)#", "\\1", $style ) );
		
		if( $type == 'size' ) {
			$style = intval( $style );
			
			if( $this->font_sizes[$style] ) {
				$real = $this->font_sizes[$style];
			} else {
				$real = 12;
			}
			
			return "<!--sizestart:{$style}--><span style=\"font-size:" . $real . "pt;line-height:100%\"><!--/sizestart-->" . $text . "<!--sizeend--></span><!--/sizeend-->";
		}
		
		if( $type == 'font' ) {
			$style = preg_replace( "/[^\d\w\#\-\_\s]/s", "", $style );
			return "<!--fontstart:{$style}--><span style=\"font-family:" . $style . "\"><!--/fontstart-->" . $text . "<!--fontend--></span><!--/fontend-->";
		}
		
		$style = preg_replace( "/[^\d\w\#\s]/s", "", $style );
		return "<!--colorstart:{$style}--><span style=\"color:" . $style . "\"><!--/colorstart-->" . $text . "<!--colorend--></span><!--/colorend-->";
	}
	
	function build_email($url = array()) {
		
		$url['html'] = $this->clear_url( $url['html'] );
		$url['show'] = stripslashes( $url['show'] );
		
		return "<a href=\"mailto:{$url['html']}\">{$url['show']}</a>";
	
	}

	function build_flash($size, $url) {

		$size = explode(",", $size);

		$width = trim(intval($size[0]));
		$height = trim(intval($size[1]));

		if (!$width OR !$height) return "[flash=".implode(",",$size)."]".$url."[/flash]";

		$url = $this->clear_url( urldecode( $url ) );
		
		if( $url == "" ) return;

		$type = explode( ".", $url );
		$type = strtolower( end( $type ) );
		
		if ( strtolower($type) != "swf" )
		{
			return "[flash=".implode(",",$size)."]".$url."[/flash]";
		}

		return "<!--dle_flash_begin:{$width}||{$height}||{$url}--><object classid='clsid:D27CDB6E-AE6D-11cf-96B8-444553540000' width='$width' height='$height'><param name='movie' value='$url'><param name='wmode' value='transparent' /><param name='play' value='true'><param name='loop' value='true'><param name='quality' value='high'><param name='allowscriptaccess' value='never'><embed AllowScriptAccess='never' src='$url' width='$width' height='$height' play='true' loop='true' quality='high' wmode='transparent'></embed></object><!--dle_flash_end-->";


	}

	function decode_flash($url)
	{
		$url = explode( "||", $url );
		
		return '[flash='.$url[0].','.$url[1].']'.$url[2].'[/flash]';
	}

	function build_youtube($url) {

		if (!count($this->video_config)) {

			include (ENGINE_DIR . '/data/videoconfig.php');
			$this->video_config = $video_config;

		}	

		$url = $this->clear_url( urldecode( $url ) );
		$url = str_replace("&amp;","&", $url );
		
		if( $url == "" ) return;

		$source = @parse_url ( $url );

		$source['host'] = str_replace( "www.", "", strtolower($source['host']) );

		if ($source['host'] != "youtube.com" AND $source['host'] != "rutube.ru") return "[youtube=".$url."]";

		$a = explode('&', $source['query']);
		$i = 0;

		while ($i < count($a)) {
		    $b = explode('=', $a[$i]);
		    if ($b[0] == "v") $video_link = $b[1];
		    $i++;
		}

		if ($source['host'] == "youtube.com")
			return '<!--dle_youtube_begin:'.$url.'--><object width="'.$this->video_config['width'].'" height="'.$this->video_config['height'].'"><param name="movie" value="http://www.youtube.com/v/'.$video_link.'&hl=ru&fs=1"></param><param name="wmode" value="transparent" /><param name="allowFullScreen" value="true"></param><param name="allowscriptaccess" value="always"></param><embed src="http://www.youtube.com/v/'.$video_link.'&hl=ru&fs=1" type="application/x-shockwave-flash" allowscriptaccess="always" allowfullscreen="true" wmode="transparent" width="'.$this->video_config['width'].'" height="'.$this->video_config['height'].'"></embed></object><!--dle_youtube_end-->';
		else
			return '<!--dle_youtube_begin:'.$url.'--><OBJECT width="'.$this->video_config['width'].'" height="'.$this->video_config['height'].'"><PARAM name="movie" value="http://video.rutube.ru/'.$video_link.'"></PARAM><param name="wmode" value="transparent" /></PARAM><PARAM name="allowFullScreen" value="true"></PARAM><EMBED src="http://video.rutube.ru/'.$video_link.'" type="application/x-shockwave-flash" wmode="transparent" width="'.$this->video_config['width'].'" height="'.$this->video_config['height'].'" allowFullScreen="true" ></EMBED></OBJECT><!--dle_youtube_end-->';

	}

	function build_url($url = array()) {
		global $config;
		
		$skip_it = 0;
		
		if( preg_match( "/([\.,\?]|&#33;)$/", $url['show'], $match ) ) {
			$url['end'] .= $match[1];
			$url['show'] = preg_replace( "/([\.,\?]|&#33;)$/", "", $url['show'] );
		}
		
		$url['html'] = $this->clear_url( $url['html'] );
		$url['show'] = stripslashes( $url['show'] );

		if( $this->safe_mode ) {

			$url['show'] = str_replace( "&nbsp;", " ", $url['show'] );
	
			if (strlen(trim($url['show'])) < 3 )
				return "[url=" . $url['html'] . "]" . $url['show'] . "[/url]";

		}
		
		if( strpos( $url['html'], $config['http_home_url'] ) !== false AND strpos( $url['html'], $config['admin_path'] ) !== false ) {
			
			return "[url=" . $url['html'] . "]" . $url['show'] . "[/url]";
		
		}
		
		if( ! preg_match( "#^(http|news|https|ed2k|ftp|aim|mms)://|(magnet:?)#", $url['html'] ) ) {
			$url['html'] = 'http://' . $url['html'];
		}

		if ($url['html'] == 'http://' )
			return "[url=" . $url['html'] . "]" . $url['show'] . "[/url]";
		
		if( preg_match( "/^<img src/i", $url['show'] ) ) {
			$skip_it = 1;
		}
		
		$url['show'] = str_replace( "&amp;amp;", "&amp;", $url['show'] );
		$url['show'] = preg_replace( "/javascript:/i", "javascript&#58; ", $url['show'] );
		
		if( (strlen( $url['show'] ) - 58) < 3 ) $skip_it = 1;
		
		if( ! preg_match( "/^(http|ed2k|ftp|https|news|aim|mms):\/\//i", $url['show'] ) ) $skip_it = 1;
		
		$show = $url['show'];
		
		if( $skip_it != 1 ) {
			$stripped = preg_replace( "#^(http|ed2k|ftp|https|news|aim|mms)://(\S+)$#i", "\\2", $url['show'] );
			$uri_type = preg_replace( "#^(http|ed2k|ftp|https|news|aim|mms)://(\S+)$#i", "\\1", $url['show'] );
			
			$show = $uri_type . '://' . substr( $stripped, 0, 35 ) . '...' . substr( $stripped, - 15 );
		}
		
		if( $this->check_home( $url['html'] ) ) $target = "";
		else $target = "target=\"_blank\"";
		
		if( $url['leech'] ) {
			
			$url['html'] = $config['http_home_url'] . "engine/go.php?url=" . rawurlencode( base64_encode( $url['html'] ) );
			
			return "<!--dle_leech_begin--><a href=\"" . $url['html'] . "\" " . $target . ">" . $show . "</a><!--dle_leech_end-->" . $url['end'];
		
		} else {

			if ($this->safe_mode AND !$config['allow_search_link'])
				return "<noindex><a href=\"" . $url['html'] . "\" " . $target . " rel=\"nofollow\">" . $show . "</a></noindex>" . $url['end'];
			else		
				return "<a href=\"" . $url['html'] . "\" " . $target . ">" . $show . "</a>" . $url['end'];
		
		}
	
	}
	
	function code_tag($txt = "") {
		if( $txt == "" ) {
			return;
		}
		
		$this->code_count ++;
		
		$txt = str_replace( "&", "&amp;", $txt );
		$txt = str_replace( "&lt;", "&#60;", $txt );
		$txt = str_replace( "'", "&#39;", $txt );
		$txt = str_replace( "&gt;", "&#62;", $txt );
		$txt = str_replace( "<", "&#60;", $txt );
		$txt = str_replace( ">", "&#62;", $txt );
		$txt = str_replace( "&quot;", "&#34;", $txt );
		$txt = str_replace( "\\\"", "&#34;", $txt );
		$txt = str_replace( ":", "&#58;", $txt );
		$txt = str_replace( "[", "&#91;", $txt );
		$txt = str_replace( "]", "&#93;", $txt );
		$txt = str_replace( ")", "&#41;", $txt );
		$txt = str_replace( "(", "&#40;", $txt );
		$txt = str_replace( "\r", "", $txt );
		$txt = str_replace( "\n", "<br />", $txt );
		
		$txt = preg_replace( "#\s{1};#", "&#59;", $txt );
		$txt = preg_replace( "#\t#", "&nbsp;&nbsp;&nbsp;&nbsp;", $txt );
		$txt = preg_replace( "#\s{2}#", "&nbsp;&nbsp;", $txt );
		
		$p = "[code]{" . $this->code_count . "}[/code]";
		
		$this->code_text[$p] = "[code]{$txt}[/code]";
		
		return $p;
	}

	function decode_code($txt = "", $use_html) { 

//		$txt = stripslashes( $txt );
		$txt = str_replace( "&amp;", "&", $txt );

		if( $use_html ) {
			$txt = str_replace( "&lt;br /&gt;", "\n", $txt );
		}

		return "[code]".$txt."[/code]";
	}
	
	function build_video($url) {
		global $config;

		if (!count($this->video_config)) {

			include (ENGINE_DIR . '/data/videoconfig.php');
			$this->video_config = $video_config;

		}
	
		$option = explode( "|", trim( $url ) );
		
		$url = $this->clear_url( urldecode( $option[0] ) );
		
		$type = explode( ".", $url );
		$type = strtolower( end( $type ) );
		
		if( preg_match( "/[?&;%<\[\]]/", $url ) ) {
			
			return "[video=" . $url . "]";
		
		}
		
		if( $option[1] != "" ) {
			
			$option[1] = htmlspecialchars( strip_tags( stripslashes( $option[1] ) ), ENT_QUOTES );
			$decode_url = $url . "|" . $option[1];
		
		} else
			$decode_url = $url;
		
		if( $type == "flv" or $type == "mp4" or $type == "m4v" or $type == "m4a" or $type == "mov" or $type == "3gp" or $type == "f4v") {
			
			if( $config['flv_watermark'] ) $watermark = "&logo={THEME}/dleimages/flv_watermark.png";
			else $watermark = "";

			if( $option[1] != "" ) {

				$option[1] = "&image=".urlencode($option[1]);

			}

			$id_player = md5( microtime() );
			
			$list = explode( ",", $url );
			$url = urlencode(trim($list[0]));

			$color = array ();

			if ($this->video_config['backgroundBarColor']) $color['backgroundBarColor'] = "&backgroundBarColor=".$this->video_config['backgroundBarColor'];
			if ($this->video_config['btnsColor']) $color['btnsColor'] = "&btnsColor=".$this->video_config['btnsColor'];
			if ($this->video_config['outputTxtColor']) $color['outputTxtColor'] = "&outputTxtColor=".$this->video_config['outputTxtColor'];
			if ($this->video_config['outputBkgColor']) $color['outputBkgColor'] = "&outputBkgColor=".$this->video_config['outputBkgColor'];
			if ($this->video_config['loadingBarColor']) $color['loadingBarColor'] = "&loadingBarColor=".$this->video_config['loadingBarColor'];
			if ($this->video_config['loadingBackgroundColor']) $color['loadingBackgroundColor'] = "&loadingBackgroundColor=".$this->video_config['loadingBackgroundColor'];
			if ($this->video_config['progressBarColor']) $color['progressBarColor'] = "&progressBarColor=".$this->video_config['progressBarColor'];
			if ($this->video_config['volumeStatusBarColor']) $color['volumeStatusBarColor'] = "&volumeStatusBarColor=".$this->video_config['volumeStatusBarColor'];
			if ($this->video_config['volumeBackgroundColor']) $color['volumeBackgroundColor'] = "&volumeBackgroundColor=".$this->video_config['volumeBackgroundColor'];
			
			return "<!--dle_video_begin:{$decode_url}--><object classid=\"clsid:D27CDB6E-AE6D-11cf-96B8-444553540000\" codebase=\"http://download.macromedia.com/pub/shockwave/cabs/flash/swflash.cab#version=9,0,0,0\" width=\"{$this->video_config['width']}\" height=\"{$this->video_config['height']}\" id=\"Player-{$id_player}\">
				<param name=\"movie\" value=\"" . $config['http_home_url'] . "engine/classes/flashplayer/media_player.swf?MediaLink={$url}&defaultMedia=1{$option[1]}{$watermark}&showPlayButton=true&playOnStart={$this->video_config['play']}{$color['backgroundBarColor']}{$color['btnsColor']}&outlineColor=0x666666{$color['outputBkgColor']}{$color['outputTxtColor']}{$color['loadingBarColor']}{$color['loadingBackgroundColor']}{$color['progressBarColor']}{$color['volumeBackgroundColor']}{$color['volumeStatusBarColor']}\" />
				<param name=\"allowFullScreen\" value=\"true\" />
				<param name=\"quality\" value=\"high\" />
				<param name=\"bgcolor\" value=\"#000000\" />
				<param name=\"wmode\" value=\"opaque\" />
				<embed src=\"" . $config['http_home_url'] . "engine/classes/flashplayer/media_player.swf?MediaLink={$url}&defaultMedia=1{$option[1]}{$watermark}&showPlayButton=true&playOnStart={$this->video_config['play']}{$color['backgroundBarColor']}{$color['btnsColor']}&outlineColor=0x666666{$color['outputBkgColor']}{$color['outputTxtColor']}{$color['loadingBarColor']}{$color['loadingBackgroundColor']}{$color['progressBarColor']}{$color['volumeBackgroundColor']}{$color['volumeStatusBarColor']}\" quality=\"high\" bgcolor=\"#000000\" wmode=\"opaque\" allowFullScreen=\"true\" width=\"{$this->video_config['width']}\" height=\"{$this->video_config['height']}\" align=\"middle\" type=\"application/x-shockwave-flash\" pluginspage=\"http://www.macromedia.com/go/getflashplayer\"></embed>
				</object><!--dle_video_end-->";

			
		} elseif( $type == "avi" or $type == "divx" ) {
			
			return "<!--dle_video_begin:{$decode_url}--><object classid=\"clsid:67DABFBF-D0AB-41fa-9C46-CC0F21721616\" width=\"{$this->video_config['width']}\" height=\"{$this->video_config['height']}\" codebase=\"http://go.divx.com/plugin/DivXBrowserPlugin.cab\">
				<param name=\"custommode\" value=\"none\" />
				<param name=\"mode\" value=\"zero\" />
				<param name=\"autoPlay\" value=\"{$this->video_config['play']}\" />
				<param name=\"src\" value=\"{$url}\" />
				<param name=\"previewImage\" value=\"{$option[1]}\" />
				<embed type=\"video/divx\" src=\"{$url}\" custommode=\"none\" width=\"{$this->video_config['width']}\" height=\"{$this->video_config['height']}\" mode=\"zero\"  autoPlay=\"{$this->video_config['play']}\" previewImage=\"{$option[1]}\" pluginspage=\"http://go.divx.com/plugin/download/\">
				</embed>
				</object><!--dle_video_end-->";
		
		} else {
			
			return "<!--dle_video_begin:{$url}--><object id=\"mediaPlayer\" width=\"{$this->video_config['width']}\" height=\"{$this->video_config['height']}\" classid=\"CLSID:6BF52A52-394A-11d3-B153-00C04F79FAA6\" standby=\"Loading Microsoft Windows Media Player components...\" type=\"application/x-oleobject\">
				<param name=\"url\" VALUE=\"{$url}\" />
				<param name=\"autoStart\" VALUE=\"{$this->video_config['play']}\" />
				<param name=\"showControls\" VALUE=\"true\" />
				<param name=\"TransparentatStart\" VALUE=\"false\" />
				<param name=\"AnimationatStart\" VALUE=\"true\" />
				<param name=\"StretchToFit\" VALUE=\"true\" />
				<embed pluginspage=\"http://www.microsoft.com/Windows/Downloads/Contents/MediaPlayer/\" src=\"{$url}\" width=\"{$this->video_config['width']}\" height=\"{$this->video_config['height']}\" type=\"application/x-mplayer2\" autorewind=\"1\" showstatusbar=\"1\" showcontrols=\"1\" autostart=\"{$this->video_config['play']}\" allowchangedisplaysize=\"1\" volume=\"70\" stretchtofit=\"1\"></embed>
				</object><!--dle_video_end-->";
		}
	
	}
	function build_audio($url) {
		global $config;
		
		if( $url == "" ) return;
		
		if( preg_match( "/[?&;%<\[\]]/", $url ) ) {
			
			return "[audio=" . $url . "]";
		}

		if (!count($this->video_config)) {

			include (ENGINE_DIR . '/data/videoconfig.php');
			$this->video_config = $video_config;

		}

		$url = $this->clear_url( urldecode( $url ) );
		
		$list = explode( ",", $url );
		$url = urlencode(trim($list[0]));

		$list = implode( ",", $list );
		$id_player = md5( microtime() );
		$color = array ();

		if ($this->video_config['backgroundBarColor']) $color['backgroundBarColor'] = "&backgroundBarColor=".$this->video_config['backgroundBarColor'];
		if ($this->video_config['btnsColor']) $color['btnsColor'] = "&btnsColor=".$this->video_config['btnsColor'];
		if ($this->video_config['outputTxtColor']) $color['outputTxtColor'] = "&outputTxtColor=".$this->video_config['outputTxtColor'];
		if ($this->video_config['outputBkgColor']) $color['outputBkgColor'] = "&outputBkgColor=".$this->video_config['outputBkgColor'];
		if ($this->video_config['loadingBarColor']) $color['loadingBarColor'] = "&loadingBarColor=".$this->video_config['loadingBarColor'];
		if ($this->video_config['loadingBackgroundColor']) $color['loadingBackgroundColor'] = "&loadingBackgroundColor=".$this->video_config['loadingBackgroundColor'];
		if ($this->video_config['progressBarColor']) $color['progressBarColor'] = "&progressBarColor=".$this->video_config['progressBarColor'];
		if ($this->video_config['volumeStatusBarColor']) $color['volumeStatusBarColor'] = "&volumeStatusBarColor=".$this->video_config['volumeStatusBarColor'];
		if ($this->video_config['volumeBackgroundColor']) $color['volumeBackgroundColor'] = "&volumeBackgroundColor=".$this->video_config['volumeBackgroundColor'];

		
		return "<!--dle_audio_begin:{$list}--><object classid=\"clsid:D27CDB6E-AE6D-11cf-96B8-444553540000\" codebase=\"http://download.macromedia.com/pub/shockwave/cabs/flash/swflash.cab#version=9,0,0,0\" width=\"{$this->video_config['width']}\" height=\"30\" id=\"Player-{$id_player}\">
				<param name=\"movie\" value=\"" . $config['http_home_url'] . "engine/classes/flashplayer/media_player.swf?MediaLink={$url}&defaultMedia=1&showPlayButton=false&playOnStart={$this->video_config['play']}{$color['backgroundBarColor']}{$color['btnsColor']}&outlineColor=0x666666{$color['outputBkgColor']}{$color['outputTxtColor']}{$color['loadingBarColor']}{$color['loadingBackgroundColor']}{$color['progressBarColor']}{$color['volumeBackgroundColor']}{$color['volumeStatusBarColor']}\" />
				<param name=\"allowFullScreen\" value=\"false\" />
				<param name=\"quality\" value=\"high\" />
				<param name=\"wmode\" value=\"transparent\" />
				<embed src=\"" . $config['http_home_url'] . "engine/classes/flashplayer/media_player.swf?MediaLink={$url}&defaultMedia=1&showPlayButton=false&playOnStart={$this->video_config['play']}{$color['backgroundBarColor']}{$color['btnsColor']}&outlineColor=0x666666{$color['outputBkgColor']}{$color['outputTxtColor']}{$color['loadingBarColor']}{$color['loadingBackgroundColor']}{$color['progressBarColor']}{$color['volumeBackgroundColor']}{$color['volumeStatusBarColor']}\" quality=\"high\" wmode=\"transparent\" allowFullScreen=\"false\" width=\"{$this->video_config['width']}\" height=\"30\" align=\"middle\" type=\"application/x-shockwave-flash\" pluginspage=\"http://www.macromedia.com/go/getflashplayer\"></embed>
				</object><!--dle_audio_end-->";
	
	}
	
	function build_image($url = "", $align = "") {
		global $config;
		
		$url = trim( $url );
		$url = urldecode( $url );
		$option = explode( "|", trim( $align ) );
		$align = $option[0];
		
		if( $align != "left" and $align != "right" ) $align = '';
		
		if( preg_match( "/[?&;%<\[\]]/", $url ) ) {
			
			if( $align != "" ) return "[img=" . $align . "]" . $url . "[/img]";
			else return "[img]" . $url . "[/img]";
		
		}
		
		$url = $this->clear_url( urldecode( $url ) );

		$info = $url;

		$info = $info."|".$align;
		
		if( $url == "" ) return;
		
		if( $option[1] != "" ) {
			
			$alt = htmlspecialchars( strip_tags( stripslashes( $option[1] ) ), ENT_QUOTES );
			$info = $info."|".$alt;
			$caption = "<span class=\"highslide-caption\">" . $alt . "</span>";
			$alt = "alt=\"" . $alt . "\" title=\"" . $alt . "\" ";
		
		} else {
			
			$alt = htmlspecialchars( strip_tags( stripslashes( $_POST['title'] ) ), ENT_QUOTES );
			$caption = "";
			$alt = "alt=\"" . $alt . "\" title=\"" . $alt . "\" ";
		
		}
		
		if( intval( $config['tag_img_width'] ) ) {

			if (clean_url( $config['http_home_url'] ) != clean_url ( $url ) ) {
			
				$img_info = @getimagesize( $url );
				
				if( $img_info[0] > $config['tag_img_width'] ) {
					
					$out_heigh = ($img_info[1] / 100) * ($config['tag_img_width'] / ($img_info[0] / 100));
					$out_heigh = floor( $out_heigh );

					if( $align == '' ) return "<!--dle_image_begin:{$info}--><a href=\"{$url}\" onclick=\"return hs.expand(this)\" ><img src=\"$url\" width=\"{$config['tag_img_width']}\" height=\"{$out_heigh}\" {$alt} /></a>{$caption}<!--dle_image_end-->";
					else return "<!--dle_image_begin:{$info}--><a href=\"{$url}\" onclick=\"return hs.expand(this)\" ><img align=\"$align\" src=\"$url\" width=\"{$config['tag_img_width']}\" height=\"{$out_heigh}\" {$alt} /></a>{$caption}<!--dle_image_end-->";

				
				}
			}		
		}
		

		if( $align == '' ) return "<!--dle_image_begin:{$info}--><img src=\"{$url}\" {$alt} /><!--dle_image_end-->";
		else return "<!--dle_image_begin:{$info}--><img src=\"{$url}\" align=\"{$align}\" {$alt} /><!--dle_image_end-->";

	}

	function decode_dle_img($txt) {

		$txt = stripslashes( $txt );
		$txt = explode("|", $txt );
		$url = $txt[0];
		$align = $txt[1];
		$alt = $txt[2];
		$extra = "";

		if( ! $align and ! $alt ) return "[img]" . $url . "[/img]";
		
		if( $align ) $extra = $align;
		if( $alt ) {

			$alt = str_replace("&#039;", "'", $alt);
			$alt = str_replace("&quot;", '"', $alt);
			$alt = str_replace("&amp;", '&', $alt);
			$extra .= "|" . $alt;

		}
		
		return "[img=" . $extra . "]" . $url . "[/img]";

	}
	
	function build_thumb($gurl = "", $url = "", $align = "") {
		$url = trim( $url );
		$gurl = trim( $gurl );
		$option = explode( "|", trim( $align ) );
		
		$align = $option[0];
		
		if( $align != "left" and $align != "right" ) $align = '';
		
		if( preg_match( "/[?&;%<\[\]]/", $gurl ) ) {
			
			if( $align != "" ) return "[thumb=" . $align . "]" . $gurl . "[/thumb]";
			else return "[thumb]" . $gurl . "[/thumb]";
		
		}
		
		$url = $this->clear_url( urldecode( $url ) );
		$gurl = $this->clear_url( urldecode( $gurl ) );
		
		if( $gurl == "" or $url == "" ) return;
		
		if( $option[1] != "" ) {
			
			$alt = htmlspecialchars( strip_tags( stripslashes( $option[1] ) ), ENT_QUOTES );
			$caption = "<span class=\"highslide-caption\">" . $alt . "</span>";
			$alt = "alt=\"" . $alt . "\" title=\"" . $alt . "\" ";
		
		} else {
			
			$alt = htmlspecialchars( strip_tags( stripslashes( $_POST['title'] ) ), ENT_QUOTES );
			$alt = "alt='" . $alt . "' title='" . $alt . "' ";
			$caption = "";
		
		}
		
		if( $align == '' ) return "<!--TBegin--><a href=\"$gurl\" onclick=\"return hs.expand(this)\" ><img src=\"$url\" {$alt} /></a>{$caption}<!--TEnd-->";
		else return "<!--TBegin--><a href=\"$gurl\" onclick=\"return hs.expand(this)\" ><img align=\"$align\" src=\"$url\" {$alt} /></a>{$caption}<!--TEnd-->";
	
	}
	
	function build_spoiler($title = "") {
		global $lang;
		
		$title = trim( $title );
		
		$title = stripslashes( $title );
		$title = str_replace( "&amp;amp;", "&amp;", $title );
		$title = preg_replace( "/javascript:/i", "javascript&#58; ", $title );
		
		$id_spoiler = md5( microtime() );
		
		if( ! $title ) {
			
			return "<!--dle_spoiler--><div class=\"title_spoiler\"><img id=\"image-" . $id_spoiler . "\" style=\"vertical-align: middle;border: none;\" alt=\"\" src=\"{THEME}/dleimages/spoiler-plus.gif\" />&nbsp;<a href=\"javascript:ShowOrHide('" . $id_spoiler . "')\"><!--spoiler_title-->" . $lang['spoiler_title'] . "<!--spoiler_title_end--></a></div><div id=\"" . $id_spoiler . "\" class=\"text_spoiler\" style=\"display:none;\"><!--spoiler_text-->";
		
		} else {
			
			return "<!--dle_spoiler $title --><div class=\"title_spoiler\"><img id=\"image-" . $id_spoiler . "\" style=\"vertical-align: middle;border: none;\" alt=\"\" src=\"{THEME}/dleimages/spoiler-plus.gif\" />&nbsp;<a href=\"javascript:ShowOrHide('" . $id_spoiler . "')\"><!--spoiler_title-->" . $title . "<!--spoiler_title_end--></a></div><div id=\"" . $id_spoiler . "\" class=\"text_spoiler\" style=\"display:none;\"><!--spoiler_text-->";
		
		}
	
	}
	
	function clear_url($url) {
		
		$url = strip_tags( trim( stripslashes( $url ) ) );
		
		$url = str_replace( '\"', '"', $url );
		
		if( ! $this->safe_mode or $this->wysiwyg ) {
			
			$url = htmlspecialchars( $url, ENT_QUOTES );
		
		}
		
		$url = str_replace( "document.cookie", "", $url );
		$url = str_replace( " ", "%20", $url );
		$url = str_replace( "'", "", $url );
		$url = str_replace( '"', "", $url );
		$url = str_replace( "<", "&#60;", $url );
		$url = str_replace( ">", "&#62;", $url );
		$url = preg_replace( "/javascript:/i", "", $url );
		$url = preg_replace( "/data:/i", "", $url );
		
		return $url;
	
	}
	
	function decode_leech($url = "", $show = "") {
		
		$show = stripslashes( $show );

		if( $this->leech_mode ) return "[url=" . $url . "]" . $show . "[/url]";
		
		$url = explode( "url=", $url );
		$url = end( $url );
		$url = rawurldecode( $url );
		$url = base64_decode( $url );
		$url = str_replace("&amp;","&", $url );
		
		return "[leech=" . $url . "]" . $show . "[/leech]";
	}

	function decode_url($url = "", $show = "") {
		
		$show = stripslashes( $show );

		$url = str_replace("&amp;","&", $url );
		
		return "[url=" . $url . "]" . $show . "[/url]";
	}
	
	function decode_thumb($txt) {
		$align = false;
		$alt = false;
		$extra = "";
		$txt = stripslashes( $txt );
		
		$url = str_replace( "<a href=\"#\" onClick=\"ShowBild('", "", $txt );
		$url = explode( "');", $url );
		$url = reset( $url );
		
		if( strpos( $txt, "align=\"" ) !== false ) {
			
			$align = preg_replace( "#(.+?)align=\"(.+?)\"(.*)#is", "\\2", $txt );
		}
		
		if( strpos( $txt, "alt=\"" ) !== false ) {
			
			$alt = preg_replace( "#(.+?)alt=\"(.+?)\"(.*)#is", "\\2", $txt );
		}
		
		if( $align != "left" and $align != "right" ) $align = false;
		
		if( ! $align and ! $alt ) return "[thumb]" . $url . "[/thumb]";
		
		if( $align ) $extra = $align;
		if( $alt ) $extra .= "|" . $alt;
		
		return "[thumb=" . $extra . "]" . $url . "[/thumb]";
	
	}
	
	function decode_newthumb($txt) {
		$align = false;
		$alt = false;
		$extra = "";
		$txt = stripslashes( $txt );
		
		$url = str_replace( "<a href=\"", "", $txt );
		$url = explode( "\"", $url );
		$url = reset( $url );
		
		if( strpos( $txt, "align=\"" ) !== false ) {
			
			$align = preg_replace( "#(.+?)align=\"(.+?)\"(.*)#is", "\\2", $txt );
		}
		
		if( strpos( $txt, "alt=\"" ) !== false ) {
			
			$alt = preg_replace( "#(.+?)alt=\"(.+?)\"(.*)#is", "\\2", $txt );
		}
		
		if( $align != "left" and $align != "right" ) $align = false;
		
		if( ! $align and ! $alt ) return "[thumb]" . $url . "[/thumb]";
		
		if( $align ) $extra = $align;
		if( $alt ) { 
			$alt = str_replace("&#039;", "'", $alt);
			$alt = str_replace("&quot;", '"', $alt);
			$alt = str_replace("&amp;", '&', $alt);
			$extra .= "|" . $alt;

		}
		
		return "[thumb=" . $extra . "]" . $url . "[/thumb]";
	
	}
	
	function decode_img($img, $txt) {
		$txt = stripslashes( $txt );
		$align = false;
		$alt = false;
		$extra = "";
		
		if( strpos( $txt, "align=\"" ) !== false ) {
			
			$align = preg_replace( "#(.+?)align=\"(.+?)\"(.*)#is", "\\2", $txt );
		}
		
		if( strpos( $txt, "alt=\"\"" ) !== false ) {
			
			$alt = false;

		} elseif( strpos( $txt, "alt=\"" ) !== false ) {
			
			$alt = preg_replace( "#(.+?)alt=\"(.+?)\"(.*)#is", "\\2", $txt );
		}
		
		if( $align != "left" and $align != "right" ) $align = false;
		
		if( ! $align and ! $alt ) return "[img]" . $img . "[/img]";
		
		if( $align ) $extra = $align;
		if( $alt ) $extra .= "|" . $alt;
		
		return "[img=" . $extra . "]" . $img . "[/img]";
	
	}
	
	function check_home($url) {
		global $config;
		
		$value = str_replace( "http://", "", $config['http_home_url'] );
		$value = str_replace( "www.", "", $value );
		$value = explode( '/', $value );
		$value = reset( $value );
		if( $value == "" ) return false;
		
		if( strpos( $url, $value ) === false ) return false;
		else return true;
	}
	
	function word_filter($source, $encode = true) {
		
		if( $encode ) {
			
			$all_words = @file( ENGINE_DIR . '/data/wordfilter.db.php' );
			$find = array ();
			$replace = array ();
			
			if( ! $all_words or ! count( $all_words ) ) return $source;
			
			foreach ( $all_words as $word_line ) {
				$word_arr = explode( "|", $word_line );
				
				if( get_magic_quotes_gpc() ) {
					
					$word_arr[1] = addslashes( $word_arr[1] );
				
				}

				if( $word_arr[4] ) {

					$register ="";

				} else $register ="i";

				$allow_find = true;

				if ( $word_arr[5] == 1 AND $this->safe_mode ) $allow_find = false;
				if ( $word_arr[5] == 2 AND !$this->safe_mode ) $allow_find = false;
				
				if ( $allow_find ) {

					if( $word_arr[3] ) {
						
						$find_text = "#(^|\b|\s|\<br \/\>)" . preg_quote( $word_arr[1], "#" ) . "(\b|!|\?|\.|,|$)#".$register;
						
						if( $word_arr[2] == "" ) $replace_text = "\\1";
						else $replace_text = "\\1<!--filter:" . $word_arr[1] . "-->" . $word_arr[2] . "<!--/filter-->";
					
					} else {
						
						$find_text = "#(" . preg_quote( $word_arr[1], "#" ) . ")#".$register;
						
						if( $word_arr[2] == "" ) $replace_text = "";
						else $replace_text = "<!--filter:" . $word_arr[1] . "-->" . $word_arr[2] . "<!--/filter-->";
					
					}

					if ( $word_arr[6] ) {

						if ( preg_match($find_text, $source) ) {

							$this->not_allowed_text = true;
							return $source;

						}

					} else {

						$find[] = $find_text;
						$replace[] = $replace_text;
					}

				}

			}

			if( !count( $find ) ) return $source;
			
			$source = preg_split( '((>)|(<))', $source, - 1, PREG_SPLIT_DELIM_CAPTURE );
			$count = count( $source );
			
			for($i = 0; $i < $count; $i ++) {
				if( $source[$i] == "<" or $source[$i] == "[" ) {
					$i ++;
					continue;
				}
				
				if( $source[$i] != "" ) $source[$i] = preg_replace( $find, $replace, $source[$i] );
			}
			
			$source = join( "", $source );
		
		} else {
			
			$source = preg_replace( "#<!--filter:(.+?)-->(.+?)<!--/filter-->#", "\\1", $source );
		
		}
		
		return $source;
	}

}
?>