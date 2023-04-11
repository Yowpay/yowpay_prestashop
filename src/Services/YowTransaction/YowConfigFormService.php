<?php

namespace YowPayment\Services\YowTransaction;
use Configuration;
use Context;
use Exception;
use Language;
use Module;
use PrestaShop\PrestaShop\Core\Addon\Module\ModuleManagerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Tools;
use PrestaShopLogger;
use YowPayment\Services\Api\ApiService;


class YowConfigFormService
{
    /** @var ContainerInterface */
    private $container;

    /** @var Module */
    private $module;

    /**
     * @param ContainerInterface $container
     * @param Module $module
     */
    public function __construct
    (
        ContainerInterface  $container,
        Module $module
    )
    {
        $this->container = $container;
        $this->module = $module;
    }

    /**
     * Update general settings config values
     *
     * @return string
     */
    public function postProcessGeneralSettings()
    {
        Configuration::updateValue('CHEQUE_APP_PLUGIN_ENABLED', Tools::getValue('CHEQUE_APP_PLUGIN_ENABLED'));

        $languages = Language::getLanguages();
        $values = [];
        foreach ($languages as $language) {
            $values['CHEQUE_APP_CHECKOUT_TITLE'][$language['id_lang']] = Tools::getValue('CHEQUE_APP_CHECKOUT_TITLE_' . $language['id_lang']);
            $values['CHEQUE_APP_CHECKOUT_DESCRIPTION'][$language['id_lang']] = Tools::getValue('CHEQUE_APP_CHECKOUT_DESCRIPTION_' . $language['id_lang']);
        }

        Configuration::updateValue('CHEQUE_APP_CHECKOUT_TITLE', $values['CHEQUE_APP_CHECKOUT_TITLE']);
        Configuration::updateValue('CHEQUE_APP_CHECKOUT_DESCRIPTION', $values['CHEQUE_APP_CHECKOUT_DESCRIPTION']);
        Configuration::updateValue('CHEQUE_APP_FULL_EXPLANATION', Tools::getValue('CHEQUE_APP_FULL_EXPLANATION'));

        if (Configuration::get('CHEQUE_APP_PLUGIN_ENABLED') === '0') {
            $moduleManagerBuilder = ModuleManagerBuilder::getInstance();
            $moduleManager = $moduleManagerBuilder->build();

            $moduleManager->disable('yowpayment');
        }

        return $this->module->l('Settings updated');
    }

    /**
     * Update production settings config values
     *
     * @return array
     */
    public function postProcessProductionSettings()
    {
        Configuration::updateValue('CHEQUE_APP_SECRET', trim(Tools::getValue('CHEQUE_APP_SECRET')));
        Configuration::updateValue('CHEQUE_APP_TOKEN', trim(Tools::getValue('CHEQUE_APP_TOKEN')));

        if (!$this->sendPaymentConfigUrls()) {
            return [
                'status' => 'error',
                'message' => $this->module->l('API Credentials are not correct')
            ];
        }

        if (!$this->getAccountConnection()) {
            return [
                'status' => 'error',
                'message' => $this->module->l('There was an error while connecting to bank account')
            ];
        }

        return [
            'status' => 'success',
            'message' => $this->module->l('Settings updated')
        ];
    }

    /**
     * @return bool
     */
    public function getAccountConnection()
    {
        $details = [
            'timestamp' => time()
        ];

        /** @var ApiService $apiService */
        $apiService = $this->container->get('yow_transactions.api.service');

        PrestaShopLogger::addLog("Requesting account data from API");
        $connectionResult = $apiService->getBankData($details);
        if (!$connectionResult) {
            PrestaShopLogger::addLog("Failed to get bank account details");
            return false;
        }

        PrestaShopLogger::addLog("Account data updated successfully");
        if (!isset($connectionResult['statusCode'])) {
            PrestaShopLogger::addLog("API haven't provided account status");
            return false;
        }
        $accountStatus = $this->resolveAccountStatus($connectionResult['statusCode']);

        if ($accountStatus == 'active') {
            Configuration::updateValue('CHEQUE_APP_ACCOUNT_OWNER', $connectionResult['accountHolder']);
            Configuration::updateValue('CHEQUE_APP_ACCOUNT_IBAN', $connectionResult['iban']);
            Configuration::updateValue('CHEQUE_APP_ACCOUNT_SWIFT', $connectionResult['swift']);
            Configuration::updateValue('CHEQUE_APP_ACCOUNT_BANKING_EXPIRATION_TIME', $connectionResult['consentExpirationTime']);
            Configuration::updateValue('CHEQUE_APP_ACCOUNT_BANKING_REMAINING_TIME', $connectionResult['remainingTime']);
        }

        return true;
    }

    /**
     * Returns general settings form fields
     *
     * @return array[]
     */
    public function renderGeneralSettingsForm()
    {
        return [
            'form' => [
                'legend' => [
                    'title' => $this->module->l('General Settings'),
                ],
                'input' => [
                    [
                        'type' => 'switch',
                        'label' => $this->module->l('Enable YowPay?'),
                        'desc' => $this->module->l('This controls whether or not «Pay with SEPA Instant Transfer - by YowPay» is enable in the payment mode list within Prestashop'),
                        'name' => 'CHEQUE_APP_PLUGIN_ENABLED',
                        'required' => true,
                        'values' => [
                            [
                                'id' => 'plugin_enabled',
                                'value' => 1,
                                'label' => $this->module->l('Yes'),
                            ],
                            [
                                'id' => 'plugin_disabled',
                                'value' => 0,
                                'label' => $this->module->l('No'),
                            ]
                        ],
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->module->l('Title'),
                        'desc' => $this->module->l('This controls the title which the user sees during checkout'),
                        'name' => 'CHEQUE_APP_CHECKOUT_TITLE',
                        'required' => true,
                        'lang' => true,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->module->l('Description'),
                        'desc' => $this->module->l('This controls the description which the user sees during checkout'),
                        'name' => 'CHEQUE_APP_CHECKOUT_DESCRIPTION',
                        'required' => true,
                        'lang' => true,
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->module->l('Display full explanation?'),
                        'desc' => $this->module->l('Display full explanation of the YowPay process with icons in the payment description, during the checkout'),
                        'name' => 'CHEQUE_APP_FULL_EXPLANATION',
                        'required' => true,
                        'values' => [
                            [
                                'id' => 'plugin_enabled',
                                'value' => 1,
                                'label' => $this->module->l('Yes'),
                            ],
                            [
                                'id' => 'plugin_disabled',
                                'value' => 0,
                                'label' => $this->module->l('No'),
                            ]
                        ],
                    ],
                    [
                        'type' => 'html',
                        'html_content' => Context::getContext()->smarty->fetch(_PS_MODULE_DIR_ . 'yowpayment/views/templates/partials/payment_explanation.tpl'),
                        'name' => 'CHEQUE_APP_FULL_EXPLANATION_VIEW',
                    ]

                ],
                'submit' => [
                    'title' => $this->module->l('Save'),
                    'icon' => false,
                    'class' => 'btn btn-primary col-lg-offset-4'
                ],
            ],
        ];
    }

    /**
     * returns production settings form fields
     *
     * @return array[]
     */
    public function renderProductionSettingsForm()
    {
        return [
            'form' => [
                'legend' => [
                    'title' => $this->module->l('Production Settings')
                ],
                'input' => [
                    [
                        'type' => 'text',
                        'label' => $this->module->l('App Token'),
                        'desc' => $this->module->l('Enter the App Token created in your YowPay account and related to this E-commerce website'),
                        'name' => 'CHEQUE_APP_TOKEN',
                        'required' => true,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->module->l('App Secret'),
                        'desc' => $this->module->l('Enter the App Secret created in your YowPay account and related to this E-commerce website'),
                        'name' => 'CHEQUE_APP_SECRET',
                        'required' => true,
                        'class' => 'app-secret-field'
                    ],
                ],
                'submit' => [
                    'title' => $this->module->l('Store credentials and link with YowPay'),
                    'class' => 'btn btn-primary col-lg-offset-4',
                    'icon' => false
                ],
            ]
        ];
    }

    /**
     * Send payment necessary urls to YowPay system via API
     *
     * @return bool
     */
    private function sendPaymentConfigUrls()
    {

        $shopUrl = Tools::getCurrentUrlProtocolPrefix() . Tools::getShopDomain();

        try {
            PrestaShopLogger::addLog("Trying to save necessary urls");
            $configDetails = [
                'returnUrl' => $shopUrl . '/index.php?fc=module&module=yowpayment&controller=success',
                'cancelUrl' => $shopUrl . '/index.php?fc=module&module=yowpayment&controller=cancel',
                'webhookUrl' => $shopUrl . '/index.php?fc=module&module=yowpayment&controller=hook',
                'timestamp' => time()
            ];
            $apiService = $this->container->get('yow_transactions.api.service');

            if (!$apiService->setPaymentConfigLinks($configDetails)) {
                PrestaShopLogger::addLog("API responded an error!");
                return false;
            }
        } catch (Exception $exception) {
            PrestaShopLogger::addLog("Failed to get transactions config service from container");
            return false;
        }

        PrestaShopLogger::addLog("Urls are saved!");

        return true;
    }

    /**
     * @param $accountStatus
     * @return string|void
     */
    private function resolveAccountStatus($accountStatus)
    {
        switch ($accountStatus) {
            case '0':
                Configuration::updateValue('CHEQUE_APP_ACCOUNT_BANKING_STATUS', 'not_provided');
                return 'not_provided';
            case '1':
                Configuration::updateValue('CHEQUE_APP_ACCOUNT_BANKING_STATUS', 'active');
                return 'active';
            case'2':
                Configuration::updateValue('CHEQUE_APP_ACCOUNT_BANKING_STATUS', 'expired');
                return 'expired';
            case '3':
                Configuration::updateValue('CHEQUE_APP_ACCOUNT_BANKING_STATUS', 'lost');
                return 'lost';
        }
    }

}