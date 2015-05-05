<?php
/**
 * Adyen_payment
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the H&O Commercial License
 * that is bundled with this package in the file LICENSE_HO.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.h-o.nl/license
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to info@h-o.com so we can send you a copy immediately.
 *
 * @category  Adyen
 * @package   Adyen_payment
 * @author    Paul Hachmang – H&O <info@h-o.nl>
 * @copyright 2015 Copyright © H&O (http://www.h-o.nl/)
 * @license   H&O Commercial License (http://www.h-o.nl/license)
 */
 
class Adyen_Payment_Helper_Billing_Agreement extends Mage_Core_Helper_Abstract
{

    public function getCustomerReference(Mage_Customer_Model_Customer $customer)
    {
        var_dump($customer);exit;
    }

    /**
     * @return Mage_Customer_Model_Customer|null
     */
    public function getCurrentCustomer()
    {
        if (Mage::app()->getStore()->isAdmin()) {
            return Mage::getSingleton('adminhtml/session_quote')->getCustomer();
        }

        if($customer = Mage::getSingleton('customer/session')->isLoggedIn()) {
            return Mage::getSingleton('customer/session')->getCustomer();
        }

        if ($this->_isPersistent()) {
            return $this->_getPersistentHelper()->getCustomer();
        }

        return null;
    }

    /**
     * Retrieve persistent helper
     *
     * @return Mage_Persistent_Helper_Session
     */
    protected function _getPersistentHelper()
    {
        return Mage::helper('persistent/session');
    }


    /**
     * @return bool
     */
    protected function _isPersistent()
    {
        if(! Mage::helper('core')->isModuleEnabled('Mage_Persistent')
            || Mage::getSingleton('customer/session')->isLoggedIn()) {
            return false;
        }

        if ($this->_getPersistentHelper()->isPersistent()) {
            return true;
        }

        return false;
    }
}