<?php
/**
 * Adyen_Payment
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the H&O Commercial License
 * that is bundled with this package in the file LICENSE_HO.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.h-o.nl/license
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to info@h-o.com so we can send you a copy immediately.
 *
 * @category  Adyen
 * @package   Adyen_Payment
 * @author    Paul Hachmang – H&O <info@h-o.nl>
 * @copyright 2015 Copyright © H&O (http://www.h-o.nl/)
 * @license   H&O Commercial License (http://www.h-o.nl/license)
 */
 
class Adyen_Payment_Model_Adyen_Ideal
    extends Adyen_Payment_Model_Adyen_Hpp
{
    protected $_code = 'adyen_ideal';
    protected $_formBlockType = 'adyen/form_ideal';

    /**
     * @return mixed
     */
    public function getShowIdealLogos()
    {
        return $this->_getConfigData('show_ideal_logos', 'adyen_ideal');
    }

    public function getIssuers()
    {
        $issuerData = json_decode($this->getConfigData('issuers'), true);
        $issuers = array();
        if (! $issuerData) {
            return $issuers;
        }
        foreach ($issuerData as $issuer) {
            $issuers[$issuer['issuerId'].'/'.$issuer['name']] = array(
                'label' => $issuer['name']
            );
        }

        if (isset($issuers[$this->getInfoInstance()->getPoNumber()])) {
            $issuers[$this->getInfoInstance()->getPoNumber()]['selected'] = true;
        }
        ksort($issuers);
        return $issuers;
    }
}
