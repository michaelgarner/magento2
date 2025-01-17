<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @copyright   Copyright (c) 2014 X.commerce, Inc. (http://www.magentocommerce.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
namespace Magento\Tax\Model\Sales\Total\Quote;

use Magento\Tax\Model\Config;
use Magento\Tax\Model\Calculation;

class SetupUtil
{
    /**
     * Default tax related configurations
     *
     * @var array
     */
    protected $defaultConfig = [
        Config::CONFIG_XML_PATH_SHIPPING_TAX_CLASS => '0',
        Config::CONFIG_XML_PATH_PRICE_INCLUDES_TAX => 0, //Excluding tax
        Config::CONFIG_XML_PATH_SHIPPING_INCLUDES_TAX => 0, //Excluding tax
        Config::CONFIG_XML_PATH_BASED_ON => 'shipping', // or 'billing'
        Config::CONFIG_XML_PATH_APPLY_ON => '0',
        Config::CONFIG_XML_PATH_APPLY_AFTER_DISCOUNT => '0',
        Config::CONFIG_XML_PATH_DISCOUNT_TAX => '0',
        Config::XML_PATH_ALGORITHM => Calculation::CALC_TOTAL_BASE,
        //@TODO: add config for cross border trade
    ];

    const TAX_RATE_TX = 'tax_rate_tx';
    const TAX_RATE_AUSTIN = 'tax_rate_austin';
    const TAX_RATE_SHIPPING = 'tax_rate_shipping';
    const TAX_STORE_RATE = 'tax_store_rate';
    const REGION_TX = '57';
    const REGION_CA = '12';
    const COUNTRY_US = 'US';
    const AUSTIN_POST_CODE = '79729';

    /**
     * Tax rates
     *
     * @var array
     */
    protected $taxRates = [
        self::TAX_RATE_TX => [
            'data' => [
                'tax_country_id' => self::COUNTRY_US,
                'tax_region_id' => self::REGION_TX,
                'tax_postcode' => '*',
                'code' => self::TAX_RATE_TX,
                'rate' => '20',
            ],
            'id' => null,
        ],
        self::TAX_RATE_AUSTIN => [
            'data' => [
                'tax_country_id' => self::COUNTRY_US,
                'tax_region_id' => self::REGION_TX,
                'tax_postcode' => self::AUSTIN_POST_CODE,
                'code' => self::TAX_RATE_AUSTIN,
                'rate' => '5',
            ],
            'id' => null,
        ],
        self::TAX_RATE_SHIPPING => [
            'data' => [
                'tax_country_id' => self::COUNTRY_US,
                'tax_region_id' => '*',
                'tax_postcode' => '*',
                'code' => self::TAX_RATE_SHIPPING,
                'rate' => '7.5',
            ],
            'id' => null,
        ],
        self::TAX_STORE_RATE => [
            'data' => [
                'tax_country_id' => self::COUNTRY_US,
                'tax_region_id' => self::REGION_CA,
                'tax_postcode' => '*',
                'code' => self::TAX_STORE_RATE,
                'rate' => '8.25',
            ],
            'id' => null,
        ],
    ];

    const PRODUCT_TAX_CLASS_1 = 'product_tax_class_1';
    const PRODUCT_TAX_CLASS_2 = 'product_tax_class_2';
    const SHIPPING_TAX_CLASS = 'shipping_tax_class';

    /**
     * List of product tax class that will be created
     *
     * @var array
     */
    protected $productTaxClasses = [
        self::PRODUCT_TAX_CLASS_1 => null,
        self::PRODUCT_TAX_CLASS_2 => null,
        self::SHIPPING_TAX_CLASS => null,
    ];

    const CUSTOMER_TAX_CLASS_1 = 'customer_tax_class_1';

    /**
     * List of customer tax class to be created
     *
     * @var array
     */
    protected $customerTaxClasses = [
        self::CUSTOMER_TAX_CLASS_1 => null,
    ];

    /**
     * List of tax rules
     *
     * @var array
     */
    protected $taxRules = [];

    const CONFIG_OVERRIDES = 'config_overrides';
    const TAX_RATE_OVERRIDES = 'tax_rate_overrides';
    const TAX_RULE_OVERRIDES = 'tax_rule_overrides';

    /**
     * Default data for shopping cart rule
     *
     * @var array
     */
    protected $defaultShoppingCartPriceRule = [
        'name' => 'Shopping Cart Rule',
        'is_active' => 1,
        'customer_group_ids' => array(\Magento\Customer\Service\V1\CustomerGroupServiceInterface::CUST_GROUP_ALL),
        'coupon_type' => \Magento\SalesRule\Model\Rule::COUPON_TYPE_NO_COUPON,
        'simple_action' => 'by_percent',
        'discount_amount' => 40,
        'stop_rules_processing' => 1,
        'website_ids' => [1],
    ];

    /**
     * Object manager
     *
     * @var \Magento\Framework\ObjectManagerInterface
     */
    var $objectManager;

    /**
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     */
    public function __construct($objectManager)
    {
        $this->objectManager = $objectManager;
    }

    /**
     * Create customer tax classes
     *
     * @return $this
     */
    protected function createCustomerTaxClass()
    {
        foreach (array_keys($this->customerTaxClasses) as $className) {
            $this->customerTaxClasses[$className] = $this->objectManager->create('Magento\Tax\Model\ClassModel')
                ->setClassName($className)
                ->setClassType(\Magento\Tax\Model\ClassModel::TAX_CLASS_TYPE_CUSTOMER)
                ->save()
                ->getId();
        }

        return $this;
    }

    /**
     * Create product tax classes
     *
     * @return $this
     */
    protected function createProductTaxClass()
    {
        foreach (array_keys($this->productTaxClasses) as $className) {
            $this->productTaxClasses[$className] = $this->objectManager->create('Magento\Tax\Model\ClassModel')
                ->setClassName($className)
                ->setClassType(\Magento\Tax\Model\ClassModel::TAX_CLASS_TYPE_PRODUCT)
                ->save()
                ->getId();
        }

        return $this;
    }

    /**
     * Set the configuration.
     *
     * @param array $configData
     * @return $this
     */
    protected function setConfig($configData)
    {
        /** @var \Magento\Core\Model\Resource\Config $config */
        $config = $this->objectManager->get('Magento\Core\Model\Resource\Config');
        foreach ($configData as $path => $value) {
            if ($path == Config::CONFIG_XML_PATH_SHIPPING_TAX_CLASS) {
                $value = $this->productTaxClasses[$value];
            }
            $config->saveConfig(
                $path,
                $value,
                \Magento\Framework\App\ScopeInterface::SCOPE_DEFAULT,
                0
            );
        }

        /** @var \Magento\Framework\App\Config\ReinitableConfigInterface $config */
        $config = $this->objectManager->get('Magento\Framework\App\Config\ReinitableConfigInterface');
        $config->reinit();

        return $this;
    }

    /**
     * Create tax rates
     *
     * @param array $overrides
     * @return $this
     */
    protected function createTaxRates($overrides)
    {
        $taxRateOverrides = empty($overrides[self::TAX_RATE_OVERRIDES]) ? [] : $overrides[self::TAX_RATE_OVERRIDES];
        foreach (array_keys($this->taxRates) as $taxRateCode) {
            if (isset($taxRateOverrides[$taxRateCode])) {
                $this->taxRates[$taxRateCode]['data']['rate'] = $taxRateOverrides[$taxRateCode];
            }
            $this->taxRates[$taxRateCode]['id'] = $this->objectManager->create('Magento\Tax\Model\Calculation\Rate')
                ->setData($this->taxRates[$taxRateCode]['data'])
                ->save()
                ->getId();
        }
        return $this;
    }

    /**
     * Convert the code to id for productTaxClass, customerTaxClass and taxRate in taxRuleOverrideData
     *
     * @param array $taxRuleOverrideData
     * @param array $taxRateIds
     * @return array
     */
    protected function processTaxRuleOverrides($taxRuleOverrideData, $taxRateIds)
    {
        if (!empty($taxRuleOverrideData['tax_customer_class'])) {
            $customerTaxClassIds = [];
            foreach ($taxRuleOverrideData['tax_customer_class'] as $customerClassCode) {
                $customerTaxClassIds[] = $this->customerTaxClasses[$customerClassCode];
            }
            $taxRuleOverrideData['tax_customer_class'] = $customerTaxClassIds;
        }
        if (!empty($taxRuleOverrideData['tax_product_class'])) {
            $productTaxClassIds = [];
            foreach ($taxRuleOverrideData['tax_product_class'] as $productClassCode) {
                $productTaxClassIds[] = $this->productTaxClasses[$productClassCode];
            }
            $taxRuleOverrideData['tax_product_class'] = $productTaxClassIds;
        }
        if (!empty($taxRuleOverrideData['tax_rate'])) {
            $taxRateIdsForRule = [];
            foreach ($taxRuleOverrideData['tax_rate'] as $taxRateCode) {
                $taxRateIdsForRule[] = $taxRateIds[$taxRateCode];
            }
            $taxRuleOverrideData['tax_rate'] = $taxRateIdsForRule;
        }

        return $taxRuleOverrideData;
    }

    /**
     * Return a list of product tax class ids NOT including shipping product tax class
     *
     * @return array
     */
    protected function getProductTaxClassIds()
    {
        $productTaxClassIds = [];
        foreach ($this->productTaxClasses as $productTaxClassName => $productTaxClassId) {
            if ($productTaxClassName != self::SHIPPING_TAX_CLASS) {
                $productTaxClassIds[] = $productTaxClassId;
            }
        }

        return $productTaxClassIds;
    }

    /**
     * Return a list of tax rate ids NOT including shipping tax rate
     *
     * @return array
     */
    protected function getDefaultTaxRateIds()
    {
        $taxRateIds = [
            $this->taxRates[self::TAX_RATE_TX]['id'],
            $this->taxRates[self::TAX_STORE_RATE]['id'],
        ];

        return $taxRateIds;
    }

    /**
     * Return the default customer group tax class id
     *
     * @return int
     */
    public function getDefaultCustomerTaxClassId()
    {
        /** @var  \Magento\Customer\Service\V1\CustomerGroupServiceInterface $groupService */
        $groupService = $this->objectManager->get('Magento\Customer\Service\V1\CustomerGroupServiceInterface');
        $defaultGroup = $groupService->getDefaultGroup();
        return $defaultGroup->getTaxClassId();
    }

    /**
     * Create tax rules
     *
     * @param array $overrides
     * @return $this
     */
    protected function createTaxRules($overrides)
    {
        $taxRateIds = [];
        foreach ($this->taxRates as $taxRateCode => $taxRate) {
            $taxRateIds[$taxRateCode] = $taxRate['id'];
        }

        //The default customer tax class id is used to calculate store tax rate
        $customerClassIds = [
            $this->customerTaxClasses[self::CUSTOMER_TAX_CLASS_1],
            $this->getDefaultCustomerTaxClassId()
        ];

        //By default create tax rule that covers all product tax classes except SHIPPING_TAX_CLASS
        //The tax rule will cover all tax rates except TAX_RATE_SHIPPING
        $taxRuleDefaultData = [
            'code' => 'Test Rule',
            'priority' => '0',
            'position' => '0',
            'tax_customer_class' => $customerClassIds,
            'tax_product_class' => $this->getProductTaxClassIds(),
            'tax_rate' => $this->getDefaultTaxRateIds(),
        ];

        //Create tax rules
        if (empty($overrides[self::TAX_RULE_OVERRIDES])) {
            //Create separate shipping tax rule
            $shippingTaxRuleData = [
                'code' => 'Shipping Tax Rule',
                'priority' => '0',
                'position' => '0',
                'tax_customer_class' => $customerClassIds,
                'tax_product_class' => [$this->productTaxClasses[self::SHIPPING_TAX_CLASS]],
                'tax_rate' => [$this->taxRates[self::TAX_RATE_SHIPPING]['id']],
            ];
            $this->taxRules[$shippingTaxRuleData['code']] = $this->objectManager
                ->create('Magento\Tax\Model\Calculation\Rule')
                ->setData($shippingTaxRuleData)
                ->save()
                ->getId();

            //Create a default tax rule
            $this->taxRules[$taxRuleDefaultData['code']] = $this->objectManager
                ->create('Magento\Tax\Model\Calculation\Rule')
                ->setData($taxRuleDefaultData)
                ->save()
                ->getId();
        } else {
            foreach ($overrides[self::TAX_RULE_OVERRIDES] as $taxRuleOverrideData ) {
                //convert code to id for productTaxClass, customerTaxClass and taxRate
                $taxRuleOverrideData = $this->processTaxRuleOverrides($taxRuleOverrideData, $taxRateIds);
                $mergedTaxRuleData = array_merge($taxRuleDefaultData, $taxRuleOverrideData);
                $this->taxRules[$mergedTaxRuleData['code']] = $this->objectManager
                    ->create('Magento\Tax\Model\Calculation\Rule')
                    ->setData($mergedTaxRuleData)
                    ->save()
                    ->getId();
            }
        }

        return $this;
    }

    /**
     * Set up tax classes, tax rates and tax rules
     * The override structure:
     * override['self::CONFIG_OVERRIDES']
     *      [
     *          [config_path => config_value]
     *      ]
     * override['self::TAX_RATE_OVERRIDES']
     *      [
     *          ['tax_rate_code' => tax_rate]
     *      ]
     * override['self::TAX_RULE_OVERRIDES']
     *      [
     *          [
     *              'code' => code //Required, has to be unique
     *              'priority' => 0
     *              'position' => 0
     *              'tax_customer_class' => array of customer tax class names as defined in this class
     *              'tax_product_class' => array of product tax class names as defined in this class
     *              'tax_rate' => array of tax rate codes as defined in this class
     *          ]
     *      ]
     *
     * @param array $overrides
     * @return void
     */
    public function setupTax($overrides)
    {
        //Create product tax classes
        $this->createProductTaxClass();

        //Create customer tax classes
        $this->createCustomerTaxClass();

        //Create tax rates
        $this->createTaxRates($overrides);

        //Create tax rules
        $this->createTaxRules($overrides);

        //Tax calculation configuration
        if (!empty($overrides[self::CONFIG_OVERRIDES])) {
            $this->setConfig($overrides[self::CONFIG_OVERRIDES]);
        }
    }

    /**
     * Create a simple product with given sku, price and tax class
     *
     * @param string $sku
     * @param float $price
     * @param int $taxClassId
     * @return \Magento\Catalog\Model\Product
     */
    public function createSimpleProduct($sku, $price, $taxClassId)
    {
        /** @var \Magento\Catalog\Model\Product $product */
        $product = $this->objectManager->create('Magento\Catalog\Model\Product');
        $product->isObjectNew(true);
        $product->setTypeId('simple')
            ->setAttributeSetId(4)
            ->setName('Simple Product')
            ->setSku($sku)
            ->setPrice($price)
            ->setTaxClassId($taxClassId)
            ->setStockData(
                [
                    'use_config_manage_stock' => 1,
                    'qty' => 100,
                    'is_qty_decimal' => 0,
                    'is_in_stock' => 1
                ]
            )->setMetaTitle('meta title')
            ->setMetaKeyword('meta keyword')
            ->setMetaDescription('meta description')
            ->setVisibility(\Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH)
            ->setStatus(\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED)
            ->save();

        $product = $product->load($product->getId());
        return $product;
    }

    /**
     * Create a customer group and associated it with given customer tax class
     *
     * @param int $customerTaxClassId
     * @return int
     */
    protected function createCustomerGroup($customerTaxClassId)
    {
        /** @var \Magento\Customer\Service\V1\CustomerGroupService $customerGroupService */
        $customerGroupService = $this->objectManager->create('Magento\Customer\Service\V1\CustomerGroupService');
        $customerGroupBuilder = $this->objectManager->create('\Magento\Customer\Service\V1\Data\CustomerGroupBuilder')
            ->setCode('custom_group')
            ->setTaxClassId($customerTaxClassId);
        $customerGroup = new \Magento\Customer\Service\V1\Data\CustomerGroup($customerGroupBuilder);
        $customerGroupId = $customerGroupService->createGroup($customerGroup);
        return $customerGroupId;
    }

    /**
     * Create a customer
     *
     * @return \Magento\Customer\Model\Customer
     */
    protected function createCustomer()
    {
        $customerGroupId = $this->createCustomerGroup($this->customerTaxClasses[self::CUSTOMER_TAX_CLASS_1]);
        /** @var \Magento\Customer\Model\Customer $customer */
        $customer = $this->objectManager->create('Magento\Customer\Model\Customer');
        $customer->isObjectNew(true);
        $customer->setWebsiteId(1)
            ->setEntityTypeId(1)
            ->setAttributeSetId(1)
            ->setEmail('customer@example.com')
            ->setPassword('password')
            ->setGroupId($customerGroupId)
            ->setStoreId(1)
            ->setIsActive(1)
            ->setFirstname('Firstname')
            ->setLastname('Lastname')
            ->save();

        return $customer;
    }

    /**
     * Create customer address
     *
     * @param array $addressOverride
     * @param int $customerId
     * @return \Magento\Customer\Model\Address
     */
    protected function createCustomerAddress($addressOverride, $customerId)
    {
        $defaultAddressData = [
            'attribute_set_id' => 2,
            'telephone' => 3468676,
            'postcode' => self::AUSTIN_POST_CODE,
            'country_id' => self::COUNTRY_US,
            'city' => 'CityM',
            'company' => 'CompanyName',
            'street' => ['Green str, 67'],
            'lastname' => 'Smith',
            'firstname' => 'John',
            'parent_id' => 1,
            'region_id' => self::REGION_TX,
        ];
        $addressData = array_merge($defaultAddressData, $addressOverride);

        /** @var \Magento\Customer\Model\Address $customerAddress */
        $customerAddress = $this->objectManager->create('Magento\Customer\Model\Address');
        $customerAddress->setData($addressData)
            ->setCustomerId($customerId)
            ->save();

        return $customerAddress;
    }

    /**
     * Create shopping cart rule
     *
     * @param array $ruleDataOverride
     * @return $this
     */
    protected function createCartRule($ruleDataOverride)
    {
        /** @var \Magento\SalesRule\Model\Rule $salesRule */
        $salesRule = $this->objectManager->create('Magento\SalesRule\Model\Rule');
        $ruleData = array_merge($this->defaultShoppingCartPriceRule, $ruleDataOverride);
        $salesRule->setData($ruleData);
        $salesRule->save();

        return $this;
    }

    /**
     * Create a quote object with customer
     *
     * @param array $quoteData
     * @param \Magento\Customer\Model\Customer $customer
     * @return \Magento\Sales\Model\Quote
     */
    protected function createQuote($quoteData, $customer)
    {
        /** @var \Magento\Customer\Service\V1\CustomerAddressServiceInterface $addressService */
        $addressService = $this->objectManager->create('Magento\Customer\Service\V1\CustomerAddressServiceInterface');

        /** @var array $shippingAddressOverride */
        $shippingAddressOverride = empty($quoteData['shipping_address']) ? [] : $quoteData['shipping_address'];
        /** @var  \Magento\Customer\Model\Address $shippingAddress */
        $shippingAddress = $this->createCustomerAddress($shippingAddressOverride, $customer->getId());

        /** @var \Magento\Sales\Model\Quote\Address $quoteShippingAddress */
        $quoteShippingAddress = $this->objectManager->create('Magento\Sales\Model\Quote\Address');
        $quoteShippingAddress->importCustomerAddressData($addressService->getAddress($shippingAddress->getId()));

        /** @var array $billingAddressOverride */
        $billingAddressOverride = empty($quoteData['billing_address']) ? [] : $quoteData['billing_address'];
        /** @var  \Magento\Customer\Model\Address $billingAddress */
        $billingAddress = $this->createCustomerAddress($billingAddressOverride, $customer->getId());

        /** @var \Magento\Sales\Model\Quote\Address $quoteBillingAddress */
        $quoteBillingAddress = $this->objectManager->create('Magento\Sales\Model\Quote\Address');
        $quoteBillingAddress->importCustomerAddressData($addressService->getAddress($billingAddress->getId()));

        /** @var \Magento\Sales\Model\Quote $quote */
        $quote = $this->objectManager->create('Magento\Sales\Model\Quote');
        $quote->setStoreId(1)
            ->setIsActive(true)
            ->setIsMultiShipping(false)
            ->assignCustomerWithAddressChange($customer, $quoteBillingAddress, $quoteShippingAddress)
            ->setCheckoutMethod($customer->getMode())
            ->setPasswordHash($customer->encryptPassword($customer->getPassword()));

        return $quote;
    }

    /**
     * Add products to quote
     *
     * @param \Magento\Sales\Model\Quote $quote
     * @param array $itemsData
     * @return $this
     */
    protected function addProductToQuote($quote, $itemsData)
    {
        foreach ($itemsData as $itemData) {
            $sku = $itemData['sku'];
            $price = $itemData['price'];
            $qty = isset($itemData['qty']) ? $itemData['qty'] : 1;
            $taxClassName =
                isset($itemData['tax_class_name']) ? $itemData['tax_class_name'] : self::PRODUCT_TAX_CLASS_1;
            $taxClassId = $this->productTaxClasses[$taxClassName];
            $product = $this->createSimpleProduct($sku, $price, $taxClassId);
            $quote->addProduct($product, $qty);
        }
        return $this;
    }

    /**
     * Create a quote based on given data
     *
     * @param array $quoteData
     * @return \Magento\Sales\Model\Quote
     */
    public function setupQuote($quoteData)
    {
        $customer = $this->createCustomer();

        $quote = $this->createQuote($quoteData, $customer);

        $this->addProductToQuote($quote, $quoteData['items']);

        //Set shipping amount
        if (isset($quoteData['shipping_method'])) {
            $quote->getShippingAddress()->setShippingMethod($quoteData['shipping_method']);
            $quote->getShippingAddress()->setCollectShippingRates(true);
        }

        //create shopping cart rules if necessary
        if (!empty($quoteData['shopping_cart_rules'])) {
            foreach ($quoteData['shopping_cart_rules'] as $ruleData) {
                $ruleData['customer_group_ids'] = array($customer->getGroupId());
                $this->createCartRule($ruleData);
            }
        }

        return $quote;
    }
}
