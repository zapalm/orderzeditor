<?php
/**
 * Orders Editor 1.0: module for PrestaShop 1.2-1.3
 *
 * @author    zapalm <zapalm@ya.ru>
 * @copyright 2010-2015 zapalm
 * @link      http://prestashop.modulez.ru/en/administrative-tools/26-free-orders-editor-module-for-prestashop.html The module's homepage
 * @license   http://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
 * @version   1.0
 */

if (!defined('_PS_VERSION_'))
	exit;

include_once(PS_ADMIN_DIR.'/../classes/AdminTab.php');
include_once('defines.php');

class orderzadmin extends AdminTab
{
	protected $m_file_exists_cache = array();

	public function __construct()
	{
		global $cookie;

		$this->name = 'orderzeditor';
		$this->table = 'order';
		$this->className = 'Order';
		$this->edit = true;
		$this->delete = true;
		$this->noAdd = true;
		$this->colorOnBackground = true;
		$this->_select = '
			a.id_order AS id_pdf,
			CONCAT(LEFT(c.`firstname`, 1), \'. \', c.`lastname`) AS `customer`,
			osl.`name` AS `osname`,
			os.`color`,
			IF((SELECT COUNT(so.id_order) FROM `'._DB_PREFIX_.'orders` so WHERE so.id_customer = a.id_customer AND so.valid = 1) > 1, 0, 1) as new,
			(SELECT COUNT(od.`id_order`) FROM `'._DB_PREFIX_.'order_detail` od WHERE od.`id_order` = a.`id_order` GROUP BY `id_order`) AS product_number';
		$this->_join = 'LEFT JOIN `'._DB_PREFIX_.'customer` c ON (c.`id_customer` = a.`id_customer`)
			LEFT JOIN `'._DB_PREFIX_.'order_history` oh ON (oh.`id_order` = a.`id_order`)
			LEFT JOIN `'._DB_PREFIX_.'order_state` os ON (os.`id_order_state` = oh.`id_order_state`)
			LEFT JOIN `'._DB_PREFIX_.'order_state_lang` osl ON (os.`id_order_state` = osl.`id_order_state` AND osl.`id_lang` = '.intval($cookie->id_lang).')';
		$this->_where = 'AND oh.`id_order_history` = (SELECT MAX(`id_order_history`) FROM `'._DB_PREFIX_.'order_history` moh WHERE moh.`id_order` = a.`id_order` GROUP BY moh.`id_order`)';

		$statesArray = array();
		$states = OrderState::getOrderStates(intval($cookie->id_lang));

		foreach ($states as $state)
			$statesArray[$state['id_order_state']] = $state['name'];

		$this->fieldsDisplay = array(
			'id_order' => array('title' => $this->l('ID'), 'align' => 'center', 'width' => 25),
			'new' => array('title' => $this->l('New'), 'width' => 25, 'align' => 'center', 'type' => 'bool', 'filter_key' => 'new', 'tmpTableFilter' => true, 'icon' => array(0 => 'blank.gif', 1 => 'news-new.gif'), 'orderby' => false),
			'customer' => array('title' => $this->l('Customer'), 'widthColumn' => 160, 'width' => 140, 'filter_key' => 'customer', 'tmpTableFilter' => true),
			'total_paid' => array('title' => $this->l('Total'), 'width' => 70, 'align' => 'right', 'prefix' => '<b>', 'suffix' => '</b>', 'price' => true, 'currency' => true),
			'payment' => array('title' => $this->l('Payment'), 'width' => 100),
			'osname' => array('title' => $this->l('Status'), 'widthColumn' => 250, 'type' => 'select', 'select' => $statesArray, 'filter_key' => 'os!id_order_state', 'filter_type' => 'int', 'width' => 200),
			'date_add' => array('title' => $this->l('Date'), 'width' => 90, 'align' => 'right', 'type' => 'datetime', 'filter_key' => 'a!date_add')
		);

		parent::__construct();
	}

	public function getPriceDisplayMethod($id_group)
	{
		return Db::getInstance()->getValue('
		SELECT `price_display_method`
		FROM `'._DB_PREFIX_.'group`
		WHERE `id_group` = '.intval($id_group));
	}

	public function getDefaultPriceDisplayMethod()
	{
		return Db::getInstance()->getValue('
		SELECT `price_display_method`
		FROM `'._DB_PREFIX_.'group`
		WHERE `id_group` = 1');
	}

	public function getTaxCalculationMethod($id_customer)
	{
		if ($id_customer)
		{
			$customer = new Customer(intval($id_customer));
			return $this->getPriceDisplayMethod(intval($customer->id_default_group));
		}
		else
			return $this->getDefaultPriceDisplayMethod();
	}

	public function ceilf($value, $precision = 0)
	{
		$precisionFactor = $precision == 0 ? 1 : pow(10, $precision);
		$tmp = $value * $precisionFactor;
		$tmp2 = (string)$tmp;
		// If the current value has already the desired precision
		if (strpos($tmp2, '.') === false)
			return ($value);
		if ($tmp2[strlen($tmp2) - 1] == 0)
			return $value;

		return ceil($tmp) / $precisionFactor;
	}

	public function floorf($value, $precision = 0)
	{
		$precisionFactor = $precision == 0 ? 1 : pow(10, $precision);
		$tmp = $value * $precisionFactor;
		$tmp2 = (string)$tmp;
		// If the current value has already the desired precision
		if (strpos($tmp2, '.') === false)
			return ($value);
		if ($tmp2[strlen($tmp2) - 1] == 0)
			return $value;

		return floor($tmp) / $precisionFactor;
	}

	public function ps_round($value, $precision = 0)
	{
		$method = (PS_VER_MM == 1.2 ? intval(PS_PRICE_ROUND_MODE) : intval(Configuration::get('PS_PRICE_ROUND_MODE')));
		if ($method == PS_ROUND_UP)
			return $this->ceilf($value, $precision);
		elseif ($method == PS_ROUND_DOWN)
			return $this->floorf($value, $precision);

		return round($value, $precision);
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

		$id_lang = (!isset($cookie) || !is_object($cookie)) ? intval(Configuration::get('PS_LANG_DEFAULT')) : intval($cookie->id_lang);

		$file = _PS_MODULE_DIR_.$this->name.'/'.Language::getIsoById($id_lang).'.php';

		if ($this->file_exists_cache($file) && include_once($file))
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

	public function display()
	{
		global $cookie, $currentIndex;

		include_once('tools.php');
		include_once('editor.php');

		$back = __PS_BASE_URI__.substr($_SERVER['SCRIPT_NAME'], strlen(__PS_BASE_URI__)).'?tab=orderzadmin';
		$html = '<h2>'.$this->l('Orders Editor');

		if (isset($_GET['updateorder']))
		{
			$order = $this->loadObject();
			$carrier = new Carrier($order->id_carrier);
			$currency = new Currency($order->id_currency);
			$products = $order->getProducts();
			$customizedDatas = Product::getAllCustomizedDatas(intval($order->id_cart));
			Product::addCustomizationPrice($products, $customizedDatas);

			$html .= ' / '.$this->l('Edit the order').' № '.$order->id.'</h2>';

			if (isset($_GET['st']))
				$_GET['st'] ? $html .= '<div class="conf confirm"><img src="../img/admin/ok.gif" />'.$this->l('Updated successful.').'</div>' : $html .= '<div class="alert error">'.$this->l('You must fill required data fields.').'</div>';

			$html .= '
				<script type="text/javascript">
				// <![CDATA[
					$("document").ready( function(){
						if(document.getElementById("gift").checked == false) {
							$("#gift_div").toggle("slow");
						}
					});

					function check_fill(field_name, field_data) {
						if(field_data == "") {
							alert("'.$this->l('You must fill the field').': " + field_name);
						}
					}

					function check_price(price) {
						check_fill("'.$this->l('Price').'", price);
						if(eval(price) < 0) {
							alert("'.$this->l('Product price must be > 0 !').'");
						}
					}

					function check_qty(qty, stock) {
						check_fill("'.$this->l('Qty').'", qty);
						if(eval(qty) <=0 || eval(qty) > eval(stock)) {
							alert("'.$this->l('Product Qty must be in range: 0 < Qty < Stock !').'");
						}
					}
				//]]>
				</script>
			';

			///////////////////////////////// Order details ////////////////////////////////////////
			$res = Db::getInstance()->ExecuteS('
				SELECT '._DB_PREFIX_.'module . *
				FROM '._DB_PREFIX_.'module, '._DB_PREFIX_.'hook_module, '._DB_PREFIX_.'hook
				WHERE '._DB_PREFIX_.'module.active =1
				AND '._DB_PREFIX_.'module.id_module = '._DB_PREFIX_.'hook_module.id_module
				AND '._DB_PREFIX_.'hook_module.id_hook = '._DB_PREFIX_.'hook.id_hook
				AND '._DB_PREFIX_.'hook.name = "payment"
			');

			foreach ($res as $k => $paymod)
			{
				$inst = Module::getInstanceByName($paymod['name']);
				if ($inst)
					$paymods[$inst->name] = $inst->displayName;
			}

			$html .= '
				<div style="float: left;">
					<fieldset style="width: 400px">
						<legend><img src="../img/admin/details.gif" /> '.$this->l('Order details').'</legend>
						<form name="details_form" method="post" action="'.$currentIndex.'&back='.$back.'&updateorder&id_order='.$order->id.'&token='.$this->token.'">
							'.$this->l('Payment mode:').'
							<select name="payment_module">';
							foreach ($paymods as $k => $p)
								$html .= '<option value="'.$k.'|'.$p.'" '.($k==$order->module?'selected="selected"':'').'>'.$p.'</option>';
							$html .= '
							</select>
							<br><br>';

							$html .= '
							<div style="margin: 2px 0 1em 0px;">
								<table class="table" width="300px;" cellspacing="0" cellpadding="0">
									<tr><td width="150px;">'.$this->l('Products price inc. tax').'</td><td align="right">'.Tools::displayPrice($order->getTotalProductsWithTaxes($products), $currency, false, false).'</td></tr>
									<tr><td>'.$this->l('Discounts').'</td><td align="right">&mdash;<input type="text" style="text-align:right" size="7" name="total_discounts" value="'.$order->total_discounts.'"> ('.$currency->sign.')</td></tr>
									<tr><td>'.$this->l('Wrapping').'</td><td align="right"><input type="text" style="text-align:right" size="7" name="total_wrapping" value="'.$order->total_wrapping.'"> ('.$currency->sign.')</td></tr>
									<tr><td>'.$this->l('Shipping').'</td><td align="right"><input size="7" style="text-align:right" type="text" name="total_shipping" value="'.$order->total_shipping.'"> ('.$currency->sign.')</td></tr>
									<tr style="font-size: 20px"><td>'.$this->l('Total').'</td><td align="right">'.Tools::displayPrice($order->total_paid, $currency, false, false).($order->total_paid != $order->total_paid_real ? '<br /><font color="red">('.$this->l('Paid:').' '.Tools::displayPrice($order->total_paid_real, $currency, false, false).')</font>' : '').'</td></tr>
								</table>
							</div>
							<div style="float: left; margin-right: 10px; margin-left: 0px;">
								<span class="bold">'.$this->l('Recycled package:').'</span>
								<input type="checkbox" name="recyclable" '.($order->recyclable?'checked="checked"':'').'>
							</div><br>
							<div style="float: left; margin-right: 0px;">
								<span class="bold">'.$this->l('Gift wrapping:').'</span>
								 <input type="checkbox" name="gift" id="gift" onclick="$(\'#gift_div\').toggle(\'slow\');"'.($order->gift?'checked="checked"':'').'>
							</div>
							<div id="gift_div" style="clear: left; margin: 0px 42px 0px 42px; padding-top: 2px;">
								<div style="border: 1px dashed #999; padding: 5px; margin-top: 8px;"><b>'.$this->l('Message').':</b><br />
									<textarea cols="39" rows="4" name="gift_message">'.nl2br2($order->gift_message).'</textarea></div>
								</div>
							<br><br>
							<div class="margin-form">
								<input type="submit" value="'.$this->l('Save').'" name="editDetails" class="button" />
							</div>
						</form>
					</fieldset>
				</div>
			';

			// поместить справа, все что в этом диве
			$html .= '<div style="float: left; margin-left: 40px">';

			/////////////////////////////////// Shipping information //////////////////////////////////////////
			$html .= '
				<fieldset style="width: 400px">
					<legend><img src="../img/admin/delivery.gif" /> '.$this->l('Shipping information').'</legend>
					<form name="carrier_form" method="post" action="'.$currentIndex.'&back='.$back.'&updateorder&id_order='.$order->id.'&token='.$this->token.'">
						'.$this->l('Total weight:').' <b>'.number_format($order->getTotalWeight(), 3).' '.Configuration::get('PS_WEIGHT_UNIT').'</b><br /><br />
						'.$this->l('Carrier:').'
						<select name="id_carrier" >';
							$carriers = $carrier->getCarriers(intval($cookie->id_lang), true);
							foreach ($carriers as $k=>$c)
								$html .= '<option value="'.$c['id_carrier'].'" '.($c['id_carrier'] == $order->id_carrier ? 'selected="selected"' : '').'>'.$c['name'].'</option>';
						$html .= '
						</select>
						<br><br>
						<div class="margin-form">
							<input type="submit" value="'.$this->l('Save').'" name="edit_carrier" class="button" />
						</div>
					</form>
				</fieldset>
			';

			// конец дива для выравнивания вправо
			$html .= '</div>';

			////////////////////////////////// Add new product to the order ///////////////////////////////
			$html .= '
				<br class="clear">
				<br class="clear">
				<br class="clear">
				<form name="search_form" method="post" action="'.$currentIndex.'&updateorder&search_products&id_order='.$order->id.'&token='.$this->token.'&id_lang='.$cookie->id_lang.'">
					<fieldset style="width: 868px; ">
						<legend><img src="../img/admin/edit.gif" /> '.$this->l('Add new product(s) to the order').'</legend>';
						$html .= $this->l('Search product to add').':
						<input name="search_txt" type="text" value="" />
						<input type="submit" value="'.$this->l('   Search   ').'" name="productSearch" class="button" />
						<br>
						<br>
						<div style="float:left;">
							<table style="width: 868px;" cellspacing="0" cellpadding="0" class="table" id="orderProducts">
								<tr>
									<th align="left" style="width: 60px">&nbsp;</th>
									<th>'.$this->l('Product').'</th>
									<th style="width: 60px; text-align: center">'.$this->l('Tax').'</th>
									<th style="width: 90px; text-align: center">'.$this->l('Price').'</th>
									<th style="width: 20px; text-align: center">'.$this->l('Add').'</th>
								</tr>';
								if (isset($_GET['search_products']))
								{
									if (isset($_POST['addProducts']))
									{
										if ($_POST['product_add'])
										{
											foreach ($_POST['product_add'] as $id_product => $v)
											{
												$result = Db::getInstance()->ExecuteS('select p.*, pl.name, t.rate as tax_rate, tl.name as tax_name from '._DB_PREFIX_.'product p
													left join '._DB_PREFIX_.'product_lang pl on p.id_product=pl.id_product
													left join '._DB_PREFIX_.'tax t on t.id_tax=p.id_tax
													left join '._DB_PREFIX_.'tax_lang tl on t.id_tax=tl.id_tax AND pl.id_lang=tl.id_lang
													right join '._DB_PREFIX_.'lang l on pl.id_lang=l.id_lang AND l.id_lang='.intval(Tools::getValue('id_lang')).'
													where p.id_product='.$id_product
												);
												$result = $result[0];

												$err = 1;
												$err &= Db::getInstance()->Execute('insert into '._DB_PREFIX_.'order_detail (id_order, product_id, product_name, product_quantity, product_quantity_in_stock, product_price, product_ean13 ,product_reference ,product_supplier_reference ,product_weight ,tax_name ,tax_rate )
													values ('.$order->id.','.$result['id_product'].',\''.addslashes($result['name']).'\',1,1,'.
													$result['price'].',\''.$result['ean13'].'\',\''.addslashes($result['reference']).'\',\''.addslashes($result['supplier_reference']).'\','.$result['weight'].',\''.addslashes($result['tax_name']).'\','.($result['tax_rate']?$result['tax_rate']:0).')'
												);

												// забираем товар со склада
												$err &= take_product_quantity($result['id_product']);

												$result = get_total_products($order->id);
												$err &= update_total_products($result['total_products'], $result['total_products_wt'], $order->id);
												$err &= update_total_paid($order->id);
											}
											Tools::redirectLink($back.'&st='.$err.'&id_order='.$order->id.'&updateorder&token='.$this->token);
										}
										else
										  Tools::redirectLink($back.'&st=0&id_order='.$order->id.'&updateorder&token='.$this->token);
									}
									elseif ($_POST['search_txt'])
									{
										$sql = "select i.id_image, p.id_product, p.price, pl.name, t.rate from "._DB_PREFIX_."product p
											left join "._DB_PREFIX_."product_lang pl on p.id_product=pl.id_product
											left join "._DB_PREFIX_."image i on p.id_product=i.id_product AND i.cover = 1
											left join "._DB_PREFIX_."tax t on t.id_tax=p.id_tax
											right join "._DB_PREFIX_."lang l on pl.id_lang=l.id_lang AND l.id_lang=".$cookie->id_lang."
											where pl.name like '%".$_POST['search_txt']."%' limit 25";

										$ps = Db::getInstance()->ExecuteS($sql);

										foreach ($ps as $p)
										{
											$html .= '
											<tr>
												<td><img src="'.__PS_BASE_URI__.'img/p/'.intval($p['id_product']).'-'.intval($p['id_image']).'-small.jpg'.'"></td>
												<td>'.$p['name'].'</td>
												<td>'.number_format($p['rate'], 1, '.', '').' %</td>
												<td>'.Tools::displayPrice($p['price'], $currency, false, false).'</td>
												<td><input type="checkbox" name="product_add['.intval($p['id_product']).']"></td>
											</tr>';
										}
									}
									else
									{
										$html .= '
											<tr>
												<td colspan=5><span style="font-style:italic;">'.$this->l('No search results.').'</span></td>
											</tr>
										';
									}
								}
								else
								{
									$html .= '
										<tr>
											<td colspan=5><span style="font-style:italic;">'.$this->l('No search results.').'</span></td>
										</tr>
									';
								}

							$html .= '
							</table>
							<br class="clear">
							<div class="margin-form">
								<input type="submit" value="'.$this->l('Add selected products to the order').'" name="addProducts" class="button" />
							</div>
					</fieldset>
				</form>
			';

			//////////////////////////////////// Products ///////////////////////////////////
			$html .= '
				<br class="clear">
				<a name="products"><br /></a>
				<form action="'.$currentIndex.'&back='.$back.'&updateorder&editProducts&id_order='.$order->id.'&token='.$this->token.'" method="post">
					<input type="hidden" name="id_order" value="'.$order->id.'" />
					<fieldset style="width: 868px;">
						<legend><img src="../img/admin/cart.gif" alt="'.$this->l('Products').'" />'.$this->l('Products in order').'</legend>
						<div style="float:left;">
							<table style="width: 868px;" cellspacing="0" cellpadding="0" class="table" id="orderProducts">
								<tr>
									<th align="center" style="width: 60px">&nbsp;</th>
									<th>'.$this->l('Product').'</th>
									<th style="width: 110px; text-align: center">'.$this->l('Price excl. tax').'</th>
									<th style="width: 40px; text-align: center">'.$this->l('Qty').'</th>
									<th style="width: 40px; text-align: center">'.$this->l('Stock').'</th>
									<th style="width: 110px; text-align: center">'.$this->l('Total inc. tax').' <sup>*</sup></th>
									<th style="width: 40px; text-align: center">'.$this->l('Delete').'</th>
								</tr>';
								foreach ($products as $k => $product)
								{
									$image = array();
									if (isset($product['product_attribute_id']) && intval($product['product_attribute_id']))
										$image = Db::getInstance()->getRow('
											SELECT id_image
											FROM '._DB_PREFIX_.'product_attribute_image
											WHERE id_product_attribute = '.intval($product['product_attribute_id'])
										);
									if (!isset($image['id_image']) || !$image['id_image'])
										$image = Db::getInstance()->getRow('
											SELECT id_image
											FROM '._DB_PREFIX_.'image
											WHERE id_product = '.intval($product['product_id']).' AND cover = 1'
										);
									$stock = Db::getInstance()->getRow('
										SELECT '.($product['product_attribute_id'] ? 'pa' : 'p').'.quantity
										FROM '._DB_PREFIX_.'product p
										'.($product['product_attribute_id'] ? 'LEFT JOIN '._DB_PREFIX_.'product_attribute pa ON p.id_product = pa.id_product' : '').'
										WHERE p.id_product = '.intval($product['product_id']).'
										'.($product['product_attribute_id'] ? 'AND pa.id_product_attribute = '.intval($product['product_attribute_id']) : '')
									);
									if (isset($image['id_image']))
									{
										$target = '../img/tmp/product_mini_'.intval($product['product_id']).(isset($product['product_attribute_id']) ? '_'.intval($product['product_attribute_id']) : '').'.jpg';
										if (file_exists($target))
											$products[$k]['image_size'] = getimagesize($target);
									}
									if ($product['product_attribute_id'] > 0)
									{
										$product_stock_sql = '
											SELECT quantity
											FROM `'._DB_PREFIX_.'product_attribute`
											WHERE id_product_attribute='.$product['product_attribute_id'];
									}
									else
									{
										$product_stock_sql = '
											select p.quantity from '._DB_PREFIX_.'product p join '._DB_PREFIX_.'product_lang pl on p.id_product=pl.id_product
											left join '._DB_PREFIX_.'tax t on t.id_tax=p.id_tax
											left join '._DB_PREFIX_.'tax_lang tl on t.id_tax=tl.id_tax
											where p.id_product='.$product['product_id'].' and pl.id_lang='.intval($cookie->id_lang);
									}
									$product_stock = intval(Db::getInstance()->getValue($product_stock_sql));
									$c_decimals = (is_array($currency) ? intval($currency['decimals']) : intval($currency->decimals)) * _PS_PRICE_DISPLAY_PRECISION_;
									if ($product['product_quantity'] > $product['customizationQuantityTotal'])
									{
										$html .= '
										<tr'.((isset($image['id_image']) && isset($products[$k]['image_size'])) ? ' height="'.($products[$k]['image_size'][1] + 7).'"' : '').'>
											<td align="center">'.(isset($image['id_image']) ? cacheImage(_PS_IMG_DIR_.'p/'.intval($product['product_id']).'-'.intval($image['id_image']).'.jpg',
											'product_mini_'.intval($product['product_id']).(isset($product['product_attribute_id']) ? '_'.intval($product['product_attribute_id']) : '').'.jpg', 45, 'jpg') : '--').'</td>
											<td><input type="text" onkeyup="check_pname(this.value);" size="54" name="product_name['.intval($product['id_order_detail']).']" value="'.stripslashes($product['product_name']).'"><br /></td>
											<td align="center"><input type="text" onkeyup="check_price(this.value);" size="8" name="product_price['.intval($product['id_order_detail']).']" value="'.$this->ps_round($product['product_price'], $c_decimals).'">('.$currency->sign.')</td>
											<td align="center" class="productQuantity"><input type="text" onkeyup="check_qty(this.value, '.($product['product_quantity'] + $stock['quantity']).');" size="2" name="product_quantity['.intval($product['id_order_detail']).']" value="'.(intval($product['product_quantity']) - $product['customizationQuantityTotal']).'"></td>
											<td align="center" class="productQuantity">'.intval($stock['quantity']).'</td>
											<input type="hidden" name="product_stock['.intval($product['id_order_detail']).']" value="'.$product_stock.'">
											<input type="hidden" name="product_quantity_old['.intval($product['id_order_detail']).']" value="'.$product['product_quantity'].'">
											<input type="hidden" name="product_tax['.intval($product['id_order_detail']).']" value="'.$product['tax_rate'].'">';
											if ($product['product_attribute_id'] > 0)
												$html .= '<input type="hidden" name="product_attribute_id['.intval($product['id_order_detail']).']" value="'.$product['product_attribute_id'].'">';
											else
												$html .= '<input type="hidden" name="product_id['.intval($product['id_order_detail']).']" value="'.$product['product_id'].'">';
											$html .= '
											<td align="center">'.Tools::displayPrice(($this->getTaxCalculationMethod($order->id_customer) == PS_TAX_EXC ? $product['product_price'] : $this->ps_round($product['product_price'] * (1 + ($product['tax_rate'] * 0.01)), 2)) * (intval($product['product_quantity']) - $product['customizationQuantityTotal']), $currency, false, false).'</td>
											<td align="center"><input type="checkbox" name="product_delete['.intval($product['id_order_detail']).']"></td>
										</tr>';
									}
								}
							$html .= '
							</table>
							<br>
							<div class="margin-form">
								<input type="submit" value="'.$this->l('Save').'" name="editProducts" class="button" />
							</div>';
							$html .= '
						</div>
					</fieldset>
				</form>
				<br class="clear">
			';

			/////////////////////////////////// Module block //////////////////////////////////////////
			$html .= '
				<fieldset style="font-size: 0.9em; width: 886px; padding: 4px;">
					<div>
						<b>'.$this->l('Module info').':</b> '.
						$this->l('Forums').' [
							<a class="link" href="http://www.prestashop.com/forums/topic/74518-module-orders-editor-by-zapalm/" target="_blank">'.$this->l('english').'</a>,
							<a class="link" href="http://prestadev.ru/forum/tema-1821.html" target="_blank">'.$this->l('russian').'</a>,
							<a class="link" href="http://www.prestashop.com/forums/topic/74519-module-sous-la-direction-de-commandes-par-zapalm/" target="_blank">'.$this->l('french').'</a>,
							<a class="link" href="http://www.prestashop.com/forums/topic/74520-modulo-los-pedidos-editor-zapalm/" target="_blank">'.$this->l('spanish').'</a>
						],
						<a class="link" href="https://github.com/zapalm/orderzeditor" target="_blank">GitHub</a>,
						<a class="link" href="http://prestashop.modulez.ru/en/administrative-tools/26-free-orders-editor-module-for-prestashop.html" target="_blank">'.$this->l('Homepage').'</a>
					</div>
				</fieldset>
			';

			echo $html;
		}
		else
		{
			$html .= ' / '.$this->l('Orders list').'</h2>';
			echo $html;

			$this->getList(intval($cookie->id_lang), !Tools::getValue($this->table.'Orderby') ? 'date_add' : null, !Tools::getValue($this->table.'Orderway') ? 'DESC' : null);
			$this->displayList();
		}
	}
}