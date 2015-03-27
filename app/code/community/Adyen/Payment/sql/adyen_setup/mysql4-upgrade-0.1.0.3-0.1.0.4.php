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
$installer->run("
DROP TABLE IF EXISTS `{$this->getTable('adyen/event')}`;
CREATE TABLE `{$this->getTable('adyen/event')}` (
`event_id` int(11) NOT NULL AUTO_INCREMENT,
`psp_reference` varchar(55) DEFAULT NULL COMMENT 'pspReference',
`adyen_event_code` varchar(55) DEFAULT NULL COMMENT 'Adyen Event Code',
`adyen_event_result` text DEFAULT NULL COMMENT 'Adyen Event Result',
`increment_id` varchar(50) DEFAULT NULL COMMENT 'Increment Id',
`payment_method` varchar(50) DEFAULT NULL COMMENT 'Payment Method',
`created_at` datetime NULL DEFAULT NULL COMMENT 'Created At',
PRIMARY KEY (`event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
ALTER TABLE `{$this->getTable('adyen/event')}` ADD INDEX(adyen_event_code, psp_reference);
");

$installer->endSetup();