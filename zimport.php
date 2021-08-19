<?php
/**
 * Enhanced import tool: module for PrestaShop 1.3
 *
 * @author    Maksim T. <zapalm@yandex.com>
 * @copyright 2010 Maksim T.
 * @link      https://prestashop.modulez.ru/en/import-and-export-data/14-enhanced-import-tool.html The module's homepage
 * @license   https://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
 */

if (!defined('_CAN_LOAD_FILES_')) {
    exit;
}

/**
 * Module ZImport.
 *
 * @author Maksim T. <zapalm@yandex.com>
 */
class ZImport extends Module
{
    /** The product ID of the module on its homepage. */
    const HOMEPAGE_PRODUCT_ID = 14;

    /** @var string The module's tab name. */
    private $module_tab;

    /**
     * @inheritDoc
     *
     * @author Maksim T. <zapalm@yandex.com>
     */
    function __construct()
    {
        $this->name       = 'zimport';
        $this->tab        = 'Tools';
        $this->version    = '1.3.0';
        $this->author     = 'zapalm';
        $this->module_tab = 'zimportadmin';

        parent::__construct();

        $this->displayName = $this->l('Enhanced import tool');
        $this->description = $this->l('Enhanced import tool, that supports Excel files.');
    }

    /**
     * @inheritDoc
     *
     * @author Maksim T. <zapalm@yandex.com>
     */
    public function install()
    {
        $result = (bool)parent::install();

        if ($result) {
            $result = $this->installModuleTab($this->module_tab, 'AdminTools');
        }

        $this->registerModuleOnQualityService('installation');

        return $result;
    }

    /**
     * @inheritDoc
     *
     * @author Maksim T. <zapalm@yandex.com>
     */
    public function uninstall()
    {
        $result = (bool)parent::uninstall();

        if ($result) {
            $result = $this->uninstallModuleTab($this->module_tab);
        }

        $this->registerModuleOnQualityService('uninstallation');

        return $result;
    }

    /**
     * Installs a tab of the module.
     *
     * @param string $tab_name
     * @param string $parent_tab_name
     *
     * @return bool
     *
     * @author Maksim T. <zapalm@yandex.com>
     */
    private function installModuleTab($tab_name, $parent_tab_name)
    {
        @copy(_PS_MODULE_DIR_ . $this->name . '/zimportadmin.gif', _PS_IMG_DIR_ . 't/' . $tab_name . '.gif');
        $tab = new Tab();

        // Sub-tab name in different languages
        $languages = Language::getLanguages();
        foreach ($languages as $l) {
            switch ($l['iso_code']) {
                case 'ru':
                    $tab->name[$l['id_lang']] = 'Импорт+';
                    break;
                case 'fr':
                    $tab->name[$l['id_lang']] = 'Importer+';
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
        $tab->module     = $this->name;
        $tab->id_parent  = Tab::getIdFromClassName($parent_tab_name);

        return (bool)$tab->save();
    }

    /**
     * Uninstalls a tab of the module.
     *
     * @param string $tab_name
     *
     * @return bool
     *
     * @author Maksim T. <zapalm@yandex.com>
     */
    private function uninstallModuleTab($tab_name)
    {
        $idTab = Tab::getIdFromClassName($tab_name);
        if ($idTab > 0) {
            @unlink(_PS_IMG_DIR_ . 't/' . $tab_name . '.gif');

            $tab = new Tab($idTab);

            return (bool)$tab->delete();
        }

        return true;
    }

    /**
     * @inheritDoc
     *
     * @author Maksim T. <zapalm@yandex.com>
     */
    public function getContent()
    {
        $output = '<h2>' . $this->displayName . '</h2>';

        // The block about the module (version: 2021-08-15)
        $modulezUrl    = 'https://prestashop.modulez.ru' . (Language::getIsoById(false === empty($GLOBALS['cookie']->id_lang) ? $GLOBALS['cookie']->id_lang : Context::getContext()->language->id) === 'ru' ? '/ru/' : '/en/');
        $modulePage    = $modulezUrl . '14-enhanced-import-tool.html';
        $licenseTitle  = 'Academic Free License (AFL 3.0)';
        $output       .=
            (version_compare(_PS_VERSION_, '1.6', '<') ? '<br class="clear" />' : '') . '
            <div class="panel">
                <div class="panel-heading">
                    <img src="' . $this->_path . 'logo.gif" width="16" height="16" alt=""/>
                    ' . $this->l('Module info') . '
                </div>
                <div class="form-wrapper">
                    <div class="row">               
                        <div class="form-group col-lg-4" style="display: block; clear: none !important; float: left; width: 33.3%;">
                            <span><b>' . $this->l('Version') . ':</b> ' . $this->version . '</span><br/>
                            <span><b>' . $this->l('License') . ':</b> ' . $licenseTitle . '</span><br/>
                            <span><b>' . $this->l('Website') . ':</b> <a class="link" href="' . $modulePage . '" target="_blank">prestashop.modulez.ru</a></span><br/>
                            <span><b>' . $this->l('Author') . ':</b> ' . $this->author . '</span><br/><br/>
                        </div>
                        <div class="form-group col-lg-2" style="display: block; clear: none !important; float: left; width: 16.6%;">
                            <img width="250" alt="' . $this->l('Website') . '" src="https://prestashop.modulez.ru/img/marketplace-logo.png" />
                        </div>
                    </div>
                </div>
            </div> ' .
            (version_compare(_PS_VERSION_, '1.6', '<') ? '<br class="clear" />' : '') . '
        ';

        return $output;
    }

    /**
     * Registers current module installation/uninstallation in the quality service.
     *
     * This method is needed for a developer to quickly find out about a problem with installing or uninstalling a module.
     *
     * @param string $operation The operation. Possible values: installation, uninstallation.
     *
     * @author Maksim T. <zapalm@yandex.com>
     */
    private function registerModuleOnQualityService($operation)
    {
        @file_get_contents('https://prestashop.modulez.ru/scripts/quality-service/index.php?' . http_build_query([
            'data' => json_encode([
                'productId'           => self::HOMEPAGE_PRODUCT_ID,
                'productSymbolicName' => $this->name,
                'productVersion'      => $this->version,
                'operation'           => $operation,
                'status'              => (empty($this->_errors) ? 'success' : 'error'),
                'message'             => (false === empty($this->_errors) ? strip_tags(stripslashes(implode(' ', (array)$this->_errors))) : ''),
                'prestashopVersion'   => _PS_VERSION_,
                'thirtybeesVersion'   => (defined('_TB_VERSION_') ? _TB_VERSION_ : ''),
                'shopDomain'          => (method_exists('Tools', 'getShopDomain') && Tools::getShopDomain() ? Tools::getShopDomain() : (Configuration::get('PS_SHOP_DOMAIN') ? Configuration::get('PS_SHOP_DOMAIN') : Tools::getHttpHost())),
                'shopEmail'           => Configuration::get('PS_SHOP_EMAIL'), // This public e-mail from a shop's contacts can be used by a developer to send only an urgent information about security issue of a module!
                'phpVersion'          => PHP_VERSION,
                'ioncubeVersion'      => (function_exists('ioncube_loader_iversion') ? ioncube_loader_iversion() : ''),
                'languageIsoCode'     => Language::getIsoById(false === empty($GLOBALS['cookie']->id_lang) ? $GLOBALS['cookie']->id_lang : Context::getContext()->language->id),
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]));
    }
}