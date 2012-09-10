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

foreach ($main_categories as $main_category) {
	$unscrapped_pages = array();

	try {
		$dbh = fastenal_connectDB();
		$sql = 'SELECT * FROM categories '.
				'WHERE is_leaf=true and scrapped_flag=true '.
				'and products_tbl_name is not null and valid_flag = true '.
				"and title like '%$main_category%'";
		$categories_rows = $dbh->query($sql);
		if ($categories_rows === false || $categories_rows->rowCount() == 0) exit;
		while(false !== ($categories_row = $categories_rows->fetch())) {
			$pages_tblname = 'pages_'.$categories_row['products_tbl_name'];
			$sql = "SELECT * FROM $pages_tblname WHERE scrapped_flag=false";
			$pages_rows = $dbh->query($sql);
			if (false !== $pages_rows && $pages_rows->rowCount() > 0) {
				while (false != ($pages_row = $pages_rows->fetch())) {
					$unscrapped_pages[] = array('categories_id' => $categories_row['id'], 'page_num' => $pages_row['page_num']);
				}
			}
			unset($pages_rows);
		}
		unset($categories_rows);

		echo $main_category." unscrapped pages: ".count($unscrapped_pages).PHP_EOL;
	} catch (Exception $e) {
		die("Error: " . $e->getMessage());
	}
}