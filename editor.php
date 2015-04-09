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

// переход в админку
function home($back, $result, $id_order, $token)
{
	Tools::redirectLink($back.'&st='.($result ? '1' : '0').'&id_order='.$id_order.'&updateorder&token='.$token);
}

// редактирование доставки
if (isset($_POST['edit_carrier']))
{
	if ($_POST['id_carrier'] && $_GET['id_order'])
		$result = Db::getInstance()->Execute('UPDATE '._DB_PREFIX_.'orders SET `id_carrier`='.intval($_POST['id_carrier']).', date_upd = NOW() WHERE `id_order`='.intval($_GET['id_order']));
	else
		$result = false;

	Tools::redirectLink($_GET['back'].'&st='.($result ? '1' : '0').'&id_order='.$_GET['id_order'].'&updateorder&token='.$_GET['token']);
}
// редактирование деталей заказа
elseif (isset($_POST['editDetails']))
{
	if ($_POST['payment_module'] && floatval($_POST['total_discounts']) >= 0 && floatval($_POST['total_wrapping']) >= 0 && floatval($_POST['total_shipping']) >= 0 && $_GET['id_order'])
	{
		$payment_module = explode('|', $_POST['payment_module']);
		if (PS_VER_MM == 1.2)
			$total_products = get_total_products($_GET['id_order']);
		$sql = '
			UPDATE '._DB_PREFIX_.'orders SET
			`payment` = "'.$payment_module[1].'",
			`module` = "'.$payment_module[0].'",
			total_discounts ='.floatval($_POST['total_discounts']).',
			gift ='.(isset($_POST['gift']) ? 1 : 0).',
			total_wrapping ='.floatval($_POST['total_wrapping']).',
			gift_message ="'.$_POST['gift_message'].'",
			total_shipping ='.floatval($_POST['total_shipping']).',
			recyclable ='.(isset($_POST['recyclable']) ? 1 : 0).',
			'.(PS_VER_MM == 1.2 ? '`total_products`='.$total_products['total_products'].',' : '');
		// total_paid и total_paid_real не могут быть меньше нуля...
		$total_paid = (PS_VER_MM == 1.2 ? $total_products['total_products_wt'] : 'total_products_wt').'-'.strval((floatval($_POST['total_discounts']) ? floatval($_POST['total_discounts']) : 0)).'+'.strval((isset($_POST['gift']) ? floatval($_POST['total_wrapping']) ? floatval($_POST['total_wrapping']) : 0 : 0) + (floatval($_POST['total_shipping']) ? floatval($_POST['total_shipping']) : 0));
		$sql .= '
			`total_paid`=IF('.$total_paid.' < 0, 0, '.$total_paid.'),
			`total_paid_real`=`total_paid`,
			date_upd = NOW() WHERE `id_order` ='.intval($_GET['id_order']);

		$result = Db::getInstance()->Execute($sql);

		// удаление ваучеров заказа, если они есть (т.к. применяется скидка на весь заказ)
		$result = Db::getInstance()->Execute('DELETE FROM '._DB_PREFIX_.'order_discount WHERE `id_order`='.intval($_GET['id_order']));
	}
	else
		$result = false;

	Tools::redirectLink($_GET['back'].'&st='.($result ? '1' : '0').'&id_order='.$_GET['id_order'].'&updateorder&token='.$_GET['token']);
}
// редактирование списка товаров
elseif (isset($_POST['editProducts']))
{
	// удаление
	if (!empty($_POST['product_delete']))
	{
		$err = 1;

		foreach ($_POST['product_delete'] as $id_order_detail => $value)
		{
			$err &= return_product_quantity($id_order_detail);
			$err &= Db::getInstance()->Execute('delete from '._DB_PREFIX_.'order_detail where id_order_detail='.$id_order_detail);
		}

		$total_products = get_total_products($_POST['id_order']);

		$err &= update_total_products($total_products['total_products'], $total_products['total_products_wt'], $_POST['id_order']);
		$err &= update_total_paid($_POST['id_order']);
		home($_GET['back'], $err, $_POST['id_order'], $_GET['token']);
		exit();
	}
	// редактирование
	if (!empty($_POST['product_price']))
	{
		if ($_POST['id_order'])
		{
			$total_products = $total_products_wt = 0.0;
			$err = 1;
			foreach ($_POST['product_price'] as $id_order_detail => $price_product)
			{
				$name = htmlspecialchars(addslashes($_POST['product_name'][$id_order_detail]));
				$err &= Db::getInstance()->Execute('update '._DB_PREFIX_.'order_detail set product_price='.$price_product.', product_quantity='.$_POST['product_quantity'][$id_order_detail].', product_quantity_in_stock='.$_POST['product_quantity'][$id_order_detail].', product_name="'.$name.'" where id_order_detail='.$id_order_detail);

				$qty_difference = $_POST['product_quantity_old'][$id_order_detail] - $_POST['product_quantity'][$id_order_detail];
				$stock = max(0, $_POST['product_stock'][$id_order_detail] + $qty_difference);
				if (isset($_POST['product_attribute_id'][$id_order_detail]) && $_POST['product_attribute_id'][$id_order_detail] > 0)
					$err &= Db::getInstance()->Execute('update '._DB_PREFIX_.'product_attribute set quantity='.$stock.' where id_product_attribute='.$_POST['product_attribute_id'][$id_order_detail]);
				else
					$err &= Db::getInstance()->Execute('update '._DB_PREFIX_.'product set quantity='.$stock.' where id_product='.$_POST['product_id'][$id_order_detail]);

				$total_products += intval($_POST['product_quantity'][$id_order_detail]) * floatval($price_product);
				$total_products_wt += intval($_POST['product_quantity'][$id_order_detail]) * (floatval($price_product) + (floatval($price_product) * floatval($_POST['product_tax'][$id_order_detail]) / 100));
			}

			$err &= update_total_products($total_products, $total_products_wt, $_POST['id_order']);

			$err &= update_total_paid($_POST['id_order']);

			home($_GET['back'], $err, $_POST['id_order'], $_GET['token']);
			exit();
		}
	}
}