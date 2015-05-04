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
class Adyen_Payment_Model_Adyen_Openinvoice extends Adyen_Payment_Model_Adyen_Hpp {

    protected $_canUseInternal = false;
    protected $_code = 'adyen_openinvoice';
    protected $_formBlockType = 'adyen/form_openinvoice';
    protected $_infoBlockType = 'adyen/info_openinvoice';
    protected $_paymentMethod = 'openinvoice';


    public function isApplicableToQuote($quote, $checksBitMask)
    {

        if($this->_getConfigData('failed_attempt_disable', 'adyen_openinvoice')) {
            $openInvoiceInactiveForThisQuoteId = Mage::getSingleton('checkout/session')->getOpenInvoiceInactiveForThisQuoteId();
            if($openInvoiceInactiveForThisQuoteId != "") {
                // check if quoteId is the same
                if($quote->getId() == $openInvoiceInactiveForThisQuoteId) {
                    return false;
                }
            }
        }

        // different don't show
        if($this->_getConfigData('different_address_disable', 'adyen_openinvoice')) {

            // get billing and shipping information
            $billing = $quote->getBillingAddress()->getData();
            $shipping = $quote->getShippingAddress()->getData();

            // check if the following items are different: street, city, postcode, region, countryid
            if(isset($billing['street']) && isset($billing['city']) && $billing['postcode'] && isset($billing['region']) && isset($billing['country_id'])) {
                $billingAddress = array($billing['street'], $billing['city'], $billing['postcode'], $billing['region'],$billing['country_id']);
            } else {
                $billingAddress = array();
            }
            if(isset($shipping['street']) && isset($shipping['city']) && $shipping['postcode'] && isset($shipping['region']) && isset($shipping['country_id'])) {
                $shippingAddress = array($shipping['street'], $shipping['city'], $shipping['postcode'], $shipping['region'],$shipping['country_id']);
            } else {
                $shippingAddress = array();
            }

            // if the result are not the same don't show the payment method open invoice
            $diff = array_diff($billingAddress,$shippingAddress);
            if(is_array($diff) && !empty($diff)) {
                return false;
            }
        }
        return parent::isApplicableToQuote($quote, $checksBitMask);
    }

    public function assignData($data) {
        if (!($data instanceof Varien_Object)) {
            $data = new Varien_Object($data);
        }

        $info = $this->getInfoInstance();
        $info->setCcType('openinvoice');

        // check if option gender or date of birth is enabled
        $genderShow = $this->genderShow();
        $dobShow = $this->dobShow();
        $telephoneShow = $this->telephoneShow();

        if($genderShow || $dobShow || $telephoneShow) {

            // set gender and dob to the quote
            $quote = $this->getQuote();

            // dob must be in yyyy-MM-dd
            $dob = $data->getYear() . "-" . $data->getMonth() . "-" . $data->getDay();

            if($dobShow)
                $quote->setCustomerDob($dob);

            if($genderShow) {
                $quote->setCustomerGender($data->getGender());
                // Fix for OneStepCheckout (won't convert quote customerGender to order object)
                $info->setAdditionalInformation('customerGender', $data->getGender());
            }

            if($telephoneShow) {
                $telephone = $data->getTelephone();
                $quote->getBillingAddress()->setTelephone($data->getTelephone());
            }

            /* Check if the customer is logged in or not */
            if (Mage::getSingleton('customer/session')->isLoggedIn()) {

                /* Get the customer data */
                $customer = Mage::getSingleton('customer/session')->getCustomer();

                // set the email and/or gender
                if($dobShow) {
                    $customer->setDob($dob);
                }

                if($genderShow) {
                    $customer->setGender($data->getGender());
                }

                if($telephoneShow) {
                    $billingAddress = $customer->getPrimaryBillingAddress();
                    if($billingAddress) {
                        $billingAddress->setTelephone($data->getTelephone());
                    }
                }

                // save changes into customer
                $customer->save();
            }
        }

        return $this;
    }

    /**
     * @desc Get url of Adyen payment
     * @return string
     * @todo add brandCode here
     */
    public function getFormUrl() {
        $paymentRoutine = $this->_getConfigData('payment_routines', 'adyen_hpp');
        $openinvoiceType = $this->_getConfigData('openinvoicetypes', 'adyen_openinvoice');

        switch ($this->getConfigDataDemoMode()) {
            case true:
                if ($paymentRoutine == 'single' && empty($openinvoiceType)) {
                    $url = 'https://test.adyen.com/hpp/pay.shtml';
                } else {
                    $url = "https://test.adyen.com/hpp/details.shtml?brandCode=".$openinvoiceType;
                }
                break;
            default:
                if ($paymentRoutine == 'single' && empty($openinvoiceType)) {
                    $url = 'https://live.adyen.com/hpp/pay.shtml';
                } else {
                    $url = "https://live.adyen.com/hpp/details.shtml?brandCode=".$openinvoiceType;
                }
                break;
        }
        return $url;
    }

    public function getFormName() {
        return "Adyen HPP";
    }

    /**
     * @desc Openinvoice Optional Fields.
     * @desc Notice these are used to prepopulate the fields, but client can edit them at Adyen.
     * @return type array
     */
    public function getFormFields() {
        $adyFields = parent::getFormFields();
        $adyFields = $this->getOptionalFormFields($adyFields,$this->_order);
        return $adyFields;
    }

    public function getOptionalFormFields($adyFields,$order) {
        if (empty($order)) return $adyFields;

        $helper = Mage::helper('adyen');
        $secretWord = $this->_getSecretWord();

        $billingAddress = $order->getBillingAddress();
        $adyFields['shopper.firstName'] = $billingAddress->getFirstname();
        $adyFields['shopper.lastName'] = $billingAddress->getLastname();
        $adyFields['billingAddress.street'] = $helper->getStreet($billingAddress)->getName();
        $adyFields['billingAddress.houseNumberOrName'] = $helper->getStreet($billingAddress)->getHouseNumber();
        $adyFields['billingAddress.city'] = $billingAddress->getCity();
        $adyFields['billingAddress.postalCode'] = $billingAddress->getPostcode();
        $adyFields['billingAddress.stateOrProvince'] = $billingAddress->getRegion();
        $adyFields['billingAddress.country'] = $billingAddress->getCountryId();
        $sign = $adyFields['billingAddress.street'] .
            $adyFields['billingAddress.houseNumberOrName'] .
            $adyFields['billingAddress.city'] .
            $adyFields['billingAddress.postalCode'] .
            $adyFields['billingAddress.stateOrProvince'] .
            $adyFields['billingAddress.country']
        ;
        //Generate HMAC encrypted merchant signature
        $signMac = Zend_Crypt_Hmac::compute($secretWord, 'sha1', $sign);
        $adyFields['billingAddressSig'] = base64_encode(pack('H*', $signMac));


        $deliveryAddress = $order->getShippingAddress();
        if($deliveryAddress != null)
        {
            $adyFields['deliveryAddress.street'] = $helper->getStreet($deliveryAddress)->getName();
            $adyFields['deliveryAddress.houseNumberOrName'] = $helper->getStreet($deliveryAddress)->getHouseNumber();
            $adyFields['deliveryAddress.city'] = $deliveryAddress->getCity();
            $adyFields['deliveryAddress.postalCode'] = $deliveryAddress->getPostcode();
            $adyFields['deliveryAddress.stateOrProvince'] = $deliveryAddress->getRegion();
            $adyFields['deliveryAddress.country'] = $deliveryAddress->getCountryId();
            $sign = $adyFields['deliveryAddress.street'] .
                $adyFields['deliveryAddress.houseNumberOrName'] .
                $adyFields['deliveryAddress.city'] .
                $adyFields['deliveryAddress.postalCode'] .
                $adyFields['deliveryAddress.stateOrProvince'] .
                $adyFields['deliveryAddress.country']
            ;
            //Generate HMAC encrypted merchant signature
            $secretWord = $this->_getSecretWord();
            $signMac = Zend_Crypt_Hmac::compute($secretWord, 'sha1', $sign);
            $adyFields['deliveryAddressSig'] = base64_encode(pack('H*', $signMac));
        }


        if ($adyFields['shopperReference'] != (self::GUEST_ID .  $order->getRealOrderId())) {

            $customer = Mage::getModel('customer/customer')->load($adyFields['shopperReference']);

            if($this->getCustomerAttributeText($customer, 'gender') != "") {
                $adyFields['shopper.gender'] = strtoupper($this->getCustomerAttributeText($customer, 'gender'));
            } else {
                // fix for OneStepCheckout (guest is not logged in but uses email that exists with account)
                $_customer = Mage::getModel('customer/customer');
                if($order->getCustomerGender()) {
                    $customerGender = $order->getCustomerGender();
                } else {
                    // this is still empty for OneStepCheckout so uses extra saved parameter
                    $payment = $order->getPayment();
                    $customerGender = $payment->getAdditionalInformation('customerGender');
                }
                $adyFields['shopper.gender'] = strtoupper($_customer->getResource()->getAttribute('gender')->getSource()->getOptionText($customerGender));
            }

            $adyFields['shopper.infix'] = $customer->getPrefix();
            $dob = $customer->getDob();

            if (!empty($dob)) {
                $adyFields['shopper.dateOfBirthDayOfMonth'] = $this->getDate($dob, 'd');
                $adyFields['shopper.dateOfBirthMonth'] = $this->getDate($dob, 'm');
                $adyFields['shopper.dateOfBirthYear'] = $this->getDate($dob, 'Y');
            } else {
                // fix for OneStepCheckout (guest is not logged in but uses email that exists with account)
                $dob = $order->getCustomerDob();
                if (!empty($dob)) {
                    $adyFields['shopper.dateOfBirthDayOfMonth'] = $this->getDate($dob, 'd');
                    $adyFields['shopper.dateOfBirthMonth'] = $this->getDate($dob, 'm');
                    $adyFields['shopper.dateOfBirthYear'] = $this->getDate($dob, 'Y');
                }
            }
        } else {
            // checkout as guest use details from the order
            $_customer = Mage::getModel('customer/customer');
            $adyFields['shopper.gender'] = strtoupper($_customer->getResource()->getAttribute('gender')->getSource()->getOptionText($order->getCustomerGender()));
            $adyFields['shopper.infix'] = $order->getCustomerPrefix();
            $dob = $order->getCustomerDob();
            if (!empty($dob)) {
                $adyFields['shopper.dateOfBirthDayOfMonth'] = $this->getDate($dob, 'd');
                $adyFields['shopper.dateOfBirthMonth'] = $this->getDate($dob, 'm');
                $adyFields['shopper.dateOfBirthYear'] = $this->getDate($dob, 'Y');
            }
        }
        // for sweden add here your socialSecurityNumber
        // $adyFields['shopper.socialSecurityNumber'] = "Result of your custom input field";

        $adyFields['shopper.telephoneNumber'] = $billingAddress->getTelephone();

        $openinvoiceType = $this->_getConfigData('openinvoicetypes', 'adyen_openinvoice');

        // get current payment method
        if($order->getPayment()->getMethod() == "adyen_openinvoice" || $order->getPayment()->getMethodInstance()->getInfoInstance()->getCcType() == "klarna" || $order->getPayment()->getMethodInstance()->getInfoInstance()->getCcType() == "afterpay_default" ) {
            // initialize values if they are empty
            $adyFields['shopper.gender'] = (isset($adyFields['shopper.gender'])) ? $adyFields['shopper.gender'] : "";
            $adyFields['shopper.infix'] = (isset($adyFields['shopper.infix'])) ? $adyFields['shopper.infix'] : "";
            $adyFields['shopper.dateOfBirthDayOfMonth'] = (isset($adyFields['shopper.dateOfBirthDayOfMonth'])) ? $adyFields['shopper.dateOfBirthDayOfMonth'] : "";
            $adyFields['shopper.dateOfBirthMonth'] = (isset($adyFields['shopper.dateOfBirthMonth'])) ? $adyFields['shopper.dateOfBirthMonth'] : "";
            $adyFields['shopper.dateOfBirthYear'] = (isset($adyFields['shopper.dateOfBirthYear'])) ? $adyFields['shopper.dateOfBirthYear'] : "";

            $shoppperSign = $adyFields['shopper.firstName'] . $adyFields['shopper.infix'] . $adyFields['shopper.lastName'] . $adyFields['shopper.gender'] . $adyFields['shopper.dateOfBirthDayOfMonth'] . $adyFields['shopper.dateOfBirthMonth'] . $adyFields['shopper.dateOfBirthYear'] . $adyFields['shopper.telephoneNumber'];
            $shopperSignMac = Zend_Crypt_Hmac::compute($secretWord, 'sha1', $shoppperSign);
            $adyFields['shopperSig'] = base64_encode(pack('H*', $shopperSignMac));
        }


        $count = 0;
        $currency = $order->getOrderCurrencyCode();
        $additional_data_sign = array();

        foreach ($order->getItemsCollection() as $item) {
            //skip dummies
            if ($item->isDummy()) continue;

            ++$count;
            $linename = "line".$count;
            $additional_data_sign['openinvoicedata.' . $linename . '.currencyCode'] = $currency;
            $additional_data_sign['openinvoicedata.' . $linename . '.description'] = $item->getName();
            $additional_data_sign['openinvoicedata.' . $linename . '.itemAmount'] = $helper->formatAmount($item->getPrice(), $currency);
            $additional_data_sign['openinvoicedata.' . $linename . '.itemVatAmount'] = ($item->getTaxAmount() > 0 && $item->getPriceInclTax() > 0) ? $helper->formatAmount($item->getPriceInclTax(), $currency) - $helper->formatAmount($item->getPrice(), $currency):$helper->formatAmount($item->getTaxAmount(), $currency);
            $additional_data_sign['openinvoicedata.' . $linename . '.numberOfItems'] = (int) $item->getQtyOrdered();

            if(($order->getPayment()->getMethod() == "adyen_openinvoice" && $openinvoiceType == "afterpay_default") || ($order->getPayment()->getMethodInstance()->getInfoInstance()->getCcType() == "afterpay_default")) {
                $additional_data_sign['openinvoicedata.' . $linename . '.vatCategory'] = "High";
            } else {
                $additional_data_sign['openinvoicedata.' . $linename . '.vatCategory'] = "None";
            }


        }

        //discount cost
        if($order->getDiscountAmount() > 0 || $order->getDiscountAmount() < 0)
        {
            $linename = "line".++$count;
            $additional_data_sign['openinvoicedata.' . $linename . '.currencyCode'] = $currency;
            $additional_data_sign['openinvoicedata.' . $linename . '.description'] = $helper->__('Total Discount');
            $additional_data_sign['openinvoicedata.' . $linename . '.itemAmount'] = $helper->formatAmount($order->getDiscountAmount(), $currency);
            $additional_data_sign['openinvoicedata.' . $linename . '.itemVatAmount'] = "0";
            $additional_data_sign['openinvoicedata.' . $linename . '.numberOfItems'] = 1;
            if(($order->getPayment()->getMethod() == "adyen_openinvoice" && $openinvoiceType == "afterpay_default") || ($order->getPayment()->getMethodInstance()->getInfoInstance()->getCcType() == "afterpay_default")) {
                $additional_data_sign['openinvoicedata.' . $linename . '.vatCategory'] = "High";
            } else {
                $additional_data_sign['openinvoicedata.' . $linename . '.vatCategory'] = "None";
            }

        }

        //shipping cost
        if($order->getShippingAmount() > 0 || $order->getShippingTaxAmount() > 0)
        {
            $linename = "line".++$count;
            $additional_data_sign['openinvoicedata.' . $linename . '.currencyCode'] = $currency;
            $additional_data_sign['openinvoicedata.' . $linename . '.description'] = $order->getShippingDescription();
            $additional_data_sign['openinvoicedata.' . $linename . '.itemAmount'] = $helper->formatAmount($order->getShippingAmount(), $currency);
            $additional_data_sign['openinvoicedata.' . $linename . '.itemVatAmount'] = $helper->formatAmount($order->getShippingTaxAmount(), $currency);
            $additional_data_sign['openinvoicedata.' . $linename . '.numberOfItems'] = 1;
            if(($order->getPayment()->getMethod() == "adyen_openinvoice" && $openinvoiceType == "afterpay_default") || ($order->getPayment()->getMethodInstance()->getInfoInstance()->getCcType() == "afterpay_default")) {
                $additional_data_sign['openinvoicedata.' . $linename . '.vatCategory'] = "High";
            } else {
                $additional_data_sign['openinvoicedata.' . $linename . '.vatCategory'] = "None";
            }
        }

        if($order->getPaymentFeeAmount() > 0) {
            $linename = "line".++$count;
            $additional_data_sign['openinvoicedata.' . $linename . '.currencyCode'] = $currency;
            $additional_data_sign['openinvoicedata.' . $linename . '.description'] = $helper->__('Payment Fee');
            $additional_data_sign['openinvoicedata.' . $linename . '.itemAmount'] = $helper->formatAmount($order->getPaymentFeeAmount(), $currency);
            $additional_data_sign['openinvoicedata.' . $linename . '.itemVatAmount'] = "0";
            $additional_data_sign['openinvoicedata.' . $linename . '.numberOfItems'] = 1;
            if(($order->getPayment()->getMethod() == "adyen_openinvoice" && $openinvoiceType == "afterpay_default") || ($order->getPayment()->getMethodInstance()->getInfoInstance()->getCcType() == "afterpay_default")) {
                $additional_data_sign['openinvoicedata.' . $linename . '.vatCategory'] = "High";
            } else {
                $additional_data_sign['openinvoicedata.' . $linename . '.vatCategory'] = "None";
            }
        }

        // Klarna wants tax cost provided in the lines of the products so overal tax cost is not needed anymore
//        $linename = "line".++$count;
//        $additional_data_sign['openinvoicedata.' . $linename . '.currencyCode'] = $currency;
//        $additional_data_sign['openinvoicedata.' . $linename . '.description'] = $helper->__('Tax');
//        $additional_data_sign['openinvoicedata.' . $linename . '.itemAmount'] = $helper->formatAmount($order->getTaxAmount(), $currency);
//        $additional_data_sign['openinvoicedata.' . $linename . '.itemVatAmount'] = "0";
//        $additional_data_sign['openinvoicedata.' . $linename . '.numberOfItems'] = 1;
//        $additional_data_sign['openinvoicedata.' . $linename . '.vatCategory'] = "None";

        // general for invoicelines
        $additional_data_sign['openinvoicedata.refundDescription'] = "Refund / Correction for ".$adyFields['merchantReference'];
        $additional_data_sign['openinvoicedata.numberOfLines'] = $count;

        // add merchantsignature in additional signature
        $additional_data_sign['merchantSig'] = $adyFields['merchantSig'];

        // generate signature
        ksort($additional_data_sign);

        // signature is first alphabatical keys seperate by : and then | and then the values seperate by :
        foreach($additional_data_sign as $key => $value) {
            // add to fields
            $adyFields[$key] = $value;
        }

        $keys = implode(':',array_keys($additional_data_sign));
        $values = implode(':',$additional_data_sign);
        $sign_additional_data = trim($keys) . '|' . trim($values);
        $signMac = Zend_Crypt_Hmac::compute($secretWord, 'sha1', $sign_additional_data);
        $adyFields['openinvoicedata.sig'] =  base64_encode(pack('H*', $signMac));

        Mage::log($adyFields, self::DEBUG_LEVEL, 'http-request.log');

        return $adyFields;
    }

    /**
     * Get Attribute label
     * @param type $customer
     * @param type $code
     * @return type
     */
    public function getCustomerAttributeText($customer, $code='gender') {
        $helper = Mage::helper('adyen');
        return $helper->htmlEscape($customer->getResource()->getAttribute($code)->getSource()->getOptionText($customer->getGender()));
    }

    /**
     * Date Manipulation
     * @param type $date
     * @param type $format
     * @return type date
     */
    public function getDate($date = null, $format = 'Y-m-d H:i:s') {
        if (strlen($date) < 0) {
            $date = date('d-m-Y H:i:s');
        }
        $timeStamp = new DateTime($date);
        return $timeStamp->format($format);
    }


    public function genderShow() {
        return $this->_getConfigData('gender_show', 'adyen_openinvoice');
    }

    public function dobShow() {
        return $this->_getConfigData('dob_show', 'adyen_openinvoice');
    }

    public function telephoneShow() {
        return $this->_getConfigData('telephone_show', 'adyen_openinvoice');
    }
}