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
/** @var Adyen_Payment_Model_Resource_Setup $installer */
$installer = $this;

$installer->startSetup();

$installer->getConnection()->addColumn($this->getTable('sales/quote_address'), 'payment_fee_amount', "decimal(12,4) null default null");
$installer->getConnection()->addColumn($this->getTable('sales/quote_address'), 'base_payment_fee_amount', "decimal(12,4) null default null");

$installer->getConnection()->addColumn($this->getTable('sales/order'), 'payment_fee_amount', "decimal(12,4) null default null");
$installer->getConnection()->addColumn($this->getTable('sales/order'), 'base_payment_fee_amount', "decimal(12,4) null default null");

$installer->getConnection()->addColumn($this->getTable('sales/invoice'), 'payment_fee_amount', "decimal(12,4) null default null");
$installer->getConnection()->addColumn($this->getTable('sales/invoice'), 'base_payment_fee_amount', "decimal(12,4) null default null");

$installer->getConnection()->addColumn($this->getTable('sales/creditmemo'), 'payment_fee_amount', "decimal(12,4) null default null");
$installer->getConnection()->addColumn($this->getTable('sales/creditmemo'), 'base_payment_fee_amount', "decimal(12,4) null default null");

$installer->getConnection()->addColumn($this->getTable('adyen/event'), 'success', "tinyint(1) null default null");

$installer->addAttribute('order_payment', 'adyen_klarna_number', array());

$installer->getConnection()->addColumn($this->getTable('sales/quote_address'), 'payment_installment_fee_amount', "decimal(12,4) null default null");
$installer->getConnection()->addColumn($this->getTable('sales/quote_address'), 'base_payment_installment_fee_amount', "decimal(12,4) null default null");

$installer->getConnection()->addColumn($this->getTable('sales/order'), 'payment_installment_fee_amount', "decimal(12,4) null default null");
$installer->getConnection()->addColumn($this->getTable('sales/order'), 'base_payment_installment_fee_amount', "decimal(12,4) null default null");

$installer->getConnection()->addColumn($this->getTable('sales/invoice'), 'payment_installment_fee_amount', "decimal(12,4) null default null");
$installer->getConnection()->addColumn($this->getTable('sales/invoice'), 'base_payment_installment_fee_amount', "decimal(12,4) null default null");

$installer->getConnection()->addColumn($this->getTable('sales/creditmemo'), 'payment_installment_fee_amount', "decimal(12,4) null default null");
$installer->getConnection()->addColumn($this->getTable('sales/creditmemo'), 'base_payment_installment_fee_amount', "decimal(12,4) null default null");

$installer->addAttribute('order_payment', 'adyen_avs_result', array());
$installer->addAttribute('order_payment', 'adyen_cvc_result', array());

$installer->addAttribute('order_payment', 'adyen_boleto_paid_amount', array());

$installer->endSetup();