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

class OrderzEditor extends Module
{
	private $module_tab = 'orderzadmin';

	function __construct()
	{
		$this->name = 'orderzeditor';
		$this->tab = 'Tools';
		$this->version = '1.2.0.0';

		parent::__construct();

		$this->displayName = $this->l('zapalm\'s Orders editor');
		$this->description = $this->l('Must have tool to edit orders.');
	}

	public function install()
	{
		if (version_compare(_PS_VERSION_, '1.2.0.0', '>='))
			$this->installModuleTab($this->module_tab, Tab::getIdFromClassName('AdminOrders'));

		return parent::install();
	}

	public function uninstall()
	{
		if (version_compare(_PS_VERSION_, '1.2.0.0', '>='))
			$this->uninstallModuleTab($this->module_tab);

		return parent::uninstall();
	}

	private function installModuleTab($tab_class, $id_tab_parent)
	{
		@copy(_PS_MODULE_DIR_.$this->name.'/'.$tab_class.'.gif', _PS_IMG_DIR_.'t/'.$tab_class.'.gif');
		$tab = new Tab();

		// subtab name in different languages
		$langs = Language::getLanguages();
		foreach ($langs as $l)
		{
			switch ($l['iso_code'])
			{
				case 'ru': $tab->name[$l['id_lang']] = 'Редактор заказов';
					break;
				case 'fr': $tab->name[$l['id_lang']] = 'Redacteur des ordres';
					break;
				case 'es': $tab->name[$l['id_lang']] = 'Editor de pedidos';
					break;
				case 'ca': $tab->name[$l['id_lang']] = 'Editor de comandes';
					break;
				case 'se': $tab->name[$l['id_lang']] = 'Order Editor';
					break;
				default : $tab->name[$l['id_lang']] = 'Orders editor';
					break;
			}
		}

		$tab->class_name = $tab_class;
		$tab->module = $this->name;
		$tab->id_parent = $id_tab_parent;

		return $tab->save();
	}

	private function uninstallModuleTab($tab_class)
	{
		$id_tab = Tab::getIdFromClassName($tab_class);
		if ($id_tab != 0)
		{
			$tab = new Tab($id_tab);
			$tab->delete();
			@unlink(_PS_IMG_DIR_.'t/'.$tab_class.'.gif');

			return true;
		}

		return false;
	}
}