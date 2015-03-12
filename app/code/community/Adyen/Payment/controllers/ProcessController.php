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

class Adyen_Payment_ProcessController extends Mage_Core_Controller_Front_Action {
    /**
     * @var Adyen_Payment_Model_Adyen_Data_Server
     */
    const SOAP_SERVER = 'Adyen_Payment_Model_Adyen_Data_Server_Notification';

    const OPENINVOICE_SOAP_SERVER = 'Adyen_Payment_Model_Adyen_Data_Server_Openinvoice';

    /**
     * Redirect Block
     * need to be redeclared
     */
    protected $_redirectBlockType = 'adyen/redirect';

    /**
     * @desc Soap Interface/Webservice
     * @since 0.0.9.9r2
     */
    public function soapAction() {
        $classmap = Mage::getModel('adyen/adyen_data_notificationClassmap');
        $server = new SoapServer($this->_getWsdl(), array('classmap' => $classmap));
        $server->setClass(self::SOAP_SERVER);
        $server->addFunction(SOAP_FUNCTIONS_ALL);
        $server->handle();

        //get soap request
        $request = Mage::registry('soap-request');


        if (empty($request)) {
            return false;
        }

        if (is_array($request->notification->notificationItems->NotificationRequestItem)) {
            foreach ($request->notification->notificationItems->NotificationRequestItem as $item) {
                $this->processResponse($item);
            }
        } else {
            $item = $request->notification->notificationItems->NotificationRequestItem;
            $this->processResponse($item);
        }
        exit();
    }

    public function openinvoiceAction() {
        $_mode = $this->_getConfigData('demoMode');
        $wsdl = ($_mode == 'Y') ?
            'https://ca-test.adyen.com/ca/services/OpenInvoiceDetail?wsdl' :
            'https://ca-live.adyen.com/ca/services/OpenInvoiceDetail?wsdl';
        $server = new SoapServer($wsdl);
        $server->setClass(self::OPENINVOICE_SOAP_SERVER);
        $server->addFunction(SOAP_FUNCTIONS_ALL);
        $server->handle();
        exit();
    }

    /**
     * @desc Get Wsdl
     * @since 0.0.9.9r2
     * @return string
     */
    protected function _getWsdl() {
        return Mage::getModuleDir('etc', 'Adyen_Payment') . DS . 'Notification.wsdl';
    }

    protected function _expireAjax() {
        if (!$this->getCheckout()->getQuote()->hasItems()) {
            $this->getResponse()->setHeader('HTTP/1.1', '403 Session Expired');
            exit;
        }
    }

    public function redirectAction() {
        try {
            $session = $this->_getCheckout();
            $order = $this->_getOrder();
            $session->setAdyenQuoteId($session->getQuoteId());
            $session->setAdyenRealOrderId($session->getLastRealOrderId());
            $order->loadByIncrementId($session->getLastRealOrderId());

            //redirect only if this order is never recorded
            $hasEvent = Mage::getResourceModel('adyen/adyen_event')
                ->getLatestStatus($session->getLastRealOrderId());
            if (!empty($hasEvent) || !$order->getId()) {
                $this->_redirect('/');
                return $this;
            }

            //redirect to adyen
            if (strcmp($order->getState(), Mage_Sales_Model_Order::STATE_PENDING_PAYMENT) === 0 ||
                (strcmp($order->getState(), Mage_Sales_Model_Order::STATE_NEW) === 0)) {
                $this->getResponse()->setBody(
                    $this->getLayout()
                        ->createBlock($this->_redirectBlockType)
                        ->setOrder($order)
                        ->toHtml()
                );
                $session->unsQuoteId();
            }
            //continue shopping
            else {
                $this->_redirect('/');
                return $this;
            }
        } catch (Exception $e) {
            $session->addException($e, Mage::helper('adyen')->__($e->getMessage()));
            $this->cancel();
        }
    }

    public function validate3dAction() {
        try {
            // get current order
            $session = $this->_getCheckout();
            $order = $this->_getOrder();
            $session->setAdyenQuoteId($session->getQuoteId());
            $session->setAdyenRealOrderId($session->getLastRealOrderId());
            $order->loadByIncrementId($session->getLastRealOrderId());
            $adyenStatus = $order->getAdyenEventCode();

            // get payment details
            $payment = $order->getPayment();
            $paRequest = $payment->getAdditionalInformation('paRequest');
            $md = $payment->getAdditionalInformation('md');
            $issuerUrl = $payment->getAdditionalInformation('issuerUrl');

            $infoAvailable = $payment && !empty($paRequest) && !empty($md) && !empty($issuerUrl);

            // check adyen status and check if all information is available
            if (!empty($adyenStatus) && $adyenStatus == 'RedirectShopper' && $infoAvailable) {

                $request = $this->getRequest();
                $requestMD = $request->getPost('MD');
                $requestPaRes = $request->getPost('PaRes');

                // authorise the payment if the user is back from the external URL
                if ($request->isPost() && !empty($requestMD) && !empty($requestPaRes)) {
                    if ($requestMD == $md) {
                        $payment->setAdditionalInformation('paResponse', $requestPaRes);
                        // send autorise3d request, catch exception in case of 'Refused'
                        try {
                            $result = $payment->getMethodInstance()->authorise3d($payment, $order->getGrandTotal());
                        } catch (Exception $e) {
                            $result = 'Refused';
                            $order->setAdyenEventCode($result)->save();
                        }

                        // check if authorise3d was successful
                        if ($result == 'Authorised') {
                            $order->setState(Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW, true,
                                Mage::helper('adyen')->__('3D-secure validation was successful.'))->save();
                            $this->_redirect('checkout/onepage/success');
                        }
                        else {
                            $order->addStatusHistoryComment(Mage::helper('adyen')->__('3D-secure validation was unsuccessful.'))->save();
                            $session->addException($e, Mage::helper('adyen')->__($e->getMessage()));
                            $this->cancel();
                        }
                    }
                    else {
                        $this->_redirect('/');
                        return $this;
                    }

                }

                // otherwise, redirect to the external URL
                else {
                    $order->setState(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT, true,
                        Mage::helper('adyen')->__('Customer was redirected to bank for 3D-secure validation.'))->save();
                    $this->getResponse()->setBody(
                        $this->getLayout()->createBlock($this->_redirectBlockType)->setOrder($order)->toHtml()
                    );
                }

            }
            else {
                $this->_redirect('/');
                return $this;
            }
        } catch (Exception $e) {
            $session->addException($e, Mage::helper('adyen')->__($e->getMessage()));
            $this->cancel();
        }
    }

    /**
     * Adyen returns POST variables to this action
     */
    public function successAction() {
        $status = $this->processResponse();

        if ($status) {
            $session = $this->_getCheckout();
            $session->unsAdyenRealOrderId();
            $session->setQuoteId($session->getAdyenQuoteId(true));
            $session->getQuote()->setIsActive(false)->save();

            $this->_redirect('checkout/onepage/success');
        } else {
            $this->cancel();
        }
    }

    /**
     * @desc reloads the items in the cart && cancel the order
     * @since v009
     */
    public function cancel() {

        $session = $this->_getCheckout();
        $order = Mage::getModel('sales/order');
        $incrementId = $session->getLastRealOrderId();

        if (empty($incrementId)) {
            $session->addError($this->__('Your payment failed, Please try again later'));
            $this->_redirectCheckoutCart();
            return;
        }

        $order->loadByIncrementId($incrementId);

        // reactivate the quote again
        $quoteId = $order->getQuoteId();
        Mage::getModel('sales/quote')
            ->load($quoteId)
            ->setIsActive(1)
            ->save();
        Mage::getSingleton('checkout/session')->setQuoteId($quoteId);

        //handle the old order here
        try {
            $order->setActionFlag(Mage_Sales_Model_Order::ACTION_FLAG_CANCEL, true);
            $order->cancel()->save();
        } catch (Mage_Core_Exception $e) {
            Mage::logException($e);
        }

        $params = $this->getRequest()->getParams();
        if(isset($params['authResult']) && $params['authResult'] == Adyen_Payment_Model_Event::ADYEN_EVENT_CANCELLED) {
            $session->addError($this->__('You have cancelled the order. Please try again'));
        } else {
            $session->addError($this->__('Your payment failed, Please try again later'));
        }

        $this->_redirectCheckoutCart();
    }

    protected function _redirectCheckoutCart()
    {
        $this->_redirect(Mage::getStoreConfig('payment/adyen_abstract/payment_cancelled_redirect'));
    }


    public function insAction() {
        try {

            $status = $this->processResponse();

            if($status == "401"){
                $this->_return401();
            } else {
                echo "[accepted]";
            }
        } catch (Exception $e) {
            Mage::logException($e);
        }

        exit();
    }

    public function jsonAction() {

        try {
            $notificationItems = json_decode(file_get_contents('php://input'), true);

            foreach($notificationItems['notificationItems'] as $notificationItem)
            {
                $status = $this->processResponse($notificationItem['NotificationRequestItem']);
                if($status == "401"){
                    $this->_return401();
                }
            }
            echo "[accepted]";
        } catch (Exception $e) {
            Mage::logException($e);
        }
        exit();
    }

    public function cashAction() {

        $status = $this->processCashResponse();

        $session = $this->_getCheckout();
        $session->unsAdyenRealOrderId();
        $session->setQuoteId($session->getAdyenQuoteId(true));
        $session->getQuote()->setIsActive(false)->save();
        if ($status) {
            $this->_redirect('checkout/onepage/success');
        } else {
            $this->cancel();
        }
    }

    /* START actions for POS */
    public function successPosAction() {

        echo $this->processPosResponse();
        return $this;
    }

    public function getOrderStatusAction()
    {
        if($_POST['merchantReference'] != "") {
            // get the order
            $order = Mage::getModel('sales/order')->loadByIncrementId($_POST['merchantReference']);
            // if order is not cancelled then order is success
            if($order->getStatus() == Mage_Sales_Model_Order::STATE_PROCESSING || $order->getAdyenEventCode() == Adyen_Payment_Model_Event::ADYEN_EVENT_POSAPPROVED || substr($order->getAdyenEventCode(), 0, 13)  == Adyen_Payment_Model_Event::ADYEN_EVENT_AUTHORISATION) {
                echo 'true';
            } elseif($order->getStatus() == 'pending' &&  $order->getAdyenEventCode() == "") {
                echo 'wait';
                Mage::log("NO MATCH! JUST WAIT order is not matching with merchantReference:".$_POST['merchantReference'] . " status is:" . $order->getStatus() . " and adyen event status is:" . $order->getAdyenEventCode(), Zend_Log::DEBUG, "adyen_notification_pos.log", true);
            } else {
                Mage::log("NO MATCH! order is not matching with merchantReference:".$_POST['merchantReference'] . " status is:" . $order->getStatus() . " and adyen event status is:" . $order->getAdyenEventCode(), Zend_Log::DEBUG, "adyen_notification_pos.log", true);
            }

            // extra check cancelled
        }
        return;
    }

    public function cancelAction()
    {
        $this->cancel();
    }


    public function processPosResponse() {
        return Mage::getModel('adyen/process')->processPosResponse();
    }
    /* END actions for POS */

    public function processCashResponse() {
        return Mage::getModel('adyen/process')->processCashResponse();
    }

    protected function _return401(){
        header('HTTP/1.1 401 Unauthorized',true,401);
    }

    /**
     * @since v008
     * @desc Update order status accordingly
     * @throws Exception
     */
    public function processResponse($soapItem = null) {
        return Mage::getModel('adyen/process')->processResponse($soapItem);
    }

    protected function _getCheckout() {
        return Mage::getSingleton('checkout/session');
    }

    protected function _getOrder() {
        return Mage::getModel('sales/order');
    }

    /**
     * @desc Give Default settings
     * @example $this->_getConfigData('demoMode','adyen_abstract')
     * @since 0.0.2
     * @param string $code
     */
    protected function _getConfigData($code, $paymentMethodCode = null, $storeId = null) {
        return Mage::getModel('adyen/process')->getConfigData($code, $paymentMethodCode, $storeId);
    }
}
