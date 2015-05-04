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
class Adyen_Payment_Helper_Data extends Mage_Payment_Helper_Data {

    /**
     * Zend_Log debug level
     * @var int
     */
    const DEBUG_LEVEL = 7;
    
    /**
     * @return array
     */
    public function getCcTypes()
    {
        $_types = Mage::getConfig()->getNode('default/adyen/payment/cctypes')->asArray();
        uasort($_types, array('Mage_Payment_Model_Config', 'compareCcTypes'));
        $types = array();
        foreach ($_types as $data) {
            $types[$data['code']] = $data['name'];
        }
        return $types;
    }


    /**
     * @return array
     */
    public function getBoletoTypes()
    {
        $_types = Mage::getConfig()->getNode('default/adyen/payment/boletotypes')->asArray();
        $types = array();
        foreach ($_types as $data) {
            $types[$data['code']] = $data['name'];
        }
        return $types;
    }


    /**
     * @return array
     */
    public function getOpenInvoiceTypes()
    {
        $_types = Mage::getConfig()->getNode('default/adyen/payment/openinvoicetypes')->asArray();
        $types = array();
        foreach ($_types as $data) {
            $types[$data['code']] = $data['name'];
        }
        return $types;
    }


    /**
     * @return array
     */
    public function getRecurringTypes()
    {
        $_types = Mage::getConfig()->getNode('default/adyen/payment/recurringtypes')->asArray();
        $types = array();
        foreach ($_types as $data) {
            $types[$data['code']] = $data['name'];
        }
        return $types;
    }


    /**
     * @return string
     */
    public function getExtensionVersion()
    {
        return (string) Mage::getConfig()->getModuleConfig('Adyen_Payment')->version;
    }


    /**
     * @return bool|int
     */
    public function hasEnableScanner()
    {
        if(Mage::getStoreConfig('payment/adyen_pos/active')) {
            return (int) Mage::getStoreConfig('payment/adyen_pos/enable_scanner');
        }
        return false;
    }


    /**
     * @return int
     */
    public function hasAutoSubmitScanner()
    {
        return (int) Mage::getStoreConfig('payment/adyen_pos/auto_submit_scanner');
    }


    /**
     * @return bool|int
     */
    public function hasExpressCheckout()
    {
        if(Mage::getStoreConfig('payment/adyen_pos/active')) {
            return (int) Mage::getStoreConfig('payment/adyen_pos/express_checkout');
        }
        return false;
    }


    /**
     * @return bool|int
     */
    public function hasCashExpressCheckout()
    {
        if(Mage::getStoreConfig('payment/adyen_pos/active')) {
            return (int) Mage::getStoreConfig('payment/adyen_pos/cash_express_checkout');
        }
        return false;
    }


    /**
     * @return mixed
     */
    public function getOrderStatus()
    {
        return Mage::getStoreConfig('payment/adyen_abstract/order_status');
    }


    /**
     * @param Mage_Sales_Model_Quote | Mage_Sales_Model_Order $object
     * @return bool
     */
    public function isPaymentFeeEnabled($object)
    {
        $fee = Mage::getStoreConfig('payment/adyen_openinvoice/fee');
        $paymentMethod = $object->getPayment()->getMethod() ;
        if ($paymentMethod == 'adyen_openinvoice' && $fee > 0) {
            return true;
        }
        else {
            return false;
        }
    }


    /**
     * @param Mage_Sales_Model_Quote | Mage_Sales_Model_Order $object
     * @return float
     */
    public function getPaymentFeeAmount($object)
    {
        return Mage::getStoreConfig('payment/adyen_openinvoice/fee');
    }


    /**
     * Return the formatted currency. Adyen accepts the currency in multiple formats.
     * @param $amount
     * @param $currency
     *
     * @return string
     */
    public function formatAmount($amount, $currency)
    {
        switch($currency) {
            case "JPY":
            case "IDR":
            case "KRW":
            case "BYR":
            case "VND":
            case "CVE":
            case "DJF":
            case "GNF":
            case "PYG":
            case "RWF":
            case "UGX":
            case "VUV":
            case "XAF":
            case "XOF":
            case "XPF":
            case "GHC":
                $format = 0;
                break;
            case "MRO":
                $format = 1;
                break;
            case "BHD":
            case "JOD":
            case "KWD":
            case "OMR":
            case "LYD":
            case "TND":
                $format = 3;
                break;
            default:
                $format = 2;
                break;
        }

        return number_format($amount, $format, '', '');
    }

    public function originalAmount($amount, $currency) {
        // check the format
        switch($currency) {
            case "JPY":
            case "IDR":
            case "KRW":
            case "BYR":
            case "VND":
            case "CVE":
            case "DJF":
            case "GNF":
            case "PYG":
            case "RWF":
            case "UGX":
            case "VUV":
            case "XAF":
            case "XOF":
            case "XPF":
            case "GHC":
                $format = 1;
                break;
            case "MRO":
                $format = 10;
                break;
            case "BHD":
            case "JOD":
            case "KWD":
            case "OMR":
            case "LYD":
            case "TND":
                $format = 1000;
                break;
            default:
                $format = 100;
                break;
        }

        return ($amount / $format);
    }
    
    /**
     * Creditcard type that is selected is different from creditcard type that we get back from the request this
     * function get the magento creditcard type this is needed for getting settings like installments
     * @param $ccType
     * @return mixed
     */
    public function getMagentoCreditCartType($ccType) {

        $ccTypesMapper = array("amex" => "AE",
            "visa" => "VI",
            "mastercard" => "MC",
            "discover" => "DI",
            "diners" => "DC",
            "maestro" => "SM",
            "jcb" => "JCB",
            "elo" => "ELO",
            "hipercard" => "hipercard"
        );

        if(isset($ccTypesMapper[$ccType])) {
            $ccType = $ccTypesMapper[$ccType];
        }

        return $ccType;
    }


    /**
     * Used via Payment method.Notice via configuration ofcourse Y or N
     * @return boolean true on demo, else false
     */
    public function getConfigDataDemoMode($storeId = null)
    {
        if ($this->getConfigData('demoMode', null, $storeId) == 'Y') {
            return true;
        }
        return false;
    }


    /**
     * @param null $storeId
     *
     * @return mixed
     */
    public function getConfigDataWsUserName($storeId = null)
    {
        if ($this->getConfigDataDemoMode($storeId)) {
            return $this->getConfigData('ws_username_test', null, $storeId);
        }
        return $this->getConfigData('ws_username_live', null, $storeId);
    }


    /**
     * @param null $storeId
     *
     * @return string
     */
    public function getConfigDataWsPassword($storeId = null)
    {
        if ($this->getConfigDataDemoMode($storeId)) {
            return Mage::helper('core')->decrypt($this->_getConfigData('ws_password_test', null, $storeId));
        }
        return Mage::helper('core')->decrypt($this->_getConfigData('ws_password_live', null, $storeId));
    }


    /**
     * @param      $code
     * @param null $paymentMethodCode
     * @param null $storeId
     * @deprecated please use getConfigData
     * @return mixed
     */
    public function _getConfigData($code, $paymentMethodCode = null, $storeId = null)
    {
        return $this->getConfigData($code, $paymentMethodCode, $storeId);
    }


    /**
     * @desc    Give Default settings
     * @example $this->_getConfigData('demoMode','adyen_abstract')
     * @since   0.0.2
     *
     * @param string $code
     *
     * @return mixed
     */
    public function getConfigData($code, $paymentMethodCode = null, $storeId = null) {
        if (null === $storeId) {
            $storeId = Mage::app()->getStore()->getStoreId();
        }
        if (empty($paymentMethodCode)) {
            return trim(Mage::getStoreConfig("payment/adyen_abstract/$code", $storeId));
        }
        return trim(Mage::getStoreConfig("payment/$paymentMethodCode/$code", $storeId));
    }


    /**
     * Get the client ip address
     * @return string
     */
    public function getClientIp() {
        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
        } else if(isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else if(isset($_SERVER['HTTP_X_FORWARDED'])) {
            $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
        }else if(isset($_SERVER['HTTP_FORWARDED_FOR'])) {
            $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
        } else if(isset($_SERVER['HTTP_FORWARDED'])) {
            $ipaddress = $_SERVER['HTTP_FORWARDED'];
        } else if(isset($_SERVER['REMOTE_ADDR'])) {
            $ipaddress = $_SERVER['REMOTE_ADDR'];
        } else {
            $ipaddress = '';
        }

        return $ipaddress;
    }


    /**
     * Is th IP in the given range
     * @param $ip
     * @param $from
     * @param $to
     *
     * @return bool
     */
    public function ipInRange($ip, $from, $to) {
        $ip = ip2long($ip);
        $lowIp = ip2long($from);
        $highIp = ip2long($to);

        if ($ip <= $highIp && $lowIp <= $ip) {
            return true;
        }
        return false;
    }

}
