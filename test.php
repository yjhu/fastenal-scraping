<?php
require_once 'fastenal_db.php';
global $main_categories;
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

function fix_pagetbls() {
	global $main_categories;
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
				$sql = "SELECT count(*) FROM $pages_tblname";
				$pages_rows = $dbh->query($sql);
				if (false === $pages_rows || $pages_rows->rowCount() != 1)
					exit;
				else {
					$pages_row = $pages_rows->fetch();
					if ($pages_row[0] + 1 != (int)ceil($categories_row['products_num']/10)) {
						echo "Error: Recreating page tables of category_id ".$categories_row['id']."\r\n";
						$sql = "delete from $pages_tblname";
						$rows = $dbh->query($sql);
						if (false === $rows) {
							echo "ERROR when $sql.\r\n";
						}
						for ($i = 2; $i <= (int)ceil($categories_row['products_num']/10); $i++) {
							$sql = "insert into $pages_tblname values ($i, false, -1)";
							$rows = $dbh->query($sql);
							if (false === $rows) {
								echo "ERROR when $sql.\r\n";
							}
						}
					}
				}
				unset($pages_rows);
			}
			unset($categories_rows);
		} catch (Exception $e) {
			die("Error: " . $e->getMessage());
		}
	}
}

function fix_0valued_inventory() {
	global $main_categories;
	foreach ($main_categories as $main_category) {
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
				$sql = "UPDATE $products_tblname SET scrapped_flag=false WHERE inventory = 0";
				echo $categories_row['title'].":\r\n";
				echo "$sql\r\n";
				$rows = $dbh->query($sql);
				if (false === $rows)
					exit;
			}
		} catch (Exception $e) {
			die("Error: " . $e->getMessage());
		}
	}
}

fix_pagetbls();
//fix_0valued_inventory();