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
class Adyen_Payment_Model_Authenticate extends Mage_Core_Model_Abstract {

    /**
     * @param type $actionName
     * @param type $varienObj
     * @return type 
     */
    public function authenticate($actionName, $varienObj) {
        $authStatus = false;
        switch ($actionName) {
            case 'success':
                $authStatus = $this->_signAuthenticate($varienObj);
                break;
            default:
                $authStatus = $this->_httpAuthenticate($varienObj);
                break;
        }
        return $authStatus;
    }

    /**
     * @desc Authenticate using sha1 Merchant signature
     * @see success Action during checkout
     * @param Varien_Object $response
     */
    protected function _signAuthenticate(Varien_Object $response) {
        if($this->_getConfigData('demoMode')=== 'Y') {
        	$secretWord = $this->_getConfigData('secret_wordt', 'adyen_hpp');
        }else{
        	$secretWord = $this->_getConfigData('secret_wordp', 'adyen_hpp');
        }

        $sign = $response->getData('authResult') . $response->getData('pspReference') .
                $response->getData('merchantReference') . $response->getData('skinCode') .
                $response->getData('merchantReturnData');

        $signMac = Zend_Crypt_Hmac::compute($secretWord, 'sha1', $sign);
        $localStringToHash = base64_encode(pack('H*', $signMac));
        if (strcmp($localStringToHash, $response->getData('merchantSig')) === 0) {
            return true;
        }
        return false;
    }

    /**
     * @desc Authenticate using http_auth
     * @see Notifications
     * @todo get rid of global variables here
     * @param Varien_Object $response
     */
    protected function _httpAuthenticate(Varien_Object $response) {
        $this->fixCgiHttpAuthentication(); //add cgi support
        $internalMerchantAccount = $this->_getConfigData('merchantAccount');
        $username = $this->_getConfigData('notification_username');
        $password = Mage::helper('core')->decrypt($this->_getConfigData('notification_password'));
        $submitedMerchantAccount = $response->getData('merchantAccountCode');
        $notificationHmac = $this->_getConfigData('notification_hmac');

        if (empty($submitedMerchantAccount) && empty($internalMerchantAccount)) {
        	if(strtolower(substr($response->getData('pspReference'),0,17)) == "testnotification_" || strtolower(substr($response->getData('pspReference'),0,5)) == "test_") {
                Mage::log('Notification test failed: merchantAccountCode is empty in magento settings', Zend_Log::DEBUG, "adyen_notification.log", true);
                echo 'merchantAccountCode is empty in magento settings'; exit();
        	}
            return false;
        }

        // If notification username and password is not set and HMAC is used as only authentication method validate HMAC key only
        if($notificationHmac != "" && $username == "" && $password == "") {
            return $this->validateNotificationHmac($response);
        }

        // validate username and password
        if ((!isset($_SERVER['PHP_AUTH_USER']) && !isset($_SERVER['PHP_AUTH_PW']))) {
        	if(strtolower(substr($response->getData('pspReference'),0,17)) == "testnotification_" || strtolower(substr($response->getData('pspReference'),0,5)) == "test_") {
                Mage::log('Authentication failed: PHP_AUTH_USER and PHP_AUTH_PW are empty. See Adyen Magento manual CGI mode', Zend_Log::DEBUG, "adyen_notification.log", true);
                echo 'Authentication failed: PHP_AUTH_USER and PHP_AUTH_PW are empty. See Adyen Magento manual CGI mode'; exit();
        	}
            return false;
        }

        // If HMAC encryption is used check if the notification is valid
        if($notificationHmac != "") {
            // if validation failed return false
            if(!$this->validateNotificationHmac($response)) {
                return false;
            }
        }

        $accountCmp = strcmp($submitedMerchantAccount, $internalMerchantAccount);
        $usernameCmp = strcmp($_SERVER['PHP_AUTH_USER'], $username);
        $passwordCmp = strcmp($_SERVER['PHP_AUTH_PW'], $password);
        if ($accountCmp === 0 && $usernameCmp === 0 && $passwordCmp === 0) {
            return true;
        }
        
        // If notification is test check if fields are correct if not return error
        if(strtolower(substr($response->getData('pspReference'),0,17)) == "testnotification_" || strtolower(substr($response->getData('pspReference'),0,5)) == "test_") {
        	if($accountCmp != 0) {
                Mage::log('MerchantAccount in notification is not the same as in Magento settings', Zend_Log::DEBUG, "adyen_notification.log", true);
                echo 'MerchantAccount in notification is not the same as in Magento settings'; exit();
        	} elseif($usernameCmp != 0 || $passwordCmp != 0) {
                Mage::log('username (PHP_AUTH_USER) and\or password (PHP_AUTH_PW) are not the same as Magento settings', Zend_Log::DEBUG, "adyen_notification.log", true);
                echo 'username (PHP_AUTH_USER) and\or password (PHP_AUTH_PW) are not the same as Magento settings'; exit();
        	}
        }

        return false;
    }

    public function validateNotificationHmac(Varien_Object $response) {

        // validate if signature is valid
        $submitedMerchantAccount = $response->getData('merchantAccountCode');
        $additionalData = $response->getData('additionalData'); // json
        $additionalDataHmac = $response->getData('additionalData_hmacSignature'); // httppost

        $hmacSignature = "";
        if(isset($additionalData["hmacSignature"]) && $additionalData["hmacSignature"] != "") {
            $hmacSignature = $additionalData["hmacSignature"];
        } elseif(isset($additionalDataHmac) && $additionalDataHmac != "") {
            $hmacSignature = $additionalDataHmac;
        }

        $notificationHmac = $this->_getConfigData('notification_hmac');
        if($hmacSignature != "") {
            // create Hmac signature

            $pspReference = trim($response->getData('pspReference'));
            $originalReference =  trim($response->getData('originalReference'));
            $merchantReference =  trim($response->getData('merchantReference'));
            $valueArray = $response->getData('amount');

            // json
            if($valueArray && is_array($valueArray)) {
                $value =  $valueArray['value'];
                $currencyCode = $valueArray['currency'];
            } else {

                // try http post values
                $valueValue = $response->getData('value');
                $currencyValue = $response->getData('currency');

                if(isset($valueValue) && $valueValue != "") {
                    $value = $valueValue;
                } else {
                    $value = "";
                }

                if(isset($currencyValue) && $currencyValue != "") {
                    $currencyCode = $currencyValue;
                } else {
                    $currencyCode = "";
                }
            }

            $eventCode =  $response->getData('eventCode');
            $success =  $response->getData('success');

            $sign = $pspReference . ":" . $originalReference . ":" . $submitedMerchantAccount . ":" . $merchantReference . ":" . $value . ":" .  $currencyCode . ":" .  $eventCode . ":" . $success;

            // decodeHex
            $decodeHex = pack('H*', $notificationHmac);

            $signMac = Zend_Crypt_Hmac::compute($decodeHex, 'sha256', $sign);
            $calculatedSign = base64_encode(pack('H*', $signMac));


            // validate signature with the one in the notification
            if(strcmp($calculatedSign, $hmacSignature) == 0) {
                return true;
            } else {
                Mage::log('HMAC Calculation is not correct. The HMAC key in notifications is not the same as Calculated HMAC key. Please check if the HMAC key in notification is the same as magento settings. If not sure generate new HMAC code save notification and put the key in Magento settings as well.', Zend_Log::DEBUG, "adyen_notification.log", true);
                if(strtolower(substr($response->getData('pspReference'),0,17)) == "testnotification_" || strtolower(substr($response->getData('pspReference'),0,5)) == "test_") {
                    echo 'HMAC Calculation is not correct. The HMAC key in notifications is not the same as Calculated HMAC key. Please check if the HMAC key in notification is the same as magento settings. If not sure generate new HMAC code save notification and put the key in Magento settings as well.'; exit();
                }
            }
        } else {
            Mage::log('HMAC is missing in Notification.', Zend_Log::DEBUG, "adyen_notification.log", true);
            if(strtolower(substr($response->getData('pspReference'),0,17)) == "testnotification_" || strtolower(substr($response->getData('pspReference'),0,5)) == "test_") {
                echo 'HMAC is missing in Notification.'; exit();
            }
        }
        return false;
    }

    /**
     * Fix these global variables for the CGI
     */
    public function fixCgiHttpAuthentication() { // unsupported is $_SERVER['REMOTE_AUTHORIZATION']: as stated in manual :p
    	if (isset($_SERVER['REDIRECT_REMOTE_AUTHORIZATION']) && $_SERVER['REDIRECT_REMOTE_AUTHORIZATION'] != '') { //pcd note: no idea who sets this
            list($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']) = explode(':', base64_decode($_SERVER['REDIRECT_REMOTE_AUTHORIZATION']));
        } elseif(!empty($_SERVER['HTTP_AUTHORIZATION'])){ //pcd note: standard in magento?
            list($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']) = explode(':', base64_decode(substr($_SERVER['HTTP_AUTHORIZATION'], 6)));
        } elseif (!empty($_SERVER['REMOTE_USER'])) { //pcd note: when cgi and .htaccess modrewrite patch is executed
            list($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']) = explode(':', base64_decode(substr($_SERVER['REMOTE_USER'], 6)));
        } elseif (!empty($_SERVER['REDIRECT_REMOTE_USER'])) { //pcd note: no idea who sets this
            list($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']) = explode(':', base64_decode(substr($_SERVER['REDIRECT_REMOTE_USER'], 6)));
        }
    }
    
    /**
     * @desc Give Default settings
     * @example $this->_getConfigData('demoMode','adyen_abstract')
     * @since 0.0.2
     * @param string $code
     */
    protected function _getConfigData($code, $paymentMethodCode = null, $storeId = null) {
        return Mage::helper('adyen')->_getConfigData($code, $paymentMethodCode, $storeId);
    }    

}