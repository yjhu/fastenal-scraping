<?php
require_once 'fastenal_db.php';
require_once 'fastenal_page.php';

global $fnames;

define('AUTO_ATTRIBUTES', true);

function escape_comma($inputstr) {
	$inputstr = str_replace("/'", "'", $inputstr);
	$inputstr = str_replace('/"', '"', $inputstr);
	return str_replace(',',' ', $inputstr);
}

class Category {
	public $id;
	public $title;
	public $products_num;
	public $subcategories_num;
	public $children = array();
	public $is_leaf = false;
	public $parent = null;
	public $tblbasename;
	public $scrapped_flag;
	public $total_pages;
	public $scrapped_pages;
	public $total_products;
	public $scrapped_products;

	public $data_filename;

	function loadByRow($row) {
		$this->id = $row['id'];
		$this->title = $row['title'];
		$this->products_num = $row['products_num'];
		$this->subcategories_num = $row['subcategories_num'];
		$this->is_leaf = $row['is_leaf'];
		$this->tblbasename = $row['products_tbl_name'];
		$this->scrapped_flag = $row['scrapped_flag'];
		return $this;
	}

	function load($dbh, $title) {
		$sql = "SELECT * From categories where title like '$title'";
		$rows = $dbh->query($sql);
		if ($rows === false || $rows->rowCount() != 1)
			return false;
		else {
			$row = $rows->fetch();
			return $this->loadByRow($row);
		}
	}

	private function getScrappedPages($dbh) {
		$tblname = 'pages_'.$this->tblbasename;
		$sql = "SELECT count(*) From $tblname WHERE scrapped_flag = true";
		$rows = $dbh->query($sql);
		if (false === $rows || $rows->rowCount() != 1)
			return 0;
		else {
			$row = $rows->fetch();
			return (int)$row[0];
		}
	}

	private function getAllProducts($dbh) {
		$tblname = 'products_'.$this->tblbasename;
		$sql = "SELECT count(*) From $tblname";
		$rows = $dbh->query($sql);
		if (false === $rows || $rows->rowCount() != 1)
			return 0;
		else {
			$row = $rows->fetch();
			return (int)$row[0];
		}
	}

	private function getScrappedProducts($dbh) {
		$tblname = 'products_'.$this->tblbasename;
		$sql = "SELECT count(*) From $tblname WHERE scrapped_flag = true";
		$rows = $dbh->query($sql);
		if (false === $rows || $rows->rowCount() != 1)
			return 0;
		else {
			$row = $rows->fetch();
			return (int)$row[0];
		}
	}

	private function getScrappedTimestr($dbh, $sku) {
		$sql = "SELECT UNIX_TIMESTAMP(scrapped_ts) FROM inventories WHERE sku LIKE '$sku' ORDER BY scrapped_ts DESC LIMIT 1";
		$rows = $dbh->query($sql);
		if ($rows === false || $rows->rowCount() != 1) {
			echo "Error: when accessing inventories for sku#$sku.\r\n";
			exit;
		}
		$row = $rows->fetch();
// 		echo $row[0];
		return date('Y-m-d H:i:s T I', (int)$row[0]);
	}
	
	private function save_data($dbh) {
		global $dirname;

		$titles = explode('>', $this->title);
		array_shift($titles);array_shift($titles);
		$data_filename = implode('_', $titles);
		$data_filename = preg_replace('/[&,]/', '', $data_filename);
		$data_filename = preg_replace('/\s+/', '-', $data_filename);
		$data_filename = preg_replace('/[\/\\\]+/', '-', $data_filename);
		$data_filename = strtolower($data_filename);
		$data_filename = $dirname.'\\reports\\'.$data_filename.".csv";

		echo "Saving $this->title to $data_filename ......";
		
		$fp = fopen($data_filename, 'w+');
		if (false === $fp) {
			echo "Error: fopen($data_filename)".PHP_EOL;
			exit;
		}

		$products_tblname = 'products_'.$this->tblbasename;
		$sql = "SELECT * FROM $products_tblname WHERE scrapped_flag = true";
		$rows = $dbh->query($sql);
		if (false === $rows) {
			echo "Error: when accessing table $this->title $products_tblname".PHP_EOL;
			exit;
		}

		$header = 'Scrapping Time, Category Path, Fastenal SKU Number,Description,Price($),Size,Product Weight(lb),Inventory';
		if (AUTO_ATTRIBUTES) {
			$auto_attributes = array();
			foreach(range(0, $rows->columnCount() - 1) as $column_index) {
				$metas[] = $rows->getColumnMeta($column_index);
			}
			for ($i = 0; $i < count($metas); $i++) {
				if (stristr($metas[$i]['name'], 'attr_')) {
					$auto_attributes[] = array('name' => dbcollumn2attrname($metas[$i]['name']), 'index' => $i);
				}
			}
			foreach($auto_attributes as $a) {
				$header .= ','.$a['name'];
			}
		}
		fwrite($fp, $header."\r\n");
		
		foreach($rows as $r) {
			$line = $this->getScrappedTimestr($dbh, $r['sku']).','.$this->title.',';
			$line .="'".$r['sku']."',".escape_comma($r['title']).','.escape_comma($r['wholesale_price']).','.escape_comma($r['size']).','
					.escape_comma($r['weight']).','.escape_comma($r['inventory']);
			if (AUTO_ATTRIBUTES) {
				foreach ($auto_attributes as $a) {
					$line .= ','.escape_comma($r[$a['index']]);
				}
			}
			fwrite($fp, $line."\r\n");
		}

		fclose($fp);
		
		echo "done.\r\n";
		return basename($data_filename);
	}

	function loadPagesProductsInfo($dbh) {
		if ($this->is_leaf) {
			echo "loading $this->title\r\n";
			if ($this->products_num > 10) {
				$this->total_pages = (int)ceil($this->products_num/10);
				$this->scrapped_pages = $this->getScrappedPages($dbh) + 1;
			}
			$this->total_products = $this->getAllProducts($dbh);
			$this->scrapped_products = $this->getScrappedProducts($dbh);
// 			if ($this->scrapped_products > 0)
// 				$this->data_filename = $this->save_data($dbh);
		}
		return $this;
	}

	function loadChildren($dbh, $cascading = true) {
		if ($this->is_leaf && $this->scrapped_flag) {
			$this->loadPagesProductsInfo($dbh);
		} else {
			$sql = "SELECT * FROM categories WHERE parent = $this->id";
			$rows = $dbh->query($sql);
			if ($rows === false || $rows->rowCount() == 0)
				return false;
			foreach ($rows as $row) {
				$child = new Category();
				$child->loadByRow($row);
				$child->parent = $this;
				if ($cascading) {
					$child->loadChildren($dbh, $cascading);
				}
				$this->children[] = $child;
			}
		}
		return $this;
	}

	function dump($fp = STDOUT, $depth = 0) {
		echo "dumping $this->title\r\n";
		$line = '';
		if ($depth > 0) {
			$line .= str_repeat('|   ', $depth-1);
			$line .= '|---';
		}
		$titles = explode('>', $this->title);
		$line .= array_pop($titles);
		$products_info = ($this->products_num == 0) ? "Only 1 product" : "Products: $this->products_num";
		if ($this->subcategories_num > 0)
			$line .= "($products_info/Categories: $this->subcategories_num)";
		else
			$line .= "($products_info)";
		fwrite($fp, $line."\r\n");
		if (!$this->is_leaf) {
			foreach ($this->children as $child) {
				$child->dump($fp, $depth + 1);
			}
		} else {
			$scrapped_line = str_repeat('|   ', $depth).'[';
			if ($this->scrapped_flag) {
				$scrapped_line .= "Products: $this->total_products totally, $this->scrapped_products scrapped;";
				if ($this->products_num > 10)
					$scrapped_line .= "Pages: $this->total_pages totally, $this->scrapped_pages scrapped;";
			} else {
				$scrapped_line .= 'Unscrapped.';
			}
			$scrapped_line .= ']';

			fwrite($fp, $scrapped_line."\r\n");

// 			if ($this->scrapped_products > 0) {
// 				$scrapped_line = str_repeat('|   ', $depth).'[';
// 				$scrapped_line .= 'Data saved to file: reports\\'.$this->data_filename;
// 				$scrapped_line .= ']';
// 				fwrite($fp, $scrapped_line."\r\n");
// 			}
		}
	}
}

global $dirname;
date_default_timezone_set('America/Los_Angeles');

$dirname = date('Y-m-d');
$dirname = 'data\\'.$dirname;
if (false == is_dir($dirname)) {
	mkdir($dirname);
//	mkdir($dirname.'\reports');
}


try {
	$dbh = fastenal_connectDB();
	$c_products = new Category();
	$c_products->load($dbh, 'Home>Products');
	$c_products->loadChildren($dbh);
	$fp = fopen("$dirname\README.txt", 'w+');
	$c_products->dump($fp);
	fclose($fp);
} catch (Exception $e) {
	die("Error: " . $e->getMessage());
}