<?php
/**
 * Enhanced import tool: module for PrestaShop 1.3
 *
 * @author    Maksim T. <zapalm@yandex.com>
 * @copyright 2010 Maksim T.
 * @link      https://prestashop.modulez.ru/en/import-and-export-data/14-enhanced-import-tool.html The module's homepage
 * @license   https://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
 */

if (!defined('_CAN_LOAD_FILES_'))
	exit;

include_once(PS_ADMIN_DIR.'/../classes/AdminTab.php');
include_once(PS_ADMIN_DIR.'/../images.inc.php');
include_once(_PS_ROOT_DIR_.'/modules/zimport/comment.php');

@ini_set('max_execution_time', 0);
define('MAX_LINE_SIZE', 4096);

define('UNFRIENDLY_ERROR', false); // Used for validatefields diying without user friendly error or not

// this value set the number of columns visible on each page
define('MAX_COLUMNS', 6);
// correct Mac error on eof
@ini_set('auto_detect_line_endings', '1');

/**
 * Import processor and the tab controller.
 *
 * The improvement of AdminImport tab.
 *
 * @todo The class has some documentation in Russian (translate it to English).
 *
 * @author Maksim T. <zapalm@yandex.com>
 */
class zimportadmin extends AdminTab
{
	/** @var string наименование таба */
  	public $name = 'zimport';

	/** @var string наименование временного файла */
	protected $tmp_csv = '.tmp';

	/** @var bool установка для демо-магазина, чтобы не удаляли наполнение */
	protected $can_delete = true;

	/** @var array замечания импорта */
	protected $_warnings = array();

	/** @var array рантайм кэш списка файлов перевода */
	protected $m_file_exists_cache = array();

	/** @var string путь к директории с файлами для импорта */
	protected $import_dir;

	/** @var string путь к директории модуля */
	protected $module_dir;

	public static $column_mask;

	public $entities = array();

	public $available_fields = array();

	public static $required_fields = array('name');

	public static $default_values = array();

	public static $validators = array(
		'active' => array('zimportadmin', 'getBoolean'),
		'tax_rate' => array('zimportadmin', 'getPrice'),
		'price_tex' => array('zimportadmin', 'getPrice'), // Tax excluded
		'price_tin' => array('zimportadmin', 'getPrice'), // Tax included
		'reduction_price' => array('zimportadmin', 'getPrice'),
		'reduction_percent' => array('zimportadmin', 'getPrice'),
		'wholesale_price' => array('zimportadmin', 'getPrice'),
		'ecotax' => array('zimportadmin', 'getPrice'),
		'name' => array('zimportadmin', 'createMultiLangField'),
		'description' => array('zimportadmin', 'createMultiLangField'),
		'description_short' => array('zimportadmin', 'createMultiLangField'),
		'meta_title' => array('zimportadmin', 'createMultiLangField'),
		'meta_keywords' => array('zimportadmin', 'createMultiLangField'),
		'meta_description' => array('zimportadmin', 'createMultiLangField'),
		'link_rewrite' => array('zimportadmin', 'createMultiLangField'),
		'available_now' => array('zimportadmin', 'createMultiLangField'),
		'available_later' => array('zimportadmin', 'createMultiLangField'),
		'category' => array('zimportadmin', 'split'),
		);

	public function __construct()
	{
		$this->import_dir = PS_ADMIN_DIR.'/import/';
		$this->module_dir = _PS_ROOT_DIR_.'/modules/'.$this->name.'/';

		$this->entities = array_flip(array($this->l('Categories'), $this->l('Products'), $this->l('Attributes'), $this->l('Customers'), $this->l('Addresses'), $this->l('Manufacturers'), $this->l('Suppliers'), $this->l('Comments')));

		switch (intval(Tools::getValue('entity')))
		{
			case $this->entities[$this->l('Attributes')]:

				self::$required_fields = array();

				$this->available_fields = array(
					'no' => $this->l('Ignore this column'),
					'id_product' => $this->l('Product ID').'*',
					'product_reference' => $this->l('Product reference'),
					'options' => $this->l('Options (Group:Value)').'*',
					'reference' => $this->l('Reference'),
					'supplier_reference' => $this->l('Supplier reference'),
					'ean13' => $this->l('EAN13'),
					'wholesale_price' => $this->l('Wholesale price'),
					'price' => $this->l('Price'),
					'ecotax' => $this->l('Ecotax'),
					'quantity' => $this->l('Quantity'),
					'weight' => $this->l('Weight'),
					'default_on' => $this->l('Default')
				);

				self::$default_values = array(
					'reference' => '',
					'product_reference' => '',
					'supplier_reference' => '',
					'ean13' => '',
					'wholesale_price' => 0,
					'price' => 0,
					'ecotax' => 0,
					'quantity' => 0,
					'weight' => 0,
					'default_on' => 0
				);

				break;

			case $this->entities[$this->l('Categories')]:

				$this->available_fields = array(
				'no' => $this->l('Ignore this column'),
				'id' => $this->l('ID'),
				'active' => $this->l('Active (0/1)'),
				'name' => $this->l('Name *'),
				'parent' => $this->l('Parent category'),
				'description' => $this->l('Description'),
				'meta_title' => $this->l('Meta-title'),
				'meta_keywords' => $this->l('Meta-keywords'),
				'meta_description' => $this->l('Meta-description'),
				'link_rewrite' => $this->l('URL rewrited'),
				'image' => $this->l('Image URL'));

				self::$default_values = array('active' => '1', 'parent' => '1', 'link_rewrite' => '');

				break;

			case $this->entities[$this->l('Products')]:

				self::$required_fields = array();

				self::$validators['image'] = array('zimportadmin', 'split');

				$this->available_fields = array(
				'no' => $this->l('Ignore this column'),
				'id' => $this->l('ID'),
				'active' => $this->l('Active (0/1)'),
				'name' => $this->l('Name *'),
				'category' => $this->l('Categories (x,y,z...)'),
				'price_tex' => $this->l('Price tax excl.'),
				'price_tin' => $this->l('Price tax incl.'),
				'tax_rate' => $this->l('Tax rate'),
				'wholesale_price' => $this->l('Wholesale price'),
				'on_sale' => $this->l('On sale (0/1)'),
				'reduction_price' => $this->l('Reduction amount'),
				'reduction_percent' => $this->l('Reduction per cent'),
				'reduction_from' => $this->l('Reduction from (yyyy-mm-dd)'),
				'reduction_to' => $this->l('Reduction to (yyyy-mm-dd)'),
				'reference' => $this->l('Reference #'),
				'supplier_reference' => $this->l('Supplier reference #'),
				'supplier' => $this->l('Supplier'),
				'manufacturer' => $this->l('Manufacturer'),
				'ean13' => $this->l('EAN13'),
				'ecotax' => $this->l('Ecotax'),
				'weight' => $this->l('Weight'),
				'quantity' => $this->l('Quantity'),
				'description_short' => $this->l('Short description'),
				'description' => $this->l('Description'),
				'tags' => $this->l('Tags (x,y,z...)'),
				'meta_title' => $this->l('Meta-title'),
				'meta_keywords' => $this->l('Meta-keywords'),
				'meta_description' => $this->l('Meta-description'),
				'link_rewrite' => $this->l('URL rewrited'),
				'available_now' => $this->l('Text when in-stock'),
				'available_later' => $this->l('Text if back-order allowed'),
				'image' => $this->l('Image URLs (x,y,z...)'),
				'feature' => $this->l('Feature'));

				self::$default_values = array(
				'id_category' => array(1),
				'id_category_default' => 1,
				'active' => '1',
				'quantity' => 0,
				'price' => 0,
				'id_tax' => 0,
				'description_short' => array(intval(Configuration::get('PS_LANG_DEFAULT')) => ''),
				'link_rewrite' => array(intval(Configuration::get('PS_LANG_DEFAULT')) => ''));

				break;

			case $this->entities[$this->l('Customers')]:

				//Overwrite required_fields AS only email is required whereas other entities
				self::$required_fields = array('email', 'passwd', 'lastname', 'firstname');

				$this->available_fields = array(
				'no' => $this->l('Ignore this column'),
				'id' => $this->l('ID'),
				'active' => $this->l('Active  (0/1)'),
				'id_gender' => $this->l('Gender ID (Mr = 1, Ms = 2, else 9)'),
				'email' => $this->l('E-mail *'),
				'passwd' => $this->l('Password *'),
				'birthday' => $this->l('Birthday (yyyy-mm-dd)'),
				'lastname' => $this->l('Lastname *'),
				'firstname' => $this->l('Firstname *'),
				'newsletter' => $this->l('Newsletter (0/1)'),
				'optin' => $this->l('Optin (0/1)'));

				self::$default_values = array('active' => '1');

			break;
			case $this->entities[$this->l('Addresses')]:

				//Overwrite required_fields
				self::$required_fields = array('lastname', 'firstname', 'address1', 'postcode', 'country', 'city');

				$this->available_fields = array(
				'no' => $this->l('Ignore this column'),
				'id' => $this->l('ID'),
				'alias' => $this->l('Alias *'),
				'active' => $this->l('Active  (0/1)'),
				'customer_email' => $this->l('Customer e-mail'),
				'manufacturer' => $this->l('Manufacturer'),
				'supplier' => $this->l('Supplier'),
				'company' => $this->l('Company'),
				'lastname' => $this->l('Lastname *'),
				'firstname' => $this->l('Firstname *'),
				'address1' => $this->l('Address 1 *'),
				'address2' => $this->l('Address 2'),
				'postcode' => $this->l('Postcode *'),
				'city' => $this->l('City *'),
				'country' => $this->l('Country *'),
				'state' => $this->l('State'),
				'other' => $this->l('Other'),
				'phone' => $this->l('Phone'),
				'phone_mobile' => $this->l('Mobile Phone'));

				self::$default_values = array('alias' => 'Alias', 'postcode' => 'X');

			break;
			case $this->entities[$this->l('Manufacturers')]:
			case $this->entities[$this->l('Suppliers')]:

				//Overwrite validators AS name is not MultiLangField
				self::$validators = array(
				'description' => array('zimportadmin', 'createMultiLangField'),
				'description_short' => array('zimportadmin', 'createMultiLangField'),
				'meta_title' => array('zimportadmin', 'createMultiLangField'),
				'meta_keywords' => array('zimportadmin', 'createMultiLangField'),
				'meta_description' => array('zimportadmin', 'createMultiLangField'));

				$this->available_fields = array(
				'no' => $this->l('Ignore this column'),
				'id' => $this->l('ID'),
				'name' => $this->l('Name *'),
				'description' => $this->l('Description'),
				'short_description' => $this->l('Short description'),
				'meta_title' => $this->l('Meta-title'),
				'meta_keywords' => $this->l('Meta-keywords'),
				'meta_description' => $this->l('Meta-description'));
			break;

			case $this->entities[$this->l('Comments')]:
				self::$required_fields = array('id_product', 'id_customer', 'content');

				$this->available_fields = array(
					'no' => $this->l('Ignore this column'),
					'id_product' => $this->l('Product ID').'*',
					'id_customer' => $this->l('Customer ID').'*',
					'content' => $this->l('Content').'*',
					'grade' => $this->l('Grade'),
					'validate' => $this->l('Validated'),
					'date_add' => $this->l('Date add')
				);

				self::$default_values = array(
					'grade' => 0.0,
					'validate' => 0,
					'date_add' => date("Y-m-d H:i:s")
				);
		}
		parent::__construct();
	}

	private static function getBoolean($field)
	{
		return (boolean)$field;
	}

	private static function getPrice($field)
	{
		$field = (floatval(str_replace(',', '.', $field)));
		$field = (floatval(str_replace('%', '', $field)));
		return $field;
	}

	private static function split($field)
	{
		$separator = ((is_null(Tools::getValue('multiple_value_separator')) OR trim(Tools::getValue('multiple_value_separator')) == '' ) ? ',' : Tools::getValue('multiple_value_separator'));
		$tab = explode($separator, $field);
		$res = array_map('strval', $tab);
		$res = array_map('trim', $tab);
		return $tab;
	}

	private static function createMultiLangField($field)
	{
		$languages = Language::getLanguages();
		$res = array();
		foreach ($languages AS $lang)
			$res[$lang['id_lang']] = $field;
		return $res;
	}

	private function getTypeValuesOptions($nb_c)
	{
		$i = 0;
		$noPreSelect = array('price_tin', 'feature');

		$options = '';
		foreach ($this->available_fields AS $k => $field)
		{
			$options .= '<option value="'.$k.'"';
			if ($k === 'price_tin')
				++$nb_c;
			if ($i === ($nb_c + 1) AND (!in_array($k, $noPreSelect)))
				$options .= ' selected="selected"';
			$options .= '>'.$field.'</option>';
			++$i;
		}
		return $options;
	}

	/*
	* Return fields to be display AS piece of advise
	*
	* @param $inArray boolean
	* @return string or return array
	*/
	public function getAvailableFields($inArray = false)
	{
		$i = 0;
		$fields = array();
		foreach ($this->available_fields AS $k => $field)
		{
			if ($k === 'no')
				continue;
			if ($k === 'price_tin')
			{
				$fields[$i-1] = $fields[$i-1].' '.$this->l('or').' '.$field;
			}
			else
				$fields[] = $field;
			++$i;
		}
		if ($inArray)
			return $fields;
		else
			return implode("\n\r", $fields);
	}

	private function receiveTab()
	{
		$type_value = Tools::getValue('type_value') ? Tools::getValue('type_value') : array();
		foreach ($type_value AS $nb => $type)
			if ($type != 'no')
				self::$column_mask[$type] = $nb;
	}

	public static function getMaskedRow($row)
	{
		$res = array();
		foreach (self::$column_mask AS $type => $nb)
			$res[$type] = isset($row[$nb]) ? $row[$nb] : null;
		return $res;
	}

	private static function setDefaultValues(&$info)
	{
		foreach (self::$default_values AS $k => $v)
			if (!isset($info[$k]) OR $info[$k] == '')
				$info[$k] = $v;
	}

	private static function setEntityDefaultValues(&$entity)
	{
		$members = get_object_vars($entity);
		foreach (self::$default_values AS $k => $v)
			if ((array_key_exists($k, $members) AND $entity->$k === NULL) OR !array_key_exists($k, $members))
				$entity->$k = $v;
	}

	private static function fillInfo($infos, $key, &$entity)
	{
		if (isset(self::$validators[$key][1]) && self::$validators[$key][1] == 'createMultiLangField' && Tools::getValue('iso_lang'))
		{
			$id_lang = Language::getIdByIso(Tools::getValue('iso_lang'));
			$tmp = call_user_func(self::$validators[$key], $infos);
			foreach ($tmp as $id_lang_tmp => $value)
				if (empty($entity->{$key}[$id_lang_tmp]) OR $id_lang_tmp == $id_lang)
					$entity->{$key}[$id_lang_tmp] = $value;
		}
		else
			$entity->{$key} = isset(self::$validators[$key]) ? call_user_func(self::$validators[$key], $infos) : $infos;
		return true;
	}

	public static function fgetcsv($handle, $lenght, $delimiter)
	{
		if (feof($handle))
			return false;
		$line = fgets($handle, $lenght);
		if ($line === false)
			return false;
		$tmpTab = explode($delimiter, $line);

		foreach ($tmpTab AS &$row)
			if (preg_match ('/^".*"$/Uims',$row))
				$row = trim($row, '"');
		return $tmpTab;
	}

	static public function array_walk(&$array, $funcname, &$user_data = false)
	{
		if (!is_callable($funcname)) return false;

		foreach ($array AS $k => $row)
			if (!call_user_func_array($funcname, array($row, $k, $user_data)))
				return false;
		return true;
	}

	private static function copyImg($id_entity, $id_image = NULL, $url, $entity = 'products')
	{
		$tmpfile = tempnam(_PS_TMP_IMG_DIR_, 'ps_import');
		$watermark_types = explode(',', Configuration::get('WATERMARK_TYPES'));

		switch($entity)
		{
			default:
			case 'products':
				$path = _PS_PROD_IMG_DIR_.intval($id_entity).'-'.intval($id_image);
			break;
			case 'categories':
				$path = _PS_CAT_IMG_DIR_.intval($id_entity);
			break;
		}

		if (@copy($url, $tmpfile))
		{
			imageResize($tmpfile, $path.'.jpg');
			$imagesTypes = ImageType::getImagesTypes($entity);
			foreach ($imagesTypes AS $k => $imageType)
				imageResize($tmpfile, $path.'-'.stripslashes($imageType['name']).'.jpg', $imageType['width'], $imageType['height']);
			if (in_array($imageType['id_image_type'], $watermark_types))
				Module::hookExec('watermark', array('id_image' => $id_image, 'id_product' => $id_entity));
		}
		else
		{
			unlink($tmpfile);
			return false;
		}
		unlink($tmpfile);
		return true;
	}

	public function categoryImport()
	{
		$catMoved = array();

		$this->receiveTab();
		$handle = $this->openCsvFile();
		$defaultLanguageId = intval(Configuration::get('PS_LANG_DEFAULT'));
		self::setLocale();
		for ($current_line = 0; $line = fgetcsv($handle, MAX_LINE_SIZE, Tools::getValue('separator')); $current_line++)
		{
			if (Tools::getValue('convert'))
				$this->utf8_encode_array($line);
			$info = self::getMaskedRow($line);

			self::setDefaultValues($info);
			$category = new Category();
			self::array_walk($info, array('zimportadmin', 'fillInfo'), $category);

			if (isset($category->parent) AND is_numeric($category->parent))
			{
				if (isset($catMoved[$category->parent]))
					$category->parent = $catMoved[$category->parent];
				$category->id_parent = $category->parent;
			}
			elseif (isset($category->parent) AND is_string($category->parent))
			{
				$categoryParent = Category::searchByName($defaultLanguageId, $category->parent, true);
				if ($categoryParent['id_category'])
					$category->id_parent =	intval($categoryParent['id_category']);
				else
				{
					$categoryToCreate= new Category();
					$categoryToCreate->name = self::createMultiLangField($category->parent);
					$categoryToCreate->active = 1;
					$categoryToCreate->id_parent = 1; // Default parent is home for unknown category to create
					if (($fieldError = $categoryToCreate->validateFields(UNFRIENDLY_ERROR, true)) === true AND ($langFieldError = $categoryToCreate->validateFieldsLang(UNFRIENDLY_ERROR, true)) === true AND $categoryToCreate->add())
						$category->id_parent = $categoryToCreate->id;
					else
					{
						$this->_errors[] = $categoryToCreate->name[$defaultLanguageId].(isset($categoryToCreate->id) ? ' ('.$categoryToCreate->id.')' : '').' '.Tools::displayError('cannot be saved');
						$this->_errors[] = ($fieldError !== true ? $fieldError : '').($langFieldError !== true ? $langFieldError : '').mysql_error();
					}
				}
			}

			if (isset($category->image) AND !empty($category->image))
				if (!(self::copyImg($category->id, NULL, $category->image, 'categories')))
					$this->_warnings[] = $category->image.' '.Tools::displayError('cannot be copied');
			if (isset($category->link_rewrite) AND !empty($category->link_rewrite[$defaultLanguageId]))
				$valid_link = Validate::isLinkRewrite($category->link_rewrite[$defaultLanguageId]);
			else
				$valid_link = false;

			$bak = $category->link_rewrite[$defaultLanguageId];
			if ((isset($category->link_rewrite) AND empty($category->link_rewrite[$defaultLanguageId])) OR !$valid_link)
			{
				$category->link_rewrite = Tools::link_rewrite(Category::hideCategoryPosition($category->name[$defaultLanguageId]));
				if ($category->link_rewrite == '')
				{
					$category->link_rewrite = 'friendly-url-autogeneration-failed';
					$this->_warnings[] = Tools::displayError('URL rewriting failed to auto-generate a friendly URL for: ').$category->name[$defaultLanguageId];
				}
				$category->link_rewrite = self::createMultiLangField($category->link_rewrite);
			}

			if (!$valid_link)
				$this->_warnings[] = Tools::displayError('Rewrited link for').' '.$bak.(isset($info['id']) ? ' (ID '.$info['id'].') ' : '').' '.Tools::displayError('was re-written as').' '.$category->link_rewrite[$defaultLanguageId];
			$res = false;
			if (($fieldError = $category->validateFields(UNFRIENDLY_ERROR, true)) === true AND ($langFieldError = $category->validateFieldsLang(UNFRIENDLY_ERROR, true)) === true)
			{
				$categoryAlreadyCreated = self::searchByNameAndParentCategoryId($defaultLanguageId, $category->name[$defaultLanguageId], $category->id_parent);

				// If category already in base, get id category back
				if ($categoryAlreadyCreated['id_category'])
				{
					$catMoved[$category->id] = intval($categoryAlreadyCreated['id_category']);
					$category->id =	intval($categoryAlreadyCreated['id_category']);
				}

				// If id category AND id category already in base, trying to update
				if ($category->id AND $category->categoryExists($category->id))
					$res = $category->update();

				// If no id_category or update failed
				if (!$res AND $res = $category->add())
					$category->addGroups(array(1));
			}
			// If both failed, mysql error
			if (!$res)
			{
				$this->_errors[] = $info['name'].(isset($info['id']) ? ' (ID '.$info['id'].')' : '').' '.Tools::displayError('cannot be saved');
				$this->_errors[] = ($fieldError !== true ? $fieldError : '').($langFieldError !== true ? $langFieldError : '').mysql_error();
			}
		}
		$this->closeCsvFile($handle);
	}

	public function productImport()
	{
		global $cookie;
		$this->receiveTab();
		$handle = $this->openCsvFile();
		$defaultLanguageId = intval(Configuration::get('PS_LANG_DEFAULT'));
		self::setLocale();
		for ($current_line = 0; $line = fgetcsv($handle, MAX_LINE_SIZE, Tools::getValue('separator')); $current_line++)
		{
			if (Tools::getValue('convert'))
				$this->utf8_encode_array($line);
			$info = self::getMaskedRow($line);

			if (Tools::getValue('identify_by_reference') && array_key_exists('reference', $info))
			{
				$id_product = (int)Db::getInstance()->getValue('SELECT p.`id_product` FROM `'._DB_PREFIX_.'product` p WHERE p.`reference` = "'.pSQL($info['reference']).'"');
				if ($id_product > 0)
					$info['id'] = $id_product;
			}
			
			// если не указан ни артикул, ни id, значит - импортирование нового товара
			if (array_key_exists('id', $info) && intval($info['id']) && Product::existsInDatabase(intval($info['id'])))
			{
				$product = new Product(intval($info['id']));
				
				$current_date = date('Y-m-d H:i:s');
				if ($product->reduction_from == '0000-00-00' || $product->reduction_from == '0000-00-00 00:00:00')
					$product->reduction_from = $current_date;
				if ($product->reduction_to == '0000-00-00' || $product->reduction_to == '0000-00-00 00:00:00')
					$product->reduction_to = $current_date;

				$category_data = Product::getIndexedCategories($product->id);
				foreach ($category_data as $item)
					$product->category[] = $item['id_category'];
			}
			else
				$product = new Product();

			// сохраняем текущее количество товара
			$quantity_old = (int)$product->quantity;

			// при режиме обновления, когда объект не существует - будет пропускать данные
			if (Tools::getValue('update_mode') && !$product->id)
				continue;

			// при режиме обновления не будем записывать значения по-умолчанию
			if (!Tools::getValue('update_mode') || !$product->id)
				self::setEntityDefaultValues($product);

			self::array_walk($info, array('zimportadmin', 'fillInfo'), $product);

			// обновляем остатки
			if (array_key_exists('quantity', $info) && Tools::getValue('update_remains'))
				$product->quantity = $quantity_old + (int)$info['quantity'];

			// Find id_tax corresponding to given values for product taxe
			if (isset($product->tax_rate))
				$product->id_tax = intval(Tax::getTaxIdByRate(floatval($product->tax_rate)));
			if (isset($product->tax_rate) AND !$product->id_tax)
			{
				$tax = new Tax();
				$tax->rate = floatval($product->tax_rate);
				$tax->name = self::createMultiLangField(strval($product->tax_rate));
				if (($fieldError = $tax->validateFields(UNFRIENDLY_ERROR, true)) === true AND ($langFieldError = $tax->validateFieldsLang(UNFRIENDLY_ERROR, true)) === true AND $tax->add())
					$product->id_tax = intval($tax->id);
				else
				{
					$this->_errors[] = 'TAX '.$tax->name[$defaultLanguageId].' '.Tools::displayError('cannot be saved');
					$this->_errors[] = ($fieldError !== true ? $fieldError : '').($langFieldError !== true ? $langFieldError : '').mysql_error();
				}
			}

			if (isset($product->manufacturer) AND is_numeric($product->manufacturer) AND Manufacturer::manufacturerExists(intval($product->manufacturer)))
				$product->id_manufacturer = intval($product->manufacturer);
			elseif (isset($product->manufacturer) AND is_string($product->manufacturer) AND !empty($product->manufacturer))
			{
				if ($manufacturer = Manufacturer::getIdByName($product->manufacturer))
					$product->id_manufacturer = intval($manufacturer);
				else
				{
					$manufacturer = new Manufacturer();
					$manufacturer->name = $product->manufacturer;
					if (($fieldError = $manufacturer->validateFields(UNFRIENDLY_ERROR, true)) === true AND ($langFieldError = $manufacturer->validateFieldsLang(UNFRIENDLY_ERROR, true)) === true AND $manufacturer->add())
						$product->id_manufacturer = intval($manufacturer->id);
					else
					{
						$this->_errors[] = $manufacturer->name.(isset($manufacturer->id) ? ' ('.$manufacturer->id.')' : '').' '.Tools::displayError('cannot be saved');
						$this->_errors[] = ($fieldError !== true ? $fieldError : '').($langFieldError !== true ? $langFieldError : '').mysql_error();
					}
				}
			}

			if (isset($product->supplier) AND is_numeric($product->supplier) AND Supplier::supplierExists(intval($product->supplier)))
				$product->id_supplier = intval($product->supplier);
			elseif (isset($product->supplier) AND is_string($product->supplier) AND !empty($product->supplier))
			{
				if ($supplier = Supplier::getIdByName($product->supplier))
					$product->id_supplier = intval($supplier);
				else
				{
					$supplier = new Supplier();
					$supplier->name = $product->supplier;
					if (($fieldError = $supplier->validateFields(UNFRIENDLY_ERROR, true)) === true AND ($langFieldError = $supplier->validateFieldsLang(UNFRIENDLY_ERROR, true)) === true AND $supplier->add())
						$product->id_supplier = intval($supplier->id);
					else
					{
						$this->_errors[] = $supplier->name.(isset($supplier->id) ? ' ('.$supplier->id.')' : '').' '.Tools::displayError('cannot be saved');
						$this->_errors[] = ($fieldError !== true ? $fieldError : '').($langFieldError !== true ? $langFieldError : '').mysql_error();
					}
				}
			}

			if (isset($product->price_tex) AND !isset($product->price_tin))
				$product->price = $product->price_tex;
			elseif (isset($product->price_tin) AND !isset($product->price_tex))
			{
				$product->price = $product->price_tin;
				// If a tax is already included in price, withdraw it from price
				if ($product->tax_rate)
					$product->price = floatval(number_format($product->price / (1 + $product->tax_rate / 100), 6));
			}
			elseif (isset($product->price_tin) AND isset($product->price_tex))
				$product->price = $product->price_tex;

			if (isset($product->category) AND is_array($product->category) and sizeof($product->category))
			{
				$product->id_category = array(); // Reset default values array

				foreach ($product->category AS $value)
				{
					if (is_numeric($value))
					{
						if (Category::categoryExists(intval($value)))
							$product->id_category[] = intval($value);
						elseif ($value == 0)
							$product->id_category[] = 1;
						else
						{
							$categoryToCreate= new Category();
							$categoryToCreate->id = intval($value);
							$categoryToCreate->name = self::createMultiLangField($value);
							$categoryToCreate->active = 1;
							$categoryToCreate->id_parent = 1; // Default parent is home for unknown category to create
							if (($fieldError = $categoryToCreate->validateFields(UNFRIENDLY_ERROR, true)) === true AND ($langFieldError = $categoryToCreate->validateFieldsLang(UNFRIENDLY_ERROR, true)) === true AND $categoryToCreate->add())
								$product->id_category[] = intval($categoryToCreate->id);
							else
							{
								$this->_errors[] = $categoryToCreate->name[$defaultLanguageId].(isset($categoryToCreate->id) ? ' ('.$categoryToCreate->id.')' : '').' '.Tools::displayError('cannot be saved');
								$this->_errors[] = ($fieldError !== true ? $fieldError : '').($langFieldError !== true ? $langFieldError : '').mysql_error();
							}
						}
					}
					elseif (is_string($value) AND !empty($value))
					{
						$category = Category::searchByName($defaultLanguageId, $value, true);
						if ($category['id_category'])
						{
							$product->id_category[] =	intval($category['id_category']);
						}
						else
						{
							$categoryToCreate= new Category();
							$categoryToCreate->name = self::createMultiLangField($value);
							$categoryToCreate->active = 1;
							$categoryToCreate->id_parent = 1; // Default parent is home for unknown category to create
							if (($fieldError = $categoryToCreate->validateFields(UNFRIENDLY_ERROR, true)) === true AND ($langFieldError = $categoryToCreate->validateFieldsLang(UNFRIENDLY_ERROR, true)) === true AND $categoryToCreate->add())
								$product->id_category[] = intval($categoryToCreate->id);
							else
							{
								$this->_errors[] = $categoryToCreate->name[$defaultLanguageId].(isset($categoryToCreate->id) ? ' ('.$categoryToCreate->id.')' : '').' '.Tools::displayError('cannot be saved');
								$this->_errors[] = ($fieldError !== true ? $fieldError : '').($langFieldError !== true ? $langFieldError : '').mysql_error();
							}
						}
					}
					elseif (is_string($value) AND empty($value))
						$product->id_category[] = 1;
				}
			}

			$product->id_category_default = isset($product->id_category[0]) ? intval($product->id_category[0]) : '';
			$link_rewrite = is_array($product->link_rewrite) ? $product->link_rewrite[$defaultLanguageId] : '';
			$valid_link = Validate::isLinkRewrite($link_rewrite);

			$bak = $product->link_rewrite;
			if ((isset($product->link_rewrite[$defaultLanguageId]) AND empty($product->link_rewrite[$defaultLanguageId])) OR !$valid_link)
			{
				$link_rewrite = Tools::link_rewrite($product->name[$defaultLanguageId]);
				if ($link_rewrite == '')
					$link_rewrite = 'friendly-url-autogeneration-failed';
			}
			if (!$valid_link)
				$this->_warnings[] = Tools::displayError('Rewrited link for'). ' '.$bak.(isset($info['id']) ? ' (ID '.$info['id'].') ' : '').' '.Tools::displayError('was re-written as').' '.$link_rewrite;

			$product->link_rewrite = self::createMultiLangField($link_rewrite);

			$res = false;
			$fieldError = $product->validateFields(UNFRIENDLY_ERROR, true);
			$langFieldError = $product->validateFieldsLang(UNFRIENDLY_ERROR, true);
			if ($fieldError === true AND $langFieldError === true)
			{
				// check quantity
				if ($product->quantity == NULL)
					$product->quantity = 0;
				// If id product AND id product already in base, trying to update
				if ($product->id AND Product::existsInDatabase(intval($product->id)))
				{

					$datas = Db::getInstance()->getRow('SELECT `date_add` FROM `'._DB_PREFIX_.'product` WHERE `id_product` = '.intval($product->id));
					$product->date_add = pSQL($datas['date_add']);
					$res = $product->update();
				}
				// If no id_product or update failed
				if (!$res)
					$res = $product->add();
			}
			// If both failed, mysql error
			if (!$res)
			{
				$this->_errors[] = $info['name'].(isset($info['id']) ? ' (ID '.$info['id'].')' : '').' '.Tools::displayError('cannot be saved');
				$this->_errors[] = ($fieldError !== true ? $fieldError : '').($langFieldError !== true ? $langFieldError : '').mysql_error();

			}
			else
			{
				if (isset($product->tags) AND !empty($product->tags))
				{
					// Delete tags for this id product, for no duplicating error
					Tag::deleteTagsForProduct($product->id);

					$tag = new Tag();
					if (!is_array($product->tags))
					{
						$product->tags = self::createMultiLangField($product->tags);
						foreach($product->tags AS $key => $tags)
						{
							$isTagAdded = $tag->addTags($key, $product->id, $tags);
							if (!$isTagAdded)
							{
								$this->_addProductWarning($info['name'], $product->id, $this->l('Tags list').' '.$this->l('is invalid'));
								break;
							}
						}

					}
					else
					{
						foreach ($product->tags AS $key => $tags)
						{
							$str = '';
							foreach($tags AS $one_tag)
								$str .= $one_tag.',';
							$str = rtrim($str, ',');

							$isTagAdded = $tag->addTags($key, $product->id, $str);
							if (!$isTagAdded)
							{
								$this->_addProductWarning($info['name'], $product->id,'Invalid tag(s) ('.$str.')');
								break;
							}
						}
					}
				}

				if (isset($product->image) AND is_array($product->image) and sizeof($product->image))
				{
					$productHasImages = (bool)Image::getImages(intval($cookie->id_lang), intval($product->id));
					foreach ($product->image AS $key => $url)
						if (!empty($url))
						{
							$image = new Image();
							$image->id_product = intval($product->id);
							$image->position = Image::getHighestPosition($product->id) + 1;
							$image->cover = (!$key AND !$productHasImages) ? true : false;
							$image->legend = self::createMultiLangField($product->name[$defaultLanguageId]);
							if (($fieldError = $image->validateFields(UNFRIENDLY_ERROR, true)) === true AND ($langFieldError = $image->validateFieldsLang(UNFRIENDLY_ERROR, true)) === true AND $image->add())
							{
								if (!self::copyImg($product->id, $image->id, $url))
									$this->_warnings[] = Tools::displayError('Error copying image: ').$url;
							}
							else
							{
								$this->_warnings[] = $image->legend[$defaultLanguageId].(isset($image->id_product) ? ' ('.$image->id_product.')' : '').' '.Tools::displayError('cannot be saved');
								$this->_errors[] = ($fieldError !== true ? $fieldError : '').($langFieldError !== true ? $langFieldError : '').mysql_error();
							}
						}
				}
				if (isset($product->id_category))
					$product->updateCategories(array_map('intval', $product->id_category));

				$features = get_object_vars($product);
				foreach ($features AS $feature => $value)
					if (!strncmp($feature, '#F_', 3) AND Tools::strlen($product->{$feature}))
					{
						$feature_name = str_replace('#F_', '', $feature);
						$id_feature = Feature::addFeatureImport($feature_name);
						$id_feature_value = FeatureValue::addFeatureValueImport($id_feature, $product->{$feature});
						Product::addFeatureProductImport($product->id, $id_feature, $id_feature_value);
					}
			}
		}
		$this->closeCsvFile($handle);
	}

	/**
	 * получить id комбинации по ее артикулу
	 *
	 * @param string $reference
	 * @return int вернет id комбинации или 0, если она не существует
	 */
	public static function getCombinationIdByRef($reference)
	{
		if (empty($reference))
			return 0;
		
		return (int)Db::getInstance()->getValue('SELECT `id_product_attribute` FROM `'._DB_PREFIX_.'product_attribute` WHERE `reference` = "'.pSQL($reference).'"');
	}

	/**
	 * обновить комбинацию
	 *
	 * позаимствовал у PS 1.4.11.0 с небольшими изменениями
	 *
	 * @return bool|int false при ошибке обновления или id комбинации, которая была обновлена
	 */
	public static function updateCombinationEntity($product, $id_product_attribute, $wholesale_price, $price, $weight, $ecotax, $quantity, $id_images, $reference, $supplier_reference, $ean13, $default, $location = null)
	{
		if (!$id_product_attribute = $product->updateProductAttribute($id_product_attribute, '', $price, $weight, $ecotax, $quantity, $id_images, $reference, $supplier_reference, $ean13, $default, $location)
			OR !Db::getInstance()->Execute('UPDATE `'._DB_PREFIX_.'product_attribute` SET `wholesale_price` = '.(float)($wholesale_price).' WHERE `id_product_attribute` = '.(int)($id_product_attribute))
		)
			return false;
		return (int)($id_product_attribute);
	}

	/**
	 * получить атрибуты комбинации
	 *
	 * @param int $id_product_attribute
	 * @param int $id_lang
	 * @return array
	 */
	public static function getCombinationAttributes($id_product_attribute, $id_lang)
	{
		return Db::getInstance()->executeS('
			SELECT agl.`name` AS group_name, al.`name` AS attribute_name
			FROM `'._DB_PREFIX_.'product_attribute` pa
			LEFT JOIN `'._DB_PREFIX_.'product_attribute_combination` pac ON pac.`id_product_attribute` = pa.`id_product_attribute`
			LEFT JOIN `'._DB_PREFIX_.'attribute` a ON a.`id_attribute` = pac.`id_attribute`
			LEFT JOIN `'._DB_PREFIX_.'attribute_group` ag ON ag.`id_attribute_group` = a.`id_attribute_group`
			LEFT JOIN `'._DB_PREFIX_.'attribute_lang` al ON (a.`id_attribute` = al.`id_attribute` AND al.`id_lang` = '.intval($id_lang).')
			LEFT JOIN `'._DB_PREFIX_.'attribute_group_lang` agl ON (ag.`id_attribute_group` = agl.`id_attribute_group` AND agl.`id_lang` = '.intval($id_lang).')
			WHERE pa.`id_product_attribute` = '.intval($id_product_attribute)
		);
	}

	/**
	 * получить комбинацию
	 *
	 * @param int $id_product_attribute
	 * @return array
	 */
	public static function getCombination($id_product_attribute)
	{
		return Db::getInstance()->getRow('SELECT pa.* FROM `'._DB_PREFIX_.'product_attribute` pa WHERE pa.`id_product_attribute` = '.(int)$id_product_attribute);
	}

	/**
	 * получить id товара по его артикулу
	 * 
	 * @param string $reference
	 * @return int
	 */
	public static function getProductIdByRef($reference)
	{
		if (empty($reference))
			return 0;
		
		return (int)Db::getInstance()->getValue('SELECT p.`id_product` FROM `'._DB_PREFIX_.'product` p WHERE p.`reference` = "'.pSQL($reference).'"');
	}

	/**
	 * получить id товара по id комбинации
	 * 
	 * @param int $id_product_attribute
	 * @return int
	 */
	public static function getProductIdByCombinationId($id_product_attribute)
	{
		return (int)Db::getInstance()->getValue('SELECT pa.`id_product` FROM `'._DB_PREFIX_.'product_attribute` pa WHERE pa.`id_product_attribute` = '.(int)$id_product_attribute);
	}

	/**
	 * импортировать атрибуты комбинаций
	 *
	 * идентификация комбинаций производится только по артикулу
	 * позаимствовал у PS 1.4.11.0, немного переписал под PS 1.3
	 *
	 * @return int число импортированных комбинаций
	 */
	public function attributeImport()
	{
		global $cookie;

		$defaultLanguageId = intval(Configuration::get('PS_LANG_DEFAULT'));

		$groups = array();
		foreach (AttributeGroup::getAttributesGroups($defaultLanguageId) as $group)
			$groups[strtolower($group['name'])] = (int)$group['id_attribute_group'];

		$attributes = array();
		foreach (Attribute::getAttributes($defaultLanguageId) as $attribute)
			$attributes[strtolower($attribute['attribute_group'].'_'.$attribute['name'])] = (int)($attribute['id_attribute']);

		$this->receiveTab();
		$handle = $this->openCsvFile();
		$fsep = ((is_null(Tools::getValue('multiple_value_separator')) OR trim(Tools::getValue('multiple_value_separator')) == '' ) ? ',' : Tools::getValue('multiple_value_separator'));
		$option_glue = Tools::getValue('option_glue', ':');
		self::setLocale();
		for ($current_line = 0, $lines_ok = 0; $line = fgetcsv($handle, MAX_LINE_SIZE, Tools::getValue('separator')); $current_line++)
		{
			if (Tools::getValue('convert'))
				$this->utf8_encode_array($line);

			$info = array_map('trim', self::getMaskedRow($line));

			// получим id комбинации по артикулу
			$id_product_attribute = array_key_exists('reference', $info) ? self::getCombinationIdByRef($info['reference']) : 0;

			// при режиме обновления, когда объект не существует - будет пропускать данные
			if (Tools::getValue('update_mode') && !$id_product_attribute)
				continue;

			// при режиме обновления запишем в info данные комбинации, иначе запишем значения по-умолчанию
			if (Tools::getValue('update_mode') && $id_product_attribute > 0)
			{
				$combination = self::getCombination($id_product_attribute);
				foreach ($combination as $key => $val)
					if (!isset($info[$key]))
						$info[$key] = $val;
			}
			else
				self::setDefaultValues($info);
			
			// определяем товар, к которому относится комбинация
			if (Tools::getValue('identify_by_reference') && array_key_exists('product_reference', $info))
				$info['id_product'] = self::getProductIdByRef($info['product_reference']);
			
			// если id товара неопределен, то попробуем его получить по комбинации
			if ((!array_key_exists('id_product', $info) || !$info['id_product']) && $id_product_attribute > 0)
				$info['id_product'] = self::getProductIdByCombinationId($id_product_attribute);

			if (array_key_exists('id_product', $info) && $info['id_product'] > 0 && Product::existsInDatabase($info['id_product']))
				$product = new Product((int)$info['id_product'], false, $defaultLanguageId);
			else
			{
				$this->_errors[] = sprintf(Tools::displayError('Product ID or product reference or combination reference are empty or product is not exists, line: %d.'), $current_line);
				continue;
			}

			// @todo эта возможность загрузки картинок для комбинаций не используется (скопировано из PS1.4, но не доделано - нет таких полей)
			$id_images = null;
			if (isset($info['image_url']) && $info['image_url'])
			{
				$productHasImages = (bool)Image::getImages((int)($cookie->id_lang), (int)($product->id));
				$url = $info['image_url'];
				$image = new Image();
				$image->id_product = (int)($product->id);
				$image->position = Image::getHighestPosition($product->id) + 1;
				$image->cover = (!$productHasImages) ? true : false;
				$image->legend = self::createMultiLangField($product->name);

				if (($fieldError = $image->validateFields(UNFRIENDLY_ERROR, true)) === true AND ($langFieldError = $image->validateFieldsLang(UNFRIENDLY_ERROR, true)) === true AND $image->add())
				{
					if (!self::copyImg($product->id, $image->id, $url))
						$this->_warnings[] = Tools::displayError('Error copying image: ').$url;
					else
						$id_images = array($image->id);
				}
				else
				{
					$this->_warnings[] = $image->legend[$defaultLanguageId].(isset($image->id_product) ? ' ('.$image->id_product.')' : '').' '.Tools::displayError('Cannot be saved');
					$this->_errors[] = ($fieldError !== true ? $fieldError : '').($langFieldError !== true ? $langFieldError : '').Db::getInstance()->getMsgError();
				}
			}
			elseif (isset($info['image_position']) && $info['image_position'])
			{
				$images = $product->getImages($defaultLanguageId);

				if ($images)
					foreach ($images as $row)
						if ($row['position'] == (int)$info['image_position'])
						{
							$id_images = array($row['id_image']);
							break;
						}
				if (!$id_images)
					$this->_warnings[] = sprintf(Tools::displayError('No image found for combination with id_product = %s and image position = %s.'), $product->id, (int)$info['image_position']);
			}

			// если не указывались картинки для загрузки, то получим массив существующих картинок
			if (!$id_images)
				$id_images = Product::_getAttributeImageAssociations($id_product_attribute);

			$info['ecotax'] = str_replace(',', '.', $info['ecotax']);
			$info['weight'] = str_replace(',', '.', $info['weight']);

			if (!array_key_exists('quantity', $info))
				$info['quantity'] = 0;

			if (empty($info['options']) && $id_product_attribute == 0)
			{
				$this->_errors[] = sprintf(Tools::displayError('To add a new product combination options should be set, line: %d.'), $current_line);
				continue;
			}

			// если не указаны опции, то нужно их сформировать, т.к. всегда они (комбинации) импортируются заново
			if (empty($info['options']) && $id_product_attribute > 0)
			{
				$combs = self::getCombinationAttributes($id_product_attribute, $defaultLanguageId);
				if (empty($combs))
				{
					// будет странно, если появится такая ошибка, т.к. существует запись о комбинации, но без набора атрибутов
					$this->_errors[] = sprintf(Tools::displayError('Options for an existent product combination should be set, line: %d.'), $current_line);
					continue;
				}

				$options = array();
				foreach ($combs as $comb)
					$options[] = $comb['group_name'].$option_glue.$comb['attribute_name'];
				
				$info['options'] = implode($fsep, $options);
			}

			if ($id_product_attribute > 0)
			{
				if (Tools::getValue('update_remains'))
					$info['quantity'] = (int)$info['quantity'] + Product::getQuantity($product->id, $id_product_attribute);

				self::updateCombinationEntity($product, $id_product_attribute, (float)$info['wholesale_price'], (float)$info['price'], (float)$info['weight'], (float)$info['ecotax'], (int)$info['quantity'], $id_images, $info['reference'], 0, $info['ean13'], (int)$info['default_on'], null);
			}
			else
				$id_product_attribute = $product->addCombinationEntity((float)$info['wholesale_price'], (float)$info['price'], (float)$info['weight'], (float)$info['ecotax'], (int)$info['quantity'], $id_images, $info['reference'], 0, $info['ean13'], (int)$info['default_on'], null);

			if ($id_product_attribute)
				$lines_ok++;

			// импортируем атрибуты комбинации (вставка производится всегда, даже когда производится обновление комбинации)
			foreach (explode($fsep, $info['options']) as $option)
			{
				list($group, $attribute) = array_map('strtolower', array_map('trim', explode($option_glue, $option)));
				if (!isset($groups[$group]))
				{
					$obj = new AttributeGroup();
					$obj->is_color_group = false;
					$obj->name[$defaultLanguageId] = $group;
					$obj->public_name[$defaultLanguageId] = $group;
					if (($fieldError = $obj->validateFields(UNFRIENDLY_ERROR, true)) === true AND ($langFieldError = $obj->validateFieldsLang(UNFRIENDLY_ERROR, true)) === true)
					{
						$obj->add();
						$groups[$group] = $obj->id;
					}
					else
						$this->_errors[] = ($fieldError !== true ? $fieldError : '').($langFieldError !== true ? $langFieldError : '');
				}
				if (!isset($attributes[$group.'_'.$attribute]))
				{
					$obj = new Attribute();
					$obj->id_attribute_group = $groups[$group];
					$obj->name[$defaultLanguageId] = str_replace(array('\n','\r') , '', $attribute);
					if (($fieldError = $obj->validateFields(UNFRIENDLY_ERROR, true)) === true AND ($langFieldError = $obj->validateFieldsLang(UNFRIENDLY_ERROR, true)) === true)
					{
						$obj->add();
						$attributes[$group.'_'.$attribute] = $obj->id;
					}
					else
						$this->_errors[] = ($fieldError !== true ? $fieldError : '').($langFieldError !== true ? $langFieldError : '');
				}

				Db::getInstance()->Execute('INSERT INTO '._DB_PREFIX_.'product_attribute_combination (id_attribute, id_product_attribute) VALUES ('.(int)$attributes[$group.'_'.$attribute].','.(int)$id_product_attribute.')');
			}
		}
		$this->closeCsvFile($handle);

		return (int)$lines_ok;
	}

	public function customerImport()
	{
		$this->receiveTab();
		$handle = $this->openCsvFile();
		self::setLocale();
		for ($current_line = 0; $line = fgetcsv($handle, MAX_LINE_SIZE, Tools::getValue('separator')); $current_line++)
		{
			if (Tools::getValue('convert'))
				$this->utf8_encode_array($line);
			$info = self::getMaskedRow($line);

			self::setDefaultValues($info);
			$customer = new Customer();
			self::array_walk($info, array('zimportadmin', 'fillInfo'), $customer);

			if ($customer->passwd)
				$customer->passwd = md5(_COOKIE_KEY_.$customer->passwd);

			$res = false;
			if (($fieldError = $customer->validateFields(UNFRIENDLY_ERROR, true)) === true AND ($langFieldError = $customer->validateFieldsLang(UNFRIENDLY_ERROR, true)) === true)
			{
				if ($customer->id AND $customer->customerIdExists($customer->id))
					$res = $customer->update();
				if (!$res)
					$res = $customer->add();
				if ($res)
					$customer->addGroups(array(1));
			}
			if (!$res)
			{
				$this->_errors[] = $info['email'].(isset($info['id']) ? ' (ID '.$info['id'].')' : '').' '.Tools::displayError('cannot be saved');
				$this->_errors[] = ($fieldError !== true ? $fieldError : ($langFieldError !== true ? $langFieldError : '')).mysql_error();
			}
		}
		$this->closeCsvFile($handle);
	}

	public function addressImport()
	{
		$this->receiveTab();
		$handle = $this->openCsvFile();
		self::setLocale();
		$defaultLanguageId = Configuration::get('PS_LANG_DEFAULT');
		for ($current_line = 0; $line = fgetcsv($handle, MAX_LINE_SIZE, Tools::getValue('separator')); $current_line++)
		{
			if (Tools::getValue('convert'))
				$this->utf8_encode_array($line);
			$info = self::getMaskedRow($line);

			self::setDefaultValues($info);
			$address = new Address();
			self::array_walk($info, array('zimportadmin', 'fillInfo'), $address);

			if (isset($address->country) AND is_numeric($address->country))
			{
				if (Country::getNameById($defaultLanguageId, intval($address->country)))
					$address->id_country = intval($address->country);
			}
			elseif(isset($address->country) AND is_string($address->country) AND !empty($address->country))
			{
				if ($id_country = Country::getIdByName(NULL, $address->country))
					$address->id_country = intval($id_country);
				else
				{
					$country = new Country();
					$country->active = 1;
					$country->name = self::createMultiLangField($address->country);
					$country->id_zone = 0; // Default zone for country to create
					$country->iso_code = strtoupper(substr($address->country, 0, 2)); // Default iso for country to create
					$country->contains_states = 0; // Default value for country to create
					if (($fieldError = $country->validateFields(UNFRIENDLY_ERROR, true)) === true AND ($langFieldError = $country->validateFieldsLang(UNFRIENDLY_ERROR, true)) === true AND $country->add())
						$address->id_country = intval($country->id);
					else
					{
						$this->_errors[] = $country->name[$defaultLanguageId].' '.Tools::displayError('cannot be saved');
						$this->_errors[] = ($fieldError !== true ? $fieldError : '').($langFieldError !== true ? $langFieldError : '').mysql_error();
					}
				}
			}

			if (isset($address->state) AND is_numeric($address->state))
			{
				if (State::getNameById(intval($address->state)))
					$address->id_state = intval($address->state);
			}
			elseif(isset($address->state) AND is_string($address->state) AND !empty($address->state))
			{
				if ($id_state = State::getIdByName($address->state))
					$address->id_state = intval($id_state);
				else
				{
					$state = new State();
					$state->active = 1;
					$state->name = $address->state;
					$state->id_country = isset($country->id) ? intval($country->id) : 0;
					$state->id_zone = 0; // Default zone for state to create
					$state->iso_code = strtoupper(substr($address->state, 0, 2)); // Default iso for state to create
					$state->tax_behavior = 0;
					if (($fieldError = $state->validateFields(UNFRIENDLY_ERROR, true)) === true AND ($langFieldError = $state->validateFieldsLang(UNFRIENDLY_ERROR, true)) === true AND $state->add())
						$address->id_state = intval($state->id);
					else
					{
						$this->_errors[] = $state->name.' '.Tools::displayError('cannot be saved');
						$this->_errors[] = ($fieldError !== true ? $fieldError : '').($langFieldError !== true ? $langFieldError : '').mysql_error();
					}
				}
			}

			if(isset($address->customer_email) and !empty($address->customer_email))
			{
				$customer = Customer::customerExists($address->customer_email, true);
				if ($customer)
					$address->id_customer = intval($customer);
				else
					$this->_errors[] = mysql_error().' '.$address->customer_email.' '.Tools::displayError('does not exist in base').' '.(isset($info['id']) ? ' (ID '.$info['id'].')' : '').' '.Tools::displayError('cannot be saved');
			}

			if (isset($address->manufacturer) AND is_numeric($address->manufacturer) AND Manufacturer::manufacturerExists(intval($address->manufacturer)))
				$address->id_manufacturer = intval($address->manufacturer);
			elseif (isset($address->manufacturer) AND is_string($address->manufacturer) AND !empty($address->manufacturer))
			{
				$manufacturer = new Manufacturer();
				$manufacturer->name = $address->manufacturer;
				if (($fieldError = $manufacturer->validateFields(UNFRIENDLY_ERROR, true)) === true AND ($langFieldError = $manufacturer->validateFieldsLang(UNFRIENDLY_ERROR, true)) === true AND $manufacturer->add())
					$address->id_manufacturer = intval($manufacturer->id);
				else
				{
					$this->_errors[] = mysql_error().' '.$manufacturer->name.(isset($manufacturer->id) ? ' ('.$manufacturer->id.')' : '').' '.Tools::displayError('cannot be saved');
					$this->_errors[] = ($fieldError !== true ? $fieldError : '').($langFieldError !== true ? $langFieldError : '').mysql_error();
				}
			}

			if (isset($address->supplier) AND is_numeric($address->supplier) AND Supplier::supplierExists(intval($address->supplier)))
				$address->id_supplier = intval($address->supplier);
			elseif (isset($address->supplier) AND is_string($address->supplier) AND !empty($address->supplier))
			{
				$supplier = new Supplier();
				$supplier->name = $address->supplier;
				if (($fieldError = $supplier->validateFields(UNFRIENDLY_ERROR, true)) === true AND ($langFieldError = $supplier->validateFieldsLang(UNFRIENDLY_ERROR, true)) === true AND $supplier->add())
					$address->id_supplier = intval($supplier->id);
				else
				{
					$this->_errors[] = mysql_error().' '.$supplier->name.(isset($supplier->id) ? ' ('.$supplier->id.')' : '').' '.Tools::displayError('cannot be saved');
					$this->_errors[] = ($fieldError !== true ? $fieldError : '').($langFieldError !== true ? $langFieldError : '').mysql_error();
				}
			}

			$res = false;
			if (($fieldError = $address->validateFields(UNFRIENDLY_ERROR, true)) === true AND ($langFieldError = $address->validateFieldsLang(UNFRIENDLY_ERROR, true)) === true)
			{
				if ($address->id AND $address->addressExists($address->id))
					$res = $address->update();
				if (!$res)
					$res = $address->add();
			}
			if (!$res)
			{
				$this->_errors[] = $info['alias'].(isset($info['id']) ? ' (ID '.$info['id'].')' : '').' '.Tools::displayError('cannot be saved');
				$this->_errors[] = ($fieldError !== true ? $fieldError : '').($langFieldError !== true ? $langFieldError : '').mysql_error();
			}
		}
		$this->closeCsvFile($handle);
	}

	public function manufacturerImport()
	{
		$this->receiveTab();
		$handle = $this->openCsvFile();
		self::setLocale();
		for ($current_line = 0; $line = fgetcsv($handle, MAX_LINE_SIZE, Tools::getValue('separator')); $current_line++)
		{
			if (Tools::getValue('convert'))
				$this->utf8_encode_array($line);
			$info = self::getMaskedRow($line);

			self::setDefaultValues($info);
			$manufacturer = new Manufacturer();
			self::array_walk($info, array('zimportadmin', 'fillInfo'), $manufacturer);

			$res = false;
			if (($fieldError = $manufacturer->validateFields(UNFRIENDLY_ERROR, true)) === true AND ($langFieldError = $manufacturer->validateFieldsLang(UNFRIENDLY_ERROR, true)) === true)
			{
				if ($manufacturer->id AND $manufacturer->manufacturerExists($manufacturer->id))
					$res = $manufacturer->update();
				if (!$res)
					$res = $manufacturer->add();
			}
			if (!$res)
			{
				$this->_errors[] = mysql_error().' '.$info['name'].(isset($info['id']) ? ' (ID '.$info['id'].')' : '').' '.Tools::displayError('cannot be saved');
				$this->_errors[] = ($fieldError !== true ? $fieldError : '').($langFieldError !== true ? $langFieldError : '').mysql_error();
			}
		}
		$this->closeCsvFile($handle);
	}

	public function supplierImport()
	{
		$this->receiveTab();
		$handle = $this->openCsvFile();
		self::setLocale();
		for ($current_line = 0; $line = fgetcsv($handle, MAX_LINE_SIZE, Tools::getValue('separator')); $current_line++)
		{
			if (Tools::getValue('convert'))
				$this->utf8_encode_array($line);
			$info = self::getMaskedRow($line);

			self::setDefaultValues($info);
			$supplier = new Supplier();
			self::array_walk($info, array('zimportadmin', 'fillInfo'), $supplier);

			if (($fieldError = $supplier->validateFields(UNFRIENDLY_ERROR, true)) === true AND ($langFieldError = $supplier->validateFieldsLang(UNFRIENDLY_ERROR, true)) === true)
			{
				$res = false;
				if ($supplier->id AND $supplier->supplierExists($supplier->id))
					$res = $supplier->update();
				if (!$res)
					$res = $supplier->add();
				if (!$res)
					$this->_errors[] = mysql_error().' '.$info['name'].(isset($info['id']) ? ' (ID '.$info['id'].')' : '').' '.Tools::displayError('cannot be saved');
			}
			else
			{
				$this->_errors[] = $this->l('Supplier not valid').' ('.$supplier->name.')';
				$this->_errors[] = ($fieldError !== true ? $fieldError : '').($langFieldError !== true ? $langFieldError : '');
			}
		}
		$this->closeCsvFile($handle);
	}

	public function CommentImport()
	{
		$this->receiveTab();
		$handle = $this->openCsvFile();
		self::setLocale();
		for ($current_line = 0; $line = fgetcsv($handle, MAX_LINE_SIZE, Tools::getValue('separator')); $current_line++)
		{
			if (Tools::getValue('convert'))
				$this->utf8_encode_array($line);
			$info = self::getMaskedRow($line);

			self::setDefaultValues($info);
			$comment = new Comment();
			self::array_walk($info, array('zimportadmin', 'fillInfo'), $comment);

			if (($fieldError = $comment->validateFields(UNFRIENDLY_ERROR, true)) === true AND ($langFieldError = $comment->validateFieldsLang(UNFRIENDLY_ERROR, true)) === true)
			{
				$res = false;
				if ($comment->id AND Comment::isCommentExists($comment->id))
					$res = $comment->update();
				if (!$res)
					$res = $comment->add();
				if (!$res)
					$this->_errors[] = mysql_error().' '.$info['content'].(isset($info['id_product_comments']) ? ' (ID '.$info['id_product_comments'].')' : '').' '.Tools::displayError('cannot be saved');
			}
			else{
				$this->_errors[] = $this->l('comment not valid').' ('.$comment->content.')';
				$this->_errors[] = ($fieldError !== true ? $fieldError : '').($langFieldError !== true ? $langFieldError : '');
			}
		}
		$this->closeCsvFile($handle);
	}

	public function display()
	{
		if (!Tools::isSubmit('submitImportFile'))
			$this->displayForm();
	}

	public function displayForm($isMainTab = true)
	{
		global $currentIndex, $cookie;

		@unlink($this->import_dir.$this->tmp_csv);

		parent::displayForm();

		if ((Tools::getValue('import')))
			echo '<div class="module_confirmation conf confirm"><img src="../img/admin/ok.gif" alt="" title="" style="margin-right:5px; float:left;" />'.$this->l('The .CSV file has been imported into your shop.').'</div>';

		if(!is_writable(PS_ADMIN_DIR.'/import/'))
			$this->displayWarning($this->l('dir import on admin dir must be writable (CHMOD 777)'));

		if(isset($this->_warnings) AND sizeof($this->_warnings))
		{
			$warnings = '';
			foreach ($this->_warnings as $warning)
				$warnings .= $warning.'<br />';
			$this->displayWarning($warnings);
		}

		echo '
			<style>
				div.warn {
					background-color: #FFFAC6;
					border: 1px solid #D3C200;
					color: #383838;
					line-height: 20px;
					margin: 0 0 10px;
					padding: 10px 15px;
				}
			</style>

			<script type="text/javascript">
			// <![CDATA[
				var params_show_st;
				$("document").ready( function(){
					if(is_csv()) {
						params_show_st = true;
					}
					else {
						params_show_st = false;
						$("#csv_param").toggle("slow");
					}
				});
				function is_csv() {
					return $("#csv").val().search(".csv") != -1;
				}
				function ch_params_show_st(v) {
					if( is_csv() == false) {
						if(params_show_st == true) {
							params_show_st = false;
							$("#csv_param").toggle("slow");
						}
					}
					else {
						if(params_show_st == false) {
							params_show_st = true;
							$("#csv_param").toggle("slow");
						}
					}
				}
			//]]>
			</script>
		';

		echo '
		<h2>'.$this->l('Enhanced import tool').'</h2>
		<div style="float: left;">
			<fieldset style="width:900px"><legend><img src="../img/admin/import.gif" />'.$this->l('Upload').'</legend>
				<form action="'.$currentIndex.'&token='.$this->token.'" method="POST" enctype="multipart/form-data">
					<b class="clear">'.$this->l('Select a file').' </b><br>
					<input name="file" type="file" size="40" /><br /><br>
					<div class="warn">
						'.$this->l('You can also upload your file by FTP and put it in').' <b>'.$this->import_dir.'</b><br>
					</div>
					<div class="warn">
						'.$this->l('Allowed .CSV files are only UTF-8 and iso-8859-1 encoded ones.').'
						'.$this->l('Excel 2003 XML file format is only supported.').'
					</div>
					<br/>
					<div class="margin-form">
						<input type="submit" name="submitFileUpload" value="'.$this->l('Upload').'" class="button" />
					</div>
				</form>
			</fieldset>
		</div>
		<br class="clear">
		';

		echo '
		<div class="space" style="height:517px;">
				<form id="preview_import" action="'.$currentIndex.'&token='.$this->token.'" method="post" class="width2" style="display: inline;" enctype="multipart/form-data" class="clear" onsubmit="if ($(\'#truncate\').get(0).checked) {if (confirm(\''.$this->l('Are you sure you want to delete', __CLASS__, true, false).'\' + \' \' + $(\'#entity > option:selected\').text().toLowerCase() + \''.$this->l('?', __CLASS__, true, false).'\')){this.submit();} else {return false;}}">
					<fieldset style="float: left; width: 650px">
						<legend><img src="../img/admin/import.gif" />'.$this->l('Importation').'</legend>
						<label class="clear">'.$this->l('Select which entity to import:').' </label>
						<div class="margin-form">
							<select name="entity" id="entity">';
		foreach ($this->entities AS $entity => $i)
		{
			echo '<option value="'.$i.'"';
			if (Tools::getValue('entity') == $i)
				echo ' selected="selected" ';
			echo'>'.$entity.'</option>';
		}
		echo '				</select>
						</div>
						<label class="clear">'.$this->l('Select imported file:').' </label>
						<div class="margin-form">
							<select name="csv" id="csv" onchange="ch_params_show_st()" style="width:300px;">';
		foreach (scandir($this->import_dir) as $filename)
			if (!in_array($filename, array('.','..','.htaccess','index.php',$this->tmp_csv)))
				echo '<option value="'.$filename.'">'.$filename.'</option>';
		echo '				</select>
						</div>
						<label class="clear">'.$this->l('Select language of the file (the locale must be installed):').' </label>
						<div class="margin-form">
							<select name="iso_lang">';
						if (!$this->_languages) {
							$this->_languages = Db::getInstance()->ExecuteS('SELECT `id_lang`, `name`, `iso_code` FROM `'._DB_PREFIX_.'lang` WHERE `active`=1');
						}
						foreach ($this->_languages AS $lang)
							echo '<option value="'.$lang['iso_code'].'" '.($lang['id_lang'] == $cookie->id_lang ? 'selected="selected"' : '').'>'.$lang['name'].'</option>';
						echo '</select></div>
						<div id="csv_param">
							<label for="convert" class="clear">'.$this->l('iso-8859-1 encoded file').' </label>
							<div class="margin-form">
								<input name="convert" id="convert" type="checkbox" style="margin-top: 6px;"/>
							</div>
							<label class="clear">'.$this->l('Field separator:').' </label>
							<div class="margin-form">
								<input type="text" size="2" value="|" name="separator"/>
								'.$this->l('e.g. ').'"1<span class="bold" style="color: red">|</span>Ipod<span class="bold" style="color: red">|</span>129.90<span class="bold" style="color: red">|</span>5"
							</div>
							<label class="clear">'.$this->l('Multiple value separator:').' </label>
							<div class="margin-form">
								<input type="text" size="2" value="`" name="multiple_value_separator"/>
								'.$this->l('e.g. ').'"Ipod|red.jpg<span class="bold" style="color: red">`</span>blue.jpg<span class="bold" style="color: red">`</span>green.jpg|129.90"
							</div>
							<label class="clear">'.$this->l('Option attribute separator:').' </label>
							<div class="margin-form">
								<input type="text" size="2" value="~" name="option_glue"/>
								'.$this->l('e.g. ').'"Color<span class="bold" style="color: red">~</span>Blue`Disk Space<span class="bold" style="color: red">~</span>16GO"
							</div>
						</div>
						<label for="truncate" class="clear">'.$this->l('Delete all').' <span id="entitie">'.$this->l('categories').'</span> '.$this->l('before import ?').' </label>
						<div class="margin-form">
							<input '.($this->can_delete?'':'onclick="this.checked=false;alert(\''.$this->l('You can not to do this in my demo-shop :)').'\');"').' name="truncate" id="truncate" type="checkbox" style="margin-top: 6px;"/>
						</div>
						<hr />
						<div class="margin-form">'.
							$this->l('Options only for products and combinations').'
						</div>
						<div id="div_identify_by_reference">
							<label for="identify_by_reference" class="clear">'.$this->l('Identify by reference firstly').'</label>
							<div class="margin-form">
								<input name="identify_by_reference" value="1" id="identify_by_reference" checked="checked" type="checkbox" style="margin-top: 6px;"/> '.
								$this->l('Allow to identify by reference firstly and then by ID').'
							</div>
						</div>
						<div id="div_update_mode">
							<label for="update_mode" class="clear">'.$this->l('Update mode').'</label>
							<div class="margin-form">
								<input name="update_mode" value="1" id="update_mode" type="checkbox" checked="checked" style="margin-top: 6px;"/> '.
								$this->l('Allow to update only fields that are not skiped (system default values are not be applied)').'
							</div>
						</div>
						<div id="div_update_remains">
							<label for="update_remains" class="clear">'.$this->l('Update stock remains').'</label>
							<div class="margin-form">
								<input name="update_remains" value="1" id="update_remains" type="checkbox" checked="checked" style="margin-top: 6px;"/> '.
								$this->l('It will add new quantity to existing quantity').'
							</div>
						</div>
						<div class="space margin-form">
							<input type="submit" name="submitImportFile" value="'.$this->l('Next step').'" class="button"/>
						</div>
					</fieldset>
				</form>
				<fieldset style="display: inline; float: right; margin-left: 20px;">
				<legend><img src="../img/admin/import.gif" />'.$this->l('Fields available').'</legend>
				<div id="availableFields" style="min-height: 218px; width: 200px; font-size: 10px;">'.nl2br($this->getAvailableFields()).'</div>
				</fieldset>
				<div class="clear" style="float:right; font-size:10px; padding-right:91px; color:red;">
					'.$this->l('* Required Fields').'
				</div>
			</div>
			<script type="text/javascript">
				$("select#entity").change
				(
					function()
					{
						$("#entitie").html($("#entity > option:selected").text().toLowerCase());
						$.getJSON("'.dirname($currentIndex).'/ajax.php",{getAvailableFields:1, entity: $("#entity").val()},
							function(j)
							{
								var fields = "";
								$("#availableFields").empty();
								for (var i = 0; i < j.length; i++)
								fields += j[i].field + "<br />";
								$("#availableFields").html(fields);
							}
						)
					}
				);
			</script>
			';
	if (Tools::getValue('entity'))
		echo' <script type="text/javascript">$("select#entity").change();</script>';
	}

	public function utf8_encode_array(&$array)
	{
	    if (is_array($array))
			self::array_walk($array, array(get_class($this), 'utf8_encode_array'));
		else
			$array = utf8_encode($array);
	}

	private function getNbrColumn($handle, $glue)
	{
		$tmp = fgetcsv($handle, MAX_LINE_SIZE, $glue);
		fseek($handle, 0);
		return sizeof($tmp);
	}

	private function openCsvFile()
	{
		$csv_file = Tools::getValue('csv');
		$p = explode('.', $csv_file);
		$ext = $p[count($p)-1];

		// полное имя csv-файла
		$file_pathname = $this->import_dir.strval(preg_replace('/\.{2,}/', '.',$csv_file));

		if ($ext == 'xml') { // Excel 2003 XML
			include $this->module_dir.'xlslib/'.'PHPExcel.php';

			$objReader = new PHPExcel_Reader_Excel2003XML();
			$objPHPExcel = $objReader->load($file_pathname);

			$objWriter = new PHPExcel_Writer_CSV($objPHPExcel);

			// полное имя преобразованного из xml csv-файла
			$file_pathname = $this->import_dir.$this->tmp_csv;

			$objWriter->setDelimiter(Tools::getValue('separator'));

			$objWriter->save($file_pathname);
		}

		$handle = fopen($file_pathname, 'r');

		/* No BOM allowed */
		$bom = fread($handle, 3);
		if ($bom != '\xEF\xBB\xBF')
			rewind($handle);

		if (!$handle)
			die(Tools::displayError('Cannot read the csv file'));

		for ($i = 0; $i < intval(Tools::getValue('skip')); ++$i)
			$line = fgetcsv($handle, MAX_LINE_SIZE, Tools::getValue('separator', ';'));
		return $handle;
	}

	private function closeCsvFile($handle)
	{
		fclose($handle);
	}

	private function generateContentTable($current_table, $nb_table, $nb_column, $handle, $glue)
	{
		echo '
		<table id="table'.$current_table.'" style="display: none;" class="table" cellspacing="0" cellpadding="0">
			<tr>';

		// Header
		for ($i = 0; $i < $nb_column; $i++)
			if (MAX_COLUMNS * intval($current_table) <= $i AND intval($i) < MAX_COLUMNS * (intval($current_table) + 1))
				echo '
				<th style="width: '.(750 / MAX_COLUMNS).'px; vertical-align: top; padding: 4px">
					<select onchange="askFeatureName(this, '.$i.');" style="width: '.(750 / MAX_COLUMNS).'px;" id="type_value['.$i.']" name="type_value['.$i.']">
						'.$this->getTypeValuesOptions($i).'
					</select>
					<div id="features_'.$i.'" style="display: none;"><input style="width: 90px" type="text" name="" id="feature_name_'.$i.'"><input type="button" value="ok" onclick="replaceFeature($(\'#feature_name_'.$i.'\').attr(\'name\'), '.$i.');"></div>
				</th>';
		echo '
			</tr>';
		ob_flush();
		ob_clean();

		/* Datas */
		self::setLocale();
		for ($current_line = 0; $current_line < 10 AND $line = fgetcsv($handle, MAX_LINE_SIZE, $glue); $current_line++)
		{
			/* UTF-8 conversion */
			if (Tools::getValue('convert'))
				$this->utf8_encode_array($line);
			echo '<tr id="table_'.$current_table.'_line_'.$current_line.'" style="padding: 4px">';
			foreach ($line AS $nb_c => $column)
				if ((MAX_COLUMNS * intval($current_table) <= $nb_c) AND (intval($nb_c) < MAX_COLUMNS * (intval($current_table) + 1)))
					echo '<td>'.substr($column, 0, 200).'</td>';
			echo '</tr>';
		}
		echo '</table>';
		fseek($handle, 0);
	}

	public function displayCSV()
	{
		global $currentIndex;

		echo '<h2>'.$this->l('Your data').'</h2>'.'
		<h3>'.$this->l('Please set the value type of each column').'</h3>';

		echo '
		<div id="error_duplicate_type" class="warning warn" style="display:none;">
			<h3>'.$this->l('Columns cannot have the same value type').'</h3>
		</div>
		<div id="required_column" class="warning warn" style="display:none;">
			<h3>'.Tools::displayError('Column').' <span id="missing_column">&nbsp;</span> '.Tools::displayError('must be set').'</h3>
		</div>';

		$glue = Tools::getValue('separator', ';');
		$handle = $this->openCsvFile();
		$nb_column = $this->getNbrColumn($handle, $glue);
		$nb_table = ceil($nb_column / MAX_COLUMNS);

		$res = array();
		foreach (self::$required_fields AS $elem)
			$res[] = '\''.$elem.'\'';

		echo '
		<form action="'.$currentIndex.'&token='.$this->token.'" method="post" id="import_form" name="import_form">
			'.$this->l('Skip').' <input type="text" size="2" name="skip" value="0" /> '.$this->l('lines').'.
			<input type="hidden" name="csv" value="'.Tools::getValue('csv').'" />
			<input type="hidden" name="convert" value="'.Tools::getValue('convert').'" />
			<input type="hidden" name="entity" value="'.intval(Tools::getValue('entity')).'" />
			<input type="hidden" name="iso_lang" value="'.Tools::getValue('iso_lang').'" />';
		if (Tools::getValue('truncate'))
			echo '<input type="hidden" name="truncate" value="1" />';

		echo '<input type="hidden" name="update_remains" value="'.(int)Tools::getValue('update_remains').'" />';
		echo '<input type="hidden" name="identify_by_reference" value="'.(int)Tools::getValue('identify_by_reference').'" />';
		echo '<input type="hidden" name="update_mode" value="'.(int)Tools::getValue('update_mode').'" />';

		echo '
			<input type="hidden" name="separator" value="'.strval(trim(Tools::getValue('separator'))).'">
			<input type="hidden" name="multiple_value_separator" value="'.strval(trim(Tools::getValue('multiple_value_separator'))).'">
			<input type="hidden" name="option_glue" value="'.strval(trim(Tools::getValue('option_glue'))).'">
			<script type="text/javascript">
				var current = 0;
				function showTable(nb)
				{
					getE(\'btn_left\').disabled = null;
					getE(\'btn_right\').disabled = null;
					if (nb <= 0)
					{
						nb = 0;
						getE(\'btn_left\').disabled = \'true\';
					}
					if (nb >= '.$nb_table.' - 1)
					{
						nb = '.$nb_table.' - 1;
						getE(\'btn_right\').disabled = \'true\';
					}
					toggle(getE(\'table\'+current), false);
					current = nb;
					toggle(getE(\'table\'+current), true);
				}
			</script>
			<div style="text-align:center; margin-bottom :20px;">
				<input name="import" type="submit" onclick="return (validateImportation(new Array('.implode(',', $res).')));" id="import" value="'.$this->l('Import data').'" class="button" />
			</div>';
		ob_flush();
		echo '
			<table>
				<tr>
					<td valign="top" align="center"><input id="btn_left" value="'.$this->l('<<').'" type="button" class="button" onclick="showTable(current - 1)" /></td>
					<td align="left">';
		for ($i = 0; $i < $nb_table; $i++)
			$this->generateContentTable($i, $nb_table, $nb_column, $handle, $glue);
		echo '		</td>
					<td valign="top" align="center"><input id="btn_right" value="'.$this->l('>>').'" type="button" class="button" onclick="showTable(current + 1)" /></td>
				</tr>
			</table>
			<script type="text/javascript">showTable(current);</script>
			<div style="text-align:center; margin-top:10px;">
				<input name="import" type="submit" onclick="return (validateImportation(new Array('.implode(',', $res).')));" id="import" value="'.$this->l('Import data').'" class="button" />
			</div>
		</form>';
	}

	private function truncateTables($case)
	{
		switch (intval($case))
		{
			case $this->entities[$this->l('Categories')]:
				Db::getInstance()->Execute('DELETE FROM `'._DB_PREFIX_.'category` WHERE id_category != 1');
				Db::getInstance()->Execute('DELETE FROM `'._DB_PREFIX_.'category_lang` WHERE id_category != 1');
				Db::getInstance()->Execute('ALTER TABLE `'._DB_PREFIX_.'category` AUTO_INCREMENT = 2 ');
				foreach (scandir(_PS_CAT_IMG_DIR_) AS $d)
					if (preg_match('/^[0-9]+\-(.*)\.jpg$/', $d))
						unlink(_PS_CAT_IMG_DIR_.$d);
				break;
			case $this->entities[$this->l('Products')]:
				Db::getInstance()->Execute('TRUNCATE TABLE `'._DB_PREFIX_.'product');
				Db::getInstance()->Execute('TRUNCATE TABLE `'._DB_PREFIX_.'feature_product');
				Db::getInstance()->Execute('TRUNCATE TABLE `'._DB_PREFIX_.'product_lang');
				Db::getInstance()->Execute('TRUNCATE TABLE `'._DB_PREFIX_.'category_product');
				Db::getInstance()->Execute('TRUNCATE TABLE `'._DB_PREFIX_.'product_tag');
				Db::getInstance()->Execute('TRUNCATE TABLE `'._DB_PREFIX_.'image');
				Db::getInstance()->Execute('TRUNCATE TABLE `'._DB_PREFIX_.'image_lang');
				foreach (scandir(_PS_PROD_IMG_DIR_) AS $d)
					if (preg_match('/^[0-9]+\-[0-9]+\-(.*)\.jpg$/', $d) OR preg_match('/^[0-9]+\-[0-9]+\.jpg$/', $d))
						unlink(_PS_PROD_IMG_DIR_.$d);
				break;
			case $this->entities[$this->l('Customers')]:
				Db::getInstance()->Execute('TRUNCATE TABLE `'._DB_PREFIX_.'customer');
				break;
			case $this->entities[$this->l('Addresses')]:
				Db::getInstance()->Execute('TRUNCATE TABLE `'._DB_PREFIX_.'address');
				break;
			case $this->entities[$this->l('Attributes')]:
				Db::getInstance()->Execute('TRUNCATE TABLE `'._DB_PREFIX_.'attribute_impact');
				Db::getInstance()->Execute('TRUNCATE TABLE `'._DB_PREFIX_.'product_attribute`');
				Db::getInstance()->Execute('TRUNCATE TABLE `'._DB_PREFIX_.'product_attribute_combination`');
				Db::getInstance()->Execute('TRUNCATE TABLE `'._DB_PREFIX_.'attribute_group`');
				Db::getInstance()->Execute('TRUNCATE TABLE `'._DB_PREFIX_.'attribute_group_lang`');
				Db::getInstance()->Execute('TRUNCATE TABLE `'._DB_PREFIX_.'attribute`');
				Db::getInstance()->Execute('TRUNCATE TABLE `'._DB_PREFIX_.'attribute_lang`');
				break;
			case $this->entities[$this->l('Manufacturers')]:
				Db::getInstance()->Execute('TRUNCATE TABLE `'._DB_PREFIX_.'manufacturer');
				Db::getInstance()->Execute('TRUNCATE TABLE `'._DB_PREFIX_.'manufacturer_lang');
				foreach (scandir(_PS_MANU_IMG_DIR_) AS $d)
					if (preg_match('/^[0-9]+\-(.*)\.jpg$/', $d))
						unlink(_PS_MANU_IMG_DIR_.$d);
				break;
			case $this->entities[$this->l('Suppliers')]:
				Db::getInstance()->Execute('TRUNCATE TABLE `'._DB_PREFIX_.'supplier');
				Db::getInstance()->Execute('TRUNCATE TABLE `'._DB_PREFIX_.'supplier_lang');
				foreach (scandir(_PS_SUPP_IMG_DIR_) AS $d)
					if (preg_match('/^[0-9]+\-(.*)\.jpg$/', $d))
						unlink(_PS_SUPP_IMG_DIR_.$d);
				break;

			case $this->entities[$this->l('Comments')]:
				Db::getInstance()->Execute('TRUNCATE TABLE `'._DB_PREFIX_.'product_comment');
				break;
		}
		return true;
	}

	public function postProcess()
	{
		global $currentIndex;

		if (Tools::isSubmit('submitFileUpload'))
		{
			$f = explode('.', $_FILES['file']['name']);
			$ext = $f[count($f)-1];
			$old_name = $f[count($f)-2];
			if ($ext && $old_name) {
				$new_name = $this->import_dir.$old_name.'__'.date('Ymdhis').'.'.$ext;
			}
			else {
				$this->_errors[] = $this->l('the file not have a extension');
			}
			if (!isset($_FILES['file']['tmp_name']) OR empty($_FILES['file']['tmp_name']))
				$this->_errors[] = $this->l('no file selected');
			elseif (!file_exists($_FILES['file']['tmp_name']) OR !@rename($_FILES['file']['tmp_name'], $new_name))
				$this->_errors[] = $this->l('an error occured while uploading and copying file');
			else
				Tools::redirectAdmin($currentIndex.'&token='.Tools::getValue('token').'&conf=18');
		}

		elseif (Tools::isSubmit('submitImportFile'))
			$this->displayCSV();
		elseif (Tools::getValue('import'))
		{
			if (Tools::getValue('truncate'))
				$this->truncateTables(intval(Tools::getValue('entity')));

			switch (intval(Tools::getValue('entity')))
			{
				case $this->entities[$this->l('Categories')]:
					$this->categoryImport();
				break;
				case $this->entities[$this->l('Products')]:
					$this->productImport();
				break;
				case $this->entities[$this->l('Customers')]:
					$this->customerImport();
				break;
				case $this->entities[$this->l('Addresses')]:
					$this->addressImport();
				break;
				case $this->entities[$this->l('Attributes')]:
					$this->attributeImport();
				break;
				case $this->entities[$this->l('Manufacturers')]:
					$this->manufacturerImport();
				break;
				case $this->entities[$this->l('Suppliers')]:
					$this->supplierImport();
				break;

				case $this->entities[$this->l('Comments')]:
					$this->commentImport();
				break;

				default:
					$this->_errors[] = $this->l('no entity selected');
			}
		}

		parent::postProcess();
	}

	public static function setLocale()
	{
		$iso_lang  = trim(Tools::getValue('iso_lang'));
		setlocale(LC_COLLATE, strtolower($iso_lang).'_'.strtoupper($iso_lang).'.UTF-8');
		setlocale(LC_CTYPE, strtolower($iso_lang).'_'.strtoupper($iso_lang).'.UTF-8');
	}

	protected function _addProductWarning($product_name, $product_id = NULL, $message = '')
	{
		$this->_warnings[] = $product_name.(isset($product_id) ? ' (ID '.$product_id.')' : '').' '.Tools::displayError($message);
	}

	public function file_exists_cache($filename)
	{
		if (!isset($this->m_file_exists_cache[$filename]))
			$this->m_file_exists_cache[$filename] = file_exists($filename);
		return $this->m_file_exists_cache[$filename];
	}

	protected function l($string, $class = null, $addslashes = null, $htmlentities = null)
	{
		global $_MODULES, $_MODULE, $cookie;

		$id_lang = (!isset($cookie) OR !is_object($cookie)) ? intval(Configuration::get('PS_LANG_DEFAULT')) : intval($cookie->id_lang);

		$file = _PS_MODULE_DIR_.$this->name.'/'.Language::getIsoById($id_lang).'.php';

		if ($this->file_exists_cache($file) AND include_once($file))
			$_MODULES = !empty($_MODULES) ? array_merge($_MODULES, $_MODULE) : $_MODULE;

		if (!is_array($_MODULES))
			return (str_replace('"', '&quot;', $string));

		$source = get_class($this);
		$string2 = str_replace('\'', '\\\'', $string);
		$currentKey = '<{'.$this->name.'}'._THEME_NAME_.'>'.$source.'_'.md5($string2);
		$defaultKey = '<{'.$this->name.'}prestashop>'.$source.'_'.md5($string2);

		if (key_exists($currentKey, $_MODULES))
			$ret = stripslashes($_MODULES[$currentKey]);
		elseif (key_exists($defaultKey, $_MODULES))
			$ret = stripslashes($_MODULES[$defaultKey]);
		else
			$ret = $string;

		return str_replace('"', '&quot;', $ret);
	}

	public function searchByNameAndParentCategoryId($id_lang, $category_name, $id_parent_category)
	{
		return Db::getInstance()->getRow('
		SELECT c.*, cl.*
	    FROM `'._DB_PREFIX_.'category` c
	    LEFT JOIN `'._DB_PREFIX_.'category_lang` cl ON (c.`id_category` = cl.`id_category` AND `id_lang` = '.intval($id_lang).')
	    WHERE `name`  LIKE \''.pSQL($category_name).'\'
		AND c.`id_category` != 1
		AND c.`id_parent` = '.intval($id_parent_category));
	}
}