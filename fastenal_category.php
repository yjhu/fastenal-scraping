<?php
include("LIBs/LIB_http.php");
include("LIBs/LIB_parse.php");
require_once 'simple_html_dom.php';
require_once 'fastenal_db.php';

global $cookiefilename;
global $proxy;
global $proxy_type;
global $proxy_userpwd;

$cookiefilename = 'cookies\cookie.txt';
$proxy = '64.79.77.189:22642';
$proxy_userpwd = null;
$proxy_type = CURLPROXY_SOCKS4;

define('FastenalCategory_DEBUG', true);

function fastenal_category_fetchAllUnscrapped() {
	try {
		$dbh = fastenal_connectDB();
		$sql = 'SELECT * FROM categories WHERE scrapped_flag = FALSE';
		$rows = $dbh->query($sql);
		if ($rows === false)
			return 0;
		if ($rows->rowCount() == 0) {
			return 0;
		}
		foreach($rows as $row) {
			$category = new fastenal_category();
			$category->load($row);
			$category->scraping();
		}
		return count($rows);
	} catch (PDOException $e) {
		die("Error: " . $e->getMessage());
	}
}

class fastenal_category {
	public $id = -1;
	public $title;
	public $url;
	public $ref_url;
	public $parent_id;
	public $parent = null;
	public $subcategories_num;
	public $products_num;
	public $children = array();
	public $is_leaf = true;
	public $valid_flag = false;
	public $scrapped_flag = false;
	public $page_url;

	private function db_connect() {
		return fastenal_connectDB();
	}

	private function get_category_id($dbh) {
//		var_dump($this);
		$rows = $dbh->query('SELECT * FROM categories WHERE title like \''.$this->title.'\'');
		if ($rows === false)
			return -1;
		if ($rows->rowCount() == 0)
			return -1;
		else {
			$row = $rows->fetch();
			return $row['id'];
		}
	}

	function __construct($title=null, $url=null, $ref_url=null) {
		$this->title = $title;
		$this->url = $url;
		$this->ref_url = $ref_url;
	}

	function dump() {
		//		var_dump($this);
		echo $this->title."($this->product_num)".PHP_EOL;
		if (count($this->children) == 0)
			return;
		foreach ($this->children as $child) {
			echo "\t".$child->title.PHP_EOL;
		}
	}

	function load($row) {
		$this->id = $row['id'];
		$this->title = $row['title'];
		$this->url = $row['url'];
		$this->ref_url = $row['ref_url'];
		$this->parent_id = $row['parent'];
		$this->products_num = $row['products_num'];
		$this->subcategories_num = $row['subcategories_num'];
		$this->is_leaf = $row['is_leaf'];
		$this->valid_flag = $row['valid_flag'];
		$this->scrapped_flag = $row['scrapped_flag'];
		$this->page_url = $row['page_url'];
	}

	function save() {
// 		echo "Saving...".PHP_EOL;
// 		var_dump($this);
		try {
			$dbh = $this->db_connect();
			if ($this->id < 0)
				$this->id = $this->get_category_id($dbh);
			if ($this->id < 0) {
				$stmt = $dbh->prepare("INSERT INTO categories (title, url, ref_url, parent, products_num, subcategories_num, scrapped_flag, valid_flag, is_leaf, page_url) VALUES (:title, :url, :ref_url, :parent, :products_num, :subcategories_num, :scrapped_flag, :valid_flag, :is_leaf, :page_url)");
				$stmt->bindParam(':title', $this->title);
				$stmt->bindParam(':url', $this->url);
				$stmt->bindParam(':ref_url', $this->ref_url);
				$stmt->bindParam(':parent', $this->parent_id);
				$stmt->bindParam(':products_num', $this->products_num);
				$stmt->bindParam(':subcategories_num', $this->subcategories_num);
				$stmt->bindParam(':scrapped_flag', $this->scrapped_flag);
				$stmt->bindParam(':valid_flag', $this->valid_flag);
				$stmt->bindParam(':is_leaf', $this->is_leaf);
				$stmt->bindParam(':page_url', $this->page_url);
				$stmt->execute();
				$this->id = $this->get_category_id($dbh);
			} else {
				$stmt = $dbh->prepare("UPDATE categories SET parent=:parent_id, products_num=:products_num, subcategories_num=:subcategories_num, scrapped_flag=:scrapped_flag, valid_flag=:valid_flag, is_leaf=:is_leaf, page_url=:page_url WHERE id=:id");
				$stmt->bindParam(':parent_id', $this->parent_id);
				$stmt->bindParam(':products_num', $this->products_num);
				$stmt->bindParam(':subcategories_num', $this->subcategories_num);
				$stmt->bindParam(':scrapped_flag', $this->scrapped_flag);
				$stmt->bindParam(':valid_flag', $this->valid_flag);
				$stmt->bindParam(':is_leaf', $this->is_leaf);
				$stmt->bindParam(':page_url', $this->page_url);
				$stmt->bindParam(':id', $this->id);
				$stmt->execute();
			}
			return;
		} catch (PDOException $e) {
			die("Error: " . $e->getMessage());
		}
	}

	protected function get_products_num($html) {
		$spans = $html->find('span#refine-by-attribute');
		if (count($spans) == 0)	{
			return 0;
		}
		preg_match('/[,\d]+/', $spans[0]->plaintext, $matches);
		return (int)implode('',explode(',', $matches[0]));
	}
	
	protected function get_page_url($html) {
		$archors = $html->find('table.pagination a');
		if (count($archors) == 0)	{
			return null;
		}
		return html_entity_decode($archors[0]->href);
	}

	function scraping() {
		$response = http_get($this->url, $this->ref_url);
		if (strlen($response['ERROR']) != 0) {
			echo "Error: ".$response['ERROR'].".\n";
			echo "When http_get($this->url, $this->ref_url).\n";
			exit;
		} else if (FastenalCategory_DEBUG) {
			echo "done with downloading $this->url\r\n";
			echo "---------------------\r\n";
		}
		$html = str_get_html(tidy_html($response['FILE']));
		$this->products_num = $this->get_products_num($html);
		$this->page_url = $this->get_page_url($html);
		$divs = $html->find('div#product-categories');
		if (count($divs) == 0) {
			$this->is_leaf = true;
			$this->scrapped_flag = true;
			$this->valid_flag = true;
			$this->subcategories_num = 0;
			$this->save();
			return;
		}
		$this->is_leaf = false;
		$product_categories_div = $divs[0];
		$archors = $product_categories_div->find('a');
		foreach ($archors as $a) {
			$title = preg_replace('/\s+/', ' ', trim(html_entity_decode($a->plaintext)));
			$title = $this->title.'>'.$title;
			$url = html_entity_decode($a->href);
			$child = new fastenal_category($title, $url, $this->url);
			$this->children[] = $child;
		}
		$this->valid_flag = true;
		$this->subcategories_num = count($this->children);
		if ($this->id < 0)
			$this->save();
		foreach($this->children as $c) {
			$c->parent_id = $this->id;
			$c->save();
		}
		$this->scrapped_flag = true;
		$this->save();
	}
}


// $products = new fastenal_category("Home>Products", "http://www.fastenal.com/web/search/product/_/Navigation", "http://www.fastenal.com/web/home.ex");
// $products->scraping();
while (fastenal_category_fetchAllUnscrapped() > 0);
//$products->dump();
?>