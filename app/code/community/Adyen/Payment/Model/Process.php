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
class Adyen_Payment_Model_Process extends Mage_Core_Model_Abstract {

    /**
     * Used in the ProcessController to handle all bussiness logic regarding Adyen to Magento
     * @since v008
     * @desc Update order status accordingly
     * @throws Exception
     */
    public function processResponse($soapItem = null) {

        $status = "";
        $response = (!empty($soapItem)) ? $soapItem : $this->getRequest()->getParams();
        Mage::getResourceModel('adyen/adyen_debug')->assignData($response);
        $actionName = $this->getRequest()->getActionName();
        $helper = Mage::helper('adyen');

        if (empty($response)) {
            Mage::log('blanco on response, please check your webserver that the result url accepts parameters', Zend_Log::CRIT, "adyen_notification.log", true);
            return "401";
        }

        $varienObj = new Varien_Object();
        foreach ($response as $code => $value) {
            if ($code == 'amount') {
                if (is_object($value))
                    $value = $value->value;
                $code = 'value';
            }
            $varienObj->setData($code, $value);
        }

        // if version is added to notification url (?version=true) then only return the version of the plugin (only works from verion 1.0.0.8)
        if($varienObj->getData('version')) {
            echo $helper->getExtensionVersion();
            exit;
        }

        //authenticate
        $authStatus = Mage::getModel('adyen/authenticate')->authenticate($actionName, $varienObj);
        if (!$authStatus) {
            $this->_writeLog('authentification failure!');
            Mage::log('authentification failure!', Zend_Log::CRIT, "adyen_notification.log", true);
            return "401";
        }

        $incrementId = $varienObj->getData('merchantReference');

        try{

            // skip notification if notification is REPORT_AVAILABLE
            $eventCode = trim($varienObj->getData('eventCode'));
            if($eventCode == Adyen_Payment_Model_Event::ADYEN_EVENT_REPORT_AVAILABLE) {
                return false;
            }

            //get order && payment objects
            $order = Mage::getModel('sales/order');

            //error
            $orderExist = $this->_incrementIdExist($varienObj, $incrementId);
            if (empty($orderExist)) {
                Mage::log('Order does not exist with incrementId:' . $incrementId, Zend_Log::DEBUG, "adyen_notification.log", true);
                // on authorization it could be that the order is not yet created
                $eventCode = trim($varienObj->getData('eventCode'));
                $success = (trim($varienObj->getData('success')) == "true") ? true : false;
                // only log the AUTHORISATION with Sucess true because with false the order will never be created in magento
                if($eventCode == Adyen_Payment_Model_Event::ADYEN_EVENT_AUTHORISATION && $success == true) {
                    // pspreference is always numeric otherwise it is a test notification
                    $pspReference = $varienObj->getData('pspReference');
                    if(is_numeric($pspReference)) {
                        $this->updateNotProcessedEvents($varienObj);
                        return false;
                    }
                }
                // it is a test notification but update the events that are in the queue
                $this->updateNotProcessedEvents(null);
                return false;
            }
            $order->loadByIncrementId($incrementId);

            //log
            $order->getPayment()->getMethodInstance()->writeLog($varienObj->debug());

            switch ($actionName) {
                case 'success':
                    $status = $this->_processPostSuccess($order, $varienObj);
                    break;
                default:
                    $status = $this->_processNotifications($order, $varienObj);
                    $this->updateNotProcessedEvents(null);
                    break;
            }
        }catch(Exception $e){
            // do nothing
            // log error
            Mage::logException($e);
            Mage::log('NOTIFICATION RESPONSE failure!' . print_r($e, true), Zend_Log::DEBUG, "adyen_notification.log", true);

        }

        return $status;
    }

    public function updateNotProcessedEvents($varienObj) {

        if($varienObj) {
            $incrementId = $varienObj->getData('merchantReference');
            $pspReference = $varienObj->getData('pspReference');
            $eventCode = $varienObj->getData('eventCode');

            // check if already exists in the queue (sometimes Adyen Platform can send the same notification twice)
            $eventResults = Mage::getModel('adyen/event_queue')->getCollection()
                ->addFieldToFilter('increment_id', $incrementId);

            $eventQueue = null;
            if(count($eventResults) > 0) {
                $eventQueue = $eventResults->getFirstItem();
            }

            if($eventQueue) {
                $attempt = (int)$eventQueue->getAttempt();
                try{
                    $eventQueue->setAttempt(++$attempt);
                    $eventQueue->save();
                } catch(Exception $e) {
                    Mage::logException($e);
                }
                Mage::log('Notification could still not processed this was attempt:'.$attempt .' for notification with incrementId:' . $incrementId, Zend_Log::DEBUG, "adyen_notification.log", true);
            } else {
                try {
                    // add current request to the queue
                    $eventQueue = Mage::getModel('adyen/event_queue');
                    $eventQueue->setPspReference($pspReference);
                    $eventQueue->setAdyenEventCode($eventCode);
                    $eventQueue->setIncrementId($incrementId);
                    $eventQueue->setAttempt(1);
                    $eventQueue->setResponse(serialize($varienObj));
                    $eventQueue->setCreatedAt(now());
                    $eventQueue->save();
                    Mage::log('Notification is added to the queue it will process when the next notification is received for incrementId:' . $incrementId, Zend_Log::DEBUG, "adyen_notification.log", true);
                } catch(Exception $e) {
                    Mage::logException($e);
                }
            }
        }

        // try to update old notifications that did not processed yet
        $collection = Mage::getModel('adyen/event_queue')->getCollection()
            ->addFieldToFilter('attempt', array('lteq' => '4'));

        foreach($collection as $event){

            if($event->getAdyenEventCode() == Adyen_Payment_Model_Event::ADYEN_EVENT_AUTHORISATION) {

                $incrementId = $event->getIncrementId();
                $orderExist = Mage::getResourceModel('adyen/order')->orderExist($incrementId);
                if (!empty($orderExist)) {
                    // try to process it now
                    $varienObj = unserialize($event->getResponse());
                    $order = Mage::getModel('sales/order');
                    $order->loadByIncrementId($incrementId);

                    //log
                    Mage::log('Notification from queue is trying to processing it again incrementId is:' . $incrementId, Zend_Log::DEBUG, "adyen_notification.log", true);
                    $order->getPayment()->getMethodInstance()->writeLog($varienObj->debug());
                    $this->_processNotifications($order, $varienObj);

                    // update event that it is processed
                    try{
                        Mage::log('Notification from queue is processed with incrementId:' . $incrementId, Zend_Log::DEBUG, "adyen_notification.log", true);
                        $event->delete();
                    } catch(Exception $e) {
                        Mage::logException($e);
                    }
                } else {
                    // order still not exists save this attempt
                    $currentAttempt = $event->getAttempt();
                    $event->setAttempt(++$currentAttempt);
                    $event->save();
                }
            }
        }
    }

    public function processPosResponse() {

        $helper = Mage::helper('adyen');
        $response = $_REQUEST;

        Mage::log("PosResonse:".print_r($response, true), Zend_Log::DEBUG, "adyen_notification.log", true);

        $varienObj = new Varien_Object();
        foreach ($response as $code => $value) {
            if ($code == 'amount') {
                if (is_object($value))
                    $value = $value->value;
                $code = 'value';
            }
            $varienObj->setData($code, $value);
        }

        $actionName = $this->getRequest()->getActionName();
        $result = $varienObj->getData('result');

        // check if result comes from POS device comes form POS
        if($actionName == "successPos" && $result != "") {

            $checksum = $varienObj->getData('checksum');

            // for android checksum is called cs
            if($checksum == "") {
                $checksum = $varienObj->getData('cs');
            }

            $amount = $varienObj->getData('originalCustomAmount');
            $currency = $varienObj->getData('originalCustomCurrency');
            $session_id = $varienObj->getData('sessionId');


            // for android sessionis is with low i
            if($session_id == "") {
                $session_id = $varienObj->getData('sessionid');
            }

            // calculate amount checksum
            $amount_checksum = 0;

            for($i=0;$i<strlen($amount);$i++)
            {
                // ASCII value use ord
                $checksum_calc = ord($amount[$i]) - 48;
                $amount_checksum += $checksum_calc;
            }

            $currency_checksum = 0;
            for($i=0;$i<strlen($currency);$i++)
            {
                $checksum_calc = ord($currency[$i]) - 64;
                $currency_checksum += $checksum_calc;
            }

            $result_checksum = 0;
            for($i=0;$i<strlen($result);$i++)
            {
                $checksum_calc = ord($result[$i]) - 64;
                $result_checksum += $checksum_calc;
            }

            $session_id_checksum = 0;
            for($i=0;$i<strlen($session_id);$i++)
            {
                $checksum_calc = $this->getAscii2Int($session_id[$i]);
                $session_id_checksum += $checksum_calc;
            }

            $total_result_checksum = (($amount_checksum + $currency_checksum + $result_checksum) * $session_id_checksum) % 100;

            // check if request is valid
            if($total_result_checksum == $checksum) {

                //get order && payment objects
                $order = Mage::getModel('sales/order');
                //$incrementId = $varienObj->getData('merchantReference');
                $incrementId = $varienObj->getData('originalCustomMerchantReference');

                //error
                $orderExist = $this->_incrementIdExist($varienObj, $incrementId);

                if (empty($orderExist)) {
                    $this->_writeLog("unknown order : $incrementId");
                } else {
                    $order->loadByIncrementId($incrementId);

                    if($result == 'APPROVED') {
                        // wait for notification to finish the order

                        // set adyen event status on true
                        $order->setAdyenEventCode(Adyen_Payment_Model_Event::ADYEN_EVENT_POSAPPROVED);

                        $comment = Mage::helper('adyen')
                            ->__('%s <br /> Result: %s <br /> paymentMethod: %s', 'Adyen App Result URL Notification:', $result, 'POS');

                        $order->addStatusHistoryComment($comment, false);

                        try {
                            Mage::log("processPosResponse Adyen Event Status is:".$order->getAdyenEventCode(), Zend_Log::DEBUG, "adyen_notification.log", true);
                            $order->save();
                        } catch (Exception $e) {
                            Mage::logException($e);
                        }
                    } else {

                        $comment = Mage::helper('adyen')
                            ->__('%s <br /> Result: %s <br /> paymentMethod: %s', 'Adyen App Result URL Notification:', $result, 'POS');

                        $order->addStatusHistoryComment($comment, Mage_Sales_Model_Order::STATE_CANCELED);

                        $order->setActionFlag(Mage_Sales_Model_Order::ACTION_FLAG_CANCEL, true);

                        if (!$order->canCancel()) {
                            $this->_writeLog('order can not be canceled', $order);
                            $order->addStatusHistoryComment($helper->__('Order can not be canceled'), Mage_Sales_Model_Order::STATE_CANCELED);
                            $order->save();

                        } else {
                            $order->cancel()->save();
                        }
                    }
                }
            } else {
                $incrementId = $varienObj->getData('originalCustomMerchantReference');
                Mage::log("Checksum failed for :".$incrementId, Zend_Log::DEBUG, "adyen_notification.log", true);
            }
        }
        // close the window
        $html = "<html><body>
		    				<script type=\"text/javascript\">
								function closeWindow() {
									window.open('', '_self', '');
									window.close();
        }
								setTimeout(closeWindow, 500);
		    				</script>
		    		</body></html>";

        return $html;
    }

    public function getAscii2Int($ascii){
        if (is_numeric($ascii)){
            $int = ord($ascii) - 48;
        } else {
            $int = ord($ascii) - 64;
        }
        return $int;
    }

    public function processCashResponse()
    {
        $response = $_REQUEST;

        $varienObj = new Varien_Object();
        foreach ($response as $code => $value) {
            if ($code == 'amount') {
                if (is_object($value))
                    $value = $value->value;
                $code = 'value';
            }
            $varienObj->setData($code, $value);
        }

        $pspReference = $varienObj->getData('pspReference');
        $merchantReference = $varienObj->getData('merchantReference');
        $skinCode =  $varienObj->getData('skinCode');
        $paymentAmount = $varienObj->getData('paymentAmount');
        $currencyCode = $varienObj->getData('currencyCode');
        $customPaymentMethod = $varienObj->getData('c_cash');
        $paymentMethod = $varienObj->getData('paymentMethod');
        $merchantSig = $varienObj->getData('merchantSig');

        $sign = $pspReference .
            $merchantReference .
            $skinCode .
            $paymentAmount .
            $currencyCode .
            $customPaymentMethod . $paymentMethod;

        $secretWord = $this->_getSecretWord();
        $signMac = Zend_Crypt_Hmac::compute($secretWord, 'sha1', $sign);
        $calMerchantSig = base64_encode(pack('H*', $signMac));

        // check if signatures are the same
        if($calMerchantSig == $merchantSig) {

            //get order && payment objects
            $order = Mage::getModel('sales/order');

            //error
            $orderExist = $this->_incrementIdExist($varienObj, $merchantReference);

            if (empty($orderExist)) {
                $this->_writeLog("unknown order : $merchantReference");
            } else {
                $order->loadByIncrementId($merchantReference);

                $comment = Mage::helper('adyen')
                    ->__('Adyen Cash Result URL Notification: <br /> pspReference: %s <br /> paymentMethod: %s', $pspReference, $paymentMethod);

                $status = true;

                $history = Mage::getModel('sales/order_status_history')
                    ->setStatus($status)
                    ->setComment($comment)
                    ->setEntityName("order")
                    ->setOrder($order);
                $history->save();
                return $status;
            }
        }
        return false;
    }

    protected function _getSecretWord($options = null) {
        switch ($this->getConfigDataDemoMode()) {
            case true:
                $secretWord = trim($this->_getConfigData('secret_wordt', 'adyen_hpp'));
                break;
            default:
                $secretWord = trim($this->_getConfigData('secret_wordp', 'adyen_hpp'));
                break;
        }
        return $secretWord;
    }

    /**
     * Used via Payment method.Notice via configuration ofcourse Y or N
     * @return boolean true on demo, else false
     */
    public function getConfigDataDemoMode() {
        if ($this->_getConfigData('demoMode') == 'Y') {
            return true;
        }
        return false;
    }

    /**
     * @desc check order existance
     * @param type $incrementId
     * @return type
     */
    protected function _incrementIdExist($varienObj, $incrementId) {

        $orderExist = Mage::getResourceModel('adyen/order')->orderExist($incrementId);
        return $orderExist;
    }

    /**
     * @desc Adyen attribute handling
     * @param Varien_Object $order
     * @param type $response
     */
    protected function _addAdyenAttributes(Varien_Object $order, $response, $updateAdyenStatus = true) {
        $klarnaReservationNumber = $response->getData('additionalData_additionalData_acquirerReference');
        $avsResult = $response->getData('additionalData_avsResult');
        $cvcResult = $response->getData('additionalData_cvcResult');
        $boletoPaidAmount = $response->getData('additionalData_boletobancario_paidAmount');
        $totalFraudScore = $response->getData('additionalData_totalFraudScore');
        $pspReference = $response->getData('pspReference');
        $eventCode = $response->getData('eventCode');
        $authResult = $response->getData('authResult');
        $incrementId = $response->getData('merchantReference');
        $paymentMethod = $response->getData('paymentMethod');
        $success = (trim($response->getData('success')) == "true") ? true : false;
        $eventData = (!empty($eventCode)) ? $eventCode : $authResult;
        $paymentObj = $order->getPayment();
        $_paymentCode = $this->_paymentMethodCode($order);

        $ccLast4 = $response->getData('additionalData_cardSummary');
        // if there is no server communication setup try to get last4 digits from reason field
        if($ccLast4 == "") {
            $reason = trim($response->getData('reason'));
            if($reason != "") {
                $reasonArray = explode(":", $reason);
                if($reasonArray != null && is_array($reasonArray)) {
                    if(isset($reasonArray[1])) {
                        $ccLast4 = $reasonArray[1];
                    }
                }
            }
        }

        $paymentObj->setLastTransId($incrementId)
            ->setAdyenPaymentMethod($paymentMethod)
            ->setCcType($paymentMethod)
        ;

        // only update this when authroization notification is not yet processed
        Mage::log("AdyenEventCode in paymentobject order:".$order->getAdyenEventCode(), Zend_Log::DEBUG, "adyen_notification.log", true);
        Mage::log("paymentobject order authResult:".$authResult, Zend_Log::DEBUG, "adyen_notification.log", true);

        if(!(substr($order->getAdyenEventCode(), 0, 13) == Adyen_Payment_Model_Event::ADYEN_EVENT_AUTHORISATION && $authResult == Adyen_Payment_Model_Event::ADYEN_EVENT_AUTHORISED)){
            Mage::log("update paymentobject eventcode with:".$eventData, Zend_Log::DEBUG, "adyen_notification.log", true);
            $paymentObj->setAdyenEventCode($eventData);
        }

        //only original here
        if ($eventCode == Adyen_Payment_Model_Event::ADYEN_EVENT_AUTHORISED
            || $eventCode == Adyen_Payment_Model_Event::ADYEN_EVENT_AUTHORISATION
            || $eventCode == Adyen_Payment_Model_Event::ADYEN_EVENT_HANDLED_EXTERNALLY
            || ($eventCode == Adyen_Payment_Model_Event::ADYEN_EVENT_CAPTURE && $_paymentCode == "adyen_pos"))
        {
            $paymentObj->setAdyenPspReference($pspReference);
            if($klarnaReservationNumber != "") {
                $paymentObj->setAdyenKlarnaNumber($klarnaReservationNumber);
            }
            if($ccLast4 != "") {
                $paymentObj->setccLast4($ccLast4);
            }
            if($avsResult != "") {
                $paymentObj->setAdyenAvsResult($avsResult);
            }
            if($cvcResult != "") {
                $paymentObj->setAdyenCvcResult($cvcResult);
            }
            if($boletoPaidAmount != "") {
                $paymentObj->setAdyenBoletoPaidAmount($boletoPaidAmount);
            }
            if($totalFraudScore != "") {
                $paymentObj->setAdyenTotalFraudScore($totalFraudScore);
            }
        }

        try {
            //save all response data for a pure duplicate detection
            Mage::getModel('adyen/event')
                ->setPspReference($pspReference)
                ->setAdyenEventCode($eventCode)
                ->setAdyenEventResult($eventData)
                ->setIncrementId($incrementId)
                ->setPaymentMethod($paymentMethod)
                ->setCreatedAt(now())
                ->setSuccess($success)
                ->saveData($updateAdyenStatus) // don't update the adyen status
            ;
        } catch (Exception $e) {
            Mage::log($e->getMessage(), Zend_Log::DEBUG, "adyen_notification.log", true);
        }
    }

    /**
     * @desc Process what happened on Adyen during Hpp
     * @param Varien_Object $params
     */
    protected function _processPostSuccess($order, $params) {

        //set these attributes here
        $this->_addAdyenAttributes($order, $params, false);
        $status = false;
        $authResult = $params->getData('authResult');
        $pspReference = $params->getData('pspReference');
        switch ($authResult) {
            case Adyen_Payment_Model_Event::ADYEN_EVENT_AUTHORISED:
            case Adyen_Payment_Model_Event::ADYEN_EVENT_PENDING:
                Mage::log("PAYMENT POST URL BEFORE SAVING STATUS:".$order->getStatus(), Zend_Log::DEBUG, "adyen_notification.log", true);

                $type = "Adyen Result URL Notification(s):";
                $pspReference = $params->getData('pspReference');
                $paymentMethod = $params->getData('paymentMethod');

                $comment = Mage::helper('adyen')
                    ->__('%s <br /> authResult: %s <br /> pspReference: %s <br /> paymentMethod: %s', $type, $authResult, $pspReference, $paymentMethod);

                $history = Mage::getModel('sales/order_status_history')
                    ->setStatus($status)
                    ->setComment($comment)
                    ->setEntityName("order")
                    ->setOrder($order);
                $history->save();
                $status = true;
                // don't save the order because of interferrence with order status (set by notifications)
                break;
            case Adyen_Payment_Model_Event::ADYEN_EVENT_CANCELLED:
                $paymentMethod = trim($params->getData('paymentMethod'));
                $isBankTransfer = $this->isBankTransfer($paymentMethod);

                //attempt to hold/cancel (exceptional to BankTransfer they stay in previous status/pending)
                if (!$isBankTransfer) {
                    $this->_addStatusHistoryComment($order, $params);
                    $this->holdCancelOrder($order, $params);
                } else {
                    $this->_addStatusHistoryComment($order, $params, $order->getStatus());
                    $status = true;
                }
                break;
            case Adyen_Payment_Model_Event::ADYEN_EVENT_REFUSED:
                $this->_addStatusHistoryComment($order, $params);
                // if refused there will be a AUTHORIZATION : FALSE notification send only exception is ideal
                $paymentMethod = trim($params->getData('paymentMethod'));
                if(strcmp($paymentMethod, 'ideal') === 0) {
                    //attempt to hold/cancel
                    $this->holdCancelOrder($order, $params);
                }
                break;
            case Adyen_Payment_Model_Event::ADYEN_EVENT_ERROR:
                $this->_addStatusHistoryComment($order, $params);
                //attempt to hold/cancel
                $this->holdCancelOrder($order, $params);
                break;
            default:
                $order->getPayment()->getMethodInstance()->writeLog('response not supported!');
                break;
        }
        return $status;
    }

    /**
     * @desc process notifications
     * @param type $order
     * @param type $response
     * @return type
     */
    public function notificationHandler($order, $response) {
        $payment = $order->getPayment()->getMethodInstance();
        $pspReference = trim($response->getData('pspReference'));
        $success = trim($response->getData('success'));
        $eventCode = trim($response->getData('eventCode'));

        //handle duplicates
        $isDuplicate = Mage::getModel('adyen/event')
            ->isDuplicate($pspReference, $eventCode, $success);
        if ($isDuplicate && $eventCode != Adyen_Payment_Model_Event::ADYEN_EVENT_RECURRING_CONTRACT) {
            $payment->writeLog("#skipping duplicate notification pspReference:$pspReference && eventCode: $eventCode && success: $success");
            return false; //hmt
        }

        //set these attributes here
        $this->_addAdyenAttributes($order, $response);

        //add comment to the order
        if (strcmp($success, 'false') == 0 || !$success) {
            $status = ($order->isCanceled() || ($order->getState() === Mage_Sales_Model_Order::STATE_PENDING_PAYMENT)) ?
                Mage_Sales_Model_Order::STATE_CANCELED : $order->getStatus();
            $this->_addStatusHistoryComment($order, $response, $status);
        } else {
            $this->_addStatusHistoryComment($order, $response, $order->getStatus());
        }

        //success failed
        if (strcmp($success, 'false') == 0 || !$success) {

            //attempt to hold/cancel
            $this->holdCancelOrder($order, $response);

            $payment->writeLog('success failed');
            //exit();
            return false; //hmt
        }
        return true;
    }

    /**
     * @desc process notifications
     * @param type $order
     * @param type $response
     */
    public function _processNotifications($order, $response) {
        $valid = $this->notificationHandler($order, $response); //hmt: added $valid

        if ($valid) {
            Mage::dispatchEvent('adyen_payment_process_notifications_before', array('order' => $order, 'adyen_response' => $response));
            if ($response->getData('handled')) {
                return;
            }
            $eventCode = trim($response->getData('eventCode'));

            $success = (bool) trim($response->getData('success'));
            $paymentMethod = trim($response->getData('paymentMethod'));
            $_paymentCode = $this->_paymentMethodCode($order);
            switch ($eventCode) {
                case Adyen_Payment_Model_Event::ADYEN_EVENT_REFUND_FAILED:
                    // do nothing only inform the merchant with order comment history
                    break;
                case Adyen_Payment_Model_Event::ADYEN_EVENT_REFUND:

                    $this->refundOrder($order, $response);
                    //refund completed
                    $this->setRefundAuthorized($order, $success);
                    break;
                case Adyen_Payment_Model_Event::ADYEN_EVENT_PENDING:
                    if($this->_getConfigData('send_email_bank_sepa_on_pending', 'adyen_abstract')) {
                        // Check if payment is banktransfer or sepa if true then send out order confirmation email
                        $isBankTransfer = $this->isBankTransfer($paymentMethod);
                        if($isBankTransfer || $paymentMethod == 'sepadirectdebit') {
                            $order->sendNewOrderEmail(); // send order email
                        }
                    }
                    break;
                case Adyen_Payment_Model_Event::ADYEN_EVENT_HANDLED_EXTERNALLY:
                case Adyen_Payment_Model_Event::ADYEN_EVENT_AUTHORISATION:
                    // for POS don't do anything on the AUTHORIZATION
                    if($_paymentCode != "adyen_pos") {
                        $this->authorizePayment($order, $success, $paymentMethod, $response);
                    }
                    break;
                case Adyen_Payment_Model_Event::ADYEN_EVENT_MANUAL_REVIEW_REJECT:
                    // don't do anything it will send a CANCEL_OR_REFUND notification when this payment is captured
                    break;
                case Adyen_Payment_Model_Event::ADYEN_EVENT_MANUAL_REVIEW_ACCEPT:
                    // don't do anything it will send a CAPTURE notification when this payment is captured
                    break;
                case Adyen_Payment_Model_Event::ADYEN_EVENT_CAPTURE:
                    if($_paymentCode != "adyen_pos") {
                        $this->setPaymentAuthorized($order, $success, $response);
                    } else {
                        // FOR POS authorize the payment on the CAPTURE notification
                        $this->authorizePayment($order, $success, $paymentMethod, $response);
                    }
                    break;
                case Adyen_Payment_Model_Event::ADYEN_EVENT_CAPTURE_FAILED:
                case Adyen_Payment_Model_Event::ADYEN_EVENT_CANCELLATION:
                case Adyen_Payment_Model_Event::ADYEN_EVENT_CANCELLED:
                    $this->holdCancelOrder($order, $response);
                    break;
                case Adyen_Payment_Model_Event::ADYEN_EVENT_CANCEL_OR_REFUND:
                    $resultModification = trim($response->getData('additionalData_modification_action'));
                    if(isset($resultModification) && $resultModification != "") {
                        if($resultModification == "cancel") {
                            $this->holdCancelOrder($order, $response);
                        } elseif($resultModification == "refund") {
                            $this->refundOrder($order, $response);
                            //refund completed
                            $this->setRefundAuthorized($order, $success);
                        }
                    } else {
                        // not sure if it cancelled or refund the order
                        $helper = Mage::helper('adyen');
                        $order->addStatusHistoryComment($helper->__('Order is cancelled or refunded'));
                        $order->save();
                    }
                    break;
                case Adyen_Payment_Model_Event::ADYEN_EVENT_RECURRING_CONTRACT:

                    // get payment object
                    $payment = $order->getPayment();

                    // save recurring contract (not for oneclicks because billing agreement does already exists
                    if($_paymentCode != "adyen_oneclick") {

                        // storedReferenceCode
                        $recurringDetailReference = trim($response->getData('pspReference'));

                        // check if there is already a BillingAgreement
                        $agreement = Mage::getModel('sales/billing_agreement')->load($recurringDetailReference, 'reference_id');

                        if ($agreement && $agreement->getAgreementId() > 0 && $agreement->isValid()) {

                            $agreement->addOrderRelation($order);
                            $agreement->setIsObjectChanged(true);
                            $order->addRelatedObject($agreement);
                            $message = Mage::helper('adyen')->__('Used existing billing agreement #%s.', $agreement->getReferenceId());

                        } else {
                            // set billing agreement data
                            $payment->setBillingAgreementData(array(
                                'billing_agreement_id'  => $recurringDetailReference,
                                'method_code'           => $payment->getMethodCode()
                            ));

                            // create billing agreement for this order
                            $agreement = Mage::getModel('sales/billing_agreement')->importOrderPayment($payment);
                            $agreement->setAgreementLabel($payment->getMethodInstance()->getTitle());

                            if ($agreement->isValid()) {
                                $message = Mage::helper('adyen')->__('Created billing agreement #%s.', $agreement->getReferenceId());

                                // save into sales_billing_agreement_order
                                $agreement->addOrderRelation($order);

                                // add to order to save agreement
                                $order->addRelatedObject($agreement);
                            } else {
                                $message = Mage::helper('adyen')->__('Failed to create billing agreement for this order.');
                            }
                        }
                        $comment = $order->addStatusHistoryComment($message);
                        $order->addRelatedObject($comment);
                        $order->save();

                        /*
                         * clear the cache for recurring payments so new card will be added
                         */
                        $merchantAccount = $this->_getConfigData('merchantAccount','adyen_abstract', $order->getStoreId());
                        $recurringType = $this->_getConfigData('recurringtypes', 'adyen_abstract', $order->getStoreId());

                        $cacheKey = $merchantAccount . "|" . $order->getCustomerId() . "|" . $recurringType;
                        Mage::app()->getCache()->remove($cacheKey);
                    }
                    break;
                default:
                    //@todo fix me cancel && error here
                    $order->getPayment()->getMethodInstance()->writeLog('notification event not supported!');
                    break;
            }
        }
    }

    public function authorizePayment($order, $success, $payment_method, $response) {

        //pre-authorise if success
        $order->sendNewOrderEmail(); // send order email

        /*
         * For AliPay or UnionPay sometimes it first send a AUTHORISATION false notification and then
         * a AUTHORISATION true notification. The second time it must revert the cancelled of the first notification before we can
         * assign a new status
         */
        if($success == "true") {
            if($payment_method == "alipay" || $payment_method == "unionpay") {
                foreach ($order->getAllItems() as $item) {
                    $item->setQtyCanceled(0);
                    $item->save();
                }
            }
        }
        $this->setPrePaymentAuthorized($order, $success);

        $this->createInvoice($order, $response);

        $_paymentCode = $this->_paymentMethodCode($order);
        if($payment_method == "c_cash" || ($this->_getConfigData('create_shipment', 'adyen_pos') && $_paymentCode == "adyen_pos"))
        {
            $this->createShipment($order);
        }
    }

    /**
     * @since v0.1.0.5
     * @param type $order
     * @param type $success
     */
    public function setPaymentAuthorized($order, $success = false, $response) {
        if ($success && !empty($order)) {

            $status = $this->_getConfigData('payment_authorized');
            // virtual order can have different status
            if($order->getIsVirtual()) {
                $virtual_status = $this->_getConfigData('payment_authorized_virtual');
                if($virtual_status != "") {
                    $status = $virtual_status;
                }
            }

            // check for boleto if payment is totally paid
            if($order->getPayment()->getMethod() == "adyen_boleto") {

                // check if paid amount is the same as orginal amount
                $orginalAmount = trim($response->getData('additionalData_boletobancario_originalAmount'));
                $paidAmount = trim($response->getData('additionalData_boletobancario_paidAmount'));

                if($orginalAmount != $paidAmount) {

                    // not the full amount is paid. Check if it is underpaid or overpaid
                    // strip the  BRL of the string
                    $orginalAmount = str_replace("BRL", "",  $orginalAmount);
                    $orginalAmount = floatval(trim($orginalAmount));

                    $paidAmount = str_replace("BRL", "",  $paidAmount);
                    $paidAmount = floatval(trim($paidAmount));

                    if($paidAmount > $orginalAmount) {
                        $overpaidStatus =  $this->_getConfigData('order_overpaid_status', 'adyen_boleto');
                        // check if there is selected a status if not fall back to the default
                        $status = (!empty($overpaidStatus)) ? $overpaidStatus : $status;
                    } else {
                        $underpaidStatus = $this->_getConfigData('order_underpaid_status', 'adyen_boleto');
                        // check if there is selected a status if not fall back to the default
                        $status = (!empty($underpaidStatus)) ? $underpaidStatus : $status;
                    }
                }
            }

            $status = (!empty($status)) ? $status : $order->getStatus();
            $order->addStatusHistoryComment(Mage::helper('adyen')->__('Adyen Payment Successfully completed'), $status);
            $order->sendOrderUpdateEmail((bool) $this->_getConfigData('send_update_mail'));
            Mage::log("PAYMENT CAPTURE STATUS:".$order->getStatus(), Zend_Log::DEBUG, "adyen_notification.log", true);
            $order->save();
            return true;
        }
        return false;
    }

    /**
     * @since v0.1.0.5
     * @param type $order
     * @param type $success
     */
    public function setPrePaymentAuthorized($order, $success = false) {
        if ($success && !empty($order)) {
            $status = $this->_getConfigData('payment_pre_authorized');
            $status = (!empty($status)) ? $status : $order->getStatus();
            $order->addStatusHistoryComment(Mage::helper('adyen')->__('Payment is pre authorised waiting for capture'), $status);
            $order->sendOrderUpdateEmail((bool) $this->_getConfigData('send_update_mail'));
            Mage::log("PAYMENT PRE AUTHORIZED STATUS:".$order->getStatus(), Zend_Log::DEBUG, "adyen_notification.log", true);
            $order->save();
            return true;
        }
        return false;
    }

    /**
     * @since v0.1.0.8
     * @param type $order
     * @param type $success
     */
    public function setRefundAuthorized($order, $success = false) {
        if ($success && !empty($order)) {
            $status = $this->_getConfigData('refund_authorized');
            $status = (!empty($status)) ? $status : $order->getStatus();
            $order->addStatusHistoryComment(Mage::helper('adyen')->__('Adyen Refund Successfully completed'), $status);
            $order->sendOrderUpdateEmail((bool) $this->_getConfigData('send_update_mail'));
            $order->save();
            return true;
        }
        return false;
    }

    /**
     * @desc Determine wether to create invoice or not using notifications
     * @param Varien_Object $response
     * @return true or false
     * @notice ideal is exception here
     * @since 0.0.9.x
     */
    public function isAutoCapture($response, $order) {
        $paymentMethod = trim($response->getData('paymentMethod'));
        $captureMode = trim($this->_getConfigData('capture_mode'));
        $sepaFlow = trim($this->_getConfigData('capture_mode', 'adyen_sepa'));
        $_paymentCode = $this->_paymentMethodCode($order);
        $captureModeOpenInvoice = $this->_getConfigData('auto_capture_openinvoice', 'adyen_abstract');
        $captureModePayPal = trim($this->_getConfigData('paypal_capture_mode', 'adyen_abstract'));

        //check if it is a banktransfer. Banktransfer only a Authorize notification is send.
        $isBankTransfer = $this->isBankTransfer($paymentMethod);

        // payment method ideal, cash or adyen_pos has direct capture
        if (strcmp($paymentMethod, 'ideal') === 0 || strcmp($paymentMethod, 'c_cash' ) === 0 || $_paymentCode == "adyen_pos" || $isBankTransfer == true || ($_paymentCode == "adyen_sepa" && $sepaFlow != "authcap")) {
            return true;
        }
        // if auto capture mode for openinvoice is turned on then use auto capture
        if ($captureModeOpenInvoice == true && (strcmp($paymentMethod, 'openinvoice') === 0 || strcmp($paymentMethod, 'afterpay_default') === 0 || strcmp($paymentMethod, 'klarna') === 0)) {
            return true;
        }
        // if PayPal capture modues is different from the default use this one
        if(strcmp($paymentMethod, 'paypal' ) === 0 && $captureModePayPal != "") {
            if(strcmp($captureModePayPal, 'auto') === 0 ) {
                return true;
            } elseif(strcmp($captureModePayPal, 'manual') === 0 ) {
                return false;
            }
        }
        if (strcmp($captureMode, 'manual') === 0) {
            return false;
        }
        //online capture after delivery, use Magento backend to online invoice (if the option auto capture mode for openinvoice is not set)
        if (strcmp($paymentMethod, 'openinvoice') === 0 || strcmp($paymentMethod, 'afterpay_default') === 0 || strcmp($paymentMethod, 'klarna') === 0) {
            return false;
        }
        return true;
    }

    public function isBankTransfer($paymentMethod) {
        if(strlen($paymentMethod) >= 12 &&  substr($paymentMethod, 0, 12) == "bankTransfer") {
            $isBankTransfer = true;
        } else {
            $isBankTransfer = false;
        }
        return $isBankTransfer;
    }

    /**
     * @desc Handle Refund here
     * @todo create credit memo && set order status to closed
     * @param Varien_Object $order
     * @param Varien_Object $response
     * @since 0.0.9.2
     */
    public function refundOrder($order, $response) {

        //skip orders with [refund-received]
        $pspReference = trim($response->getData('pspReference'));
        $result = Mage::getModel('adyen/event')
            ->getEvent($pspReference, '[refund-received]');
        if (!empty($result)) {
            $this->_writeLog("\nSkip refund process, as refund initiated via Magento id: {$order->getIncrementId()}");
            return false;
        }

        $_mail = (bool) $this->_getConfigData('send_update_mail');
        $amount = $response->getValue() / 100;

        if ($order->canCreditmemo()) {
            $service = Mage::getModel('sales/service_order', $order);
            $creditmemo = $service->prepareCreditmemo();
            $creditmemo->getOrder()->setIsInProcess(true);

            //set refund data on the order
            $creditmemo->setGrandTotal($amount);
            $creditmemo->setBaseGrandTotal($amount);
            $creditmemo->save();

            try {
                Mage::getModel('core/resource_transaction')
                    ->addObject($creditmemo)
                    ->addObject($creditmemo->getOrder())
                    ->save();
                //refund
                $creditmemo->refund();
                $transactionSave = Mage::getModel('core/resource_transaction')
                    ->addObject($creditmemo)
                    ->addObject($creditmemo->getOrder());
                if ($creditmemo->getInvoice()) {
                    $transactionSave->addObject($creditmemo->getInvoice());
                }
                $transactionSave->save();
                if ($_mail) {
                    $creditmemo->getOrder()->setCustomerNoteNotify(true);
                    $creditmemo->sendEmail();
                }
            } catch (Exception $e) {
                $this->_writeLog($e->getMessage());
            }
        } else {
            $this->_writeLog("\nOrder can not refund {$order->getIncrementId()}");
        }
    }

    /**
     * @desc Create invoice
     * @param type $order
     * @param type $response
     * @return type
     */
    public function createInvoice($order, $response) {
        $payment = $order->getPayment()->getMethodInstance();
        $pspReference = trim($response->getData('pspReference'));
        $success = trim($response->getData('success'));
        $eventCode = trim($response->getData('eventCode'));
        $reason = trim($response->getData('reason'));
        $invoiceAutoMail = (bool) $this->_getConfigData('send_invoice_update_mail');
        $_mail = (bool) $this->_getConfigData('send_update_mail');

        //create invoice
        if (strcmp($order->getState(), Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW) == 0) {
            $order->setState(Mage_Sales_Model_Order::STATE_NEW);
        }

        //capture mode
        if (!$this->isAutoCapture($response, $order)) {
            $order->addStatusHistoryComment(Mage::helper('adyen')->__('Capture Mode set to Manual'));
            $order->sendOrderUpdateEmail($_mail);
            $order->save();
            return false;
        }

        //$order->addStatusHistoryComment(Mage::helper('adyen')->__('Adyen Payment Successfully completed'), $status);


        if ($order->canInvoice()) {
            $invoice = $order->prepareInvoice();
            $invoice->getOrder()->setIsInProcess(true);
            // set transaction id so you can do a online refund this is used instead of online capture
            // because it is already auto capture in Adyen Backoffice
            $invoice->setTransactionId(1);
            $invoice->register()->pay();
            try {
                Mage::getModel('core/resource_transaction')
                    ->addObject($invoice)
                    ->addObject($invoice->getOrder())
                    ->save();
            } catch (Exception $e) {
                $payment->writeLog($e->getMessage());
            }

            //selected adyen status
            $this->setPaymentAuthorized($order, $success, $response);

            if ($invoiceAutoMail) {
                $invoice->sendEmail();
            }
        }
        $order->sendOrderUpdateEmail($_mail);
        $order->save();
    }

    public function createShipment($order) {
        // create shipment for cash payment
        $payment = $order->getPayment()->getMethodInstance();
        if($order->canShip()) {
            //                    $itemQty =  $order->getItemsCollection()->count();

            $itemQty = array();
//            $shipment = Mage::getModel('sales/service_order', $order)->prepareShipment($itemQty);
            $shipment = $order->prepareShipment($itemQty);
            if($shipment) {
                $shipment->register();
                $shipment->getOrder()->setIsInProcess(true);
                $comment = Mage::helper('adyen')->__('Shipment created by Adyen');
                $shipment->addComment($comment);
                Mage::getModel('core/resource_transaction')
                    ->addObject($shipment)
                    ->addObject($shipment->getOrder())
                    ->save();
            }
        } else {
            $payment->writeLog("Order can't be shipped");
        }
    }

    /**
     * @desc order comments or history
     * @param type $order
     * @param Varien_Object $response
     */
    protected function _addStatusHistoryComment($order, Varien_Object $response, $status = false) {
        Mage::log("_addStatusHistoryComment", Zend_Log::DEBUG, "adyen_notification.log", true);

        //notification
        $pspReference = $response->getData('pspReference');
        $success = trim($response->getData('success'));
        $success_result = (strcmp($success, 'false') == 0 || !$success) ? 'false' : 'true';
        $eventCode = $response->getData('eventCode');
        $reason = $response->getData('reason');
        $success = (!empty($reason)) ? "$success_result <br />reason:$reason" : $success_result;
        $klarnaReservationNumber = $response->getData('additionalData_additionalData_acquirerReference');
        $boletoPaidAmount = $response->getData('additionalData_boletobancario_paidAmount');

        //post
        $authResult = $response->getData('authResult');
        $pspReference = $response->getData('pspReference');

        //payment method
        $paymentMethod = $response->getData('paymentMethod');

        //data type
        $type = (!empty($authResult)) ? 'Adyen Result URL Notification(s):' : 'Adyen HTTP Notification(s):';
        switch ($type) {
            case 'Adyen Result URL Notification(s):':
                /*PCD*/ // choose not to update the adyen_event_code in the order when the order is already on notification:Authorisation status and authresult = resultURL:Authorised
                if(!(substr($order->getAdyenEventCode(), 0, 13) == Adyen_Payment_Model_Event::ADYEN_EVENT_AUTHORISATION && $authResult == Adyen_Payment_Model_Event::ADYEN_EVENT_AUTHORISED)){
                    Mage::log("Adyen Result URL order authResult:".$authResult, Zend_Log::DEBUG, "adyen_notification.log", true);

                    $order->setAdyenEventCode($authResult);
                }
                $comment = Mage::helper('adyen')
                    ->__('%s <br /> authResult: %s <br /> pspReference: %s <br /> paymentMethod: %s', $type, $authResult, $pspReference, $paymentMethod);
                break;
            default:
                Mage::log("default order authResult:".$eventCode . " : " . strtoupper($success_result), Zend_Log::DEBUG, "adyen_notification.log", true);

                if($eventCode == Adyen_Payment_Model_Event::ADYEN_EVENT_REFUND) {

                    $currency = $order->getOrderCurrencyCode();

                    // check if it is a full or partial refund
                    $amount = Mage::helper('adyen')->formatAmount(($response->getValue() / 100), $currency);
                    $orderAmount = Mage::helper('adyen')->formatAmount($order->getGrandTotal(), $currency);

                    if($amount == $orderAmount) {
                        $order->setAdyenEventCode($eventCode . " : " . strtoupper($success_result));
                    } else {
                        $order->setAdyenEventCode("(PARTIAL) " . $eventCode . " : " . strtoupper($success_result));
                    }
                } else {
                    $order->setAdyenEventCode($eventCode . " : " . strtoupper($success_result));
                }

                // if payment method is klarna or openinvoice/afterpay show the reservartion number
                if(($paymentMethod == "klarna" || $paymentMethod == "afterpay_default" || $paymentMethod == "openinvoice") && ($klarnaReservationNumber != null && $klarnaReservationNumber != "")) {
                    $klarnaReservationNumberText = "<br /> reservationNumber: " . $klarnaReservationNumber;
                } else {
                    $klarnaReservationNumberText = "";
                }

                if($boletoPaidAmount != null && $boletoPaidAmount != "") {
                    $boletoPaidAmountText = "<br /> Paid amount: " . $boletoPaidAmount;
                } else {
                    $boletoPaidAmountText = "";
                }

                $comment = Mage::helper('adyen')
                    ->__('%s <br /> eventCode: %s <br /> pspReference: %s <br /> paymentMethod: %s <br /> success: %s %s %s', $type, $eventCode, $pspReference, $paymentMethod, $success, $klarnaReservationNumberText, $boletoPaidAmountText);

                break;
        }

        $order->addStatusHistoryComment($comment, $status);
        $order->save();
    }

    /**
     * Handle order cancellation && success failure on notifications
     * Called for all failed notifications, even cancellations
     * @param unknown_type $order
     * @param unknown_type $response
     */
    public function holdCancelOrder($order, $response = null) {
        $eventCode = trim($response->getData('eventCode'));
        $orderStatus = $this->_getConfigData('payment_cancelled');
        switch ($eventCode) {
            case Adyen_Payment_Model_Event::ADYEN_EVENT_REFUND:
                $orderStatus = Mage_Sales_Model_Order::STATE_HOLDED;
                break;
            case Adyen_Payment_Model_Event::ADYEN_EVENT_CANCELLATION:
            case Adyen_Payment_Model_Event::ADYEN_EVENT_CANCELLED:
                $orderStatus = Mage_Sales_Model_Order::STATE_CANCELED;
                break;
        }
        $_mail = (bool) $this->_getConfigData('send_update_mail');
        $helper = Mage::helper('adyen');
        switch ($orderStatus) {
            case Mage_Sales_Model_Order::STATE_HOLDED:
                $order->setActionFlag(Mage_Sales_Model_Order::ACTION_FLAG_HOLD, true);
                if (!$order->canHold()) {
                    $this->_writeLog('order can not hold or is already on Hold', $order);
                    $order->addStatusHistoryComment($helper->__('Order can not Hold or is already on Hold'), Mage_Sales_Model_Order::STATE_HOLDED);
                    $order->save();
                    return false;
                }
                $order->hold()->save();
                break;
            case Mage_Sales_Model_Order::STATE_CANCELED:
                $order->setActionFlag(Mage_Sales_Model_Order::ACTION_FLAG_CANCEL, true);
                if (!$order->canCancel()) {
                    $this->_writeLog('order can not be canceled', $order);
                    $order->addStatusHistoryComment($helper->__('Order can not be canceled or is already canceled'), Mage_Sales_Model_Order::STATE_CANCELED);
                    $order->save();
                    return false;
                }
                $order->cancel()->save();
                break;
        }
        $order->sendOrderUpdateEmail($_mail);
        $order->save();
        return true;
    }

    protected function _writeLog($str, $order = null) {
        if (!empty($order)) {
            $order->getPayment()->getMethodInstance()->writeLog($str);
        }
    }

    protected function _getCheckout() {
        return Mage::getSingleton('checkout/session');
    }

    protected function _getOrder() {
        return Mage::getModel('sales/order');
    }

    /**
     * @since 0.0.1
     * @desprecated since 0.0.2, over _getPayment
     */
    protected function _getHpp() {
        return Mage::getModel('adyen/adyen_hpp');
    }

    /**
     * @since 0.0.2
     * @param unknown_type $order
     */
    protected function _paymentMethodCode($order) {
        return $order->getPayment()->getMethod();
    }

    /**
     * @since 0.0.2
     * @param unknown_type $order
     */
    protected function _getPayment($order) {
        $_paymentCode = $this->_paymentMethodCode($order);
        //@todo strict $paymentMethodCode to known payment methods i.e adyen_hpp, adyen_cc,adyen_elv
        return Mage::getModel("adyen/$_paymentCode");
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

    public function getConfigData($code, $paymentMethodCode = null, $storeId = null) {
        return $this->_getConfigData($code, $paymentMethodCode, $storeId);
    }

    public function getRequest() {
        return Mage::app()->getRequest();
    }

}