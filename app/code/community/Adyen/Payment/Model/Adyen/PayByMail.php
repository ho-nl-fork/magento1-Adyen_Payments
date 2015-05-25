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
class Adyen_Payment_Model_Adyen_PayByMail extends Adyen_Payment_Model_Adyen_Abstract {

    protected $_code = 'adyen_pay_by_mail';
    protected $_formBlockType = 'adyen/form_payByMail';
    protected $_infoBlockType = 'adyen/info_payByMail';
    protected $_paymentMethod = 'pay_by_mail';
    protected $_canUseCheckout = true;
    protected $_canUseInternal = true;
    protected $_canUseForMultishipping = true;

    /**
     * @var GUEST_ID , used when order is placed by guests
     */
    const GUEST_ID = 'customer_';

    public function __construct()
    {
        // check if this is adyen_cc payment method because this function is as well used for oneclick payments
        if($this->getCode() == "adyen_pay_by_mail") {
            $visible = Mage::getStoreConfig("payment/adyen_pay_by_mail/visible_type");
            if($visible == "backend") {
                $this->_canUseCheckout = false;
                $this->_canUseInternal = true;
            } else if($visible == "frontend") {
                $this->_canUseCheckout = true;
                $this->_canUseInternal = false;
            } else {
                $this->_canUseCheckout = true;
                $this->_canUseInternal = true;
            }
        }
        parent::__construct();
    }

    public function assignData($data)
    {

    }

    public function authorize(Varien_Object $payment, $amount) {

        $payment->setLastTransId($this->getTransactionId())->setIsTransactionPending(true);

        // create payment link and add it to comment history and send to shopper

        $order = $payment->getOrder();

        /*
         * Do not send a email notification when order is created.
         * Only do this on the AUHTORISATION notification.
         * This is needed for old versions where there is no check if email is already send
         */
//        $order->setCanSendNewEmailFlag(false);

        $fields = $this->getFormFields();

        $url = $this->getFormUrl();

        $count = 0;
        $size = count($fields);
        foreach ($fields as $field => $value) {

            if($count == 0) {
                $url .= "?";
            }
            $url .= urlencode($field) . "=" . urlencode($value);

            if($count != $size) {
                $url .= "&";
            }

            ++$count;
        }

        $comment = "<a target=\"_blank\" href=\"" . $url . "\">Generated payment url</a>";
        $status = $this->_getConfigData('order_status');

        $payment->getOrder()->addStatusHistoryComment($comment, $status);
        $payment->setAdditionalInformation('payment_url', $url);

        // send out email to shopper
//        $templateId = "Fav Email";
//
//        $emailTemplate = Mage::getModel('core/email_template')->loadByCode($templateId);
//
//        $vars = array('user_name' => $userName, 'product_name' => $productName);
//
//        $emailTemplate->getProcessedTemplate($vars);
//
//        $emailTemplate->setSenderEmail(Mage::getStoreConfig('trans_email/ident_general/email', $storeId));
//
//        $emailTemplate->setSenderName(Mage::getStoreConfig('trans_email/ident_general/name', $storeId));
//
//
//        $emailTemplate->send($receiveEmail,$receiveName, $vars);

//        $order->

//        $order->sendNewOrderEmail(); // send order email



        return $this;
    }

    public function getFormFields()
    {
        $this->_initOrder();
        $order             = $this->_order;
        $realOrderId       = $order->getRealOrderId();
        $orderCurrencyCode = $order->getOrderCurrencyCode();
        $skinCode          = trim($this->_getConfigData('skinCode', 'adyen_hpp'));
        $amount            = Mage::helper('adyen')->formatAmount($order->getGrandTotal(), $orderCurrencyCode);
        $merchantAccount   = trim($this->_getConfigData('merchantAccount'));
        $shopperEmail      = $order->getCustomerEmail();
        $customerId        = $order->getCustomerId();
        $shopperIP         = $order->getRemoteIp();
        $browserInfo       = $_SERVER['HTTP_USER_AGENT'];
        $shopperLocale     = trim($this->_getConfigData('shopperlocale'));
        $shopperLocale     = (!empty($shopperLocale)) ? $shopperLocale : Mage::app()->getLocale()->getLocaleCode();
        $countryCode       = trim($this->_getConfigData('countryCode'));
        $countryCode       = (!empty($countryCode)) ? $countryCode : false;
        // if directory lookup is enabled use the billingadress as countrycode
        if ($countryCode == false) {
            if (is_object($order->getBillingAddress()) && $order->getBillingAddress()->getCountry() != "") {
                $countryCode = $order->getBillingAddress()->getCountry();
            }
        }
        $adyFields                      = array();
        $deliveryDays                   = (int)$this->_getConfigData('delivery_days', 'adyen_hpp');
        $deliveryDays                   = (!empty($deliveryDays)) ? $deliveryDays : 5;
        $adyFields['merchantAccount']   = $merchantAccount;
        $adyFields['merchantReference'] = $realOrderId;
        $adyFields['paymentAmount']     = (int)$amount;
        $adyFields['currencyCode']      = $orderCurrencyCode;
        $adyFields['shipBeforeDate']    = date(
            "Y-m-d",
            mktime(date("H"), date("i"), date("s"), date("m"), date("j") + $deliveryDays, date("Y"))
        );
        $adyFields['skinCode']          = $skinCode;
        $adyFields['shopperLocale']     = $shopperLocale;
        $adyFields['countryCode']       = $countryCode;

        //order data
        $items          = $order->getAllItems();
        $shipmentAmount = number_format($order->getShippingAmount() + $order->getShippingTaxAmount(), 2, ',', ' ');
        $prodDetails    = Mage::helper('adyen')->__('Shipment cost: %s %s <br />', $shipmentAmount, $orderCurrencyCode);
        $prodDetails .= Mage::helper('adyen')->__('Order rows: <br />');
        foreach ($items as $item) {
            if ($item->getParentItem()) {
                continue;
            }
            $name       = $item->getName();
            $qtyOrdered = $this->_numberFormat($item->getQtyOrdered(), '0');
            $rowTotal   = number_format($item->getRowTotalInclTax(), 2, ',', ' ');
            $prodDetails .= Mage::helper('adyen')->__(
                '%s ( Qty: %s ) (Price: %s %s ) <br />',
                $name,
                $qtyOrdered,
                $rowTotal,
                $orderCurrencyCode
            );
        }
        $adyFields['orderData']       = base64_encode(gzencode($prodDetails)); //depreacated by Adyen
        $adyFields['sessionValidity'] = date(
            DATE_ATOM,
            mktime(date("H") + 1, date("i"), date("s"), date("m"), date("j"), date("Y"))
        );
        $adyFields['shopperEmail']    = $shopperEmail;
        // recurring
        $recurringType                  = trim($this->_getConfigData('recurringtypes', 'adyen_abstract'));
        $adyFields['recurringContract'] = $recurringType;
        $adyFields['shopperReference']  = (!empty($customerId)) ? $customerId : self::GUEST_ID . $realOrderId;
        //blocked methods
        $adyFields['blockedMethods'] = "";

        /*
         * This feld will be appended as-is to the return URL when the shopper completes, or abandons, the payment and
         * returns to your shop; it is typically used to transmit a session ID. This feld has a maximum of 128 characters
         * This is an optional field and not necessary by default
         */
        $adyFields['merchantReturnData'] = "";

        $openinvoiceType = $this->_getConfigData('openinvoicetypes', 'adyen_openinvoice');
        if ($this->_code == "adyen_openinvoice" || $this->getInfoInstance()->getCcType() == "klarna"
            || $this->getInfoInstance()->getCcType() == "afterpay_default"
        ) {
            $adyFields['billingAddressType']  = "1";
            $adyFields['deliveryAddressType'] = "1";
            $adyFields['shopperType']         = "1";
        } else {
            $adyFields['billingAddressType']  = "";
            $adyFields['deliveryAddressType'] = "";
            $adyFields['shopperType']         = "";
        }
        //the data that needs to be signed is a concatenated string of the form data
        $sign = $adyFields['paymentAmount'] .
            $adyFields['currencyCode'] .
            $adyFields['shipBeforeDate'] .
            $adyFields['merchantReference'] .
            $adyFields['skinCode'] .
            $adyFields['merchantAccount'] .
            $adyFields['sessionValidity'] .
            $adyFields['shopperEmail'] .
            $adyFields['shopperReference'] .
            $adyFields['recurringContract'] .
            $adyFields['blockedMethods'] .
            $adyFields['merchantReturnData'] .
            $adyFields['billingAddressType'] .
            $adyFields['deliveryAddressType'] .
            $adyFields['shopperType'];
        //Generate HMAC encrypted merchant signature
        $secretWord               = $this->_getSecretWord($order->getStoreId());
        $signMac                  = Zend_Crypt_Hmac::compute($secretWord, 'sha1', $sign);
        $adyFields['merchantSig'] = base64_encode(pack('H*', $signMac));
        // get extra fields
        //$adyFields = Mage::getModel('adyen/adyen_openinvoice')->getOptionalFormFields($adyFields, $this->_order);
        //IDEAL
        if (strpos($this->getInfoInstance()->getCcType(), "ideal") !== false) {
            $bankData = $this->getInfoInstance()->getPoNumber();
            if (!empty($bankData)) {
                $id                         = explode(DS, $bankData);
                $adyFields['skipSelection'] = 'true';
                $adyFields['brandCode']     = $this->getInfoInstance()->getCcType();
                $adyFields['idealIssuerId'] = $id['0'];
            }
        }
        // if option to put Return Url in request from magento is enabled add this in the request
        $returnUrlInRequest = $this->_getConfigData('return_url_in_request', 'adyen_hpp');
        if ($returnUrlInRequest) {
            $url = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_LINK, true) . "adyen/process/success";
            $adyFields['resURL'] = $url;
        }
        // pos over hpp
//         disable this because no one using this and it will always show POS payment method
//         $terminalcode = 'redirect';
//         $adyFields['pos.serial_number'] = $terminalcode;
//         // calculate signatature pos
//         $strsign = "merchantSig:pos.serial_number|" . $adyFields['merchantSig'] . ":" . $terminalcode;
//         $signPOS = Zend_Crypt_Hmac::compute($secretWord, 'sha1', $strsign);
//         $adyFields['pos.sig'] = base64_encode(pack('H*', $signPOS));
        Mage::log($adyFields, self::DEBUG_LEVEL, 'http-request.log', true);
        return $adyFields;
    }

    protected function _getSecretWord($storeId=null)
    {
        switch ($this->getConfigDataDemoMode()) {
            case true:
                $secretWord = trim($this->_getConfigData('secret_wordt', 'adyen_hpp', $storeId));
                break;
            default:
                $secretWord = trim($this->_getConfigData('secret_wordp', 'adyen_hpp' ,$storeId));
                break;
        }
        return $secretWord;
    }

    public function getFormUrl()
    {
        $isConfigDemoMode = $this->getConfigDataDemoMode();
        switch ($isConfigDemoMode) {
            case true:
                $url = 'https://test.adyen.com/hpp/pay.shtml';
                break;
            default:
                $url = 'https://live.adyen.com/hpp/pay.shtml';
                break;
        }
        return $url;
    }







}