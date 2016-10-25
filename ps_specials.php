<?php
/*
* 2007-2016 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2016 PrestaShop SA
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

use PrestaShop\PrestaShop\Core\Module\WidgetInterface;
use PrestaShop\PrestaShop\Adapter\Image\ImageRetriever;
use PrestaShop\PrestaShop\Adapter\Product\PriceFormatter;
use PrestaShop\PrestaShop\Core\Product\ProductListingPresenter;
use PrestaShop\PrestaShop\Adapter\Product\ProductColorsRetriever;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Ps_Specials extends Module implements WidgetInterface
{
    protected $_html = '';
    protected $_postErrors = array();

    protected static $cache_specials = array();

    public function __construct()
    {
        $this->name = 'ps_specials';
        $this->tab = 'pricing_promotion';
        $this->version = '1.0.0';
        $this->author = 'PrestaShop';
        $this->need_instance = 0;

        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->trans(
            'Specials block',
            array(),
            'Modules.Specials.Admin'
        );
        $this->description = $this->trans(
            'Displays your products that are currently on sale in a dedicated' .
            ' block.',
            array(),
            'Modules.Specials.Admin'
        );
        $this->ps_versions_compliancy = array(
            'min' => '1.7.0.0',
            'max' => _PS_VERSION_
        );
    }

    public function install()
    {
        if (!Configuration::get('BLOCKSPECIALS_SPECIALS_NBR')) {
            Configuration::updateValue('BLOCKSPECIALS_SPECIALS_NBR', 5);
        }

        $this->_clearCache('*');

        return parent::install()
            && $this->registerHook('displayLeftColumn')
            && $this->registerHook('displayRightColumn')
            && $this->registerHook('actionProductAdd')
            && $this->registerHook('actionProductUpdate')
            && $this->registerHook('actionProductDelete')
            && $this->registerHook('actionObjectSpecificPriceCoreDeleteAfter')
            && $this->registerHook('actionObjectSpecificPriceCoreAddAfter')
            && $this->registerHook('actionObjectSpecificPriceCoreUpdateAfter')
            && $this->registerHook('displayHome');
    }

    public function uninstall()
    {
        $this->_clearCache('*');

        return parent::uninstall();
    }

    public function getContent()
    {
        $output = '';
        if (Tools::isSubmit('submitSpecials')) {
            Configuration::updateValue(
                'PS_BLOCK_SPECIALS_DISPLAY',
                (int)Tools::getValue('PS_BLOCK_SPECIALS_DISPLAY')
            );
            Configuration::updateValue(
                'BLOCKSPECIALS_SPECIALS_NBR',
                (int)Tools::getValue('BLOCKSPECIALS_SPECIALS_NBR')
            );
            $output .= $this->displayConfirmation(
                $this->trans(
                    'Settings updated',
                    array(),
                    'Modules.Specials.Admin'
                )
            );
            $this->_clearCache('*');
        }
        return $output.$this->renderForm();
    }

    public function renderForm()
    {
        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->trans(
                        'Settings',
                        array(),
                        'Modules.Specials.Admin'
                    ),
                    'icon' => 'icon-cogs'
                ),
                'input' => array(
                    array(
                        'type' => 'switch',
                        'label' => $this->trans(
                            'Always display this block',
                            array(),
                            'Modules.Specials.Admin'
                        ),
                        'name' => 'PS_BLOCK_SPECIALS_DISPLAY',
                        'desc' => $this->trans(
                            'Show the block even if no products are available.',
                            array(),
                            'Modules.Specials.Admin'
                        ),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->trans(
                                    'Enabled',
                                    array(),
                                    'Modules.Specials.Admin'
                                ),
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->trans(
                                    'Disabled',
                                    array(),
                                    'Modules.Specials.Admin'
                                ),
                            ),
                        ),
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->trans(
                            'Products to display',
                            array(),
                            'Modules.Specials.Admin'
                        ),
                        'name' => 'BLOCKSPECIALS_SPECIALS_NBR',
                        'class' => 'fixed-width-xs',
                        'desc' => $this->trans(
                            'Define the number of products to be displayed in' .
                            ' this block on home page.',
                            array(),
                            'Modules.Specials.Admin'
                        ),
                    ),
                ),
                'submit' => array(
                    'title' => $this->trans(
                        'Save',
                        array(),
                        'Modules.Specials.Admin'
                    ),
                ),
            ),
        );

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang =
            Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ?
            Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') :
            0;
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitSpecials';
        $helper->currentIndex = $this->context->link->getAdminLink(
                'AdminModules',
                false
            ) .
            '&configure=' . $this->name .
            '&tab_module=' . $this->tab .
            '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFieldsValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id
        );

        return $helper->generateForm(array($fields_form));
    }

    public function hookActionProductAdd($params)
    {
        $this->_clearCache('*');
    }

    public function hookActionProductUpdate($params)
    {
        $this->_clearCache('*');
    }

    public function hookActionProductDelete($params)
    {
        $this->_clearCache('*');
    }

    public function hookActionObjectSpecificPriceCoreDeleteAfter($params)
    {
        $this->_clearCache('*');
    }

    public function hookActionObjectSpecificPriceCoreAddAfter($params)
    {
        $this->_clearCache('*');
    }

    public function hookActionObjectSpecificPriceCoreUpdateAfter($params)
    {
        $this->_clearCache('*');
    }

    public function getConfigFieldsValues()
    {
        return array(
            'PS_BLOCK_SPECIALS_DISPLAY' => Tools::getValue(
                'PS_BLOCK_SPECIALS_DISPLAY',
                Configuration::get('PS_BLOCK_SPECIALS_DISPLAY')
            ),
            'BLOCKSPECIALS_SPECIALS_NBR' => Tools::getValue(
                'BLOCKSPECIALS_SPECIALS_NBR',
                Configuration::get('BLOCKSPECIALS_SPECIALS_NBR')
            ),
        );
    }

    public function _clearCache($template, $cache_id = null, $compile_id = null)
    {
        return parent::_clearCache(
            'module:ps_specials/views/templates/hook/ps_specials.tpl'
        );
    }

    public function getWidgetVariables($hookName, array $configuration)
    {
        return array(
            'products' => $this->getSpecialProducts($hookName, $configuration),
        );
    }

    public function getSpecialProducts($hookName, array $configuration)
    {
        if (in_array($hookName, self::$cache_specials)
            && !empty(self::$cache_specials[$hookName])) {
            return self::$cache_specials[$hookName];
        }

        if ('displayHome' === $hookName) {
            $products = Product::getPricesDrop(
                (int)$configuration['cookie']->id_lang,
                0,
                Configuration::get('BLOCKSPECIALS_SPECIALS_NBR')
            );
        } else {
            $products = array(Product::getRandomSpecial(
                (int)$configuration['cookie']->id_lang
            ));
        }

        $assembler = new ProductAssembler($this->context);

        $presenterFactory = new ProductPresenterFactory($this->context);
        $presentationSettings = $presenterFactory->getPresentationSettings();
        $presenter = new ProductListingPresenter(
            new ImageRetriever(
                $this->context->link
            ),
            $this->context->link,
            new PriceFormatter(),
            new ProductColorsRetriever(),
            $this->context->getTranslator()
        );

        $products_for_template = array();

        if (is_array($products)) {
            foreach ($products as $rawProduct) {
                $products_for_template[] = $presenter->present(
                    $presentationSettings,
                    $assembler->assembleProduct($rawProduct),
                    $this->context->language
                );
            }
        }

        return self::$cache_specials[$hookName] = $products_for_template;
    }

    public function renderWidget($hookName, array $configuration)
    {
        if (Configuration::get('PS_CATALOG_MODE')) {
            return;
        }

        $special = $this->getSpecialProducts($hookName, $configuration);

        if (empty($special) &&
            !Configuration::get('PS_BLOCK_SPECIALS_DISPLAY')) {
            return;
        }

        $cacheId = ('displayHome' === $hookName) ?
            $this->getCacheId('ps_specials') :
            null;
        $isCached = $this->isCached(
            'module:ps_specials/views/templates/hook/ps_specials.tpl',
            $cacheId
        );

        if (!$isCached) {
            $this->smarty->assign(
                $this->getWidgetVariables(
                    $hookName,
                    $configuration
                )
            );
        }
        return $this->fetch(
            'module:ps_specials/views/templates/hook/ps_specials.tpl',
            $cacheId
        );
    }
}
