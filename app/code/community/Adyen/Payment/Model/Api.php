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
    const RECURRING_TYPE_ONECLICK  = 'ONECLICK';
    const RECURRING_TYPE_RECURRING = 'RECURRING';
    const RECURRING_TYPE_ONECLICK_RECURRING = 'ONECLICK,RECURRING';

    protected $_recurringTypes = [
        self::RECURRING_TYPE_ONECLICK,
        self::RECURRING_TYPE_RECURRING,
        self::RECURRING_TYPE_ONECLICK_RECURRING
    ];

    protected $_paymentMethodMap;


    /**
     * Get all the stored Credit Cards and other billing agreements stored with Adyen.
     *
     * @param string $customerReference
     * @param int|Mage_Core_model_Store|null   $store
     * @return array
     */
    public function listRecurringContracts($customerReference, $store = null)
    {
        $recurringType = $this->_helper()->getConfigData('recurringtypes', null, $store);
        if (! in_array($recurringType, $this->_recurringTypes)) {
            Adyen_Payment_Exception::throwException(
                sprintf('Recurring type (%s) must be one of the following types: ', $recurringType, $this->_recurringTypes)
            );
        }

        // Recurring type is always ONECLICK
        if($recurringType == self::RECURRING_TYPE_ONECLICK_RECURRING) {
            $recurringType = self::RECURRING_TYPE_ONECLICK;
        }

        $merchantAccount = $this->_helper()->getConfigData('merchantAccount', null, $store);

        // rest call to get list of recurring details
        $request = array(
            "action" => "Recurring.listRecurringDetails",
            "recurringDetailsRequest.merchantAccount"    => $merchantAccount,
            "recurringDetailsRequest.shopperReference"   => $customerReference,
            "recurringDetailsRequest.recurring.contract" => $recurringType,
        );

        $result = $this->_doRequest($request, $store);

        // convert result to utf8 characters
        $result = utf8_encode(urldecode($result));


        // The $result contains a JSON array containing the available payment methods for the merchant account.
        parse_str($result, $resultArr);

        $recurringContracts = [];
        foreach($resultArr as $key => $value) {
            // strip the key
            $key = str_replace("recurringDetailsResult_details_", "", $key);
            $key2 = strstr($key, '_');
            $keyNumber = str_replace($key2, "", $key);
            $keyAttribute = substr($key2, 1);

            // set ideal to sepadirectdebit because it is and we want to show sepadirectdebit logo
            if($keyAttribute == "variant" && $value == "ideal") {
                $value = 'sepadirectdebit';
            }

            if ($keyAttribute == 'variant') {
                $recurringContracts[$keyNumber]['payment_method'] = $this->_mapToPaymentMethod($value);
            }

            $recurringContracts[$keyNumber][$keyAttribute] = $value;
        }

        // unset the recurringDetailsResult because this is not a card
        unset($recurringContracts["recurringDetailsResult"]);

        return $recurringContracts;
    }

    /**
     * Map the recurring variant to a Magento payment method.
     * @param $variant
     * @return mixed
     */
    protected function _mapToPaymentMethod($variant)
    {
        if (is_null($this->_paymentMethodMap)) {
            //@todo abstract this away to some config?
            $this->_paymentMethodMap = [
                'sepadirectdebit' => 'adyen_sepa'
            ];


            $ccTypes = Mage::helper('adyen')->getCcTypes();
            $ccTypes = array_keys(array_change_key_case($ccTypes, CASE_LOWER));
            foreach ($ccTypes as $ccType) {
                $this->_paymentMethodMap[$ccType] = 'adyen_cc';
            }
        }

        return isset($this->_paymentMethodMap[$variant])
            ? $this->_paymentMethodMap[$variant]
            : $variant;
    }


    /**
     * Disable a recurring contract
     *
     * @param string                         $recurringDetailReference
     * @param int|Mage_Core_model_Store|null $store
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

        $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($httpStatus != 200) {
            Adyen_Payment_Exception::throwException(
                Mage::helper('adyen')->__('HTTP Status code %s received, data %s', $httpStatus, $result)
            );
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