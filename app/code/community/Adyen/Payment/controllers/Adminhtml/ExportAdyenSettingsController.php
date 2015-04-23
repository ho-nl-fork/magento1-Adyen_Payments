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
class Adyen_Payment_Adminhtml_ExportAdyenSettingsController extends Mage_Adminhtml_Controller_Action {

    public function indexAction()
    {
        // get all adyen settings
        $path = "payment/adyen_";
        $collection = Mage::getModel('core/config_data')->getCollection()
            ->addFieldToFilter('path', array('like' => '%'.$path.'%' ));


        $xml_contents = "<root>";

        // to array
        $list = array();
        if ($collection->getSize() > 0) {
            foreach ($collection as $configItem) {

                $path = $configItem->getPath();
                $value = $configItem->getValue();
                $scope = $configItem->getScope();
                $scopeId = "ScopeId" . $configItem->getScopeId();

                // path to array
                $pathArray = explode("/",$path);
                $root = $pathArray[0];
                $paymentMethod = $pathArray[1];
                $node = $pathArray[2];


                // some settings are encoded decode this
                if($node == "notification_password" || $node == "ws_password_test" || $node == "ws_password_live") {
                    $value = Mage::helper('core')->decrypt($value);
                }
                $list[$scope][$scopeId][$root][$paymentMethod][$node] = $value;
            }
        }

        $xml = new SimpleXMLElement('<root/>');
        $xml->formatOutput = true;

        // function call to convert array to xml
        $this->_arrayToXml($list,$xml);

        // export to xml
        $contentType = "application/xml";
        $fileName = "AdyenSettings.xml";

        $content = $xml->asXML();
        $contentLength = "";

        $this->getResponse()
            ->setHttpResponseCode(200)
            ->setHeader('Pragma', 'public', true)
            ->setHeader('Cache-Control', 'must-revalidate, post-check=0, pre-check=0', true)
            ->setHeader('Content-type', $contentType, true)
//            ->setHeader('Content-Length', is_null($contentLength) ? strlen($content) : $contentLength, true)
            ->setHeader('Content-Disposition', 'attachment; filename="'.$fileName.'"', true)
            ->setHeader('Last-Modified', date('r'), true);

        $this->getResponse()->setBody($content);
        return $this;
    }

    // function defination to convert array to xml
    protected function _arrayToXml($array_o, &$xml) {
        foreach($array_o as $key => $value) {
            if(is_array($value)) {
                if(!is_numeric($key)){
                    $subnode = $xml->addChild("$key");
                    $this->_arrayToXml($value, $subnode);
                }
                else{
                    $subnode = $xml->addChild("item$key");
                    $this->_arrayToXml($value, $subnode);
                }
            }
            else {
                $xml->addChild("$key",htmlspecialchars("$value"));
            }
        }
    }

}