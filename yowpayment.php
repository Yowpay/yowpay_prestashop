<?php
/**
 * MIT License
 * Copyright (c) 2023 Yowpay - Peer to Peer SEPA Payments made easy

 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:

 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.

 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 * @author   YowPay SARL
 * @copyright  YowPay SARL
 * @license  MIT License
 */
require_once _PS_MODULE_DIR_ . 'yowpayment/vendor/autoload.php';

if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;
use YowPayment\Services\YowTransaction\YowConfigFormService;
use YowPayment\Services\YowTransaction\YowTransactionService;

class YowPayment extends PaymentModule
{
    /** @var YowConfigFormService */
    private $yowConfigFormService;

    /** @var YowTransactionService */
    private $yowTransactionService;

    public function __construct()
    {
        $this->module_key = '69043e926f5867f43aa03604b9dfb670';
        $this->name = 'yowpayment';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.2';
        $this->ps_versions_compliancy = ['min' => '1.7', 'max' => _PS_VERSION_];
        $this->author = 'YowPay';
        $this->controllers = ['validation', 'hook', 'success', 'cancel'];
        $this->is_eu_compatible = 1;
        $this->currencies = true;
        $this->currencies_mode = 'checkbox';
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('YowPay - SEPA Instant Transfer');
        $this->description = $this->l('YowPay Payment Gateway Plug-in for PrestaShop');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');

        if (!count(Currency::checkPaymentCurrencies($this->id))) {
            $this->warning = $this->l('No currency has been set for this module.');
        }

        $this->yowConfigFormService = new YowConfigFormService($this);
        $this->yowTransactionService = new YowTransactionService();
    }

    /**
     * Actions performed while installing module
     *
     * @return bool
     */
    public function install()
    {
        if (!parent::install()) {
            PrestaShopLogger::addLog('Failed to install module');

            return false;
        }
        if (!$this->registerHook('displayPaymentReturn')) {
            PrestaShopLogger::addLog('Failed to register displayPaymentReturn hook');

            return false;
        }
        if (!$this->registerHook('paymentOptions')) {
            PrestaShopLogger::addLog('Failed to register paymentOptions hook');

            return false;
        }
        if (!$this->registerHook('displayBackOfficeHeader')) {
            PrestaShopLogger::addLog('Failed to register displayBackOfficeHeader hook');

            return false;
        }
        if (!$this->registerHook('actionCartUpdateQuantityBefore')) {
            PrestaShopLogger::addLog('Failed to register actionCartUpdateQuantityBefore hook');

            return false;
        }
        if (version_compare(_PS_VERSION_, '1.7.7.0', '<')) {
            if (!$this->registerHook('actionFrontControllerAfterInit')) {
                PrestaShopLogger::addLog('Failed to register actionFrontControllerAfterInit hook');

                return false;
            }
        } else {
            if (!$this->registerHook('actionFrontControllerInitAfter')) {
                PrestaShopLogger::addLog('Failed to register actionFrontControllerInitAfter hook');

                return false;
            }
        }
        if (!$this->createTables()) {
            PrestaShopLogger::addLog('Failed to create yow payment necessary tables');

            return false;
        }
        if (!$this->addOrderState()) {
            PrestaShopLogger::addLog('Failed to create yow payment order status');

            return false;
        }

        $this->setDefaults();

        return true;
    }

    /**
     * Actions performed while uninstalling module
     *
     * @param bool $keep
     *
     * @return bool
     */
    public function uninstall($keep = true)
    {
        if (!parent::uninstall()) {
            PrestaShopLogger::addLog('Failed to uninstall Yow Payment module');

            return false;
        }
        if (!$keep) {
            if (!$this->deleteTables()) {
                PrestaShopLogger::addLog('Failed to delete Yow Payment tables');

                return false;
            }
        }
        if (!$this->unregisterHook('displayPaymentReturn')) {
            PrestaShopLogger::addLog('Failed to unregister displayPaymentReturn hook');

            return false;
        }
        if (!$this->unregisterHook('paymentOptions')) {
            PrestaShopLogger::addLog('Failed to unregister paymentOptions hook');

            return false;
        }
        if (!$this->unregisterHook('displayBackOfficeHeader')) {
            PrestaShopLogger::addLog('Failed to unregister displayBackOfficeHeader hook');

            return false;
        }
        if (version_compare(_PS_VERSION_, '1.7.7.0', '<')) {
            if (!$this->unregisterHook('actionFrontControllerAfterInit')) {
                PrestaShopLogger::addLog('Failed to register actionFrontControllerAfterInit hook');

                return false;
            }
        } else {
            if (!$this->unregisterHook('actionFrontControllerInitAfter')) {
                PrestaShopLogger::addLog('Failed to register actionFrontControllerInitAfter hook');

                return false;
            }
        }

        return true;
    }

    /**
     * Returns the module configuration page
     *
     * @return string
     */
    public function getContent()
    {
        $this->context->controller->addJS($this->_path . 'views/js/yowpay.js');
        $this->_html = '';

        if (Tools::isSubmit('btnSubmit')) {
            $this->postValidationGeneralSettings();

            if (!isset($this->_postErrors)) {
                $this->_html .= $this->displayConfirmation($this->yowConfigFormService->postProcessGeneralSettings());
            } else {
                foreach ($this->_postErrors as $err) {
                    $this->_html .= $this->displayError($err);
                }
            }
        }

        if (Tools::isSubmit('btnSave')) {
            $this->postValidationProductionSettings();

            if (!isset($this->_postErrors)) {
                $productionSettingsResponse = $this->yowConfigFormService->postProcessProductionSettings();
                if ($productionSettingsResponse['status'] === 'error') {
                    $this->_html .= $this->displayError($productionSettingsResponse['message']);
                } else {
                    $this->_html .= $this->displayConfirmation($productionSettingsResponse['message']);
                }
            } else {
                foreach ($this->_postErrors as $err) {
                    $this->_html .= $this->displayError($err);
                }
            }
        }

        if (Tools::isSubmit('btnConnect')) {
            if (!$this->yowConfigFormService->getAccountConnection()) {
                $this->_html .= $this->displayError($this->l('There was an error while connecting to bank account'));
            }

            $this->smarty->assign([
                'url' => $this->context->link->getAdminLink('AdminModules', false) . '&token=' . Tools::getAdminTokenLite('AdminModules') . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name,
                'accountOwner' => !empty(Configuration::get('CHEQUE_APP_ACCOUNT_OWNER')) ? Configuration::get('CHEQUE_APP_ACCOUNT_OWNER') : 'N\A',
                'iban' => !empty(Configuration::get('CHEQUE_APP_ACCOUNT_IBAN')) ? Configuration::get('CHEQUE_APP_ACCOUNT_IBAN') : 'N\A',
                'swift' => !empty(Configuration::get('CHEQUE_APP_ACCOUNT_SWIFT')) ? Configuration::get('CHEQUE_APP_ACCOUNT_SWIFT') : 'N\A',
                'expirationTime' => !empty(Configuration::get('CHEQUE_APP_ACCOUNT_BANKING_EXPIRATION_TIME')) ? Configuration::get('CHEQUE_APP_ACCOUNT_BANKING_EXPIRATION_TIME') : 'N\A',
                'remainingTime' => !empty(Configuration::get('CHEQUE_APP_ACCOUNT_BANKING_REMAINING_TIME')) ? Configuration::get('CHEQUE_APP_ACCOUNT_BANKING_REMAINING_TIME') : 'N\A',
                'accountStatus' => Configuration::get('CHEQUE_APP_ACCOUNT_BANKING_STATUS'),
            ]);

            echo $this->displayBankConnection();
            exit;
        }

        $this->_html .= $this->displayCheck();
        $this->_html .= $this->renderForms();
        $this->_html .= $this->displayBankConnection();

        return $this->_html;
    }

    /**
     * register our payment method in payment options
     *
     * @throws SmartyException
     */
    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return null;
        }

        $cart = $params['cart'];
        if (!$this->yowTransactionService->isSupportCurrency($cart->id_currency)) {
            return null;
        }

        return [
            $this->getYowPaymentOption(),
        ];
    }

    /**
     * We must register displayPaymentReturn hook in install process of module
     * This function calls when displayPaymentReturn hook executes
     * There is no necessary functionality to perform, so it's empty
     *
     * @return void
     */
    public function hookDisplayPaymentReturn()
    {
    }

    /**
     * We must register paymentReturn hook in install process of module
     * This function calls when paymentReturn hook executes
     * There is no necessary functionality to perform, so it's empty
     *
     * @return void
     */
    public function hookPaymentReturn()
    {
    }

    /**
     * We register this hook to recover the cart in the order page when the payment is not confirmed
     *
     * @param $params
     *
     * @return void
     */
    public function hookActionFrontControllerInitAfter($params)
    {
        if (get_class($params['controller']) === 'OrderController') {
            header('Cache-Control: no-store, no-cache, must-revalidate');
            $this->cartConversion();
        }
    }

    /**
     * This is the same hook as hookActionFrontControllerInitAfter, but for versions older 1.7.7.0
     *
     * @return void
     */
    public function hookActionFrontControllerAfterInit()
    {
        $orderLink = explode('/', $_SERVER['REQUEST_URI']);
        $isOrderPage = count($orderLink) > 0 && isset($orderLink[count($orderLink) - 1]) && $orderLink[count($orderLink) - 1] === 'order';

        if ($isOrderPage) {
            header('Cache-Control: no-store, no-cache, must-revalidate');
            $this->cartConversion();
        }
    }

    /**
     * This hook is registered before updating cart product quantity
     *
     * @return void
     */
    public function hookActionCartUpdateQuantityBefore()
    {
        if ($this->context->cookie->wentToYp) {
            $this->context->cookie->wentToYp = false;
        }
    }

    /**
     * In installation process of module we register the displayBackOfficeHeader hook
     * It's necessary to add some js to our config page
     *
     * @return void
     */
    public function hookDisplayBackOfficeHeader()
    {
        if (Tools::getValue('configure') == $this->name) {
            $this->context->controller->addJS($this->_path . 'views/js/yowpay.js');
        }
    }

    /**
     * @return void
     */
    private function cartConversion()
    {
        if ($this->context->cookie->wentToYp) {
            $cartId = $this->context->cookie->cart_id;
            $conversionStatus = $this->yowTransactionService->convertOrderToCart($cartId);

            if (isset($conversionStatus['errors'])) {
                $this->_errors = $conversionStatus['errors'];
            }
            if (isset($this->_errors) && count($this->_errors) > 0) {
                $notifications = json_encode([
                    'error' => $this->_errors,
                ]);
                $this->context->cookie->wentToYp = false;
                $_SESSION['notifications'] = $notifications;
                Tools::redirect(__PS_BASE_URI__);
            }
            $this->context->cookie->wentToYp = false;
            Tools::redirect($conversionStatus['redirectUrl']);
        }
    }

    /**
     * Renders settings forms
     *
     * @return string
     */
    private function renderForms()
    {
        $helper = $this->getHelperForm();
        $settingsForm = '';

        $helper->submit_action = 'btnSubmit';
        $settingsForm .= $helper->generateForm([$this->yowConfigFormService->renderGeneralSettingsForm()]);

        $helper->submit_action = 'btnSave';
        $settingsForm .= $helper->generateForm([$this->yowConfigFormService->renderProductionSettingsForm()]);

        return $settingsForm;
    }

    /**
     * @return bool
     */
    private function deleteTables()
    {
        return Db::getInstance()->execute('
			DROP TABLE IF EXISTS
			`' . _DB_PREFIX_ . 'yow_transactions`'
        );
    }

    /**
     * @return bool
     */
    private function createTables()
    {
        return Db::getInstance()->execute('CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'yow_transactions`(
           `id` INT NOT NULL AUTO_INCREMENT,
          `order_id` INT,
          `transaction_id` INT,
          `transaction_code` VARCHAR (64),
          `price` FLOAT,
          `status` VARCHAR (64),
          `sender_iban` VARCHAR (255) NULL,
          `sender_swift` VARCHAR (255) NULL,
          `sender_account_holder` VARCHAR (255) NULL,
          `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL,
          `updated_at` TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NULL,
          PRIMARY KEY (`id`)
        )');
    }

    /**
     * @return PaymentOption
     *
     * @throws SmartyException
     */
    private function getYowPaymentOption()
    {
        $yowPaymentOption = new PaymentOption();
        $yowPaymentOption->setCallToActionText(Configuration::get('CHEQUE_APP_CHECKOUT_TITLE', $this->context->language->id));
        $yowPaymentOption->setAction($this->context->link->getModuleLink($this->name, 'validation', [], true));

        $this->context->smarty->assign([
            'paymentOptionDetails' => Configuration::get('CHEQUE_APP_CHECKOUT_DESCRIPTION', $this->context->language->id) ?: '',
            'displayFullExplanation' => Configuration::get('CHEQUE_APP_FULL_EXPLANATION'),
        ]);

        $yowPaymentOption->setAdditionalInformation($this->context->smarty->fetch('module:yowpayment/views/templates/front/payment_infos.tpl'));

        return $yowPaymentOption;
    }

    /**
     * Get module config fields
     *
     * @return array
     */
    private function getConfigFieldsValues()
    {
        $configFields = [
            'CHEQUE_APP_SECRET' => Tools::getValue('CHEQUE_APP_SECRET', Configuration::get('CHEQUE_APP_SECRET')),
            'CHEQUE_APP_TOKEN' => Tools::getValue('CHEQUE_APP_TOKEN', Configuration::get('CHEQUE_APP_TOKEN')),
            'CHEQUE_APP_PLUGIN_ENABLED' => Tools::getValue('CHEQUE_APP_PLUGIN_ENABLED', Configuration::get('CHEQUE_APP_PLUGIN_ENABLED')),
            'CHEQUE_APP_FULL_EXPLANATION' => Tools::getValue('CHEQUE_APP_FULL_EXPLANATION', Configuration::get('CHEQUE_APP_FULL_EXPLANATION')),
            'CHEQUE_APP_ACCOUNT_OWNER' => Configuration::get('CHEQUE_APP_FULL_EXPLANATION'),
            'CHEQUE_APP_ACCOUNT_IBAN' => Configuration::get('CHEQUE_APP_ACCOUNT_IBAN'),
            'CHEQUE_APP_ACCOUNT_SWIFT' => Configuration::get('CHEQUE_APP_ACCOUNT_SWIFT'),
            'CHEQUE_APP_ACCOUNT_BANKING_STATUS' => Configuration::get('CHEQUE_APP_ACCOUNT_BANKING_STATUS'),
            'CHEQUE_APP_ACCOUNT_BANKING_EXPIRATION_TIME' => Configuration::get('CHEQUE_APP_ACCOUNT_BANKING_EXPIRATION_TIME'),
            'CHEQUE_APP_ACCOUNT_BANKING_REMAINING_TIME' => Configuration::get('CHEQUE_APP_ACCOUNT_BANKING_REMAINING_TIME'),
        ];

        $languages = Language::getLanguages(false);
        foreach ($languages as $language) {
            $configFields['CHEQUE_APP_CHECKOUT_TITLE'][$language['id_lang']] = Tools::getValue('CHEQUE_APP_CHECKOUT_TITLE_' . $language['id_lang'], Configuration::get('CHEQUE_APP_CHECKOUT_TITLE', $language['id_lang']));
            $configFields['CHEQUE_APP_CHECKOUT_DESCRIPTION'][$language['id_lang']] = Tools::getValue('CHEQUE_APP_CHECKOUT_DESCRIPTION_' . $language['id_lang'], Configuration::get('CHEQUE_APP_CHECKOUT_DESCRIPTION', $language['id_lang']));
        }

        return $configFields;
    }

    /**
     * Validate general settings form
     *
     * @return void
     */
    private function postValidationGeneralSettings()
    {
        $languages = Language::getLanguages(false);

        foreach ($languages as $language) {
            if (Tools::isEmpty(Tools::getValue('CHEQUE_APP_CHECKOUT_TITLE_' . $language['id_lang']))) {
                $this->_postErrors[] = $this->l('The "Title" field is required for language ' . $language['iso_code']);
            }
            if (Tools::isEmpty(Tools::getValue('CHEQUE_APP_CHECKOUT_DESCRIPTION_' . $language['id_lang']))) {
                $this->_postErrors[] = $this->l('The "Description" field is required for language ' . $language['iso_code']);
            }
        }

        if (Tools::getValue('CHEQUE_APP_PLUGIN_ENABLED') === false) {
            $this->_postErrors[] = $this->l('The "Enabled" field is required.');
        }

        if (Tools::getValue('CHEQUE_APP_FULL_EXPLANATION') === false) {
            $this->_postErrors[] = $this->l('The "Explanation" field is required.');
        }
    }

    /**
     * Validates production settings form
     *
     * @return void
     */
    private function postValidationProductionSettings()
    {
        if (!Tools::getValue('CHEQUE_APP_SECRET')) {
            $this->_postErrors[] = $this->l('The "App Secret" field is required.');
        }
        if (!Tools::getValue('CHEQUE_APP_TOKEN')) {
            $this->_postErrors[] = $this->l('The "App Token" field is required.');
        }
    }

    /**
     * Returns general plugin information view
     *
     * @return false|string
     */
    private function displayCheck()
    {
        return $this->display(__FILE__, './views/templates/hook/infos.tpl');
    }

    /**
     * Returns bank connection settings view
     *
     * @return false|string
     */
    private function displayBankConnection()
    {
        $accountStatus = Configuration::get('CHEQUE_APP_ACCOUNT_BANKING_STATUS');

        $friendlyAccountStatus = strtoupper(str_replace('_', ' ', $accountStatus));

        if ($accountStatus == 'active') {
            $friendlyAccountStatus = YowTransactionService::ACCOUNT_STATUS_CONNECTED;
        }

        $this->smarty->assign([
            'url' => $this->context->link->getAdminLink('AdminModules', false) . '&token=' . Tools::getAdminTokenLite('AdminModules') . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name,
            'accountOwner' => !empty(Configuration::get('CHEQUE_APP_ACCOUNT_OWNER')) ? Configuration::get('CHEQUE_APP_ACCOUNT_OWNER') : 'N\A',
            'iban' => !empty(Configuration::get('CHEQUE_APP_ACCOUNT_IBAN')) ? Configuration::get('CHEQUE_APP_ACCOUNT_IBAN') : 'N\A',
            'swift' => !empty(Configuration::get('CHEQUE_APP_ACCOUNT_SWIFT')) ? Configuration::get('CHEQUE_APP_ACCOUNT_SWIFT') : 'N\A',
            'expirationTime' => !empty(Configuration::get('CHEQUE_APP_ACCOUNT_BANKING_EXPIRATION_TIME')) ? Configuration::get('CHEQUE_APP_ACCOUNT_BANKING_EXPIRATION_TIME') : 'N\A',
            'remainingTime' => !empty(Configuration::get('CHEQUE_APP_ACCOUNT_BANKING_REMAINING_TIME')) ? Configuration::get('CHEQUE_APP_ACCOUNT_BANKING_REMAINING_TIME') : 'N\A',
            'accountStatus' => $friendlyAccountStatus,
        ]);

        return $this->display(__FILE__, './views/templates/hook/connection.tpl');
    }

    /**
     * Returns helper form object, which is used to render forms on module config page
     *
     * @return HelperForm
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    private function getHelperForm()
    {
        $lang = new Language((int) Configuration::get('PS_LANG_DEFAULT'));

        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->id = (int) Tools::getValue('id_carrier');
        $helper->default_form_language = $lang->id;
        $helper->identifier = $this->identifier;
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false) . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = [
            'fields_value' => $this->getConfigFieldsValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        ];

        return $helper;
    }

    /**
     * set default config values
     *
     * @return void
     */
    private function setDefaults()
    {
        $languages = Language::getLanguages(false);
        $values = [];

        foreach ($languages as $language) {
            $values['CHEQUE_APP_CHECKOUT_TITLE'][$language['id_lang']] = 'YowPay Instant SEPA';
            $values['CHEQUE_APP_CHECKOUT_DESCRIPTION'][$language['id_lang']] = 'Peer to Peer SEPA Payments made easy';
        }

        Configuration::updateValue('CHEQUE_APP_CHECKOUT_TITLE', $values['CHEQUE_APP_CHECKOUT_TITLE']);
        Configuration::updateValue('CHEQUE_APP_CHECKOUT_DESCRIPTION', $values['CHEQUE_APP_CHECKOUT_DESCRIPTION']);
        Configuration::updateValue('CHEQUE_APP_PLUGIN_ENABLED', '1');
        Configuration::updateValue('CHEQUE_APP_FULL_EXPLANATION', '1');
        Configuration::updateValue('CHEQUE_APP_ACCOUNT_BANKING_STATUS', 'not_connected');
    }

    /**
     * @return bool
     */
    private function addOrderState()
    {
        if (!Configuration::get('YOWPAY_OS_WAITING') || !Validate::isLoadedObject(new OrderState(Configuration::get('YOWPAY_OS_WAITING')))) {
            $order_state = new OrderState();
            $order_state->name = [];
            foreach (Language::getLanguages() as $language) {
                $order_state->name[$language['id_lang']] = 'Awaiting for YowPay payment';
            }
            $order_state->send_email = false;
            $order_state->color = '#4169E1';
            $order_state->hidden = false;
            $order_state->delivery = false;
            $order_state->logable = false;
            $order_state->invoice = false;
            $order_state->module_name = $this->name;
            try {
                $order_state->add();
            } catch (PrestaShopDatabaseException|PrestaShopException $e) {
                PrestaShopLogger::addLog('Failed to add the yowpay order state');

                return false;
            }

            if (Shop::isFeatureActive()) {
                $shops = Shop::getShops();
                foreach ($shops as $shop) {
                    Configuration::updateValue('YOWPAY_OS_WAITING', (int) $order_state->id, false, null, (int) $shop['id_shop']);
                }
            } else {
                Configuration::updateValue('YOWPAY_OS_WAITING', (int) $order_state->id);
            }
        }

        return true;
    }
}
