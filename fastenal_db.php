<?php
function fastenal_connectDB() {
	return new PDO('mysql:host=localhost;dbname=fastenal', 'linda', 'zhu', array(PDO::ATTR_PERSISTENT => true));
}

if ( !function_exists('sem_get') ) {
	function sem_get($key) {
		return fopen(__FILE__.'.sem.'.$key, 'w+');
	}
	function sem_acquire($sem_id) {
		return flock($sem_id, LOCK_EX);
	}
	function sem_release($sem_id) {
		return flock($sem_id, LOCK_UN);
	}
}

function save_prices($dbh, $sku, $price) {
	$stmt = $dbh->prepare("INSERT INTO prices (sku, price) VALUES (:sku, :price)");
	$stmt->bindParam(':sku', $sku);
	$stmt->bindParam(':price', $price);
	$stmt->execute();
}

function save_inventories($dbh, $sku, $inventory) {
	$stmt = $dbh->prepare("INSERT INTO inventories (sku, inventory) VALUES (:sku, :inventory)");
	$stmt->bindParam(':sku', $sku);
	$stmt->bindParam(':inventory', $inventory);
	$stmt->execute();
}