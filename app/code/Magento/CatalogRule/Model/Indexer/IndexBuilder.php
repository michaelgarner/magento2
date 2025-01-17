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

namespace Magento\CatalogRule\Model\Indexer;

use Magento\CatalogRule\CatalogRuleException;
use Magento\CatalogRule\Model\Rule;
use Magento\CatalogRule\Model\Resource\Rule\CollectionFactory as RuleCollectionFactory;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Catalog\Model\Product;

class IndexBuilder
{
    const SECONDS_IN_DAY = 86400;

    /**
     * @var \Magento\Framework\App\Resource
     */
    protected $resource;

    /**
     * @var \Magento\Framework\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var RuleCollectionFactory
     */
    protected $ruleCollectionFactory;

    /**
     * @var \Magento\Framework\Logger
     */
    protected $logger;

    /**
     * @var PriceCurrencyInterface
     */
    protected $priceCurrency;

    /**
     * @var \Magento\Eav\Model\Config
     */
    protected $eavConfig;

    /**
     * @var \Magento\Framework\Stdlib\DateTime
     */
    protected $dateFormat;

    /**
     * @var \Magento\Framework\Stdlib\DateTime\DateTime
     */
    protected $dateTime;

    /**
     * @var \Magento\Catalog\Model\ProductFactory
     */
    protected $productFactory;

    /**
     * @var Product[]
     */
    protected $loadedProducts;

    /**
     * @param RuleCollectionFactory $ruleCollectionFactory
     * @param PriceCurrencyInterface $priceCurrency
     * @param \Magento\Framework\App\Resource $resource
     * @param \Magento\Framework\StoreManagerInterface $storeManager
     * @param \Magento\Framework\Logger $logger
     * @param \Magento\Eav\Model\Config $eavConfig
     * @param \Magento\Framework\Stdlib\DateTime $dateFormat
     * @param \Magento\Framework\Stdlib\DateTime\DateTime $dateTime
     * @param \Magento\Catalog\Model\ProductFactory $productFactory
     */
    public function __construct(
        RuleCollectionFactory $ruleCollectionFactory,
        PriceCurrencyInterface $priceCurrency,
        \Magento\Framework\App\Resource $resource,
        \Magento\Framework\StoreManagerInterface $storeManager,
        \Magento\Framework\Logger $logger,
        \Magento\Eav\Model\Config $eavConfig,
        \Magento\Framework\Stdlib\DateTime $dateFormat,
        \Magento\Framework\Stdlib\DateTime\DateTime $dateTime,
        \Magento\Catalog\Model\ProductFactory $productFactory
    ) {
        $this->resource = $resource;
        $this->storeManager = $storeManager;
        $this->ruleCollectionFactory = $ruleCollectionFactory;
        $this->logger = $logger;
        $this->priceCurrency = $priceCurrency;
        $this->eavConfig = $eavConfig;
        $this->dateFormat = $dateFormat;
        $this->dateTime = $dateTime;
        $this->productFactory = $productFactory;
    }

    /**
     * Reindex by id
     *
     * @param int $id
     * @return void
     */
    public function reindexById($id)
    {
        $this->reindexByIds([$id]);
    }

    /**
     * Reindex by ids
     *
     * @param array $ids
     * @throws \Magento\CatalogRule\CatalogRuleException
     * @return void
     */
    public function reindexByIds(array $ids)
    {
        try {
            $this->doReindexByIds($ids);
        } catch (\Exception $e) {
            $this->logException($e);
            throw new CatalogRuleException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Reindex by ids. Template method
     *
     * @param array $ids
     * @return void
     */
    protected function doReindexByIds($ids)
    {
        $this->cleanByIds($ids);

        foreach ($this->getActiveRules() as $rule) {
            foreach ($ids as $productId) {
                $this->applyRule($rule, $this->getProduct($productId));
            }
        }
    }

    /**
     * Full reindex
     *
     * @throws CatalogRuleException
     * @return void
     */
    public function reindexFull()
    {
        try {
            $this->doReindexFull();
        } catch (\Exception $e) {
            $this->logException($e);
            throw new CatalogRuleException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Full reindex Template method
     *
     * @return void
     */
    protected function doReindexFull()
    {
        foreach ($this->getAllRules() as $rule) {
            $this->updateRuleProductData($rule);
        }
        $this->applyAllRules();
    }

    /**
     * Clean by product ids
     *
     * @param array $productIds
     * @return void
     */
    protected function cleanByIds($productIds)
    {
        $this->getWriteAdapter()->deleteFromSelect(
            $this->getWriteAdapter()
                ->select($this->resource->getTableName('catalogrule_product'), 'product_id')
                ->distinct()
                ->where('product_id IN (?)', $productIds),
            $this->resource->getTableName('catalogrule_product')
        );

        $this->getWriteAdapter()->deleteFromSelect(
            $this->getWriteAdapter()->select($this->resource->getTableName('catalogrule_product_price'), 'product_id')
                ->distinct()
                ->where('product_id IN (?)', $productIds),
            $this->resource->getTableName('catalogrule_product_price')
        );
    }

    /**
     * @param Rule $rule
     * @param Product $product
     * @return $this
     * @throws \Exception
     */
    protected function applyRule(Rule $rule, $product)
    {
        $ruleId = $rule->getId();
        $productId = $product->getId();
        $websiteIds = array_intersect($product->getWebsiteIds(), $rule->getWebsiteIds());

        $write = $this->getWriteAdapter();

        $write->delete(
            $this->resource->getTableName('catalogrule_product'),
            array($write->quoteInto('rule_id = ?', $ruleId), $write->quoteInto('product_id = ?', $productId))
        );

        if (!$rule->getConditions()->validate($product)) {
            $write->delete(
                $this->resource->getTableName('catalogrule_product_price'),
                array($write->quoteInto('product_id = ?', $productId))
            );
            return $this;
        }

        $customerGroupIds = $rule->getCustomerGroupIds();
        $fromTime = strtotime($rule->getFromDate());
        $toTime = strtotime($rule->getToDate());
        $toTime = $toTime ? $toTime + self::SECONDS_IN_DAY - 1 : 0;
        $sortOrder = (int)$rule->getSortOrder();
        $actionOperator = $rule->getSimpleAction();
        $actionAmount = $rule->getDiscountAmount();
        $actionStop = $rule->getStopRulesProcessing();
        $subActionOperator = $rule->getSubIsEnable() ? $rule->getSubSimpleAction() : '';
        $subActionAmount = $rule->getSubDiscountAmount();

        $rows = array();
        try {
            foreach ($websiteIds as $websiteId) {
                foreach ($customerGroupIds as $customerGroupId) {
                    $rows[] = array(
                        'rule_id' => $ruleId,
                        'from_time' => $fromTime,
                        'to_time' => $toTime,
                        'website_id' => $websiteId,
                        'customer_group_id' => $customerGroupId,
                        'product_id' => $productId,
                        'action_operator' => $actionOperator,
                        'action_amount' => $actionAmount,
                        'action_stop' => $actionStop,
                        'sort_order' => $sortOrder,
                        'sub_simple_action' => $subActionOperator,
                        'sub_discount_amount' => $subActionAmount
                    );

                    if (count($rows) == 1000) {
                        $write->insertMultiple($this->getTable('catalogrule_product'), $rows);
                        $rows = array();
                    }
                }
            }

            if (!empty($rows)) {
                $write->insertMultiple($this->resource->getTableName('catalogrule_product'), $rows);
            }
        } catch (\Exception $e) {
            throw $e;
        }

        $this->applyAllRules($product);

        return $this;
    }

    /**
     * Retrieve connection for read data
     *
     * @return \Magento\Framework\DB\Adapter\AdapterInterface
     */
    protected function getReadAdapter()
    {
        $writeAdapter = $this->getWriteAdapter();
        if ($writeAdapter && $writeAdapter->getTransactionLevel() > 0) {
            // if transaction is started we should use write connection for reading
            return $writeAdapter;
        }
        return $this->resource->getConnection('read');
    }

    /**
     * Retrieve connection for write data
     *
     * @return \Magento\Framework\DB\Adapter\AdapterInterface
     */
    protected function getWriteAdapter()
    {
        return $this->resource->getConnection('write');
    }

    /**
     * @param string $tableName
     * @return string
     */
    protected function getTable($tableName)
    {
        return $this->resource->getTableName($tableName);
    }

    /**
     * @param Rule $rule
     * @return $this
     */
    protected function updateRuleProductData(Rule $rule)
    {
        $ruleId = $rule->getId();
        $write = $this->getWriteAdapter();
        if ($rule->getProductsFilter()) {
            $write->delete(
                $this->getTable('catalogrule_product'),
                array('rule_id=?' => $ruleId, 'product_id IN (?)' => $rule->getProductsFilter())
            );
        } else {
            $write->delete($this->getTable('catalogrule_product'), $write->quoteInto('rule_id=?', $ruleId));
        }

        if (!$rule->getIsActive()) {
            return $this;
        }

        $websiteIds = $rule->getWebsiteIds();
        if (!is_array($websiteIds)) {
            $websiteIds = explode(',', $websiteIds);
        }
        if (empty($websiteIds)) {
            return $this;
        }

        \Magento\Framework\Profiler::start('__MATCH_PRODUCTS__');
        $productIds = $rule->getMatchingProductIds();
        \Magento\Framework\Profiler::stop('__MATCH_PRODUCTS__');

        $customerGroupIds = $rule->getCustomerGroupIds();
        $fromTime = strtotime($rule->getFromDate());
        $toTime = strtotime($rule->getToDate());
        $toTime = $toTime ? $toTime + self::SECONDS_IN_DAY - 1 : 0;
        $sortOrder = (int)$rule->getSortOrder();
        $actionOperator = $rule->getSimpleAction();
        $actionAmount = $rule->getDiscountAmount();
        $subActionOperator = $rule->getSubIsEnable() ? $rule->getSubSimpleAction() : '';
        $subActionAmount = $rule->getSubDiscountAmount();
        $actionStop = $rule->getStopRulesProcessing();

        $rows = array();

        foreach ($productIds as $productId => $validationByWebsite) {
            foreach ($websiteIds as $websiteId) {
                if (empty($validationByWebsite[$websiteId])) {
                    continue;
                }
                foreach ($customerGroupIds as $customerGroupId) {
                    $rows[] = array(
                        'rule_id' => $ruleId,
                        'from_time' => $fromTime,
                        'to_time' => $toTime,
                        'website_id' => $websiteId,
                        'customer_group_id' => $customerGroupId,
                        'product_id' => $productId,
                        'action_operator' => $actionOperator,
                        'action_amount' => $actionAmount,
                        'action_stop' => $actionStop,
                        'sort_order' => $sortOrder,
                        'sub_simple_action' => $subActionOperator,
                        'sub_discount_amount' => $subActionAmount
                    );

                    if (count($rows) == 1000) {
                        $write->insertMultiple($this->getTable('catalogrule_product'), $rows);
                        $rows = array();
                    }
                }
            }
        }
        if (!empty($rows)) {
            $write->insertMultiple($this->getTable('catalogrule_product'), $rows);
        }

        return $this;
    }

    /**
     * @return $this
     * @throws \Exception
     */
    protected function applyAllRules()
    {
        $write = $this->getWriteAdapter();

        $fromDate = mktime(0, 0, 0, date('m'), date('d') - 1);
        $toDate = mktime(0, 0, 0, date('m'), date('d') + 1);

        $this->deleteOldData();

        $dayPrices = array();

        /**
         * Update products rules prices per each website separately
         * because of max join limit in mysql
         */
        foreach ($this->storeManager->getWebsites(false) as $website) {
            $productsStmt = $this->getRuleProductsStmt($website->getId());

            $dayPrices = array();
            $stopFlags = array();
            $prevKey = null;

            while ($ruleData = $productsStmt->fetch()) {
                $ruleProductId = $ruleData['product_id'];
                $productKey = $ruleProductId .
                    '_' .
                    $ruleData['website_id'] .
                    '_' .
                    $ruleData['customer_group_id'];

                if ($prevKey && $prevKey != $productKey) {
                    $stopFlags = array();
                }

                /**
                 * Build prices for each day
                 */
                for ($time = $fromDate; $time <= $toDate; $time += self::SECONDS_IN_DAY) {
                    if (($ruleData['from_time'] == 0 ||
                            $time >= $ruleData['from_time']) && ($ruleData['to_time'] == 0 ||
                            $time <= $ruleData['to_time'])
                    ) {
                        $priceKey = $time . '_' . $productKey;

                        if (isset($stopFlags[$priceKey])) {
                            continue;
                        }

                        if (!isset($dayPrices[$priceKey])) {
                            $dayPrices[$priceKey] = array(
                                'rule_date' => $time,
                                'website_id' => $ruleData['website_id'],
                                'customer_group_id' => $ruleData['customer_group_id'],
                                'product_id' => $ruleProductId,
                                'rule_price' => $this->calcRuleProductPrice($ruleData),
                                'latest_start_date' => $ruleData['from_time'],
                                'earliest_end_date' => $ruleData['to_time']
                            );
                        } else {
                            $dayPrices[$priceKey]['rule_price'] = $this->calcRuleProductPrice(
                                $ruleData,
                                $dayPrices[$priceKey]
                            );
                            $dayPrices[$priceKey]['latest_start_date'] = max(
                                $dayPrices[$priceKey]['latest_start_date'],
                                $ruleData['from_time']
                            );
                            $dayPrices[$priceKey]['earliest_end_date'] = min(
                                $dayPrices[$priceKey]['earliest_end_date'],
                                $ruleData['to_time']
                            );
                        }

                        if ($ruleData['action_stop']) {
                            $stopFlags[$priceKey] = true;
                        }
                    }
                }

                $prevKey = $productKey;
                if (count($dayPrices) > 1000) {
                    $this->saveRuleProductPrices($dayPrices);
                    $dayPrices = array();
                }
            }
            $this->saveRuleProductPrices($dayPrices);
        }
        $this->saveRuleProductPrices($dayPrices);

        $write->delete($this->getTable('catalogrule_group_website'), array());

        $timestamp = $this->dateTime->gmtTimestamp();

        $select = $write->select()->distinct(
            true
        )->from(
            $this->getTable('catalogrule_product'),
            array('rule_id', 'customer_group_id', 'website_id')
        )->where(
            "{$timestamp} >= from_time AND (({$timestamp} <= to_time AND to_time > 0) OR to_time = 0)"
        );
        $query = $select->insertFromSelect($this->getTable('catalogrule_group_website'));
        $write->query($query);

        return $this;
    }

    /**
     * Clean rule price index
     *
     * @return $this
     */
    protected function deleteOldData()
    {
        $this->getWriteAdapter()->delete($this->getTable('catalogrule_product_price'));
        return $this;
    }

    /**
     * @param array $ruleData
     * @param null $productData
     * @return float
     */
    protected function calcRuleProductPrice($ruleData, $productData = null)
    {
        if ($productData !== null && isset($productData['rule_price'])) {
            $productPrice = $productData['rule_price'];
        } else {
            $productPrice = $ruleData['default_price'];
        }

        switch ($ruleData['action_operator']) {
            case 'to_fixed':
                $productPrice = min($ruleData['action_amount'], $productPrice);
                break;
            case 'to_percent':
                $productPrice = $productPrice * $ruleData['action_amount'] / 100;
                break;
            case 'by_fixed':
                $productPrice = max(0, $productPrice - $ruleData['action_amount']);
                break;
            case 'by_percent':
                $productPrice = $productPrice * (1 - $ruleData['action_amount'] / 100);
                break;
            default:
                $productPrice = 0;
        }

        return $this->priceCurrency->round($productPrice);
    }

    /**
     * @param int $websiteId
     * @return \Zend\Db\Adapter\Driver\StatementInterface|\Zend_Db_Statement_Interface
     * @throws \Magento\Eav\Exception
     */
    protected function getRuleProductsStmt($websiteId)
    {
        $read = $this->getReadAdapter();
        /**
         * Sort order is important
         * It used for check stop price rule condition.
         * website_id   customer_group_id   product_id  sort_order
         *  1           1                   1           0
         *  1           1                   1           1
         *  1           1                   1           2
         * if row with sort order 1 will have stop flag we should exclude
         * all next rows for same product id from price calculation
         */
        $select = $read->select()->from(
            array('rp' => $this->getTable('catalogrule_product'))
        )->order(
            array('rp.website_id', 'rp.customer_group_id', 'rp.product_id', 'rp.sort_order', 'rp.rule_id')
        );

        /**
         * Join default price and websites prices to result
         */
        $priceAttr = $this->eavConfig->getAttribute(Product::ENTITY, 'price');
        $priceTable = $priceAttr->getBackend()->getTable();
        $attributeId = $priceAttr->getId();

        $joinCondition = '%1$s.entity_id=rp.product_id AND (%1$s.attribute_id='
            . $attributeId
            . ') and %1$s.store_id=%2$s';

        $select->join(
            array('pp_default' => $priceTable),
            sprintf($joinCondition, 'pp_default', \Magento\Store\Model\Store::DEFAULT_STORE_ID),
            array()
        );

        $website = $this->storeManager->getWebsite($websiteId);
        $defaultGroup = $website->getDefaultGroup();
        if ($defaultGroup instanceof \Magento\Store\Model\Group) {
            $storeId = $defaultGroup->getDefaultStoreId();
        } else {
            $storeId = \Magento\Store\Model\Store::DEFAULT_STORE_ID;
        }

        $select->joinInner(
            array('product_website' => $this->getTable('catalog_product_website')),
            'product_website.product_id=rp.product_id '
            . 'AND product_website.website_id = rp.website_id '
            . 'AND product_website.website_id='
            . $websiteId,
            array()
        );

        $tableAlias = 'pp' . $websiteId;
        $select->joinLeft(
            array($tableAlias => $priceTable),
            sprintf($joinCondition, $tableAlias, $storeId),
            array()
        );
        $select->columns([
            'default_price' => $this->getReadAdapter()->getIfNullSql($tableAlias . '.value', 'pp_default.value'),
        ]);

        return $read->query($select);
    }

    /**
     * @param array $arrData
     * @return $this
     * @throws \Exception
     */
    protected function saveRuleProductPrices($arrData)
    {
        if (empty($arrData)) {
            return $this;
        }

        $adapter = $this->getWriteAdapter();
        $productIds = array();

        try {
            foreach ($arrData as $key => $data) {
                $productIds['product_id'] = $data['product_id'];
                $arrData[$key]['rule_date'] = $this->dateFormat->formatDate($data['rule_date'], false);
                $arrData[$key]['latest_start_date'] = $this->dateFormat->formatDate($data['latest_start_date'], false);
                $arrData[$key]['earliest_end_date'] = $this->dateFormat->formatDate($data['earliest_end_date'], false);
            }
            $adapter->insertOnDuplicate($this->getTable('catalogrule_affected_product'), array_unique($productIds));
            $adapter->insertOnDuplicate($this->getTable('catalogrule_product_price'), $arrData);
        } catch (\Exception $e) {
            throw $e;
        }

        return $this;
    }

    /**
     * Get active rules
     *
     * @return array
     */
    protected function getActiveRules()
    {
        return $this->ruleCollectionFactory->create()
            ->addFieldToFilter('is_active', 1);
    }

    /**
     * Get active rules
     *
     * @return array
     */
    protected function getAllRules()
    {
        return $this->ruleCollectionFactory->create();
    }

    /**
     * @param int $productId
     * @return Product
     */
    protected function getProduct($productId)
    {
        if (!isset($this->loadedProducts[$productId])) {
            $this->loadedProducts[$productId] = $this->productFactory->create()->load($productId);
        }
        return $this->loadedProducts[$productId];
    }

    /**
     * @param \Exception $e
     * @return void
     */
    protected function logException($e)
    {
        $this->logger->logException($e);
    }
}
