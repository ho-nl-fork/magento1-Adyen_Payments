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
 * @package	    Adyen_Payment
 * @copyright	Copyright (c) 2011 Adyen (http://www.adyen.com)
 * @license	http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
/**
 * @category   Payment Gateway
 * @package    Adyen_Payment
 * @author     Adyen
 * @property   Adyen B.V
 * @copyright  Copyright (c) 2015 Adyen BV (http://www.adyen.com)
 */

class Adyen_Payment_Model_Resource_Billing_Agreement_Collection
    extends Mage_Sales_Model_Resource_Billing_Agreement_Collection {

    /**
     * @return array
     */
    protected function _getNameFields()
    {
        $fields = array();

        $customerAccount = Mage::getConfig()->getFieldset('customer_account');
        foreach ($customerAccount as $code => $node) {
            if ($node->is('name')) {
                $fields[$code] = $code.'.value';
            }
        }
        return $fields;
    }


    /**
     * @return $this
     */
    public function addActiveFilter()
    {
        $this->addFieldToFilter('status', Mage_Sales_Model_Billing_Agreement::STATUS_ACTIVE);
        return $this;
    }

    /**
     * Add cutomer details(email, firstname, lastname) to select
     *
     * @return Mage_Sales_Model_Resource_Billing_Agreement_Collection
     */
    public function addCustomerDetails()
    {
        $select = $this->getSelect()->joinInner(
            array('ce' => $this->getTable('customer/entity')),
            'ce.entity_id = main_table.customer_id',
            array('customer_email' => 'email')
        );

        $customer = Mage::getResourceSingleton('customer/customer');
        foreach (array_keys($this->_getNameFields()) as $field) {
            $adapter  = $this->getConnection();
            $attr     = $customer->getAttribute($field);

            $joinExpr = $field.'.entity_id = main_table.customer_id AND '
                . $adapter->quoteInto($field.'.entity_type_id = ?', $customer->getTypeId()) . ' AND '
                . $adapter->quoteInto($field.'.attribute_id = ?', $attr->getAttributeId());

            $select->joinLeft(
                array($field => $attr->getBackend()->getTable()),
                $joinExpr,
                array($field => 'value')
            );
        }

        return $this;
    }

    /**
     * Add Name to select
     *
     * @return Mage_Customer_Model_Resource_Customer_Collection
     */
    public function addNameToSelect()
    {
        $fields = $this->_getNameFields();
        $adapter = $this->getConnection();
        $concatenate = array();

        if (isset($fields['prefix'])) {
            $concatenate[] = $adapter->getCheckSql(
                '{{prefix}} IS NOT NULL AND {{prefix}} != \'\'',
                $adapter->getConcatSql(array('LTRIM(RTRIM({{prefix}}))', '\' \'')),
                '\'\'');
        }
        $concatenate[] = 'LTRIM(RTRIM({{firstname}}))';
        $concatenate[] = '\' \'';
        if (isset($fields['middlename'])) {
            $concatenate[] = $adapter->getCheckSql(
                '{{middlename}} IS NOT NULL AND {{middlename}} != \'\'',
                $adapter->getConcatSql(array('LTRIM(RTRIM({{middlename}}))', '\' \'')),
                '\'\'');
        }
        $concatenate[] = 'LTRIM(RTRIM({{lastname}}))';
        if (isset($fields['suffix'])) {
            $concatenate[] = $adapter
                    ->getCheckSql('{{suffix}} IS NOT NULL AND {{suffix}} != \'\'',
                $adapter->getConcatSql(array('\' \'', 'LTRIM(RTRIM({{suffix}}))')),
                '\'\'');
        }

        $nameExpr = $adapter->getConcatSql($concatenate);


        $this->addExpressionFieldToSelect('name', $nameExpr, $fields);

        return $this;
    }
}
