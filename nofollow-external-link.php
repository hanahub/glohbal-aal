<?php
/*

 Plugin Name: Glohbal Automatic Affiliate Links Plugin for Amazon & Rakuten (LinkShare) platforms
 Plugin URI: https://davidherron.com/content/external-links-nofollow-favicon-open-external-window-etc-wordpress
 Description: Process outbound (external) links in content, optionally adding affiliate link attributes, rel=nofollow or target=_blank attributes, and optionally adding icons.
 Version: 1.0.2
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

$gbl_links = array(
	'rakuten' => 'http://click.linksynergy.com/deeplink'
);

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

	// $affprocessor = gbl_init_affprocessor();
	$gbl_link_id = get_option('gbl_link_id');
	$gbl_advertisers = get_option('gbl_advertisers');

	if (empty($gbl_link_id) || empty($gbl_advertisers)) return $content;

	$ownDomain = $_SERVER['HTTP_HOST'];

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

			// Add rel=nofollow
			$a->setAttribute('rel', 'nofollow');
			
			// Add target=_blank if there's no target=
			$curtarget = $a->getAttribute('target');
			if (!isset($curtarget) || $curtarget == '') {
				$a->setAttribute('target', '_blank');
			}

			// Process for affiliate links
			// $newurl = $affprocessor->process($url->textContent);

			$newurl = gbl_process($url->textContent, $gbl_link_id, $gbl_advertisers);
			if ($newurl !== $url->textContent) {
				$a->setAttribute('href', $newurl);
				$a->setAttribute('rel', 'nofollow noskim norewrite');
			}
		}

  	return str_replace(array('<body>', '</body>'), '', $html->saveHTML($html->getElementsByTagName('body')->item(0)));

	} catch (Exception $e) {
		return $content;
	}
}

function gbl_process($url, $gbl_link_id, $gbl_advertisers) {
	global $gbl_links;

	foreach ($gbl_advertisers["url"] as $k => $u) {
		$urlParts = parse_url($u);
		if (!$urlParts || empty($urlParts['host']) || !empty($urlParts['fragment'])) {
			continue;
		}

		if (stripos($url, $urlParts['host']) !== false) {
			$ad_mid = $gbl_advertisers["mid"][$k];

			$newurl = "{$gbl_links['rakuten']}?id={$gbl_link_id}&mid={$ad_mid}&murl={$url}";
			return $newurl;
		}
	}

	return $url;
	
	// 	[LINK]mytheresa.com/en-de/designers/bottega-veneta.html?block=4
	// into 
	// [LINK2]http://click.linksynergy.com/deeplink?id=RtbW/HMWeFA&mid=43172&murl=https%3A%2F%2Fwww.mytheresa.com%2Fen-de%2Fdesigners%2Fbottega-veneta.html%3Fblock%3D4
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
