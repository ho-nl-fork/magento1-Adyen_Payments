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
class Adyen_Payment_Block_Form_Oneclick extends Adyen_Payment_Block_Form_Cc {

    protected function _construct() {
        parent::_construct();
        $this->setTemplate('adyen/form/oneclick.phtml');
    }


    /**
     * @return mixed|string
     */
    public function getMethodLabelAfterHtml()
    {
        $adyenHelper = Mage::helper('adyen');

        if (Mage::getStoreConfig('payment/adyen_abstract/title_renderer')
            == Adyen_Payment_Model_Source_Rendermode::MODE_TITLE) {
            return '';
        }

        if (! $this->hasData('_method_label_html')) {

            // get configuration of this specific payment method
            $methodCode = $this->getMethodCode();

            $variant = $adyenHelper->_getConfigData('variant', $methodCode);

            $result = Mage::getDesign()->getFilename("images/adyen/{$variant}.png", array('_type' => 'skin'));

            $imageUrl = file_exists($result)
                ? $this->getSkinUrl("images/adyen/{$variant}.png")
                : $this->getSkinUrl("images/adyen/img_trans.gif");


            $labelBlock = Mage::app()->getLayout()->createBlock('core/template', null, array(
                'template' => 'adyen/payment/payment_method_label.phtml',
                'payment_method_icon' =>  $imageUrl,
                'payment_method_label' => Mage::helper('adyen')->getConfigData('title', $this->getMethod()->getCode()),
                'payment_method_class' => $this->getMethod()->getCode()
            ));

            $this->setData('_method_label_html', $labelBlock->toHtml());
        }

        return $this->getData('_method_label_html');
    }


    /**
     * @return mixed
     */
    public function isRecurring() {
        return  $this->getMethod()->isRecurring();
    }


    /**
     * @return mixed
     */
    public function getInstallments() {
        $adyenHelper = Mage::helper('adyen');
        $methodCode = $this->getMethodCode();
        $ccType = $adyenHelper->_getConfigData('variant', $methodCode);
        $ccType = Mage::helper('adyen/data')->getMagentoCreditCartType($ccType);
        $result = Mage::helper('adyen/installments')->getInstallmentForCreditCardType($ccType);
        return $result;
    }


    /**
     * @return mixed
     */
    public function getRecurringDetails() {
        if(Mage::app()->getStore()->isAdmin()) {
            $storeId = Mage::getSingleton('adminhtml/session_quote')->getStoreId();
        } else {
            $storeId = Mage::app()->getStore()->getStoreId();
        }
        $recurringDetails = Mage::getStoreConfig("payment/".$this->getMethodCode(), $storeId);
        return $recurringDetails;
    }
}
