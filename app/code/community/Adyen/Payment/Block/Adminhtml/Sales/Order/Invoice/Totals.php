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



/**
 * Class Adyen_Payment_Block_Adminhtml_Sales_Order_Invoice_Totals
 * @todo don't use rewrite, causes extension conflicts
 *       http://turnkeye.com/blog/magento-development-add-total-row-checkout/
 */
class Adyen_Payment_Block_Adminhtml_Sales_Order_Invoice_Totals extends Mage_Adminhtml_Block_Sales_Order_Invoice_Totals {

    protected function _initTotals()
    {
        parent::_initTotals();
        $this->addTotal(
            new Varien_Object(
                array(
                    'code'      => 'payment_fee',
                    'strong'    => false,
                    'value'     => $this->getSource()->getPaymentFeeAmount(),
                    'base_value'=> $this->getSource()->getBasePaymentFeeAmount(),
                    'label'     => $this->helper('adyen')->__('Payment Fee'),
                    'area'      => '',
                )
            ),
            'subtotal'
        );

        $this->addTotal(
            new Varien_Object(
                array(
                    'code'      => 'payment_installment_fee',
                    'strong'    => false,
                    'value'     => $this->getSource()->getPaymentInstallmentFeeAmount(),
                    'base_value'=> $this->getSource()->getBasePaymentInstallmentFeeAmount(),
                    'label'     => $this->helper('adyen')->__('Payment Fee Installments'),
                    'area'      => '',
                )
            ),
            'subtotal'
        );

        return $this;
    }
}