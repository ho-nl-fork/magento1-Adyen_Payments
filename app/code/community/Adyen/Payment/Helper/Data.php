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
     * @var unknown_type
     */
    const DEBUG_LEVEL = 7;

    public function getCcTypes() {
        $_types = Mage::getConfig()->getNode('default/adyen/payment/cctypes')->asArray();
        uasort($_types, array('Mage_Payment_Model_Config', 'compareCcTypes'));
        $types = array();
        foreach ($_types as $data) {
            $types[$data['code']] = $data['name'];
        }
        return $types;
    }

    public function getBoletoTypes() {
        $_types = Mage::getConfig()->getNode('default/adyen/payment/boletotypes')->asArray();
        $types = array();
        foreach ($_types as $data) {
            $types[$data['code']] = $data['name'];
        }
        return $types;
    }

    public function getOpenInvoiceTypes() {
        $_types = Mage::getConfig()->getNode('default/adyen/payment/openinvoicetypes')->asArray();
        $types = array();
        foreach ($_types as $data) {
            $types[$data['code']] = $data['name'];
        }
        return $types;
    }

    public function getRecurringTypes() {
        $_types = Mage::getConfig()->getNode('default/adyen/payment/recurringtypes')->asArray();
        $types = array();
        foreach ($_types as $data) {
            $types[$data['code']] = $data['name'];
        }
        return $types;
    }

    public function getExtensionVersion() {
        return (string) Mage::getConfig()->getNode()->modules->Adyen_Payment->version;
    }

    public function hasEnableScanner() {
        if(Mage::getStoreConfig('payment/adyen_pos/active')) {
            return (int) Mage::getStoreConfig('payment/adyen_pos/enable_scanner');
        }
        return false;
    }

    public function hasAutoSubmitScanner() {
        return (int) Mage::getStoreConfig('payment/adyen_pos/auto_submit_scanner');
    }

    public function hasExpressCheckout() {
        if(Mage::getStoreConfig('payment/adyen_pos/active')) {
            return (int) Mage::getStoreConfig('payment/adyen_pos/express_checkout');
        }
        return false;
    }

    public function hasCashExpressCheckout() {
        if(Mage::getStoreConfig('payment/adyen_pos/active')) {
            return (int) Mage::getStoreConfig('payment/adyen_pos/cash_express_checkout');
        }
        return false;
    }

    public function getOrderStatus() {
        return Mage::getStoreConfig('payment/adyen_abstract/order_status');
    }

    /**
     * @param Mage_Sales_Model_Quote | Mage_Sales_Model_Order $object
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
     */
    public function getPaymentFeeAmount($object)
    {
        return Mage::getStoreConfig('payment/adyen_openinvoice/fee');
    }

    public function formatAmount($amount, $currency) {

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

    /*
     * creditcard type that is selected is different from creditcard type that we get back from the request
     * this function get the magento creditcard type this is needed for getting settings like installments
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

    public function getRecurringCards($merchantAccount, $customerId, $recurringType) {

        $cacheKey = $merchantAccount . "|" . $customerId . "|" . $recurringType;

        // Load response from cache.
        if ($recurringCards = Mage::app()->getCache()->load($cacheKey)) {
            return unserialize($recurringCards);
        }

        // create a arraylist with the cards
        $recurringCards = array();

        // do not show the oneclick if recurring type is empty or recurring
        if($recurringType == "ONECLICK" || $recurringType == "ONECLICK,RECURRING" || $recurringType == "RECURRING")
        {
            // recurring type is always ONECLICK
            if($recurringType == "ONECLICK,RECURRING") {
                $recurringType = "ONECLICK";
            }

            // rest call to get listrecurring details
            $request = array(
                "action" => "Recurring.listRecurringDetails",
                "recurringDetailsRequest.merchantAccount" => $merchantAccount,
                "recurringDetailsRequest.shopperReference" => $customerId,
                "recurringDetailsRequest.recurring.contract" => $recurringType, // i.e.: "ONECLICK" Or "RECURRING"
            );

            $ch = curl_init();

            $isConfigDemoMode = $this->getConfigDataDemoMode($storeId = null);
            $wsUsername = $this->getConfigDataWsUserName($storeId);
            $wsPassword = $this->getConfigDataWsPassword($storeId);

            if ($isConfigDemoMode)
                curl_setopt($ch, CURLOPT_URL, "https://pal-test.adyen.com/pal/adapter/httppost");
            else
                curl_setopt($ch, CURLOPT_URL, "https://pal-live.adyen.com/pal/adapter/httppost");

            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC  );
            curl_setopt($ch, CURLOPT_USERPWD,$wsUsername.":".$wsPassword);
            curl_setopt($ch, CURLOPT_POST,count($request));
            curl_setopt($ch, CURLOPT_POSTFIELDS,http_build_query($request));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            $results = curl_exec($ch);
            $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if ($httpStatus != 200) {
                Mage::throwException(
                    Mage::helper('adyen')->__('HTTP Status code %s received, data %s', $httpStatus, $results)
                );
            }

            if ($results === false) {
                Mage::throwException(
                    Mage::helper('adyen')->__('Got an empty response, status code %s', $httpStatus)
                );
            }else{
                /**
                 * The $result contains a JSON array containing
                 * the available payment methods for the merchant account.
                 */

                // convert result to utf8 characters
                $result = utf8_encode(urldecode($results));
                // convert to array
                parse_str($result,$result);

                foreach($result as $key => $value) {
                    // strip the key
                    $key = str_replace("recurringDetailsResult_details_", "", $key);
                    $key2 = strstr($key, '_');
                    $keyNumber = str_replace($key2, "", $key);
                    $keyAttribute = substr($key2, 1);

                    // set ideal to sepadirectdebit because it is and we want to show sepadirectdebit logo
                    if($keyAttribute == "variant" && $value == "ideal") {
                        $value = 'sepadirectdebit';
                    }

                    $recurringCards[$keyNumber][$keyAttribute] = $value;
                }
                // unset the recurringDetailsResult because this is not a card
                unset($recurringCards["recurringDetailsResult"]);
            }
        }

        // Save response to cache.
        Mage::app()->getCache()->save(
            serialize($recurringCards),
            $cacheKey,
            array(Mage_Core_Model_Config::CACHE_TAG),
            60 * 5 // save for 5 minutes ( and will be removed if payment is done)
        );
        return $recurringCards;
    }

    public function removeRecurringCard($merchantAccount, $shopperReference, $recurringDetailReference) {

        // rest call to disable cart
        $request = array(
            "action" => "Recurring.disable",
            "disableRequest.merchantAccount" => $merchantAccount,
            "disableRequest.shopperReference" => $shopperReference,
            "disableRequest.recurringDetailReference" => $recurringDetailReference
        );

        $ch = curl_init();

        $isConfigDemoMode = $this->getConfigDataDemoMode();
        $wsUsername = $this->getConfigDataWsUserName();
        $wsPassword = $this->getConfigDataWsPassword();

        if ($isConfigDemoMode)
            curl_setopt($ch, CURLOPT_URL, "https://pal-test.adyen.com/pal/adapter/httppost");
        else
            curl_setopt($ch, CURLOPT_URL, "https://pal-live.adyen.com/pal/adapter/httppost");

        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC  );
        curl_setopt($ch, CURLOPT_USERPWD,$wsUsername.":".$wsPassword);
        curl_setopt($ch, CURLOPT_POST,count($request));
        curl_setopt($ch, CURLOPT_POSTFIELDS,http_build_query($request));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $result = curl_exec($ch);

        if($result === false) {
            Mage::log("Disable recurring contract is failing, error is: " . curl_error($ch), self::DEBUG_LEVEL, 'http-request.log',true);
            Mage::throwException(Mage::helper('adyen')->__('Disable recurring contract is generating the error see the log'));
        } else{

            // convert result to utf8 characters
            $result = utf8_encode(urldecode($result));

            if($result != "disableResult.response=[detail-successfully-disabled]") {
                Mage::log("Disable contract is not succeeded the response is: " . $result, self::DEBUG_LEVEL, 'http-request.log',true);
                return false;
            }
            return true;
        }
        return false;
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

    public function getConfigDataWsUserName($storeId = null)
    {
        if ($this->getConfigDataDemoMode($storeId)) {
            return $this->getConfigData('ws_username_test', null, $storeId);
        }
        return $this->getConfigData('ws_username_live', null, $storeId);
    }

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
     * @todo    implement trim method for config value.
     * @return mixed
     */
    public function getConfigData($code, $paymentMethodCode = null, $storeId = null) {
        if (null === $storeId) {
            $storeId = Mage::app()->getStore()->getStoreId();
        }
        if (empty($paymentMethodCode)) {
            return Mage::getStoreConfig("payment/adyen_abstract/$code", $storeId);
        }
        return Mage::getStoreConfig("payment/$paymentMethodCode/$code", $storeId);
    }

    // Function to get the client ip address
    public function getClientIp() {
        $ipaddress = '';

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
