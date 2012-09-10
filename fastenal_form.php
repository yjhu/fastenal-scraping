<?php
define("FASTENAL_DEBUG", false);

function fastenal_clearShopCart() {
	if (FASTENAL_DEBUG) echo "clear shop cart......";
	$target = "http://www.fastenal.com/web/ShoppingCart.ex?clear=1";
	http_get($target, "");
	if (FASTENAL_DEBUG) echo "done.\n";
}

function fastenal_addToCart($handtool, $no) {
	if (FASTENAL_DEBUG) echo "add to cart, SKU:".$handtool['SKU'].", #".$no."......";
	$target = "http://www.fastenal.com:80/web/products.ex";

	$postfields['dispatch'] = "addToCart";
	$postfields['productDetailId'] = $handtool['productDetailId'];
	$postfields['sku'] = $handtool['SKU'];
	$postfields['productId'] = $handtool['productId'];
	$postfields[$handtool['addCartQty']] = $no;

	if (FASTENAL_DEBUG) var_dump($postfields);
	$response = http_post_form($target, "", $postfields);
	if (strlen($response['ERROR']) != 0) {
		echo $response['ERROR']."\n";
		echo "ERROR in fastenal_addToCart.\n";
		exit;
	}
	if (FASTENAL_DEBUG) echo "done.\n";
}

function fastenal_checkOnlineAvailable() {
	if (FASTENAL_DEBUG) echo "checking online availability......";
	$target = "http://www.fastenal.com/web/ShoppingCart.ex";
	$response = http_get($target, "");
	if (strlen($response['ERROR']) != 0) {
		echo "ERROR: fetching ".$target.".\n";
		echo $response['ERROR']."\n";
		exit;
	}
	if (FASTENAL_DEBUG) echo "done.\n";
	$page = tidy_html($response['FILE']);
//	file_put_contents("errors-dump.htm", $page);
	if (stristr($page, "onlineAvailable"))
		return true;
	else if (stristr($page, "onlineUnavailable") || stristr($page, "onlineDelay"))
		return false;
	else {
		file_put_contents("errors-dump.htm", $page);
		echo "ERROR in fastenal_checkOnlineAvailable!!\n";
		exit;
	}
}


function fastenal_getOnlineInventory($handtool) {

	$value = 128;
	$step = 64;
	$top_value = 0; $bottom_value = 0;
	$found = false;

	// step#1: upwards to find the [bot, top] values;
	while (!$found) {
		if (FASTENAL_DEBUG) echo "BOT=".$bottom_value.", TOP=".$top_value.", STEP=".$step."\n";
		fastenal_clearShopCart();
		fastenal_addToCart($handtool, $value);
		if (fastenal_checkOnlineAvailable()) {
			$value = $value * 2;
		}
		else {
			if ($value != 128) {
				$top_value = $value;
				$bottom_value = $value / 2;
				$step = $value / 4;
				$found = true;
			}
			break;
		}
	}

	// step#2 (optional): downwards to find the [bot, top] values
	while (!$found) {
		if (FASTENAL_DEBUG) echo "BOT=".$bottom_value.", TOP=".$top_value.", STEP=".$step."\n";
		$value = $value / 2;

		fastenal_clearShopCart();
		fastenal_addToCart($handtool, $value);
		if (fastenal_checkOnlineAvailable()) {
			$bottom_value = $value;
			$top_value = $value * 2;
			$step = $value / 2;
			$found = true;
		}
	}

	// step#3: to find the exact number
	while (true) {
		if (FASTENAL_DEBUG) echo "BOT=".$bottom_value.", TOP=".$top_value.", STEP=".$step."\n";
		if ($top_value - $bottom_value == 1)
			break;

		fastenal_clearShopCart();
		fastenal_addToCart($handtool, $bottom_value + $step);
		if (fastenal_checkOnlineAvailable()) {
			$bottom_value = $bottom_value + $step;
		} else {
			$top_value = $bottom_value + $step;
		}
		$step = $step / 2;
	}

	if (FASTENAL_DEBUG) echo $bottom_value;
	return $bottom_value;
}
