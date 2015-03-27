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
class Adyen_Payment_Model_Adyen_Elv extends Adyen_Payment_Model_Adyen_Abstract {

    protected $_code = 'adyen_elv';
    protected $_formBlockType = 'adyen/form_elv';
    protected $_infoBlockType = 'adyen/info_elv';
    protected $_paymentMethod = 'elv';
    protected $_canUseCheckout = true;
    protected $_canUseInternal = true;
    protected $_canUseForMultishipping = true;

    /**
     * 1)Called everytime the adyen_elv is called or used in checkout
     * @descrition Assign data to info model instance
     *
     * @param   mixed $data
     * @return  Mage_Payment_Model_Info
     */
    public function assignData($data) {
        if (!($data instanceof Varien_Object)) {
            $data = new Varien_Object($data);
        }
        $info = $this->getInfoInstance();
        $elv = array(
            'account_owner' => $data->getOwner(),
            'bank_location' => $data->getBankLocation(),
            'account_number' => $data->getAccountNumber(),
            'bank_code' => $data->getBankCode(),
            'bank_name' => $data->getBankName()
        );
        $info = $this->getInfoInstance();
        $info->setCcOwner($data->getOwner())
                ->setCcType($data->getBankLocation())
                ->setCcLast4(substr($data->getAccountNumber(), -4))
                ->setCcNumber($data->getAccountNumber())
                ->setCcNumberEnc($data->getBankCode())
                ->setPoNumber(serialize($elv)); /* @note misused field for the elv */
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

}
