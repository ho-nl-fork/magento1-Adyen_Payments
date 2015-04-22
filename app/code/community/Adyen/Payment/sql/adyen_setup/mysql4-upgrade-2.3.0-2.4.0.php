<?php
/**
 * Adyen Payment Module
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
 * @category	Adyen
 * @package	Adyen_Payment
 * @copyright	Copyright (c) 2011 Adyen (http://www.adyen.com)
 * @license	http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
/**
 * @category   Payment Gateway
 * @package    Adyen_Payment
 * @author     Adyen
 * @property   Adyen B.V
 * @copyright  Copyright (c) 2014 Adyen BV (http://www.adyen.com)
 */
$installer = new Mage_Customer_Model_Resource_Setup('core_setup');
$installer->startSetup();

/** @var Magento_Db_Adapter_Pdo_Mysql $connection */
$connection = $installer->getConnection();

$entityTypeId     = $installer->getEntityTypeId('customer');
$attributeCode    = 'adyen_customer_ref';
$attributeSetId   = $installer->getDefaultAttributeSetId($entityTypeId);
$attributeGroupId = $installer->getDefaultAttributeGroupId($entityTypeId, $attributeSetId);

$installer->addAttribute('customer', $attributeCode, array(
    'input'         => 'text',
    'type'          => 'text',
    'label'         => 'Adyen Customer Reference',
    'note'          => 'Optional customer reference ID, only fill when customer reference is not the customer ID (when importing data from older systems for example).',
    'visible'       => 1,
    'required'      => 0,
    'user_defined'  => 0,
));

$attributeSortOrder = 120;
$installer->addAttributeToGroup(
    $entityTypeId,
    $attributeSetId,
    $attributeGroupId,
    $attributeCode,
    $attributeSortOrder
);

$oAttribute = Mage::getSingleton('eav/config')->getAttribute('customer', $attributeCode);
$oAttribute->setData('used_in_forms', array('adminhtml_customer'));
$oAttribute->setData('sort_order', $attributeSortOrder);
$oAttribute->save();


$connection->addColumn($this->getTable('sales/billing_agreement'), 'agreement_data', [
    'type' => Varien_Db_Ddl_Table::TYPE_TEXT,
    'nullable' => true,
    'comment' => 'Agreement Data'
]);


$installer->endSetup();
