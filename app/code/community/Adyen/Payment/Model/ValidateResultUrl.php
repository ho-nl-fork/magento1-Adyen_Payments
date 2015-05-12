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
class Adyen_Payment_Model_ValidateResultUrl extends Mage_Core_Model_Abstract {

    /**
     * Collected debug information
     *
     * @var array
     */
    protected $_debugData = array();


    /**
     * @param $response
     * @return string
     */
    public function validateResponse($response)
    {
        $result = true;

        $this->_debugData['Step1'] = 'Processing ResultUrl';
        $storeId = null;

        if (empty($response)) {
            $this->_debugData['error'] = 'Response is empty, please check your webserver that the result url accepts parameters';
            $this->_debug($storeId);

            Mage::throwException(
                Mage::helper('adyen')->__('Response is empty, please check your webserver that the result url accepts parameters')
            );
        }

        // Log the results in log file and adyen_debug table
        $this->_debugData['response'] = $response;
        Mage::getResourceModel('adyen/adyen_debug')->assignData($response);


        $params = new Varien_Object($response);

        $actionName = $this->_getRequest()->getActionName();

        // authenticate result url
        $authStatus = Mage::getModel('adyen/authenticate')->authenticate($actionName, $params);
        if (!$authStatus) {
            Mage::throwException(
                Mage::helper('adyen')->__('ResultUrl authentification failure')
            );
        }

        $incrementId = $params->getData('merchantReference');

        if($incrementId) {
            $order = Mage::getModel('sales/order')->loadByIncrementId($incrementId);
            if ($order->getId()) {

                Mage::dispatchEvent('adyen_payment_process_resulturl_before', array('order' => $order, 'adyen_response' => $params));
                if ($params->getData('handled')) {
                    $this->_debug($storeId);
                    return;
                }
                // set StoreId for retrieving debug log setting
                $storeId = $order->getStoreId();

                // update the order
                $result = $this->_validateUpdateOrder($order, $params);

                Mage::dispatchEvent('adyen_payment_process_resulturl_after', array('order' => $order, 'adyen_response' => $params));
            } else {
                Mage::throwException(
                    Mage::helper('adyen')->__('Order does not exists with increment_id: %s', $incrementId)
                );
            }
        } else {
            Mage::throwException(
                Mage::helper('adyen')->__('Empty merchantReference')
            );
        }
        $this->_debug($storeId);

        return $result;
    }


    /**
     * @param $order
     * @param $params
     */
    protected function _validateUpdateOrder($order, $params)
    {
        $result = true;

        $this->_debugData['Step2'] = 'Updating the order';

        $authResult = $params->getData('authResult');
        $paymentMethod = trim($params->getData('paymentMethod'));
        $pspReference = trim($params->getData('pspReference'));

        $type = 'Adyen Result URL Notification(s):';
        $comment = Mage::helper('adyen')
            ->__('%s <br /> authResult: %s <br /> pspReference: %s <br /> paymentMethod: %s', $type, $authResult, $pspReference, $paymentMethod);

        $history = Mage::getModel('sales/order_status_history')
                    ->setComment($comment)
                    ->setEntityName("order")
                    ->setOrder($order);
        $history->save();

        // Update the Adyen Event Code if notification is not yet received
        if(!(substr($order->getAdyenEventCode(), 0, 13) == Adyen_Payment_Model_Event::ADYEN_EVENT_AUTHORISATION && $authResult == Adyen_Payment_Model_Event::ADYEN_EVENT_AUTHORISED)){
            $order->setAdyenEventCode($authResult);
            $this->_debugData['Step3'] = 'Updating the adyen event code with ' . $authResult;
        } else {
            $this->_debugData['Step3'] = 'Not updating the adyen event code with ' . $authResult . 'because notification is already received';
        }

        switch ($authResult) {

            case Adyen_Payment_Model_Event::ADYEN_EVENT_AUTHORISED:
            case Adyen_Payment_Model_Event::ADYEN_EVENT_PENDING:
                // do nothing wait for the notification
                $this->_debugData['Step4'] = 'Do nothing wait for the notification';
                break;
            case Adyen_Payment_Model_Event::ADYEN_EVENT_CANCELLED:
                $this->_debugData['Step4'] = 'Cancel or Hold the order';
                $result = false;
                break;
            case Adyen_Payment_Model_Event::ADYEN_EVENT_REFUSED:
                // if refused there will be a AUTHORIZATION : FALSE notification send only exception is ideal
                $this->_debugData['Step4'] = 'Cancel or Hold the order';
                $result = false;
                break;
            case Adyen_Payment_Model_Event::ADYEN_EVENT_ERROR:
                //attempt to hold/cancel
                $this->_debugData['Step4'] = 'Cancel or Hold the order';
                $result = false;
                break;
            default:
                $this->_debugData['error'] = 'This event is not supported: ' . $authResult;
                $result = false;
                break;
        }

        $order->save();

        return $result;
    }



    /**
     * Log debug data to file
     *
     * @param mixed $debugData
     */
    protected function _debug($storeId)
    {
        if ($this->_getConfigData('debug', 'adyen_abstract', $storeId)) {
            $file = 'adyen_resulturl.log';
            Mage::getModel('core/log_adapter', $file)->log($this->_debugData);
        }
    }

    /**
     * @param $paymentMethod
     * @return bool
     */
    protected function _isBankTransfer($paymentMethod)
    {
        if(strlen($paymentMethod) >= 22 &&  substr($paymentMethod, 0, 22) == 'adyen_hpp_bankTransfer') {
            $isBankTransfer = true;
        } else {
            $isBankTransfer = false;
        }
        return $isBankTransfer;
    }


    /**
     * @param $code
     * @param null $paymentMethodCode
     * @param null $storeId
     * @return mixed
     */
    protected function _getConfigData($code, $paymentMethodCode = null, $storeId = null)
    {
        return Mage::helper('adyen')->getConfigData($code, $paymentMethodCode, $storeId);
    }

    /**
     * @return mixed
     */
    protected function _getRequest()
    {
        return Mage::app()->getRequest();
    }
}