<?php
/**
 * Orders Editor 1.0: module for PrestaShop 1.2-1.3
 *
 * @author zapalm <zapalm@ya.ru>
 * @copyright (c) 2010-2014, zapalm
 * @link http://prestashop.modulez.ru/en/administrative-tools/26-free-orders-editor-module-for-prestashop.html The module's homepage
 * @license http://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
 * @version 1.0
 */

if (!defined('_PS_VERSION_'))
	exit;

// получить total_products_wt и total_products из таблицы order_detail
function get_total_products($id_order)
{
	$result = Db::getInstance()->ExecuteS('SELECT SUM(`product_quantity` * (`product_price` + (`product_price` * `tax_rate` / 100))) as total_products_wt, SUM(`product_quantity` * `product_price`) as total_products FROM `'._DB_PREFIX_.'order_detail` WHERE `id_order`='.$id_order);
	if (!$result[0]['total_products'])
		$result[0]['total_products'] = 0;

	if (!$result[0]['total_products_wt'])
		$result[0]['total_products_wt'] = 0;

	return $result[0];
}

// подсчет поля total_products и total_products_wt
function update_total_products($total_products, $total_products_wt, $id_order)
{
	return Db::getInstance()->Execute('update '._DB_PREFIX_.'orders set total_products='.$total_products.', '.(PS_VER_MM == 1.2 ? '' : 'total_products_wt='.$total_products_wt.', ').'date_upd = NOW() where id_order='.$id_order);
}

// вычисление поля total_paid и запись в БД total_paid и total_paid_real
function update_total_paid($id_order)
{
	$result = Db::getInstance()->ExecuteS('SELECT `total_discounts`, '.(PS_VER_MM == 1.2 ? '' : '`total_products_wt`,').' `total_shipping`, `total_wrapping`, `gift` FROM `'._DB_PREFIX_.'orders` WHERE `id_order`='.$id_order);
	$order = $result[0];
	if (PS_VER_MM == 1.2)
		$total_products = get_total_products($id_order);
	$total = (PS_VER_MM == 1.2 ? floatval($total_products['total_products_wt']) : floatval($order['total_products_wt'])) + floatval($order['total_shipping']) + ($order['gift'] ? floatval($order['total_wrapping']) : 0) - floatval($order['total_discounts']);
	// total_paid и total_paid_real не могут быть меньше нуля...
	$total = $total < 0 ? 0 : $total;
	$result = Db::getInstance()->Execute('update '._DB_PREFIX_.'orders set total_paid_real='.$total.', total_paid='.$total.', date_upd = NOW() where id_order='.$id_order);
	return $result;
}

// изменить количество товара в соответствии с параметром
function modify_product_quantity($id_product, $num)
{
	return Db::getInstance()->Execute('UPDATE `'._DB_PREFIX_.'product` SET `quantity`=`quantity`+'.$num.' WHERE `id_product`='.$id_product);
}

// изменить количество комбинации товара в соответствии с параметром
function modify_attribute_quantity($id_product_attribute, $num)
{
	return Db::getInstance()->Execute('UPDATE `'._DB_PREFIX_.'product_attribute` SET `quantity` = `quantity`+'.$num.' WHERE `id_product_attribute` ='.$id_product_attribute);
}

// вернуть, удаляемое количество товара, на склад
function return_product_quantity($id_order_detail)
{
	$p = Db::getInstance()->ExecuteS('SELECT `product_id`, `product_attribute_id`, `product_quantity` FROM  `'._DB_PREFIX_.'order_detail` WHERE `id_order_detail`='.$id_order_detail);
	$p = $p[0];

	if ($p['product_quantity'])
	{
		if ($p['product_attribute_id'])
			return modify_attribute_quantity($p['product_attribute_id'], intval($p['product_quantity']));
		else
			return modify_product_quantity($p['product_id'], intval($p['product_quantity']));
	}
}

// забрать со склада, приобретаемое количество товара
function take_product_quantity($product_id, $product_attribute_id = false, $num = 1)
{
	if ($product_attribute_id)
		return modify_attribute_quantity($product_attribute_id, -intval($num));
	else
		return modify_product_quantity($product_id, -intval($num));
}