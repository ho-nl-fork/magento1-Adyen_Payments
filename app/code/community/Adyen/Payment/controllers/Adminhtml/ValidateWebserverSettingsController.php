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
class Adyen_Payment_Adminhtml_ValidateWebserverSettingsController extends Mage_Adminhtml_Controller_Action {

    public function indexAction()
    {
        $result = false;

        $modus = $this->getRequest()->getParam('modus');
        $username = $this->getRequest()->getParam('username');
        $password = $this->getRequest()->getParam('password');

        // check if password is encrypted if so get it from database
        if (preg_match('/^\*+$/', $password)) {

            $websiteCode = Mage::app()->getRequest()->getParam('website');
            $storeCode = Mage::app()->getRequest()->getParam('store');

            if($storeCode) {
                $store = Mage::getModel('core/store')->load($storeCode);
                $storeId = $store->getId();
            } elseif ($websiteCode) {
                $website = Mage::getModel('core/website')->load($websiteCode);
                $storeId = $website->getId();
            } else {
                // the default
                $storeId = 0;
            }

            if($modus == 'test') {
                $configValue = 'ws_password_test';
            } else {
                $configValue = 'ws_password_live';
            }

            $password = Mage::helper('core')->decrypt(Mage::helper('adyen')->getConfigData($configValue, 'adyen_abstract', $storeId));
        }

        $ch = curl_init();
        if($modus == 'test') {
            curl_setopt($ch, CURLOPT_URL, "https://pal-test.adyen.com/pal/adapter/httppost?Payment");
        } else {
            curl_setopt($ch, CURLOPT_URL, "https://pal-live.adyen.com/pal/adapter/httppost?Payment");
        }

        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC  );
        curl_setopt($ch, CURLOPT_USERPWD,$username.":".$password);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $results = curl_exec($ch);
        $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($httpStatus == 200) {
            $result = true;
        }

        echo $result;
        return;
    }
}