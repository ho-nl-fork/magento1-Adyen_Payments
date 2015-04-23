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
 * @package	    Adyen_Payment
 * @copyright	Copyright (c) 2011 Adyen (http://www.adyen.com)
 * @license	http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
/**
 * @category   Payment Gateway
 * @package    Adyen_Payment
 * @author     Adyen
 * @property   Adyen B.V
 * @copyright  Copyright (c) 2015 Adyen BV (http://www.adyen.com)
 */



/**
 * Class Adyen_Payment_Model_Billing_Agreement
 */
class Adyen_Payment_Model_Billing_Agreement
    extends Mage_Sales_Model_Billing_Agreement {

    public function addRecurringContractData($data)
    {
        /** @var Adyen_Payment_Model_Adyen_Abstract $methodInstance */
        $methodInstance = Mage::helper('payment')->getMethodInstance($data['payment_method']);
        if (! $methodInstance) {
            Adyen_Payment_Exception::throwException('Can not update billing agreement, incorrect payment method specified in recurring contract data');
        }

        $this->setReferenceId($data['recurringDetailReference']);
        $methodInstance->addRecurringContractData($this, $data);
        $this->setAgreementData($data);

        return $this;
    }


    public function setAgreementData($data)
    {
        if (is_array($data)) {
            unset($data['creationDate']);
            unset($data['recurringDetailReference']);
            unset($data['payment_method']);
        }

        $this->setData('agreement_data', json_encode($data));
        return $this;
    }

    public function getOneClickData()
    {
        $data = $this->getData() + $this->getAgreementData();
        $data['title'] = $data['agreement_label'];
        unset($data['agreement_data']);
        unset($data['agreement_label']);

        return $data;
    }

    public function getAgreementData()
    {
        return json_decode($this->getData('agreement_data'), true);
    }
}
