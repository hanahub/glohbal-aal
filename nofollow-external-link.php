<?php
/*

 Plugin Name: Glohbal Automatic Affiliate Links Plugin for Amazon & Rakuten (LinkShare) platforms
 Plugin URI: https://davidherron.com/content/external-links-nofollow-favicon-open-external-window-etc-wordpress
 Description: Process outbound (external) links in content, optionally adding affiliate link attributes, rel=nofollow or target=_blank attributes, and optionally adding icons.
 Version: 1.0.0
 Author: Valentin Marinov
 Author URI: https://github.com/shopthefeed
 slug: external-links-nofollow
 License:     GPL2
 License URI: https://www.gnu.org/licenses/gpl-2.0.html

   This program is free software; you can redistribute it and/or modify
   it under the terms of the GNU General Public License, version 2, as
   published by the Free Software Foundation.

   This program is distributed in the hope that it will be useful,
   but WITHOUT ANY WARRANTY; without even the implied warranty of
   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
   GNU General Public License for more details.

   You should have received a copy of the GNU General Public License
   along with this program; if not, write to the Free Software
   Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

*/


define("GBLDIR", plugin_dir_path(__FILE__));
define("GBLURL", plugin_dir_url(__FILE__));
// define("GBLSLUG",dirname(plugin_basename(__FILE__)));

require GBLDIR.'AffiliateLinkProcessor/Processor.php';

if (is_admin()) {
	require_once GBLDIR.'admin.php';
}

function gbl_add_stylesheet() {
    wp_register_style('GBL-style', GBLURL.'style.css');
    wp_enqueue_style('GBL-style');
}
add_action('wp_enqueue_scripts', 'gbl_add_stylesheet');

// Add this filter with low priority so it runs after
// other filters, specifically shortcodes which might
// expand to include links.
add_filter('the_content', 'gbl_urlparse2', 99);

function gbl_urlparse2($content) {

	$affprocessor = gbl_init_affprocessor();

	$ownDomain = $_SERVER['HTTP_HOST'];

	// whitelist
	$white_list_domains_list = array();
	if (get_option('gbl_whitelist_domains')!='') {
		$white_list_domains_list = explode(",",get_option('gbl_whitelist_domains'));
	}

	// blacklist
	$black_list_domains_list = array();
	if (get_option('gbl_blacklist_domains')!='') {
		$black_list_domains_list = explode(",",get_option('gbl_blacklist_domains'));
	}

	$gbl_icons_before_after = get_option('gbl_icons_before_after');
	$gbl_target_blank = get_option('gbl_target_blank');
	$gbl_show_extlink = get_option('gbl_show_extlink');
	$gbl_show_favicon = get_option('gbl_show_favicon');

	try {
		$html = new DOMDocument(null, 'UTF-8');
		@$html->loadHTML('<meta http-equiv="content-type" content="text/html; charset=utf-8">' . $content);

		foreach ($html->getElementsByTagName('a') as $a) {

			// Skip if there's no href=
			$url = $a->attributes->getNamedItem('href');
			if (!$url) {
				continue;
			}

			// Skip if the link is internal, or if it has "#whatever"
			$urlParts = parse_url($url->textContent);
			if (!$urlParts || empty($urlParts['host']) || !empty($urlParts['fragment'])) {
				continue;
			}

			// Skip if the link points to our own domain (hence, is local)
			if (gbl_domainEndsWith($urlParts['host'], $ownDomain)) {
				continue;
			}

			$hasImages = false;
			$imgs = $a->getElementsByTagName('img');
			if ($imgs->length > 0) {
				$hasImages = true;
			}

			// true means add nofollow, false means not (is in whitelist)
			$domainNoFollow = true;

			if (count($white_list_domains_list) > 0) {
				$white_list_domains_list = array_filter($white_list_domains_list);
				foreach ($white_list_domains_list as $domain) {
					$domain = trim($domain);
					if ($domain != '') {
						$domainCheck = gbl_domainEndsWith($urlParts['host'], $domain);
						if ($domainCheck === false) {
							continue;
						} else {
							$domainNoFollow = false;
							break;
						}
					}
				}
			}

			// false means not in BlackList, true means in BlackList & add nofollow
			$domainInBlackList = false;
			$noBlackList = false;
			if (count($black_list_domains_list) > 0) {
				$black_list_domains_list = array_filter($black_list_domains_list);
				foreach ($black_list_domains_list as $domain) {
					$domain = trim($domain);
					if ($domain != '') {
						$domainCheck = gbl_domainEndsWith($urlParts['host'], $domain);
						if($domainCheck === false) {
							continue;
						} else {
							$domainInBlackList = true;
							$domainNoFollow = true;
							break;
						}
					}
				}
				if (!$domainInBlackList) $domainNoFollow = false;
			} else {
				$noBlackList = true;
			}

			// Add rel=nofollow
			if ($domainNoFollow || $domainInBlackList) {
				$a->setAttribute('rel', 'nofollow');
			}

			// $a->setAttribute('data-domain-no-follow', $domainNoFollow ? "true" : "false");
			// $a->setAttribute('data-domain-in-black-list', $domainInBlackList ? "true" : "false");

			// Add target=_blank if there's no target=
			$curtarget = $a->getAttribute('target');
			if (!empty($gbl_target_blank)
			 && $gbl_target_blank === "_blank"
			 && (!isset($curtarget) || $curtarget == '')
			) {
				$a->setAttribute('target', '_blank');
			}

			// Add the favicon
			if (!$hasImages
			 && !empty($gbl_show_favicon)
			 && $gbl_show_favicon === "show"
			 && !$a->attributes->getNamedItem('data-no-favicon')
			) {
				$img = $html->createElement('img');
				$img->setAttribute('class', 'extlink-icon');
				$img->setAttribute('src', '//www.google.com/s2/favicons?domain=' . $urlParts['host']);
				$img->setAttribute('style', 'display: inline-block; padding-right: 4px;');
				if (empty($gbl_icons_before_after)
				 || (!empty($gbl_icons_before_after) && $gbl_icons_before_after === "before")
				) {
					$a->insertBefore($img, $a->firstChild);
				} else {
					$a->appendChild($img);
				}
			}

			// Add external link icon
			if (!empty($gbl_show_extlink)
			 && $gbl_show_extlink === "show"
			) {
				$img = $html->createElement('img');
				$img->setAttribute('class', 'extlink-icon');
				$img->setAttribute('src', esc_url(plugins_url('images/extlink.png', __FILE__)));
				if (empty($gbl_icons_before_after)
				 || (!empty($gbl_icons_before_after) && $gbl_icons_before_after === "before")
				) {
					$a->insertBefore($img, $a->firstChild);
				} else {
					$a->appendChild($img);
				}
			}

			// Process for affiliate links
			$newurl = $affprocessor->process($url->textContent);
			if ($newurl !== $url->textContent) {
				$a->setAttribute('href', $newurl);
				// nofollow is required by Google et al on paid links
				// noskim tells skimlinks.com to not rewrite
				// norewrite tells viglink.com to not rewrite
				$a->setAttribute('rel', 'nofollow noskim norewrite');
			}
		}

        // loadHTML adds spurious DOCTYPE, html and body tags. That causes problems
        // when it arrives as aprt of the resulting HTML.
        // There is discussion here:  http://php.net/manual/en/domdocument.savehtml.php
        //
        // The cleanest way to remove them is first serialize the BODY tag to HTML.
        // That leaves a BODY tag wrapping the HTML snippet, which is
        // removed by the str_replace call.
        return str_replace(array('<body>', '</body>'), '', $html->saveHTML($html->getElementsByTagName('body')->item(0)));

	} catch (Exception $e) {
		return $content;
	}
}

function gbl_init_affprocessor() {

	// Duplicate changes to the Amazon sites down to gbl_amazon_buy
	$gbl_affproduct_amazon_com_au = get_option('gbl_affproduct_amazon_com_au');
	$gbl_affproduct_amazon_br     = get_option('gbl_affproduct_amazon_br');
	$gbl_affproduct_amazon_ca     = get_option('gbl_affproduct_amazon_ca');
	$gbl_affproduct_amazon_cn     = get_option('gbl_affproduct_amazon_cn');
	$gbl_affproduct_amazon_com    = get_option('gbl_affproduct_amazon_com');
	$gbl_affproduct_amazon_co_jp  = get_option('gbl_affproduct_amazon_co_jp');
	$gbl_affproduct_amazon_co_uk  = get_option('gbl_affproduct_amazon_co_uk');
	$gbl_affproduct_amazon_de     = get_option('gbl_affproduct_amazon_de');
	$gbl_affproduct_amazon_es     = get_option('gbl_affproduct_amazon_es');
	$gbl_affproduct_amazon_fr     = get_option('gbl_affproduct_amazon_fr');
	$gbl_affproduct_amazon_in     = get_option('gbl_affproduct_amazon_in');
	$gbl_affproduct_amazon_it     = get_option('gbl_affproduct_amazon_it');
	$gbl_affproduct_amazon_mx     = get_option('gbl_affproduct_amazon_mx');

	$gbl_affproduct_rakuten_id    = get_option('gbl_affproduct_rakuten_id');
	$gbl_affproduct_rakuten_mids  = get_option('gbl_affproduct_rakuten_mids');

	$gbl_affproduct_zazzle_id     = get_option('gbl_affproduct_zazzle_id');

	$affConfig = array(
		'AMAZON' => array(),
		'RAKUTEN' => array()
	);

	if (!empty($gbl_affproduct_amazon_com_au))
		$affConfig['AMAZON']['amazon.com.au']['tracking-code'] = $gbl_affproduct_amazon_com_au;
	if (!empty($gbl_affproduct_amazon_br))
		$affConfig['AMAZON']['amazon.br']['tracking-code'] = $gbl_affproduct_amazon_br;
	if (!empty($gbl_affproduct_amazon_ca))
		$affConfig['AMAZON']['amazon.ca']['tracking-code'] = $gbl_affproduct_amazon_ca;
	if (!empty($gbl_affproduct_amazon_cn))
		$affConfig['AMAZON']['amazon.cn']['tracking-code'] = $gbl_affproduct_amazon_cn;
	if (!empty($gbl_affproduct_amazon_com))
		$affConfig['AMAZON']['amazon.com']['tracking-code'] = $gbl_affproduct_amazon_com;
	if (!empty($gbl_affproduct_amazon_co_jp))
		$affConfig['AMAZON']['amazon.co.jp']['tracking-code'] = $gbl_affproduct_amazon_co_jp;
	if (!empty($gbl_affproduct_amazon_co_uk))
		$affConfig['AMAZON']['amazon.co.uk']['tracking-code'] = $gbl_affproduct_amazon_co_uk;
	if (!empty($gbl_affproduct_amazon_de))
		$affConfig['AMAZON']['amazon.de']['tracking-code'] = $gbl_affproduct_amazon_de;
	if (!empty($gbl_affproduct_amazon_es))
		$affConfig['AMAZON']['amazon.es']['tracking-code'] = $gbl_affproduct_amazon_es;
	if (!empty($gbl_affproduct_amazon_fr))
		$affConfig['AMAZON']['amazon.fr']['tracking-code'] = $gbl_affproduct_amazon_fr;
	if (!empty($gbl_affproduct_amazon_in))
		$affConfig['AMAZON']['amazon.in']['tracking-code'] = $gbl_affproduct_amazon_in;
	if (!empty($gbl_affproduct_amazon_it))
		$affConfig['AMAZON']['amazon.it']['tracking-code'] = $gbl_affproduct_amazon_it;
	if (!empty($gbl_affproduct_amazon_mx))
		$affConfig['AMAZON']['amazon.mx']['tracking-code'] = $gbl_affproduct_amazon_mx;

	if (!empty($gbl_affproduct_rakuten_id))
		$affConfig['RAKUTEN']['affiliate-code'] = $gbl_affproduct_rakuten_id;
	if (!empty($gbl_affproduct_rakuten_mids)) {
		$midlist = preg_split("/(\r\n|\n|\r)/", $gbl_affproduct_rakuten_mids);
		foreach ($midlist as $mistring) {
			$midata = explode(' ', $mistring);
			$dmn = $midata[0];
			$mid = $midata[1];
			$affConfig['RAKUTEN']['programs'][$dmn] = array('mid' => $mid);
		}
	}

	if (!empty($gbl_affproduct_zazzle_id)) {
		$affConfig['zazzle.com']['affiliateID'] = $gbl_affproduct_zazzle_id;
	}

	return new Processor($affConfig);
}


function gbl_amazon_buy($atts, $content = "", $tag = 'extlink_amazon_com_buy') {

	// Duplicate changes to the Amazon sites up to gbl_init_affprocessor
	$gbl_affproduct_amazon_com_au = get_option('gbl_affproduct_amazon_com_au');
	$gbl_affproduct_amazon_br     = get_option('gbl_affproduct_amazon_br');
	$gbl_affproduct_amazon_ca     = get_option('gbl_affproduct_amazon_ca');
	$gbl_affproduct_amazon_cn     = get_option('gbl_affproduct_amazon_cn');
	$gbl_affproduct_amazon_com    = get_option('gbl_affproduct_amazon_com');
	$gbl_affproduct_amazon_co_jp  = get_option('gbl_affproduct_amazon_co_jp');
	$gbl_affproduct_amazon_co_uk  = get_option('gbl_affproduct_amazon_co_uk');
	$gbl_affproduct_amazon_de     = get_option('gbl_affproduct_amazon_de');
	$gbl_affproduct_amazon_es     = get_option('gbl_affproduct_amazon_es');
	$gbl_affproduct_amazon_fr     = get_option('gbl_affproduct_amazon_fr');
	$gbl_affproduct_amazon_in     = get_option('gbl_affproduct_amazon_in');
	$gbl_affproduct_amazon_it     = get_option('gbl_affproduct_amazon_it');
	$gbl_affproduct_amazon_mx     = get_option('gbl_affproduct_amazon_mx');

	$gbl_amazon_buynow_target     = get_option('gbl_amazon_buynow_target');
	$gbl_amazon_buynow_display    = get_option('gbl_amazon_buynow_display');

	$ASIN = '';
	$displayAttr = '';
    foreach ($atts as $key => $value) {
        if ($key === 'asin') {
            $ASIN = $value;
        }
    }

    $targetBlank = '';
	if (!empty($gbl_amazon_buynow_target)
	 && $gbl_amazon_buynow_target === "_blank") {
		$targetBlank = ' target="_blank"';
	}

	$formDisplay = '';
	if (!empty($gbl_amazon_buynow_display)) {
	    $formDisplay = "style='display: ${gbl_amazon_buynow_display} !important'";
	}

	$affbuyform = '';

	/* if (!empty($ASIN) && !empty($gbl_affproduct_amazon_com_au) && $tag == 'extlink_amazon_com_au_buy') {
	    // not supported
	    $affbuyform = '';
	} */

	/* if (!empty($ASIN) && !empty($gbl_affproduct_amazon_br) && $tag == 'extlink_amazon_br_buy') {
	    // not supported
	    $affbuyform = '';
	} */

	if (!empty($ASIN) && !empty($gbl_affproduct_amazon_ca) && $tag == 'extlink_amazon_ca_buy') {
	    $affbuyform = <<<EOD
<form method="GET" action="//www.amazon.ca/gp/aws/cart/add.html"${targetBlank}${formDisplay}>
<input type="hidden" name="AssociateTag" value="${gbl_affproduct_amazon_ca}"/>
<input type="hidden" name="ASIN.1" value="${ASIN}/"/>
<input type="hidden" name="Quantity.1" value="1"/>
<input type="image" name="add" value="Buy from Amazon.ca" border="0" alt="Buy from Amazon.ca" src="//images.amazon.com/images/G/15/associates/network/build-links/buy-from-ca-tan.gif">
</form>
EOD;

	}

	/* if (!empty($ASIN) && !empty($gbl_affproduct_amazon_cn) && $tag == 'extlink_amazon_cn_buy') {
	    // not supported
	    $affbuyform = '';
	} */

	if (!empty($ASIN) && !empty($gbl_affproduct_amazon_com) && $tag == 'extlink_amazon_com_buy') {
	    $affbuyform = <<<EOD
<form method="GET" action="//www.amazon.com/gp/aws/cart/add.html"${targetBlank}${formDisplay}> <input type="hidden" name="AssociateTag" value="${gbl_affproduct_amazon_com}"/> <!-- input type="hidden" name="SubscriptionId" value="AWSAccessKeyId"/ --> <input type="hidden" name="ASIN.1" value="${ASIN}"/> <input type="hidden" name="Quantity.1" value="1"/> <input type="image" name="add" value="Buy from Amazon.com" border="0" alt="Buy from Amazon.com" src="//images.amazon.com/images/G/01/associates/add-to-cart.gif"> </form>
EOD;
	}

	if (!empty($ASIN) && !empty($gbl_affproduct_amazon_co_jp) && $tag == 'extlink_amazon_co_jp_buy') {
	    $affbuyform = <<<EOD
<form method="post" action="//www.amazon.co.jp/gp/aws/cart/add.html"${targetBlank}${formDisplay}> <input type="hidden" name="ASIN.1" value="${ASIN}"> <input type="hidden" name="Quantity.1" value="1"> <input type="hidden" name="AssociateTag" value="${gbl_affproduct_amazon_co_jp}"> <input type="image" name="submit.add-to-cart" src= "//rcm-images.amazon.com/images/G/09/extranet/associates/buttons/remote-buy-jp1.gif" alt="buy in amazon.co.jp"> </form>
EOD;
	}

	if (!empty($ASIN) && !empty($gbl_affproduct_amazon_co_uk) && $tag == 'extlink_amazon_co_uk_buy') {
	    $affbuyform = <<<EOD
<form method="POST" action="//www.amazon.co.uk/exec/obidos/dt/assoc/handle-buy-box=${ASIN}"${targetBlank}${formDisplay}>
<input type="hidden" name="asin.${ASIN}" value="1">
<input type="hidden" name="tag-value" value="${gbl_affproduct_amazon_co_uk}">
<input type="hidden" name="tag_value" value="${gbl_affproduct_amazon_co_uk}">
<input type="image" name="submit.add-to-cart" value="Buy from Amazon.co.uk" border="0" alt="Buy from Amazon.co.uk" src="//images.amazon.com/images/G/02/associates/buttons/buy5.gif">
</form>
EOD;

	}

	if (!empty($ASIN) && !empty($gbl_affproduct_amazon_de) && $tag == 'extlink_amazon_de_buy') {
	    $affbuyform = <<<EOD
<form method="POST" action="//www.amazon.de/exec/obidos/dt/assoc/handle-buy-box=${ASIN}"${targetBlank}${formDisplay}> <input type="hidden" name="asin.${ASIN}" value="1"> <input type="hidden" name="tag-value" value="${gbl_affproduct_amazon_de}"> <input type="hidden" name="tag_value" value="${gbl_affproduct_amazon_de}"> <input type="submit" name="submit.add-to-cart" value="bei Amazon.de kaufen"> </form>
EOD;
	}

	if (!empty($ASIN) && !empty($gbl_affproduct_amazon_es) && $tag == 'extlink_amazon_es_buy') {
	    $affbuyform = <<<EOD
<form method="POST" action="//www.amazon.es/exec/obidos/dt/assoc/handle-buy-box=${ASIN}"${targetBlank}${formDisplay}>
<input type="hidden" name="${ASIN}" value="1">
<input type="hidden" name="tag-value" value="${gbl_affproduct_amazon_es}">
<input type="hidden" name="tag_value" value="${gbl_affproduct_amazon_es}">
<input type="image" name="submit.add-to-cart" value="Comprar en Amazon.es" border="0" alt="Comprar en Amazon.es" src="//images.amazon.com/images/G/30/associates/buttons/buy_4">
</form>
EOD;
	}

	if (!empty($ASIN) && !empty($gbl_affproduct_amazon_fr) && $tag == 'extlink_amazon_fr_buy') {
	    $affbuyform = <<<EOD
<form method="POST" action="//www.amazon.fr/exec/obidos/dt/assoc/handle-buy-box=${ASIN}"${targetBlank}${formDisplay}> <input type="hidden" name="asin.${ASIN}" value="1"> <input type="hidden" name="tag-value" value="${gbl_affproduct_amazon_fr}"> <input type="hidden" name="tag_value" value="${gbl_affproduct_amazon_fr}">  <input type="submit" name="submit.add-to-cart" value="Achetez chez Amazon.fr"> </form>
EOD;
	}

	/* if (!empty($ASIN) && !empty($gbl_affproduct_amazon_in) && $tag == 'extlink_amazon_in_buy') {
	    // not supported
	    $affbuyform = '';
	} */

	if (!empty($ASIN) && !empty($gbl_affproduct_amazon_it) && $tag == 'extlink_amazon_it_buy') {
	    $affbuyform = <<<EOD
<form method="POST" action="//www.amazon.it/exec/obidos/dt/assoc/handle-buy-box=${ASIN}"${targetBlank}${formDisplay}>
<input type="hidden" name="asin.${ASIN}" value="1">
<input type="hidden" name="tag-value" value="${gbl_affproduct_amazon_it}">
<input type="hidden" name="tag_value" value="${gbl_affproduct_amazon_it}">
<input type="image" name="submit.add-to-cart" value="Compra su Amazon.it" border="0" alt="Compra su Amazon.it" src="//images.amazon.com/images/G/29/associates/buttons/buy5.gif">
</form>
EOD;

	}

	/* if (!empty($ASIN) && !empty($gbl_affproduct_amazon_mx) && $tag == 'extlink_amazon_mx_buy') {
	    // not supported
	    $affbuyform = '';
	} */

    if (empty($affbuyform)) {
        return ""; // eliminate this shortcode - not enough to support its display
    } else {
        return $affbuyform;
    }

}
add_shortcode('extlink_amazon_com_au_buy', 'gbl_amazon_buy');
add_shortcode('extlink_amazon_br_buy', 'gbl_amazon_buy');
add_shortcode('extlink_amazon_ca_buy', 'gbl_amazon_buy');
add_shortcode('extlink_amazon_cn_buy', 'gbl_amazon_buy');
add_shortcode('extlink_amazon_com_buy', 'gbl_amazon_buy');
add_shortcode('extlink_amazon_co_jp_buy', 'gbl_amazon_buy');
add_shortcode('extlink_amazon_co_uk_buy', 'gbl_amazon_buy');
add_shortcode('extlink_amazon_de_buy', 'gbl_amazon_buy');
add_shortcode('extlink_amazon_es_buy', 'gbl_amazon_buy');
add_shortcode('extlink_amazon_fr_buy', 'gbl_amazon_buy');
add_shortcode('extlink_amazon_in_buy', 'gbl_amazon_buy');
add_shortcode('extlink_amazon_it_buy', 'gbl_amazon_buy');
add_shortcode('extlink_amazon_mx_buy', 'gbl_amazon_buy');

function gbl_domainEndsWith($haystack, $needle) {
	// search forward starting from end minus needle length characters
	return $needle === ""
	  || (
		  ($temp = strlen($haystack) - strlen($needle)) >= 0
		&& stripos($haystack, $needle, $temp) !== FALSE
		 );
}
