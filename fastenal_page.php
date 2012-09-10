<?php
function productsattr2dbcollumn($inputstr) {
	$tmp_str = html_entity_decode($inputstr);
	$tmp_str = str_replace('&', 'and', $tmp_str);		// & -> and
	$tmp_str = str_replace('"', 'inch', $tmp_str);		// " -> and
	$tmp_str = str_replace(':', ' ', $tmp_str);			// : -> SPACE
	$tmp_str = preg_replace('/[\(\)]/','__',$tmp_str);	// () -> __
	$tmp_str = preg_replace('/[\[\]]/','__',$tmp_str);	// [] -> __
	$tmp_str = preg_replace('/[\.]/','_',$tmp_str);		// . -> _
	$tmp_str = strtolower(trim($tmp_str));				// trim and lowercase
	$tmp_str = preg_replace('/\s+/','_',$tmp_str);		// SPACEs -> _
	$tmp_str = str_replace('-', '_', $tmp_str);			// - -> _
	$tmp_str = preg_replace('/[\/\\\]/','_', $tmp_str);	// / or \ -> _
	return 'attr_'.$tmp_str;
}

function dbcollumn2attrname($inputstr) {
	$tmpstr = str_replace('attr_', '', $inputstr);
	$tmpstr = preg_replace('/inches|inch/', '"', $tmpstr);
	$tmpstr = preg_replace('/_+/', ' ', $tmpstr);
	$tmpstr = ucwords($tmpstr);
	return $tmpstr;
}

function get_attrkeys_from_detail_page($html) {
	$ths = $html->find('div#product-details-container table.details th');
	$attr_keys = array();
	for ($i = 0; $i < (count($ths)); $i++){
		$attr_keys[] = productsattr2dbcollumn($ths[$i]->plaintext);
	}
	return $attr_keys;
}

function get_attrvals_from_detail_page($html) {
	$tds = $html->find('div#product-details-container table.details td');
	$attr_vals = array();
	for ($i = 0; $i < (count($tds)); $i++){
		$attr_vals[] = html_entity_decode(preg_replace('/\s+/', ' ', trim($tds[$i]->plaintext)));
	}
	return $attr_vals;
}

function get_sku_from_detail_page($html) {
	$tds = $html->find('div#mainDetailsContainer table.details td');
	if (!isset($tds) || count($tds) == 0) return false;
	$sku = trim($tds[0]->plaintext);
	return $sku;
}
function get_title_from_detail_page($html) {
	$h1s = $html->find('div#mainDetailsContainer h1');
	$title = html_entity_decode(trim($h1s[0]->plaintext));
	return $title;
}
function get_price_from_detail_page($html) {
	$ps = $html->find('div#buyingInformation p.wholesale');
	$r = @preg_match('/\$[\d\.]+/',trim($ps[0]), $matches);
	if ($r === false || $r == 0) return 0;
	return str_replace('$', '', $matches[0]);
}
function get_inventory_from_detail_page($html) {
	$divs = $html->find('div#buyingInformation div.onlineAvailable');
	if (count($divs) > 0) {
		$inputs = $html->find('form#ProductAddForm input');
		foreach ($inputs as $input) {
			if ($input->getAttribute('name') == 'productId')
				$form['productId'] = $input->getAttribute('value');
			if ($input->getAttribute('name') == 'productDetailId')
				$form['productDetailId'] = $input->getAttribute('value');
			if ($input->getAttribute('id') == 'addCartQty')
				$form['addCartQty'] = $input->getAttribute('name');
		}
		$form['SKU'] = get_sku_from_detail_page($html);
		if (false === $form['SKU'])
			return 0;
		return fastenal_getOnlineInventory($form);
	} else {
		$divs = $html->find('div#buyingInformation div.onlineUnavailable');
		$o_divs = $html->find('div#buyingInformation div.onlineDelay');
		if (count($divs) + count($o_divs) > 0)
			return 0;
		else {
			return -1;
		}
	}
}