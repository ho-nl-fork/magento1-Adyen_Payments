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
class Adyen_Payment_Model_Api extends Mage_Core_Model_Abstract
{
    /**
     * Disable a recurring contract
     *
     * @param string                    $recurringDetailReference
     * @param int|Mage_Core_model_Store $store
     *
     * @throws Adyen_Payment_Exception
     * @return bool
     */
    public function disableRecurringContract($recurringDetailReference, $store = null)
    {
        $merchantAccount = $this->_helper()->getConfigData('merchantAccount', null, $store);
        $shopperReference = $this->_helper()->getConfigData('merchantAccount', null, $store);

        $request = array(
            "action" => "Recurring.disable",
            "disableRequest.merchantAccount" => $merchantAccount,
            "disableRequest.shopperReference" => $shopperReference,
            "disableRequest.recurringDetailReference" => $recurringDetailReference
        );

        $result = $this->_doRequest($request, $store);

        // convert result to utf8 characters
        $result = utf8_encode(urldecode($result));

        if ($result != "disableResult.response=[detail-successfully-disabled]") {
            Adyen_Payment_Exception::throwException(Mage::helper('adyen')->__($result));
        }

        return true;
    }


    /**
     * Do the actual API request
     *
     * @param array $request
     * @param int|Mage_Core_model_Store $storeId
     *
     * @throws Adyen_Payment_Exception
     * @return mixed
     */
    protected function _doRequest(array $request, $storeId)
    {
        if ($storeId instanceof Mage_Core_model_Store) {
            $storeId = $storeId->getId();
        }

        $requestUrl = $this->_helper()->getConfigDataDemoMode()
            ? "https://pal-test.adyen.com/pal/adapter/httppost"
            : "https://pal-live.adyen.com/pal/adapter/httppost";
        $username = $this->_helper()->getConfigDataWsUserName($storeId);
        $password = $this->_helper()->getConfigDataWsPassword($storeId);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $requestUrl);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, $username.":".$password);
        curl_setopt($ch, CURLOPT_POST, count($request));
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($request));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $result = curl_exec($ch);
        $error = curl_error($ch);

        if ($result === false) {
            Adyen_Payment_Exception::throwException($error);
        }

        curl_close($ch);

        return $result;
    }


    /**
     * @return Adyen_Payment_Helper_Data
     */
    protected function _helper()
    {
        return Mage::helper('adyen');
    }
}