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
class Adyen_Payment_Block_Redirect extends Mage_Core_Block_Abstract {

    protected function _getCheckout() {
        return Mage::getSingleton('checkout/session');
    }

    protected function _getOrder() {
        if ($this->getOrder()) {
            return $this->getOrder();
        } else {
            // log the exception
            Mage::log("Redirect exception could not load the order:", Zend_Log::DEBUG, "adyen_notification.log", true);
            return null;
        }
    }

    protected function _toHtml() {

        $paymentObject = $this->_getOrder()->getPayment();
        $payment = $this->_getOrder()->getPayment()->getMethodInstance();

        $html = '<html><head><link rel="stylesheet" type="text/css" href="'.Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_SKIN).'/frontend/base/default/css/adyenstyle.css"><script src="//ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js" ></script>';

        // for cash add epson libary to open the cash drawer
        $cashDrawer = Mage::helper('adyen')->_getConfigData("cash_drawer", "adyen_pos", null);
        if($payment->getCode() == "adyen_hpp" && $paymentObject->getCcType() == "c_cash" && $cashDrawer) {
            $jsPath = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_JS);
            $html .= '<script src="'.$jsPath.'adyen/payment/epos-device-2.6.0.js"></script>';
        }
        $html .= '</head><body class="redirect-body-adyen">';


        // if pos payment redirect to app
        if($payment->getCode() == "adyen_pos") {

            $adyFields = $payment->getFormFields();
            // use the secure url (if not secure this will be filled in with http://
            $url = urlencode(Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_LINK, true)."adyen/process/successPos");

            // detect ios or android
            $ua = strtolower($_SERVER['HTTP_USER_AGENT']);
            $android = stripos($ua,'android');

            // extra parameters so that you alway's return these paramters from the application
            $extra_paramaters = urlencode("/?originalCustomCurrency=".$adyFields['currencyCode']."&originalCustomAmount=".$adyFields['paymentAmount']. "&originalCustomMerchantReference=".$adyFields['merchantReference'] . "&originalCustomSessionId=".session_id());

            // add recurring before the callback url
            if(empty($adyFields['recurringContract'])) {
                $recurring_parameters = "";
            } else {
                $recurring_parameters = "&recurringContract=".urlencode($adyFields['recurringContract'])."&shopperReference=".urlencode($adyFields['shopperReference']). "&shopperEmail=".urlencode($adyFields['shopperEmail']);
            }

            // important url must be the latest parameter before extra parameters! otherwise extra parameters won't return in return url
            if($android !== false) { // && stripos($ua,'mobile') !== false) {
                // watch out some attributes are different from ios (sessionid and callback_automatic) added start_immediately
                $launchlink = "adyen://www.adyen.com/?sessionid=".date('U')."&amount=".$adyFields['paymentAmount']."&currency=".$adyFields['currencyCode']."&description=".$adyFields['merchantReference']. $recurring_parameters . "&start_immediately=1&callback_automatic=1&callback=".$url .$extra_paramaters;
            } else {
                //$launchlink = "adyen://payment?currency=".$adyFields['currencyCode']."&amount=".$adyFields['paymentAmount']."&description=".$adyFields['merchantReference']."&callback=".$url."&sessionId=".session_id()."&callbackAutomatic=1".$extra_paramaters;
                $launchlink = "adyen://payment?sessionId=".session_id()."&amount=".$adyFields['paymentAmount']."&currency=".$adyFields['currencyCode']."&description=".$adyFields['merchantReference']. $recurring_parameters . "&callbackAutomatic=1&callback=".$url .$extra_paramaters;
            }

            // log the launchlink
            Mage::log("Launchlink:".$launchlink, Zend_Log::DEBUG, "adyen_notification.log", true);

            // call app directly without HPP
            $html .= "<div id=\"pos-redirect-page\">
    					<div class=\"logo\"></div>
    					<div class=\"grey-header\">
    						<h1>POS Payment</h1>
    					</div>
    					<div class=\"amount-box\">".
                $adyFields['paymentAmountGrandTotal'] .
                "<a id=\"launchlink\" href=\"".$launchlink ."\" >Payment</a> ".
                "</div>";

            $html .= '<script type="text/javascript">
    				
    				function checkStatus() {
	    				$.ajax({
						    url: "'. $this->getUrl('adyen/process/getOrderStatus', array('_secure'=>true)) . '",
						    type: "POST",
						    data: "merchantReference='.$adyFields['merchantReference'] .'",
						    success: function(data) {
						    	if(data == "true") {
						    		// redirect to success page
						    		window.location.href = "'. Mage::getBaseUrl()."adyen/process/successPosRedirect" . '";
						    	} else {
						    		window.location.href = "'. Mage::getBaseUrl()."adyen/process/cancel" . '";			
						    	}
						    }
						});
					}';

            if($android !== false) {
                $html .= 'url = document.getElementById(\'launchlink\').href;';
                $html .= 'window.location.assign(url);';
                $html .= 'window.onfocus = function(){setTimeout("checkStatus()", 500);};';
            } else {
                $html .= 'document.getElementById(\'launchlink\').click();';
    			$html .= 'setTimeout("checkStatus()", 5000);';
            }
            $html .= '</script></div>';
        } else {
            $form = new Varien_Data_Form();
            $form->setAction($payment->getFormUrl())
                ->setId($payment->getCode())
                ->setName($payment->getFormName())
                ->setMethod('POST')
                ->setUseContainer(true);
            foreach ($payment->getFormFields() as $field => $value) {
                $form->addField($field, 'hidden', array('name' => $field, 'value' => $value));
            }

            $html.= $this->__(' ');
            $html.= $form->toHtml();

            if($payment->getCode() == "adyen_hpp" && $paymentObject->getCcType() == "c_cash" && $cashDrawer) {
                $cashDrawerIp = Mage::helper('adyen')->_getConfigData("cash_drawer_printer_ip", "adyen_pos", null);
                $cashDrawerPort = Mage::helper('adyen')->_getConfigData("cash_drawer_printer_port", "adyen_pos", null);
                $cashDrawerDeviceId = Mage::helper('adyen')->_getConfigData("cash_drawer_printer_device_id", "adyen_pos", null);

                if($cashDrawerIp != '' && $cashDrawerPort != '' && $cashDrawerDeviceId != '') {
                    $html.= '
                            <script type="text/javascript">
                                var ipAddress = "'.$cashDrawerIp.'";
                                var port = "'.$cashDrawerPort.'";
                                var deviceID = "'.$cashDrawerDeviceId.'";
                                var ePosDev = new epson.ePOSDevice();
                                ePosDev.connect(ipAddress, port, Callback_connect);

                                function Callback_connect(data) {
                                    if (data == "OK" || data == "SSL_CONNECT_OK") {
                                        var options = "{}";
                                        ePosDev.createDevice(deviceID, ePosDev.DEVICE_TYPE_PRINTER, options, callbackCreateDevice_printer);
                                    } else {
                                        alert("connected to ePOS Device Service Interface is failed. [" + data + "]");
                                    }
                                }

                                function callbackCreateDevice_printer(data, code) {
                                    var print = data;
                                    var drawer = "{}";
                                    var time = print.PULSE_100
                                    print.addPulse();
                                    print.send();
                                    document.getElementById("'.$payment->getCode().'").submit();
                                }
                            </script>
                    ';
                } else {
                    Mage::log("You did not fill in all the fields (ip,port,device id) to use Cash Drawer support:", Zend_Log::DEBUG, "adyen_notification.log", true);
                }
            } else {
                $html.= '<script type="text/javascript">document.getElementById("'.$payment->getCode().'").submit();</script>';
            }
        }
        $html.= '</body></html>';
        return $html;
    }

}
