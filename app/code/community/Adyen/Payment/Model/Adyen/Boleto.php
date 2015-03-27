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
class Adyen_Payment_Model_Adyen_Boleto extends Adyen_Payment_Model_Adyen_Abstract {

    protected $_code = 'adyen_boleto';
    protected $_formBlockType = 'adyen/form_boleto';
    protected $_infoBlockType = 'adyen/info_boleto';
    protected $_paymentMethod = 'boleto';
    protected $_canUseCheckout = true;
    protected $_canUseInternal = true;
    protected $_canUseForMultishipping = true;

	/**
     * 1)Called everytime the adyen_boleto is called or used in checkout
     * @descrition Assign data to info model instance
     *
     * @param   mixed $data
     * @return  Mage_Payment_Model_Info
     */
    public function assignData($data) {
        if (!($data instanceof Varien_Object)) {
            $data = new Varien_Object($data);
        }
        
        // get delivery date
        $delivery_days = (int) $this->_getConfigData('delivery_days', 'adyen_boleto');
        $delivery_days = (!empty($delivery_days)) ? $delivery_days : 5;
        $delivery_date = date("Y-m-d\TH:i:s ", mktime(date("H"), date("i"), date("s"), date("m"), date("j") + $delivery_days, date("Y")));
        
        $info = $this->getInfoInstance();
        $boleto = array(
            'firstname' => $data->getFirstname(),
            'lastname' => $data->getLastname(),
            'social_security_number' => $data->getSocialSecurityNumber(),
        	'selected_brand' => $data->getBoletoType(),
        	'delivery_date' => $delivery_date
        );

        $info = $this->getInfoInstance();
        $info->setPoNumber(serialize($boleto));
        $info->setCcType($data->getBoletoType());

        return $this;
    }

    /**
     * Called just after asssign data
     *
     * @return Mage_Payment_Model_Abstract
     */
    public function prepareSave() {
        //@todo encryption or so
        parent::prepareSave();
    }

    public function getUseTaxvat() {
        return $this->_getConfigData('use_taxvat', 'adyen_boleto');
    }
}
