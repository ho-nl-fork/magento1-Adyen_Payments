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
class Adyen_Payment_Model_Adyen_Sepa extends Adyen_Payment_Model_Adyen_Abstract {

    protected $_code = 'adyen_sepa';
    protected $_formBlockType = 'adyen/form_sepa';
    protected $_infoBlockType = 'adyen/info_sepa';
    protected $_paymentMethod = 'sepa';
    protected $_canUseCheckout = true;
    protected $_canUseInternal = true;
    protected $_canUseForMultishipping = true;

    public function __construct()
    {
        $visible = Mage::getStoreConfig("payment/adyen_sepa/visible_type");
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
        parent::__construct();
    }

    /**
     * 1)Called everytime the adyen_sepa is called or used in checkout
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
        $sepa = array(
            'account_name' => $data->getAccountName(),
            'iban' => $data->getIban(),
            'country' => $data->getCountry()
        );

        $info = $this->getInfoInstance();
        $info->setCcOwner($data->getOwner())
                ->setCcType($data->getBankLocation())
                ->setCcLast4(substr($data->getAccountNumber(), -4))
                ->setCcNumber($data->getAccountNumber())
                ->setCcNumberEnc($data->getBankCode())
                ->setPoNumber(serialize($sepa)); /* @note misused field for the elv */
        return $this;
    }

    public function validate()
    {
        parent::validate();

        // check if validator is on
        $ibanValidation = $this->_getConfigData("validate_iban", "adyen_sepa");

        if($ibanValidation) {

            $info = $this->getInfoInstance();
            $sepa = unserialize($info->getPoNumber());

            if(!$this->validateIban($sepa['iban']) || empty($sepa['iban'])){
                $errorCode = 'invalid_data';
                $errorMsg = Mage::helper('adyen')->__('Invalid Iban number.');
                Mage::throwException($errorMsg);
            }
        }
        return $this;
    }

    public function validateIban($iban) {

        $iban = strtolower(str_replace(' ','',$iban));
        $Countries = array('al'=>28,'ad'=>24,'at'=>20,'az'=>28,'bh'=>22,'be'=>16,'ba'=>20,'br'=>29,'bg'=>22,'cr'=>21,'hr'=>21,'cy'=>28,'cz'=>24,'dk'=>18,'do'=>28,'ee'=>20,'fo'=>18,'fi'=>18,'fr'=>27,'ge'=>22,'de'=>22,'gi'=>23,'gr'=>27,'gl'=>18,'gt'=>28,'hu'=>28,'is'=>26,'ie'=>22,'il'=>23,'it'=>27,'jo'=>30,'kz'=>20,'kw'=>30,'lv'=>21,'lb'=>28,'li'=>21,'lt'=>20,'lu'=>20,'mk'=>19,'mt'=>31,'mr'=>27,'mu'=>30,'mc'=>27,'md'=>24,'me'=>22,'nl'=>18,'no'=>15,'pk'=>24,'ps'=>29,'pl'=>28,'pt'=>25,'qa'=>29,'ro'=>24,'sm'=>27,'sa'=>24,'rs'=>22,'sk'=>24,'si'=>19,'es'=>24,'se'=>24,'ch'=>21,'tn'=>24,'tr'=>26,'ae'=>23,'gb'=>22,'vg'=>24);
        $Chars = array('a'=>10,'b'=>11,'c'=>12,'d'=>13,'e'=>14,'f'=>15,'g'=>16,'h'=>17,'i'=>18,'j'=>19,'k'=>20,'l'=>21,'m'=>22,'n'=>23,'o'=>24,'p'=>25,'q'=>26,'r'=>27,'s'=>28,'t'=>29,'u'=>30,'v'=>31,'w'=>32,'x'=>33,'y'=>34,'z'=>35);

        if(isset($Countries[substr($iban,0,2)]) && strlen($iban) == $Countries[substr($iban,0,2)]){

            $MovedChar = substr($iban, 4).substr($iban,0,4);
            $MovedCharArray = str_split($MovedChar);
            $NewString = "";

            foreach($MovedCharArray AS $key => $value){
                if(!is_numeric($MovedCharArray[$key])){
                    $MovedCharArray[$key] = $Chars[$MovedCharArray[$key]];
                }
                $NewString .= $MovedCharArray[$key];
            }

            if(bcmod($NewString, '97') == 1)
            {
                return TRUE;
            }
            else{
                return FALSE;
            }
        }
        else{
            return FALSE;
        }
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

    public function getBillingAgreementCollection()
    {
        return Mage::getResourceModel('sales/billing_agreement_collection')
            ->addFieldToFilter('customer_id', $this->getInfoInstance()->getQuote()->getCustomerId())
            ->addFieldToFilter('status', 'active')
            ->addFieldToFilter('method_code', $this->getCode());
    }

    /**
     * Init billing agreement
     *
     * @param Mage_Payment_Model_Billing_AgreementAbstract $agreement
     * @return $this
     */
    public function initBillingAgreementToken(Mage_Payment_Model_Billing_AgreementAbstract $agreement)
    {
        $agreement->setRedirectUrl(
            Mage::getUrl('*/*/returnWizard', array('payment_method' => $this->getCode(), 'token' => uniqid('t')))
        );
        return $this;
    }


    /**
     * Retrieve billing agreement details
     *
     * @param Mage_Payment_Model_Billing_AgreementAbstract $agreement
     * @return $this
     */
    public function getBillingAgreementTokenInfo(Mage_Payment_Model_Billing_AgreementAbstract $agreement)
    {

        return $this;
    }


    /**
     * Create billing agreement
     *
     * @param Mage_Payment_Model_Billing_AgreementAbstract $agreement
     * @return $this
     */
    public function placeBillingAgreement(Mage_Payment_Model_Billing_AgreementAbstract $agreement)
    {
        $agreement->setBillingAgreementId('SEPA12345');
        return $this;
    }


    /**
     * Update billing agreement status
     *
     * @param Mage_Payment_Model_Billing_AgreementAbstract $agreeme*
     * @return $this
     nt
     */
    public function updateBillingAgreementStatus(Mage_Payment_Model_Billing_AgreementAbstract $agreement)
    {
        return $this;
    }

//    public function validateRecurringProfile(Mage_Payment_Model_Recurring_Profile $profile)
//    {
//        return true;
//    }
//
//    public function submitRecurringProfile(Mage_Payment_Model_Recurring_Profile $profile, Mage_Payment_Model_Info $paymentInfo)
//    {
//
//    }
//
//    public function getRecurringProfileDetails($referenceId, Varien_Object $result)
//    {
//
//    }
//
//    public function canGetRecurringProfileDetails()
//    {
//        return false;
//    }
//
//    public function updateRecurringProfile(Mage_Payment_Model_Recurring_Profile $profile)
//    {
//
//    }
//
//    public function updateRecurringProfileStatus(Mage_Payment_Model_Recurring_Profile $profile)
//    {
//
//    }
}
