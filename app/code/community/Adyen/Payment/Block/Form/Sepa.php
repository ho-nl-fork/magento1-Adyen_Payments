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
class Adyen_Payment_Block_Form_Sepa extends Adyen_Payment_Block_Form_Abstract
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('adyen/form/sepa.phtml');
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


    /**
     * @return string
     */
    public function getCountryValue()
    {
        if ($country = $this->_getPaymentData('country')) {
            return $country;
        }

        $quote = $this->_getQuote();
        if (! $quote || ! $quote->getBillingAddress()) {
            return '';
        }

        return $quote->getBillingAddress()->getCountryId();
    }


    /**
     * @return string
     */
    public function getBankHolderValue()
    {
        if ($accountName = $this->_getPaymentData('account_name')) {
            return $accountName;
        }

        $quote = $this->_getQuote();
        if (! $quote || ! $quote->getBillingAddress()) {
            return '';
        }

        return $quote->getBillingAddress()->getName();
    }


    /**
     * @return string
     */
    public function getIbanValue()
    {
        return $this->_getPaymentData('iban');
    }
}
