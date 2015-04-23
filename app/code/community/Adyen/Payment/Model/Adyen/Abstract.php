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
abstract class Adyen_Payment_Model_Adyen_Abstract extends Mage_Payment_Model_Method_Abstract {

    /**
     * Zend_Log debug level
     * @var unknown_type
     */
    const DEBUG_LEVEL = 7;

    const VISIBLE_INTERNAL = 'backend';
    const VISIBLE_CHECKOUT = 'frontend';
    const VISIBLE_BOTH     = 'both';

    protected $_isGateway = false;
    protected $_canAuthorize = true;
    protected $_canCapture = true;
    protected $_canCapturePartial = true;
    protected $_canRefund = true;
    protected $_canVoid = true;
    protected $_canUseInternal = true;
    protected $_canUseCheckout = true;
    protected $_canUseForMultishipping = false;
    protected $_canRefundInvoicePartial = true;

    /**
     * TODO: whether a captured transaction may be voided by this gateway
     * This may happen when amount is captured, but not settled
     * @var bool
     */
    protected $_canCancelInvoice = true;

    /**
     * Magento Order Object
     * @var unknown_type
     */
    protected $_order;

    /**
     * Module identifiers
     */
    protected $_code = 'adyen_abstract';
    protected $_paymentMethod = 'abstract';

    /**
     * Internal objects and arrays for SOAP communication
     */
    protected $_service = NULL;
    protected $_accountData = NULL;

    /**
     * Payment Modification Request
     * @var unknown_type
     */
    protected $_paymentRequest = NULL;
    protected $_optionalData = NULL;
    protected $_testModificationUrl = 'https://pal-test.adyen.com/pal/adapter/httppost';
    protected $_liveModificationUrl = 'https://pal-live.adyen.com/pal/adapter/httppost';


    public function __construct()
    {
        $visibleType = $this->getConfigData('visible_type');
        switch ($visibleType) {
            case self::VISIBLE_INTERNAL:
                $this->_canUseCheckout = false;
                $this->_canUseInternal = true;
                break;

            case self::VISIBLE_CHECKOUT:
                $this->_canUseCheckout = true;
                $this->_canUseInternal = false;
                break;

            case self::VISIBLE_BOTH:
                $this->_canUseCheckout = true;
                $this->_canUseInternal = true;
                break;
        }
    }


    /**
     * @param Varien_Object $payment
     * @param float         $amount
     * @return $this
     */
    public function refund(Varien_Object $payment, $amount) {
        $this->writeLog('refund fx called');

        $order = $payment->getOrder();
        $pspReference = Mage::getModel('adyen/event')->getOriginalPspReference($order->getIncrementId());

        // if amound is a full refund send a refund/cancelled request so if it is not captured yet it will cancel the order
        $grandTotal = $order->getGrandTotal();

        if($grandTotal == $amount) {
            $order->getPayment()->getMethodInstance()->SendCancelOrRefund($payment, $pspReference);
        } else {
            $order->getPayment()->getMethodInstance()->sendRefundRequest($payment, $amount, $pspReference);
        }

        return $this;
    }


    /**
     * In the backend it means Authorize only
     * @param Varien_Object $payment
     * @param               $amount
     * @return $this
     */
    public function authorize(Varien_Object $payment, $amount) {
        parent::authorize($payment, $amount);
        $payment->setLastTransId($this->getTransactionId())->setIsTransactionPending(true);

        $order = $payment->getOrder();
        /*
         * Do not send a email notification when order is created.
         * Only do this on the AUHTORISATION notification.
         * This is needed for old versions where there is no check if email is already send
         */
        $order->setCanSendNewEmailFlag(false);

        if ($this->getCode() == 'adyen_boleto' || $this->getCode() == 'adyen_cc' || substr($this->getCode(), 0, 14) == 'adyen_oneclick' || $this->getCode() == 'adyen_elv' || $this->getCode() == 'adyen_sepa') {

            if(substr($this->getCode(), 0, 14) == 'adyen_oneclick') {

                // set payment method to adyen_oneclick otherwise backend can not view the order
                $payment->setMethod("adyen_oneclick");

                $recurringDetailReference = $payment->getAdditionalInformation("recurring_detail_reference");

                // load agreement based on reference_id (option to add an index on reference_id in database)
                $agreement = Mage::getModel('sales/billing_agreement')->load($recurringDetailReference, 'reference_id');

                // agreement could be a empty object
                if ($agreement && $agreement->getAgreementId() > 0 && $agreement->isValid()) {
                    $agreement->addOrderRelation($order);
                    $agreement->setIsObjectChanged(true);
                    $order->addRelatedObject($agreement);
                    $message = Mage::helper('adyen')->__('Used existing billing agreement #%s.', $agreement->getReferenceId());

                    $comment = $order->addStatusHistoryComment($message);
                    $order->addRelatedObject($comment);
                }
            }
            $_authorizeResponse = $this->_processRequest($payment, $amount, "authorise");
        }
        return $this;
    }

    /**
     * In backend it means Authorize && Capture
     * @param $payment
     * @param $amount
     */
    public function capture(Varien_Object $payment, $amount) {
        parent::capture($payment, $amount);
        $payment->setStatus(self::STATUS_APPROVED)
            ->setTransactionId($this->getTransactionId())
            ->setIsTransactionClosed(0);

        // do capture request to adyen
        $order = $payment->getOrder();
        $pspReference = Mage::getModel('adyen/event')->getOriginalPspReference($order->getIncrementId());
        $order->getPayment()->getMethodInstance()->sendCaptureRequest($payment, $amount, $pspReference);

        return $this;
    }

    public function authorise3d(Varien_Object $payment, $amount) {
        $authorizeResponse = $this->_processRequest($payment, $amount, "authorise3d");
        $responseCode = $authorizeResponse->paymentResult->resultCode;
        return $responseCode;
    }

    public function sendCaptureRequest(Varien_Object $payment, $amount, $pspReference) {
        if (empty($pspReference)) {
            $this->writeLog('oops empty pspReference');
            return $this;
        }
        $this->writeLog("sendCaptureRequest pspReference : $pspReference amount: $amount");
        return $this->_processRequest($payment, $amount, "capture", $pspReference);
    }

    public function sendRefundRequest(Varien_Object $payment, $amount, $pspReference) {
        if (empty($pspReference)) {
            $this->writeLog('oops empty pspReference');
            return $this;
        }
        $this->writeLog("sendRefundRequest pspReference : $pspReference amount: $amount");
        return $this->_processRequest($payment, $amount, "refund", $pspReference);
    }

    public function SendCancelOrRefund(Varien_Object $payment, $pspReference) {
        if (empty($pspReference)) {
            $this->writeLog('oops empty pspReference');
            return $this;
        }
        $this->writeLog("sendCancelOrRefundRequest pspReference : $pspReference");
        return $this->_processRequest($payment, null, "cancel_or_refund", $pspReference);
    }

    /**
     * Process the request here
     * @param Varien_Object $payment
     * @param unknown_type $amount
     * @param unknown_type $request
     * @param unknown_type $responseData
     */
    protected function _processRequest(Varien_Object $payment, $amount, $request, $pspReference = null) {
        $this->_initOrder();

        if (Mage::app()->getStore()->isAdmin()) {
            $storeId = $this->_order->getStoreId();
        } else {
            $storeId = null;
        }

        $this->_initService($storeId);
        $merchantAccount = trim($this->_getConfigData('merchantAccount', 'adyen_abstract', $storeId));
        $recurringType = $this->_getConfigData('recurringtypes', 'adyen_abstract', $storeId);
        $enableMoto = (int) $this->_getConfigData('enable_moto', 'adyen_cc', $storeId);
        $modificationResult = Mage::getModel('adyen/adyen_data_modificationResult');
        $requestData = Mage::getModel('adyen/adyen_data_modificationRequest')
            ->create($payment, $amount, $this->_order, $merchantAccount, $pspReference);

        switch ($request) {
            case "authorise":
                $requestData = Mage::getModel('adyen/adyen_data_paymentRequest')
                    ->create($payment, $amount, $this->_order, $this->_paymentMethod, $merchantAccount,$recurringType, $enableMoto);

                $response = $this->_service->authorise(array('paymentRequest' => $requestData));
                break;
            case "authorise3d":
                $requestData = Mage::getModel('adyen/adyen_data_paymentRequest3d')
                    ->create($payment, $amount, $this->_order, $this->_paymentMethod, $merchantAccount);

                $response = $this->_service->authorise3d(array('paymentRequest3d' => $requestData));
                break;
            case "capture":
                $response = $this->_service->capture(array(
                    'modificationRequest' => $requestData,
                    'modificationResult' => $modificationResult));
                break;
            case "refund":
                $response = $this->_service->refund(array(
                    'modificationRequest' => $requestData,
                    'modificationResult' => $modificationResult));
                break;
            case "cancel_or_refund":
                $response = $this->_service->cancelorrefund(array(
                    'modificationRequest' => $requestData,
                    'modificationResult' => $modificationResult));
                break;
        }

        if (!empty($response)) {
            $this->_processResponse($payment, $response, $request);
        }

        /*
         * clear the cache for recurring payments so new card will be added
         */
        $cacheKey = $merchantAccount . "|" . $this->_order->getCustomerId() . "|" . $recurringType;
        Mage::app()->getCache()->remove($cacheKey);

        //debug || log
        Mage::getResourceModel('adyen/adyen_debug')->assignData($response);
        $this->_debugAdyen();
        Mage::log($requestData, self::DEBUG_LEVEL, "$request.log", true);
        Mage::log("Response from Adyen:", self::DEBUG_LEVEL, "$request.log", true);
        Mage::log($response, self::DEBUG_LEVEL, "$request.log", true);

        //return $this;
        return $response;
    }

    protected function _processRecurringRequest($customerId) {

        $this->_initService();

        // customerId
        $merchantAccount = trim($this->_getConfigData('merchantAccount'));
        $recurringType = $this->_getConfigData('recurringtypes', 'adyen_abstract');

        // call to helper
        return Mage::helper('adyen')->getRecurringCards($merchantAccount, $customerId, $recurringType);
    }


    /**
     * @desc authorise response
     * Process the response of the soap
     *
     * @param Varien_Object $payment
     * @param stdClass      $response
     * @param null          $request
     *
     * @return $this
     * @todo Add comment with checkout Authorised
     */
    protected function _processResponse(Varien_Object $payment, $response, $request = null) {
        if (!($response instanceof stdClass)) {
            return false;
        }
        switch ($request) {
            case "authorise":
            case "authorise3d":
                if($response->paymentResult->fraudResult) {
                    $fraudResult = $response->paymentResult->fraudResult->accountScore;
                    $payment->setAdyenTotalFraudScore($fraudResult);
                }
                $responseCode = $response->paymentResult->resultCode;
                $pspReference = $response->paymentResult->pspReference;
                break;
            case "refund":
                $responseCode = $response->refundResult->response;
                $pspReference = $response->refundResult->pspReference;
                break;
            case "cancel_or_refund":
                $responseCode = $response->cancelOrRefundResult->response;
                $pspReference = $response->cancelOrRefundResult->pspReference;
                break;
            case "capture":
                $responseCode = $response->captureResult->response;
                $pspReference = $response->captureResult->pspReference;
                break;
            default:
                $responseCode = null;
                $this->writeLog("Unknown data type by Adyen");
                break;
        }
        switch ($responseCode) {
            case "RedirectShopper":
                $payment->setAdditionalInformation('paRequest', $response->paymentResult->paRequest);
                $payment->setAdditionalInformation('md', $response->paymentResult->md);
                $payment->setAdditionalInformation('issuerUrl', $response->paymentResult->issuerUrl);
                Mage::getSingleton('customer/session')->setRedirectUrl("adyen/process/validate3d");
                $this->_addStatusHistory($payment, $responseCode, $pspReference, $this->_getConfigData('order_status'));
                break;
            case "Refused":
                $errorMsg = Mage::helper('adyen')->__('The payment is REFUSED by Adyen.');
                Adyen_Payment_Exception::throwException($errorMsg);
                break;
            case "Authorised":
                $this->_addStatusHistory($payment, $responseCode, $pspReference, $this->_getConfigData('order_status'));
                break;
            case "Received": // boleto payment
                $additionalDataResult = $response->paymentResult->additionalData->entry;
                $pdfUrl = $additionalDataResult[0]->value;
                $this->_addStatusHistory($payment, $responseCode, $pspReference, false, $pdfUrl);
                break;
            case '[capture-received]':
            case '[refund-received]':
            case '[cancelOrRefund-received]':
                $this->_addStatusHistory($payment, $responseCode, $pspReference);
                break;
            case "Error":
                $errorMsg = Mage::helper('adyen')->__('System error, please try again later');
                Adyen_Payment_Exception::throwException($errorMsg);
                break;
            default:
                $this->writeLog("Unknown data type by Adyen");
                break;
        }

        //save all response data for a pure duplicate detection
        Mage::getModel('adyen/event')
            ->setPspReference($pspReference)
            ->setAdyenEventCode($responseCode)
            ->setAdyenEventResult($responseCode)
            ->setIncrementId($this->_order->getIncrementId())
            ->setPaymentMethod($this->getInfoInstance()->getCcType())
            ->setCreatedAt(now())
            ->saveData()
        ;
        return $this;
    }

    /**
     * @since 0.0.3
     * @param Varien_Object $payment
     * @param unknown_type $request
     * @param unknown_type $pspReference
     */
    protected function _addStatusHistory(Varien_Object $payment, $responseCode, $pspReference, $status = false, $boletoPDF = null) {

        if($boletoPDF)
            $payment->getOrder()->setAdyenBoletoPdf($boletoPDF);

        $comment = Mage::helper('adyen')->__('Adyen Result URL Notification(s): %s <br /> pspReference: %s', $responseCode, $pspReference);
        $payment->getOrder()->setAdyenEventCode($responseCode);
        $payment->getOrder()->addStatusHistoryComment($comment, $status);
        $payment->setAdyenEventCode($responseCode);
        return $this;
    }

    /**
     * Format price
     * @param unknown_type $amount
     * @param unknown_type $format
     */
    protected function _numberFormat($amount, $format = 2) {
        return (int) number_format($amount, $format, '', '');
    }

    /**
     * @desc Get SOAP client
     * @return Adyen_Payment_Model_Adyen_Abstract
     */
    protected function _initService($storeId = null) {
        $accountData = $this->getAccountData($storeId);
        $wsdl = $accountData['url']['wsdl'];
        $location = $accountData['url']['location'];
        $login = $accountData['login'];
        $password = $accountData['password'];
        $classmap = new Adyen_Payment_Model_Adyen_Data_Classmap();
        try {
            $this->_service = new SoapClient($wsdl, array(
                'login' => $login,
                'password' => $password,
                'soap_version' => SOAP_1_1,
                'style' => SOAP_DOCUMENT,
                'location' => $location,
                'trace' => 1,
                'classmap' => $classmap));
        } catch (SoapFault $fault) {
            $this->writeLog("Adyen SOAP Fault: (faultcode: {$fault->faultcode}, faultstring: {$fault->faultstring})");
            Mage::throwException(Mage::helper('adyen')->__('Can not connect payment service. Please try again later.'));
        }
        return $this;
    }

    /**
     * @desc soap urls
     * @return string
     */
    protected function _getAdyenUrls($storeId = null) {
        $test = array(
            'location' => "https://pal-test.adyen.com/pal/servlet/soap/Payment",
            'wsdl' => Mage::getModuleDir('etc', 'Adyen_Payment') . DS . 'Payment.wsdl'
        );
        $live = array(
            'location' => "https://pal-live.adyen.com/pal/servlet/soap/Payment",
            'wsdl' => Mage::getModuleDir('etc', 'Adyen_Payment') . DS . 'Payment.wsdl'
        );
        if ($this->getConfigDataDemoMode($storeId)) {
            return $test;
        } else {
            return $live;
        }
    }

    /**
     * @desc Testing purposes only
     */
    protected function _debugAdyen() {
        $this->writeLog("Request Headers: ");
        $this->writeLog($this->_service->__getLastRequestHeaders());
        $this->writeLog("Request:");
        $this->writeLog($this->_service->__getLastRequest());
        $this->writeLog("Response Headers");
        $this->writeLog($this->_service->__getLastResponseHeaders());
        $this->writeLog("Response");
        $this->writeLog($this->_service->__getLastResponse());
    }

    /**
     * Adyen User Account Data
     */
    public function getAccountData($storeId = null) {
        $url = $this->_getAdyenUrls($storeId);
        $wsUsername = $this->getConfigDataWsUserName($storeId);
        $wsPassword = $this->getConfigDataWsPassword($storeId);
        $account = array(
            'url' => $url,
            'login' => $wsUsername,
            'password' => $wsPassword
        );
        return $account;
    }

    /**
     * @desc init order object
     * @return Adyen_Payment_Model_Adyen_Abstract
     */
    protected function _initOrder() {
        if (!$this->_order) {
            $paymentInfo = $this->getInfoInstance();
            $this->_order = Mage::getModel('sales/order')
                ->loadByIncrementId($paymentInfo->getOrder()->getRealOrderId());
        }
        return $this;
    }

    /**
     * Void payment
     *
     * @param   Varien_Object $invoicePayment
     * @return  Mage_Payment_Model_Abstract
     */
    public function void(Varien_Object $payment) {
        parent::void();
        $this->cancel($payment);
        return $this;
    }

    /**
     * @todo fix me validate()
     * @see Mage_Payment_Model_Method_Abstract::validate()
     */
    public function validate() {
        return $this;
    }

    /**
     * @desc Cancel order
     * @param Varien_Object $payment
     * @param type $amount
     * @return Adyen_Payment_Model_Adyen_Abstract
     */
    public function cancel(Varien_Object $payment, $amount = null) {
        parent::cancel($payment);
        $this->writeLog("abstract -> cancel()" . get_class($this));
        return $this;
    }

    /**
     * @desc Adyen log fx
     * @param type $str
     * @return type
     */
    public function writeLog($str) {
        Mage::log($str, Zend_Log::DEBUG, "adyen_notification.log", true);
        return false;
    }

    /**
     * @status poor programming practises modification_result model not exist!
     * @param unknown_type $responseBody
     */
    public function getModificationResult($responseBody) {
        $result = new Varien_Object();
        $valArray = explode('&', $responseBody);
        foreach ($valArray as $val) {
            $valArray2 = explode('=', $val);
            $result->setData($valArray2[0], urldecode($valArray2[1]));
        }
        return $result;
    }

    public function getModificationUrl() {
        if ($this->getConfigDataDemoMode()) {
            return $this->_testModificationUrl;
        }
        return $this->_liveModificationUrl;
    }

    public function getConfigDataAutoCapture() {
        if (!$this->_getConfigData('auto_capture') || $this->_getConfigData('auto_capture') == 0) {
            return false;
        }
        return true;
    }

    public function getConfigDataAutoInvoice() {
        if (!$this->_getConfigData('auto_invoice') || $this->_getConfigData('auto_invoice') == 0) {
            return false;
        }
        return true;
    }

    public function getConfigDataAdyenCapture() {
        if ($this->_getConfigData('adyen_capture') && $this->_getConfigData('adyen_capture') == 1) {
            return true;
        }
        return false;
    }

    public function getConfigDataAdyenRefund() {
        if ($this->_getConfigData('adyen_refund') == 1) {
            return true;
        }
        return false;
    }

    /**
     * Return true if the method can be used at this time
     * @since 0.1.0.3r1
     * @return bool
     */
    public function isAvailable($quote=null) {
        if (!parent::isAvailable($quote)) {
            return false;
        }
        if (!is_null($quote)) {
            if ($this->_getConfigData('allowspecific', $this->_code)) {
                $country = $quote->getShippingAddress()->getCountry();
                $availableCountries = explode(',', $this->_getConfigData('specificcountry', $this->_code));
                if (!in_array($country, $availableCountries)) {
                    return false;
                }
            }
        }
        return true;
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

    /**
     * Used via Payment method.Notice via configuration ofcourse Y or N
     * @return boolean true on demo, else false
     */
    public function getConfigDataDemoMode($storeId = null) {
        return Mage::helper('adyen')->getConfigDataDemoMode($storeId);
    }

    public function getConfigDataWsUserName($storeId = null) {
        return Mage::helper('adyen')->getConfigDataWsUserName($storeId);
    }

    public function getConfigDataWsPassword($storeId) {
        return Mage::helper('adyen')->getConfigDataWsPassword($storeId);
    }

    /**
     * @since 0.0.2
     */
    public function getAvailableCCTypes() {
        $types = Mage::helper('adyen')->getCcTypes();
        $availableTypes = $this->_getConfigData('cctypes', 'adyen_cc');
        if ($availableTypes) {
            $availableTypes = explode(',', $availableTypes);
            foreach ($types as $code => $name) {
                if (!in_array($code, $availableTypes)) {
                    unset($types[$code]);
                }
            }
        }
        return $types;
    }

    public function getAvailableBoletoTypes() {
        $types = Mage::helper('adyen')->getBoletoTypes();
        $availableTypes = $this->_getConfigData('boletotypes', 'adyen_boleto');
        if ($availableTypes) {
            $availableTypes = explode(',', $availableTypes);
            foreach ($types as $code => $name) {
                if (!in_array($code, $availableTypes)) {
                    unset($types[$code]);
                }
            }
        }
        return $types;
    }

    public function getConfigPaymentAction() {
        return Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE;
    }

    public function canCreateBillingAgreement()
    {
        if (! $this->_canCreateBillingAgreement) {
            return false;
        }
        $recurringType = $this->_getConfigData('recurringtypes');
        if($recurringType == "ONECLICK" || $recurringType == "ONECLICK,RECURRING") {
            return true;
        }
        return false;
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
        $billingAgreement
            ->setMethodCode($this->getCode())
            ->setReferenceId($data['recurringDetailReference'])
            ->setCreatedAt($data['creationDate']);
        return $this;
    }
}
