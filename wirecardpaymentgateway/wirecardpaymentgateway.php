<?php
/**
 * Shop System Plugins - Terms of Use
 *
 * The plugins offered are provided free of charge by Wirecard AG and are explicitly not part
 * of the Wirecard AG range of products and services.
 *
 * They have been tested and approved for full functionality in the standard configuration
 * (status on delivery) of the corresponding shop system. They are under General Public
 * License version 3 (GPLv3) and can be used, developed and passed on to third parties under
 * the same terms.
 *
 * However, Wirecard AG does not provide any guarantee or accept any liability for any errors
 * occurring when used in an enhanced, customized shop system configuration.
 *
 * Operation in an enhanced, customized configuration is at your own risk and requires a
 * comprehensive test phase by the user of the plugin.
 *
 * Customers use the plugins at their own risk. Wirecard AG does not guarantee their full
 * functionality neither does Wirecard AG assume liability for any disadvantages related to
 * the use of the plugins. Additionally, Wirecard AG does not guarantee the full functionality
 * for customized shop systems or installed plugins of other vendors of plugins within the same
 * shop system.
 *
 * Customers are responsible for testing the plugin's functionality before starting productive
 * operation.
 *
 * By installing the plugin into the shop system the customer agrees to these terms of use.
 * Please do not use the plugin if you do not agree to these terms of use!
 *
 * @author Wirecard AG
 * @copyright Wirecard AG
 * @license GPLv3
 */

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;
use WirecardEE\Prestashop\Models\PaymentCreditCard;
use WirecardEE\Prestashop\Models\PaymentIdeal;
use WirecardEE\Prestashop\Models\PaymentPaypal;
use WirecardEE\Prestashop\Models\PaymentSepa;
use WirecardEE\Prestashop\Models\PaymentSofort;
use WirecardEE\Prestashop\Models\PaymentPoiPia;
use WirecardEE\Prestashop\Models\PaymentAlipayCrossborder;
use WirecardEE\Prestashop\Models\PaymentPtwentyfour;
use WirecardEE\Prestashop\Models\PaymentGuaranteedInvoiceRatepay;
use WirecardEE\Prestashop\Helper\OrderManager;

/**
 * Class WirecardPaymentGateway
 *
 * @extends PaymentModule
 * @since 1.0.0
 */
class WirecardPaymentGateway extends PaymentModule
{
    /**
     * Payment fields for configuration
     *
     * @var array
     * @since 1.0.0
     */
    private $config;

    /**
     * @var string
     * @since 1.0.0
     */
    protected $html;

    /**
     * WirecardPaymentGateway constructor.
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        require_once(_PS_MODULE_DIR_.'wirecardpaymentgateway'.DIRECTORY_SEPARATOR.'vendor'.
            DIRECTORY_SEPARATOR.'autoload.php');

        $this->name = 'wirecardpaymentgateway';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
        $this->author = 'Wirecard';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => '1.7.3.4');
        $this->bootstrap = true;
        $this->controllers = array('payment', 'validation', 'notify', 'return', 'ajax', 'creditcard', 'sepa');

        $this->is_eu_compatible = 1;
        $this->currencies = true;
        $this->currencies_mode = 'checkbox';

        parent::__construct();

        $this->displayName = $this->l('Wirecard Payment Processing Gateway');
        $this->description = $this->l('Wirecard Payment Processing Gateway Plugin for Prestashop.');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');

        $this->config = $this->getPaymentFields();
    }

    /**
     * Basic install routine
     *
     * @return bool
     * @since 1.0.0
     */
    public function install()
    {
        if (!parent::install()
            || !$this->registerHook('paymentOptions')
            || !$this->registerHook('paymentReturn')
            || !$this->registerHook('displayPaymentEU')
            || !$this->registerHook('actionFrontControllerSetMedia')
            || !$this->registerHook('actionPaymentConfirmation')
            || !$this->registerHook('displayOrderConfirmation')
            || !$this->setDefaults()) {
            return false;
        }

        if (!$this->createTable()) {
            return false;
        }

        $orderManager = new OrderManager($this);
        $orderManager->createOrderState(OrderManager::WIRECARD_OS_AUTHORIZATION);
        $orderManager->createOrderState(OrderManager::WIRECARD_OS_AWAITING);
        $orderManager->createOrderState(OrderManager::WIRECARD_OS_STARTING);

        $this->installTabs();

        return true;
    }

    /**
     * Basic uninstall routine
     *
     * @return bool
     * @since 1.0.0
     */
    public function uninstall()
    {
        if (!parent::uninstall()) {
            return false;
        }

        $this->uninstallTabs();

        return true;
    }

    /**
     * Register tabs
     *
     * @since 1.0.0
     */
    public function installTabs()
    {
        $tab = new Tab();
        $tab->active = 1;
        $tab->class_name = 'WirecardTransactions';
        $tab->name = array();
        foreach (Language::getLanguages(true) as $lang) {
            $tab->name[$lang['id_lang']] = 'Wirecard Transactions';
        }
        $tab->module = $this->name;
        $tab->add();
    }

    /**
     * Unregister tabs
     *
     * @since 1.0.0
     */
    public function uninstallTabs()
    {
        $id_tab = (int)Tab::getIdFromClassName('WirecardTransactions');
        if ($id_tab) {
            $tab = new Tab($id_tab);
            $tab->delete();
        }
    }

    /**
     * Getter for paymentfields from every payment model
     *
     * @return array
     * @since 1.0.0
     */
    public function getPaymentFields()
    {
        $payments = array();
        /** @var Payment $payment */
        foreach ($this->getPayments() as $payment) {
            array_push($payments, $payment->getFormFields());
        }
        return $payments;
    }

    /**
     * Create content on Wirecard Payment Processing Gateway settings page
     *
     * @return null|string
     * @since 1.0.0
     */
    public function getContent()
    {
        if (Tools::isSubmit('btnSubmit')) {
            $this->postProcess();
        }

        $this->context->smarty->assign(
            array(
                'module_dir' => $this->_path,
                'link' => $this->context->link,
                'ajax_configtest_url' => $this->context->link->getModuleLink('wirecardpaymentgateway', 'ajax')
            )
        );
        $this->html .= $this->displayWirecardPaymentGateway();
        $this->html .= $this->renderForm();

        return $this->html;
    }

    /**
     * Get values for configuration fields
     *
     * @return array
     * @since 1.0.0
     */
    public function getConfigFieldsValues()
    {
        $values = array();
        foreach ($this->getAllConfigurationParameters() as $parameter) {
            $val = Configuration::get($parameter['param_name']);
            if (isset($parameter['multiple']) && $parameter['multiple']) {
                if (!is_array($val)) {
                    $val = Tools::strlen($val) ? Tools::jsonDecode($val) : array();
                }
                $x = array();
                if (is_array($val)) {
                    foreach ($val as $v) {
                        $x[$v] = $v;
                    }
                    $pname = $parameter['param_name'] . '[]';
                    $values[$pname] = $x;
                }
            } else {
                $values[$parameter['param_name']] = $val;
            }
        }

        return $values;
    }

    /**
     * Get configuration parameters from config
     *
     * @return array
     * @since 1.0.0
     */
    public function getAllConfigurationParameters()
    {
        $params = array();
        foreach ($this->config as $group) {
            foreach ($group['fields'] as $f) {
                if ('hidden' == $f['type']) {
                    continue;
                }
                $f['param_name'] = $this->buildParamName(
                    $group['tab'],
                    $f['name']
                );
                $params[] = $f;
            }
        }

        return $params;
    }

    /**
     * Payment options hook
     *
     * @param $params
     * @return bool|void
     * @since 1.0.0
     */
    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }

        $result = array();
        /** @var Payment $paymentMethod */
        foreach ($this->getPayments() as $paymentMethod) {
            if (! $this->getConfigValue($paymentMethod->getType(), 'enabled')) {
                continue;
            }

            if (! $paymentMethod->isAvailable($this, $params['cart'])) {
                continue;
            }

            $paymentData = array(
                'paymentType' => $paymentMethod->getType(),
            );
            if ('invoice' == $paymentMethod->getType()) {
                $this->createRatepayScript($paymentMethod);
            }
            $payment = new PaymentOption();
            $payment->setCallToActionText($this->l($this->getConfigValue($paymentMethod->getType(), 'title')))
                ->setAction($this->context->link->getModuleLink($this->name, 'payment', $paymentData, true));
            if ($paymentMethod->getTemplateData()) {
                $this->context->smarty->assign($paymentMethod->getTemplateData());
            }

            if ($paymentMethod->getAdditionalInformationTemplate()) {
                $payment->setAdditionalInformation($this->fetch(
                    'module:' . $paymentMethod->getAdditionalInformationTemplate() . '.tpl'
                ));
            }

            $payment->setLogo(
                Media::getMediaPath(_PS_MODULE_DIR_ . $this->name . '/views/img/paymenttypes/'
                    . $paymentMethod->getType() . '.png')
            );
            $result[] = $payment;
        }

        //Implement action validation before payment
        return count($result) ? $result : false;
    }

    /**
     * Create ratepay script and device ident
     *
     * @param PaymentGuaranteedInvoiceRatepay $paymentMethod
     * @since 1.0.0
     */
    public function createRatepayScript($paymentMethod)
    {
        $merchantAccount = $this->getConfigValue('invoice', 'merchant_account_id');
        $deviceIdent = $paymentMethod->createDeviceIdent($merchantAccount);

        if (!isset($this->context->cookie->wirecardDeviceIdent)) {
            $this->context->cookie->wirecardDeviceIdent = $deviceIdent;
        }

        echo "<script language='JavaScript'>
          var di = {t:'" . $this->context->cookie->wirecardDeviceIdent ."',v:'WDWL',l:'Checkout'};
          </script>
          <script type='text/javascript' src='//d.ratepay.com/WDWL/di.js'>
          </script>
          <noscript>
          <link rel='stylesheet' type='text/css' href='//d.ratepay.com/di.css?t=" .
            $this->context->cookie->wirecardDeviceIdent . "&v=WDWL&l=Checkout'>
          </noscript>
          <object type='application/x-shockwave-flash' data='//d.ratepay.com/WDWL/c.swf' width='0' height='0'>
          <param name='movie' value='//d.ratepay.com/WDWL/c.swf' />
          <param name='flashvars' value='t=" . $this->context->cookie->wirecardDeviceIdent .
            "&v=WDWL'/><param name='AllowScriptAccess' value='always'/>
          </object>";

        $paymentMethod->setAdditionalInformationTemplate(
            'invoice',
            array('deviceIdent' => $this->context->cookie->wirecardDeviceIdent)
        );
    }

    /**
     * Get payment class from payment type
     *
     * @param $paymentType
     * @return bool|Payment
     * @since 1.0.0
     */
    public function getPaymentFromType($paymentType)
    {
        $payments = $this->getPayments();
        if ('ratepay-invoice' == $paymentType) {
            $paymentType = 'invoice';
        }
        if (array_key_exists($paymentType, $payments)) {
            return $payments[$paymentType];
        }

        return false;
    }

    /**
     * Build prefix for configuration entries
     *
     * @param $name
     * @param $field
     *
     * @return string
     * @since 1.0.0
     */
    public function buildParamName($name, $field)
    {
        return sprintf(
            'WIRECARD_PAYMENT_GATEWAY_%s_%s',
            Tools::strtoupper($name),
            Tools::strtoupper($field)
        );
    }

    /**
     * Get Configuration value for specific field
     *
     * @param $name
     * @param $field
     * @return mixed
     * @since 1.0.0
     */
    public function getConfigValue($name, $field)
    {
        return Configuration::get($this->buildParamName($name, $field));
    }

    /**
     * Create redirect Urls
     *
     * @param $paymentState
     * @return null
     * @since 1.0.0
     */
    public function createRedirectUrl($cartId, $paymentType, $paymentState)
    {
        $returnUrl = $this->context->link->getModuleLink(
            $this->name,
            'return',
            array(
                'id_cart' => $cartId,
                'payment_type' => $paymentType,
                'payment_state' => $paymentState,
            )
        );

        return $returnUrl;
    }

    /**
     * Create notification Urls
     *
     * @return null
     * @since 1.0.0
     */
    public function createNotificationUrl($cartId, $paymentType)
    {
        $returnUrl = $this->context->link->getModuleLink(
            $this->name,
            'notify',
            array(
                'id_cart' => $cartId,
                'payment_type' => $paymentType,
            )
        );

        return $returnUrl;
    }

    /**
     * Set the name to the payment type selected
     *
     * @param $params
     * @since 1.0.0
     */
    public function hookActionPaymentConfirmation($params)
    {
        $order = new Order($params['id_order']);
        $this->displayName = $order->payment;
    }

    /**
     * Display info text for Wirecard Payment Processing Gateway page
     *
     * @return string
     * @since 1.0.0
     */
    protected function displayWirecardPaymentGateway()
    {
        return $this->display(__FILE__, 'infos.tpl');
    }

    /**
     * return available country iso codes
     *
     * @return array
     * @since 1.0.0
     */
    protected function getCountries()
    {
        $cookie = $this->context->cookie;
        $countries = Country::getCountries($cookie->id_lang);
        $ret = array();
        foreach ($countries as $country) {
            $ret[] = array(
                'key' => $country['iso_code'],
                'value' => $country['name']
            );
        }
        return $ret;
    }

    /**
     * return available currency iso codes
     *
     * @return array
     * @since 1.0.0
     */
    protected function getCurrencies()
    {
        $currencies = Currency::getCurrencies();
        $ret = array();
        foreach ($currencies as $currency) {
            $ret[] = array(
                'key' => $currency['iso_code'],
                'value' => $currency['name']
            );
        }
        return $ret;
    }

    /**
     * Basic array of payment models
     *
     * @return array
     * @since 1.0.0
     */
    private function getPayments()
    {
        $payments = array(
            'paypal' => new PaymentPaypal(),
            'creditcard' => new PaymentCreditCard(),
            'sepa' => new PaymentSepa(),
            'ideal' => new PaymentIdeal(),
            'sofortbanking' => new PaymentSofort(),
            'poipia' => new PaymentPoiPia(),
            'invoice' => new PaymentGuaranteedInvoiceRatepay(),
            'alipay-xborder' => new PaymentAlipayCrossborder(),
            'p24' => new PaymentPtwentyfour()
        );

        return $payments;
    }

    /**
     * Save edited configuration values
     *
     * @since 1.0.0
     */
    private function postProcess()
    {
        if (Tools::isSubmit('btnSubmit')) {
            foreach ($this->getAllConfigurationParameters() as $parameter) {
                $val = Tools::getValue($parameter['param_name']);

                if (is_array($val)) {
                    $val = Tools::jsonEncode($val);
                }
                Configuration::updateValue($parameter['param_name'], $val);
            }
        }
        $this->html .= $this->displayConfirmation($this->l('Settings updated'));
    }

    /**
     * Render form including configuration values per payment
     *
     * @return mixed
     * @since 1.0.0
     */
    private function renderForm()
    {
        $radioType = 'switch';

        $radioOptions = array(
            array(
                'id' => 'active_on',
                'value' => 1,
                'label' => $this->l('Enabled')
            ),
            array(
                'id' => 'active_off',
                'value' => 0,
                'label' => $this->l('Disabled')
            )
        );

        $tempFields = $this->createInputFields($radioType, $radioOptions);
        $inputFields = $tempFields['inputFields'];
        $tabs = $tempFields['tabs'];

        $fields = array(
            'form' => array(
                'tabs' => $tabs,
                'legend' => array(
                    'title' => $this->l('Payment method settings'),
                    'icon' => 'icon-cogs'
                ),
                'input' => $inputFields,
                'submit' => array(
                    'title' => $this->l('Save')
                )
            ),
        );

        return $this->createForm($fields);
    }

    /**
     * Create input fields and tabs
     *
     * @param $radioType
     * @param $radioOptions
     * @return array
     * @since 1.0.0
     */
    private function createInputFields($radioType, $radioOptions)
    {
        $input_fields = array();
        $tabs = array();

        foreach ($this->config as $value) {
            $tabname = $value['tab'];
            $tabs[$tabname] = $tabname;
            foreach ($value['fields'] as $f) {
                if ('hidden' == $f['type']) {
                    continue;
                }
                $elem = array(
                    'name' => $this->buildParamName($tabname, $f['name']),
                    'label' => isset($f['label'])?$this->l($f['label']):'',
                    'tab' => $tabname,
                    'type' => $f['type'],
                    'required' => isset($f['required']) && $f['required']
                );

                if (isset($f['doc'])) {
                    $elem['desc'] = $f['doc'];
                }

                switch ($f['type']) {
                    case 'linkbutton':
                        $elem['buttonText'] = $f['buttonText'];
                        $elem['id'] = $f['id'];
                        $elem['method'] = $f['method'];
                        $elem['send'] = $f['send'];
                        break;

                    case 'text':
                        if (!isset($elem['class'])) {
                            $elem['class'] = 'fixed-width-xl';
                        }

                        if (isset($f['maxchar'])) {
                            $elem['maxlength'] = $elem['maxchar'] = $f['maxchar'];
                        }
                        break;

                    case 'onoff':
                        $elem['type'] = $radioType;
                        $elem['class'] = 't';
                        $elem['is_bool'] = true;
                        $elem['values'] = $radioOptions;
                        break;

                    case 'select':
                        if (isset($f['multiple'])) {
                            $elem['multiple'] = $f['multiple'];
                        }

                        if (isset($f['size'])) {
                            $elem['size'] = $f['size'];
                        }

                        if (isset($f['options'])) {
                            $optfunc = $f['options'];
                            $options = array();
                            if (is_array($optfunc)) {
                                $options = $optfunc;
                            } elseif (method_exists($this, $optfunc)) {
                                $options = $this->$optfunc();
                            }

                            $elem['options'] = array(
                                'query' => $options,
                                'id' => 'key',
                                'name' => 'value'
                            );
                        }
                        break;

                    default:
                        break;
                }

                $input_fields[] = $elem;
            }
        }
        return array('inputFields' => $input_fields, 'tabs' => $tabs);
    }

    /**
     * Create form via HelperFormCore
     *
     * @param $fields
     * @return mixed
     * @since 1.0.0
     */
    private function createForm($fields)
    {
        /** @var HelperFormCore $helper */
        $helper = new HelperForm();
        $helper->show_toolbar = false;

        /** @var LanguageCore $lang */
        $lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get(
            'PS_BO_ALLOW_EMPLOYEE_FORM_LANG'
        ) : 0;
        $helper->id = (int)Tools::getValue('id_carrier');
        $helper->module = $this;
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'btnSubmit';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFieldsValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id
        );

        return $helper->generateForm(array($fields));
    }

    /**
     * Set default configuration values
     *
     * @return bool
     * @since 1.0.0
     */
    private function setDefaults()
    {
        foreach ($this->config as $config) {
            foreach ($config['fields'] as $field) {
                if (array_key_exists('default', $field)) {
                    $name = $config['tab'];
                    $configParam = $this->buildParamName($name, $field['name']);
                    $defValue = $field['default'];
                    if (is_array($defValue)) {
                        $defValue = Tools::jsonEncode($defValue);
                    }

                    if (!Configuration::updateValue($configParam, $defValue)) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    /**
     * Create wirecard payment transaction table
     *
     * @return bool
     * @since 1.0.0
     */
    private function createTable()
    {
        $sql = 'CREATE TABLE IF NOT EXISTS  `' . _DB_PREFIX_ . 'wirecard_payment_gateway_tx` (';
        foreach ($this->getColumnDefs() as $column => $definitions) {
            $sql .= "\n"."\t" . $column . ' ';
            foreach ($definitions as $definition) {
                $sql .= $definition . ' ';
            }
            $sql .= ',';
        }
        $sql .= "\n".'PRIMARY KEY (`tx_id`)';
        $sql .= "\n" . ') ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

        return Db::getInstance()->execute($sql);
    }

    /**
     * Get transaction table columns
     *
     * @return array
     * @since 1.0.0
     */
    private function getColumnDefs()
    {
        return array(
            "tx_id" => array("INT(10) UNSIGNED", "NOT NULL", "AUTO_INCREMENT"),
            "transaction_id" => array("VARCHAR(36)", "NOT NULL"),
            "parent_transaction_id" => array("VARCHAR(36)", "NULL"),
            "order_id" => array("INT(10)", "NULL"),
            "cart_id" => array("INT(10) UNSIGNED", "NOT NULL"),
            "ordernumber" => array("VARCHAR(32)", "NULL"),
            "paymentmethod" => array("VARCHAR(32)", "NOT NULL"),
            "transaction_type" => array("VARCHAR(32)", "NOT NULL"),
            "transaction_state" => array("VARCHAR(32)", "NOT NULL"),
            "amount" => array("FLOAT", "NOT NULL"),
            "currency" => array("VARCHAR(3)", "NOT NULL"),
            "response" => array("TEXT", "NULL"),
            "created" => array("DATETIME", "NOT NULL"),
            "modified" => array("DATETIME", "NULL"),
        );
    }

    /**
     * Hook for media setter
     *
     * @return bool
     * @since 1.0.0
     */
    public function hookActionFrontControllerSetMedia()
    {
        $link = new Link;
        $parameters = array("action" => "getcreditcardconfig");
        $ajaxLink = $link->getModuleLink('wirecardpaymentgateway', 'creditcard', $parameters);
        $baseUrl = $this->getConfigValue('creditcard', 'base_url');
        Media::addJsDef(array('url' => $ajaxLink));
        $this->context->controller->addJquery();
        $this->context->controller->addJqueryUI('dialog');
        $this->context->controller->registerJavascript(
            'remote-bootstrap',
            $baseUrl  .'/engine/hpp/paymentPageLoader.js',
            array('server' => 'remote', 'position' => 'head', 'priority' => 20)
        );

        foreach ($this->getPayments() as $paymentMethod) {
            if ($paymentMethod->getLoadJs()) {
                $ajaxLink = $link->getModuleLink('wirecardpaymentgateway', $paymentMethod->getType());
                Media::addJsDef(array('ajax'.$paymentMethod->getType().'url' => $ajaxLink));
                $this->context->controller->addJS(
                    _PS_MODULE_DIR_ . $this->name . DIRECTORY_SEPARATOR . 'views'
                    . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . $paymentMethod->getType() . '.js'
                );
            }
        }

        return true;
    }

    /**
     * Show the payment information for PIA
     *
     * @param array $params
     * @return string
     * @since 1.0.0
     */
    public function hookOrderConfirmation($params)
    {
        if ($this->context->cookie->__get('pia-enabled')) {
            $currency = new Currency($params['order']->id_currency);
            $this->context->smarty->assign(
                array(
                    'amount' => $params['order']->total_paid,
                    'iban' => $this->context->cookie->__get('pia-iban'),
                    'bic' => $this->context->cookie->__get('pia-bic'),
                    'refId' => $this->context->cookie->__get('pia-reference-id'),
                    'currency' => $currency->iso_code
                )
            );
            return $this->display(
                __FILE__,
                DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'templates' .
                DIRECTORY_SEPARATOR . 'front' . DIRECTORY_SEPARATOR . 'pia.tpl'
            );
        }
    }
}