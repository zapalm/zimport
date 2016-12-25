<?php
/**
 * Enhanced import tool: module for PrestaShop 1.3
 *
 * @author      zapalm <zapalm@ya.ru>
 * @copyright   (c) 2010, zapalm
 * @link        http://prestashop.modulez.ru/en/administrative-tools/14-enhanced-import-tool.html Module's homepage
 * @license     http://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
 */

if (!defined('_CAN_LOAD_FILES_')) {
    exit;
}

class ZImport extends Module
{
    /** @var string наименование таба модуля */
    private $module_tab;

    function __construct()
    {
        $this->name = 'zimport';
        $this->tab = 'Tools';
        $this->version = '1.3.0';
        $this->module_tab = 'zimportadmin';

        parent::__construct();

        $this->displayName = $this->l('Enhanced import tool');
        $this->description = $this->l('Enhanced import tool, that supports Excel files.');
    }

    public function install()
    {
        return $this->installModuleTab($this->module_tab, 'AdminTools') && parent::install();
    }

    public function uninstall()
    {
        return $this->uninstallModuleTab($this->module_tab) && parent::uninstall();
    }

    private function installModuleTab($tab_name, $parent_tab_name)
    {
        @copy(_PS_MODULE_DIR_ . $this->name . '/zimportadmin.gif', _PS_IMG_DIR_ . 't/' . $tab_name . '.gif');
        $tab = new Tab();

        // subtab name in different languages
        $langs = Language::getLanguages();
        foreach ($langs as $l) {
            switch ($l['iso_code']) {
                case 'ru':
                    $tab->name[$l['id_lang']] = 'Импорт+';
                    break;
                case 'fr':
                    $tab->name[$l['id_lang']] = 'Import+';
                    break;
                case 'es':
                    $tab->name[$l['id_lang']] = 'Importar+';
                    break;
                default  :
                    $tab->name[$l['id_lang']] = 'Import+';
                    break;
            }
        }

        $tab->class_name = $tab_name;
        $tab->module = $this->name;

        $id_parent = Tab::getIdFromClassName($parent_tab_name);
        $tab->id_parent = $id_parent;

        return $tab->save();
    }

    private function uninstallModuleTab($tab_name)
    {
        $idTab = Tab::getIdFromClassName($tab_name);
        if ($idTab != 0) {
            $tab = new Tab($idTab);
            $tab->delete();
            @unlink(_PS_IMG_DIR_ . 't/' . $tab_name . '.gif');
        }

        return true;
    }

    public function getContent()
    {
        $output = '<h2>' . $this->displayName . '</h2>';

        $output .= '
            <fieldset style="width: 450px">
                <legend><img src="../img/admin/manufacturers.gif" /> ' . $this->l('Module info') . '</legend>
                <div id="dev_div">
                    <span><b>' . $this->l('Version') . ':</b> ' . $this->version . '</span><br/>
                    <span><b>' . $this->l('License') . ':</b> Academic Free License (AFL 3.0)</span><br/>
                    <span><b>' . $this->l('Website') . ':</b> <a class="link" href="http://prestashop.modulez.ru/en/administrative-tools/14-enhanced-import-tool.html" target="_blank">prestashop.modulez.ru</a><br/>
                    <span><b>' . $this->l('Author') . ':</b> zapalm <img src="../modules/' . $this->name . '/zapalm24x24.jpg" /><br/>
                </div>
            </fieldset>
            <br class="clear" />
        ';

        return $output;
    }
}
