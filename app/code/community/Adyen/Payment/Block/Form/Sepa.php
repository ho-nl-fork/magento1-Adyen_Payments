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
 * @category     Adyen
 * @package      Adyen_Payment
 * @copyright    Copyright (c) 2011 Adyen (http://www.adyen.com)
 * @license      http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */



/**
 * @category   Payment Gateway
 * @package    Adyen_Payment
 * @author     Adyen
 * @property   Adyen B.V
 * @copyright  Copyright (c) 2014 Adyen BV (http://www.adyen.com)
 */
class Adyen_Payment_Block_Form_Sepa extends Mage_Payment_Block_Form
{

    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('adyen/form/sepa.phtml');

        if (Mage::getStoreConfig('payment/adyen_abstract/title_renderer')
            == Adyen_Payment_Model_Source_Rendermode::MODE_TITLE_IMAGE) {
            $this->setMethodTitle('');
        }
    }

    public function getMethodLabelAfterHtml()
    {
        if (Mage::getStoreConfig('payment/adyen_abstract/title_renderer')
            == Adyen_Payment_Model_Source_Rendermode::MODE_TITLE) {
            return '';
        }

        if (! $this->hasData('_method_label_html')) {
            $labelBlock = Mage::app()->getLayout()->createBlock('core/template', null, array(
                'template' => 'adyen/payment/payment_method_label.phtml',
                'payment_method_icon' =>  $this->getSkinUrl('images/adyen/img_trans.gif'),
                'payment_method_label' => Mage::helper('adyen')->getConfigData('title', $this->getMethod()->getCode()),
                'payment_method_class' => $this->getMethod()->getCode()
            ));

            $this->setData('_method_label_html', $labelBlock->toHtml());
        }

        return $this->getData('_method_label_html');
    }


    public function getCountries()
    {
        $sepaCountriesAllowed = array(
            "AT",
            "BE",
            "BG",
            "CH",
            "CY",
            "CZ",
            "DE",
            "DK",
            "EE",
            "ES",
            "FI",
            "FR",
            "GB",
            "GF",
            "GI",
            "GP",
            "GR",
            "HR",
            "HU",
            "IE",
            "IS",
            "IT",
            "LI",
            "LT",
            "LU",
            "LV",
            "MC",
            "MQ",
            "MT",
            "NL",
            "NO",
            "PL",
            "PT",
            "RE",
            "RO",
            "SE",
            "SI",
            "SK"
        );
        $countryList = Mage::getResourceModel('directory/country_collection')
            ->loadData()
            ->toOptionArray(false);
        $sepaCountries = array();
        foreach ($countryList as $key => $country) {
            $value = $country['value'];
            if (!in_array($value, $sepaCountriesAllowed)) {
                unset($countryList[$key]);
            }
        }
        return $countryList;
    }


    public function getShopperCountryId()
    {
        $country = "";
        if (Mage::app()->getStore()->isAdmin()) {
            $quote = Mage::getSingleton('adminhtml/session_quote')->getQuote();
        } else {
            $quote = Mage::helper('checkout/cart')->getQuote();
        }
        if ($quote) {
            $billingAddress = $quote->getBillingAddress();
            if ($billingAddress) {
                $country = $billingAddress->getCountryId();
            }
        }
        return $country;
    }


    public function getBillingAgreements()
    {
        return $this->getMethod()->getBillingAgreementCollection();
    }
}
