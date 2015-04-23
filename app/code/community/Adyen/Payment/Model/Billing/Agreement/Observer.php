<?php
/**
 * Adyen_Payment
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
 * @package   Adyen_Payment
 * @author    Paul Hachmang – H&O <info@h-o.nl>
 * @copyright 2015 Copyright © H&O (http://www.h-o.nl/)
 * @license   H&O Commercial License (http://www.h-o.nl/license)
 */
 
class Adyen_Payment_Model_Billing_Agreement_Observer
{
    /**
     * @event controller_action_predispatch
     * @param Varien_Event_Observer $observer
     */
    public function addMethodsToConfig(Varien_Event_Observer $observer)
    {
        if(Mage::app()->getStore()->isAdmin()) {
            $store = Mage::getSingleton('adminhtml/session_quote')->getStore();
        } else {
            $store = Mage::app()->getStore();
        }

        if (Mage::getStoreConfigFlag('payment/adyen_oneclick/active', $store)) {
            try {
                $this->_addOneClickMethodsToConfig($store);
                $store->setConfig('payment/adyen_oneclick/active', 0);
            } catch (Exception $e) {
                Adyen_Payment_Exception::logException($e);
            }
        }
    }


    /**
     * @param Mage_Core_Model_Store $store
     * @return $this
     */
    protected function _addOneClickMethodsToConfig(Mage_Core_Model_Store $store)
    {
        Varien_Profiler::start(__CLASS__.'::'.__FUNCTION__);

        $customer = Mage::helper('adyen/billing_agreement')->getCurrentCustomer();

        if (! $customer || ! $customer->getId()) {
            return $this;
        }

        $baCollection = Mage::getResourceModel('adyen/billing_agreement_collection');
        $baCollection->addFieldToFilter('customer_id', $customer->getId());
        $baCollection->addFieldToFilter('store_id', $store->getId());
        $baCollection->addActiveFilter();

        foreach ($baCollection as $billingAgreement) {
            $this->_createPaymentMethodFromBA($billingAgreement, $store);
        }

//        // Adyen CC needs to be active
//        if(Mage::getStoreConfigFlag('payment/adyen_cc/active', $store)) {
//            foreach ($this->_fetchOneClickMethods($store) as $methodCode => $methodData) {
//                $this->createPaymentMethodFromOneClick($methodCode, $methodData, $store);
//            }
//        }
//        $store->setConfig('payment/adyen_oneclick/active', 0);

        Varien_Profiler::stop(__CLASS__.'::'.__FUNCTION__);
    }


    /**
     * @param Adyen_Payment_Model_Billing_Agreement $billingAgreement
     * @param Mage_Core_Model_Store                 $store
     *
     * @return bool
     */
    protected function _createPaymentMethodFromBA(
        Adyen_Payment_Model_Billing_Agreement $billingAgreement,
        Mage_Core_Model_Store $store)
    {
        $methodInstance = $billingAgreement->getPaymentMethodInstance();
        if (! $methodInstance || ! $methodInstance->getConfigData('active', $store)) {
            return false;
        }

        $methodNewCode = 'adyen_oneclick_'.$billingAgreement->getReferenceId();

        $methodData = array('model' => 'adyen/adyen_oneclick')
            + $billingAgreement->getOneClickData()
            + Mage::getStoreConfig('payment/adyen_oneclick', $store);

        foreach ($methodData as $key => $value) {
            $store->setConfig('payment/'.$methodNewCode.'/'.$key, $value);
        }

        return true;
    }



    /**
     * @param string $methodCode ideal,mc,etc.
     * @param array $methodData
     */
    public function createPaymentMethodFromOneClick($methodCode, $methodData = array(), Mage_Core_Model_Store $store)
    {

        $methodNewCode = 'adyen_oneclick_'.$methodCode;

        $methodData = $methodData + Mage::getStoreConfig('payment/adyen_oneclick', $store);
        $methodData['model'] = 'adyen/adyen_oneclick';

        foreach ($methodData as $key => $value) {
            $store->setConfig('payment/'.$methodNewCode.'/'.$key, $value);
        }

        $store->setConfig('payment/adyen_oneclick/active', 0);
    }


//    /**
//     * @param Mage_Core_Model_Store $store
//     * @return array
//     */
//    protected function _fetchOneClickMethods(Mage_Core_Model_Store $store)
//    {
//        $adyenHelper = Mage::helper('adyen');
//        $paymentMethods = array();
//
//        $merchantAccount = trim($adyenHelper->getConfigData('merchantAccount', 'adyen_abstract', $store->getId()));
//
//        $customer = Mage::helper('adyen/billing_agreement')->getCurrentCustomer();
//        if (! $customer && ! $customer->getId()) {
//            return $this;
//        }
//
//        $customerId =$customer->getId();
//        $recurringType = $adyenHelper->getConfigData('recurringtypes', 'adyen_abstract', $store->getId());
//        $recurringCarts = $adyenHelper->getRecurringCards($merchantAccount, $customerId, $recurringType);
//
//        $paymentMethods = array();
//        foreach ($recurringCarts as $key => $paymentMethod) {
//
//            $paymentMethodCode = $paymentMethod['recurringDetailReference'];
//            $paymentMethods[$paymentMethodCode] = $paymentMethod;
//
//            if($paymentMethod['variant'] == 'sepadirectdebit' || $paymentMethod['variant'] == 'ideal' || $paymentMethod['variant'] == 'openinvoice') {
//                $paymentMethods[$paymentMethodCode]['title'] = $paymentMethod['bank_ownerName'] ;
//            } else if($paymentMethod['variant'] == 'elv') {
//                $paymentMethods[$paymentMethodCode]['title'] = $paymentMethod['elv_accountHolderName'] ;
//            } else if(isset($paymentMethod["card_holderName"]) && isset($paymentMethod['card_number'])) {
//                $paymentMethods[$paymentMethodCode]['title'] = $paymentMethod["card_holderName"] . " **** " . $paymentMethod['card_number'];
//            } else {
//                // for now ignore PayPal and Klarna because we have no information on what account this is linked to. You will only get these back when you have recurring enabled
////                    $paymentMethods[$paymentMethodCode]['title'] = Mage::helper('adyen')->__('Saved Card') . " " . $paymentMethod["variant"];
//                unset($paymentMethods[$paymentMethodCode]);
//            }
//        }
//
//        return $paymentMethods;
//    }
}
