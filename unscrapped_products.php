<?php
require_once 'fastenal_db.php';

$main_categories = array (
 		'Products>Fasteners',
  		'Products>Power Transmission & Motors',
 		'Products>Safety',
 		'Products>Tools & Equipment',
		'Products>Cutting Tools & Metalworking',
		'Products>Material Handling, Storage, & Packaging',
		'Products>Electrical',
		'Products>Plumbing',
		'Products>Hydraulics & Pneumatics',
		'Products>Abrasives',
		'Products>Fleet & Automotive',
		'Products>Raw Materials',
		'Products>Lifting and Rigging',
		'Products>Welding',
		'Products>Chemicals & Paints',
		'Products>Janitorial',
		'Products>Office Products & Furniture',
		'Products>HVAC',
		'Products>Mil-Spec'
);

$products_sum = 0;
$scrapped_sum = 0;

foreach ($main_categories as $main_category) {
	$unscrapped_products = 0;
	$total_products = 0;
	
	try {
		$dbh = fastenal_connectDB();
		$sql = 'SELECT * FROM categories '.
				'WHERE is_leaf=true and scrapped_flag=true '.
				'and products_tbl_name is not null '.
				"and title like '%$main_category%'";
		$categories_rows = $dbh->query($sql);
		if ($categories_rows === false || $categories_rows->rowCount() == 0) exit;
		while(false !== ($categories_row = $categories_rows->fetch())) {
			$products_tblname = 'products_'.$categories_row['products_tbl_name'];
			
			$sql = "SELECT count(*) FROM $products_tblname WHERE scrapped_flag=false or scrapped_flag is null";
			$products_rows = $dbh->query($sql);
			if (false !== $products_rows && $products_rows->rowCount() == 1) {
				if (false != ($products_row = $products_rows->fetch())) {
					$unscrapped_products += $products_row[0];
				}
			}
			unset($products_rows);
			
			$sql = "SELECT count(*) FROM $products_tblname";
			$products_rows = $dbh->query($sql);
			if (false !== $products_rows && $products_rows->rowCount() == 1) {
				if (false != ($products_row = $products_rows->fetch())) {
					$total_products += $products_row[0];
				}
			}
			unset($products_rows);
		}
		unset($categories_rows);
		$scrapped_products = $total_products - $unscrapped_products;
		echo "$main_category (unscrapped: $unscrapped_products, totally: $total_products, scrapped: $scrapped_products).\r\n";
		$products_sum += $total_products;
		$scrapped_sum += $scrapped_products;
	} catch (Exception $e) {
		die("Error: " . $e->getMessage());
	}
}
printf("Totally %d products, %d scrapped, %.2f%% processed.\r\n", $products_sum, $scrapped_sum, (float)($scrapped_sum/$products_sum)*100);