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
class Adyen_Payment_Model_Adyen_Cc extends Adyen_Payment_Model_Adyen_Abstract
    implements Mage_Payment_Model_Billing_Agreement_MethodInterface {

    protected $_code = 'adyen_cc';
    protected $_formBlockType = 'adyen/form_cc';
    protected $_infoBlockType = 'adyen/info_cc';
    protected $_paymentMethod = 'cc';
    protected $_canCreateBillingAgreement = true;

    /**
     * 1) Called everytime the adyen_cc is called or used in checkout
     * @description Assign data to info model instance
     *
     * @param   mixed $data
     * @return  Mage_Payment_Model_Info
     */
    public function assignData($data) {

        if (!($data instanceof Varien_Object)) {
            $data = new Varien_Object($data);
        }
        $info = $this->getInfoInstance();

        // set number of installements
        $info->setAdditionalInformation('number_of_installments', $data->getAdditionalData());

        // save value remember details checkbox
        $info->setAdditionalInformation('store_cc', $data->getStoreCc());

        if ($this->isCseEnabled()) {
            $info->setCcType($data->getCcType());
            $info->setAdditionalInformation('encrypted_data', $data->getEncryptedData());
        }
        else {
            $info->setCcType($data->getCcType())
                ->setCcOwner($data->getCcOwner())
                ->setCcLast4(substr($data->getCcNumber(), -4))
                ->setCcNumber($data->getCcNumber())
                ->setCcExpMonth($data->getCcExpMonth())
                ->setCcExpYear($data->getCcExpYear())
                ->setCcCid($data->getCcCid())
                ->setPoNumber($data->getAdditionalData());
        }

        // recalculate the totals so that extra fee is defined
        $quote = (Mage::getModel('checkout/type_onepage') !== false)? Mage::getModel('checkout/type_onepage')->getQuote(): Mage::getModel('checkout/session')->getQuote();
        $quote->setTotalsCollectedFlag(false);
        $quote->collectTotals();
        // not needed
//        $quote->save();


        return $this;
    }

    public function getPossibleInstallments() {
        // retrieving quote
        $quote = (Mage::getModel('checkout/type_onepage') !== false)? Mage::getModel('checkout/type_onepage')->getQuote(): Mage::getModel('checkout/session')->getQuote();

        // get selected payment method for now
        $payment = $quote->getPayment();

        $ccType = null;
        if($payment && !empty($payment)) {
            if($payment->getMethod()) {
                $info = $payment->getMethodInstance();

                $instance = $info->getInfoInstance();
                $ccType = $instance->getCcType();
            }
        }

        $result = Mage::helper('adyen/installments')->getInstallmentForCreditCardType($ccType);

        return $result;
    }

    /**
     * @desc Helper functions to get config data
     */
    public function isCseEnabled() {
        return Mage::getStoreConfig("payment/adyen_cc/cse_enabled");
    }

    public function getCsePublicKey() {

        if (Mage::app()->getStore()->isAdmin()) {
            $quote = Mage::getSingleton('adminhtml/session_quote')->getQuote();
            $storeId = $quote->getStoreId();
        } else {
            $storeId = null;
        }

        if (Mage::helper('adyen')->getConfigDataDemoMode($storeId)) {
            return trim(Mage::getStoreConfig("payment/adyen_cc/cse_publickey_test"));
        }
        return trim(Mage::getStoreConfig("payment/adyen_cc/cse_publickey"));
    }

    /**
     * @desc Specific functions for 3d secure validation
     */

    public function getOrderPlaceRedirectUrl() {
        $redirectUrl = Mage::getSingleton('customer/session')->getRedirectUrl();

        if (!empty($redirectUrl)) {
            Mage::getSingleton('customer/session')->unsRedirectUrl();
            return Mage::getUrl($redirectUrl);
        }
        else {
            return parent::getOrderPlaceRedirectUrl();
        }
    }

    public function getFormUrl() {
        $this->_initOrder();
        $order = $this->_order;
        $payment = $order->getPayment();
        return $payment->getAdditionalInformation('issuerUrl');
    }

    public function getFormName() {
        return "Adyen CC";
    }

    public function getFormFields() {
        $this->_initOrder();
        $order = $this->_order;
        $payment = $order->getPayment();

        $adyFields = array();
        $adyFields['PaReq'] = $payment->getAdditionalInformation('paRequest');
        $adyFields['MD'] = $payment->getAdditionalInformation('md');
        $adyFields['TermUrl'] = Mage::getUrl('adyen/process/validate3d');

        return $adyFields;
    }


    /**
     * @return Adyen_Payment_Model_Api
     */
    protected function _api()
    {
        return Mage::getSingleton('adyen/api');
    }


    /**
     * Create billing agreement by token specified in request
     *
     * @param Mage_Payment_Model_Billing_AgreementAbstract $agreement
     * @return Mage_Paypal_Model_Method_Agreement
     */
    public function placeBillingAgreement(Mage_Payment_Model_Billing_AgreementAbstract $agreement)
    {
        $agreement->setRedirectUrl(
            Mage::getUrl('*/*/returnWizard', array('payment_method' => $this->getCode(), 'token' => uniqid('t')))
        );
        return $this;
    }


    /**
     * Update billing agreement status
     *
     * @param Mage_Sales_Model_Billing_Agreement|Mage_Payment_Model_Billing_AgreementAbstract $agreement
     *
     * @return Mage_Paypal_Model_Method_Agreement
     * @throws Exception
     * @throws Mage_Core_Exception
     */
    public function updateBillingAgreementStatus(Mage_Payment_Model_Billing_AgreementAbstract $agreement)
    {
        $targetStatus = $agreement->getStatus();
        $adyenHelper = Mage::helper('adyen');

        if ($targetStatus == Mage_Sales_Model_Billing_Agreement::STATUS_CANCELED) {
            try {
                $this->_api()->disableRecurringContract(
                    $agreement->getReferenceId(),
                    $agreement->getStoreId()
                );
            } catch (Adyen_Payment_Exception $e) {
                Mage::throwException($adyenHelper->__(
                    "Error while disabling Billing Agreement #%s: %s", $agreement->getReferenceId(), $e->getMessage()
                ));
            }
        } else {
            throw new Exception(Mage::helper('adyen')->__(
                'Changing billing agreement status to "%s" not yet implemented.', $targetStatus
            ));
        }
        return $this;
    }


    /**
     * Init billing agreement
     *
     * @param Mage_Payment_Model_Billing_AgreementAbstract $agreement
     * @return Mage_Paypal_Model_Method_Agreement
     */
    public function initBillingAgreementToken(Mage_Payment_Model_Billing_AgreementAbstract $agreement)
    {
        Mage::throwException('Not yet implemented to add a new credit card through the My Account section. The functionality will allow us to transfer a single cent through Adyen which allows us to set up a RECURRING profile.');
        return $this;
    }


    /**
     * Retrieve billing agreement customer details by token
     *
     * @param Mage_Payment_Model_Billing_AgreementAbstract $agreement
     * @return array
     */
    public function getBillingAgreementTokenInfo(Mage_Payment_Model_Billing_AgreementAbstract $agreement)
    {
        Mage::throwException('getBillingAgreementTokenInfo is not yet implemented');
        return $this;
    }


    /**
     * @param Adyen_Payment_Model_Billing_Agreement $billingAgreement
     * @param array                                 $data
     *
     * @return $this
     */
    public function addRecurringContractData(
        Adyen_Payment_Model_Billing_Agreement $billingAgreement,
        array $data)
    {
        parent::addRecurringContractData($billingAgreement, $data);

        $ccType = $data['variant'];
        $ccTypes = array_change_key_case(Mage::helper('adyen')->getCcTypes(), CASE_LOWER);
        if (isset($ccTypes[$ccType])) {
            $ccType = $ccTypes[$ccType];
        }

        $label = Mage::helper('adyen')->__('%s, %s, **** %s',
            $ccType,
            $data['card_holderName'],
            $data['card_number'],
            $data['card_expiryMonth'],
            $data['card_expiryYear']
        );
        $billingAgreement->setAgreementLabel($label);

        return $this;
    }
}
