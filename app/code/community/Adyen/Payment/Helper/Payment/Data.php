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
 * Because of the dynamic payment methods used we rewrite the Payment helper so we always get the right payment methods
 * back.
 * Class Adyen_Payment_Helper_Payment_Data
 */
class Adyen_Payment_Helper_Payment_Data extends Mage_Payment_Helper_Data {

    /**
     * Retrieve method model object
     *
     * @param   string $code
     * @return  Mage_Payment_Model_Method_Abstract|false
     */
    public function getMethodInstance($code)
    {
        $key = self::XML_PATH_PAYMENT_METHODS.'/'.$code.'/model';
        $class = Mage::getStoreConfig($key);

        if (! $class && strpos($code, 'adyen_hpp') !== false) {
            $methodCode = substr($code, strlen('adyen_hpp_'));
            Mage::getSingleton('adyen/observer')->createPaymentMethodFromHpp($methodCode, array(), Mage::app()->getStore(), '0');
            $class = Mage::getStoreConfig($key);
        } elseif(! $class && strpos($code, 'adyen_oneclick') !== false) {
            if(Mage::app()->getStore()->isAdmin()) {
                $methodCode = substr($code, strlen('adyen_oneclick_'));
                $store = Mage::getSingleton('adminhtml/session_quote')->getStore();
                Mage::getSingleton('adyen/observer')->createPaymentMethodFromOneClick($methodCode, array(), $store);
                $class = Mage::getStoreConfig($key, $store->getId());
            }
        }

        $methodInstance = Mage::getModel($class);
        if (method_exists($methodInstance, 'setCode')) {
            $methodInstance->setCode($code);
        }

        return $methodInstance;
    }

    /**
     * Get and sort available payment methods for specified or current store
     *
     * array structure:
     *  $index => Varien_Simplexml_Element
     *
     * @todo maybe we can use this method instead of loading the payment methods on each pageload.
     * @param mixed $store
     * @param Mage_Sales_Model_Quote $quote
     * @return array
     */
    public function getStoreMethods($store = null, $quote = null)
    {
        $res = array();
        foreach ($this->getPaymentMethods($store) as $code => $methodConfig) {
            $prefix = self::XML_PATH_PAYMENT_METHODS . '/' . $code . '/';
            if (!$model = Mage::getStoreConfig($prefix . 'model', $store)) {
                continue;
            }
            /** @var Mage_Payment_Model_Method_Abstract $methodInstance */
            $methodInstance = Mage::getModel($model);
            if (method_exists($methodInstance, 'setCode')) {
                $methodInstance->setCode($code);
            }
            if (!$methodInstance) {
                continue;
            }
            $methodInstance->setStore($store);
            if (!$methodInstance->isAvailable($quote)) {
                /* if the payment method cannot be used at this time */
                continue;
            }
            $sortOrder = (int)$methodInstance->getConfigData('sort_order', $store);
            $methodInstance->setSortOrder($sortOrder);
            $res[] = $methodInstance;
        }

        usort($res, array($this, '_sortMethods'));
        return $res;
    }

    /**
     * Retrieve payment information block
     *
     * @param   Mage_Payment_Model_Info $info
     * @return  Mage_Core_Block_Template
     */
    public function getInfoBlock(Mage_Payment_Model_Info $info)
    {
        $instance = $this->getMethodInstance($info->getMethod());
        if ($instance) {
            $instance->setInfoInstance($info);
            $info->setMethodInstance($instance);
        }

        $blockType = $instance->getInfoBlockType();
        if ($this->getLayout()) {
            $block = $this->getLayout()->createBlock($blockType);
        }
        else {
            $className = Mage::getConfig()->getBlockClassName($blockType);
            $block = new $className;
        }
        $block->setInfo($info);
        return $block;
    }
}