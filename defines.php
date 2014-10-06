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

if (!defined('_PS_PRICE_DISPLAY_PRECISION_'))
	define('_PS_PRICE_DISPLAY_PRECISION_', 2);

if (!defined('PS_TAX_EXC'))
	define('PS_TAX_EXC', 1);

if (!defined('PS_TAX_INC'))
	define('PS_TAX_INC', 0);

if (!defined('PS_PRICE_ROUND_MODE'))
	define('PS_PRICE_ROUND_MODE', 2);

if (!defined('PS_ROUND_UP'))
	define('PS_ROUND_UP', 0);

if (!defined('PS_ROUND_DOWN'))
	define('PS_ROUND_DOWN', 1);

if (!defined('PS_ROUND_HALF'))
	define('PS_ROUND_HALF', 2);

// собственный аналог version_compare() на константах
for ($ver = explode('.', _PS_VERSION_), $ver_m = array ('PS_MAJOR', 'PS_MINOR', 'PS_RELEASE', 'PS_BUILD'), $i = 0; $i < 4; $i++)
	define("$ver_m[$i]", $ver[$i]);
define('PS_VER_MM', PS_MAJOR.'.'.PS_MINOR);
define('PS_VER_RB', PS_RELEASE.'.'.PS_BUILD);