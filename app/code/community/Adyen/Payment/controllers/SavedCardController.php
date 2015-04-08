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
class Adyen_Payment_SavedCardController extends Mage_Core_Controller_Front_Action
{

    /**
     *
     * @var Mage_Customer_Model_Session
     */
    protected $_session = null;

    /**
     * Make sure customer is logged in and put it into registry
     */
    public function preDispatch()
    {
        parent::preDispatch();
        if (!$this->getRequest()->isDispatched()) {
            return;
        }
        $this->_session = Mage::getSingleton('customer/session');
        if (!$this->_session->authenticate($this)) {
            $this->setFlag('', 'no-dispatch', true);
        }
        Mage::register('current_customer', $this->_session->getCustomer());
    }

    /**
     * Profiles listing
     */
    public function indexAction()
    {
        // check if there is a submit on card delete
        if ($this->getRequest()->isPost()) {
            $recurringDetailReference =  $this->getRequest()->getParam('recurringDetailReference');
            if($recurringDetailReference != "") {
                $storeId = Mage::app()->getStore()->getStoreId();
                $merchantAccount = Mage::getStoreConfig("payment/adyen_abstract/merchantAccount", $storeId);
                $customer = Mage::registry('current_customer');
                $shopperReference = $customer->getId();
                // do api call to delete this card
                $success = Mage::helper('adyen')->removeRecurringCard($merchantAccount, $shopperReference, $recurringDetailReference);

                // show result message
                if($success) {
                    $this->_getSession()->addSuccess(Mage::helper('adyen')->__('The card has been deleted.'));

                    // remove the billing agreement
                    $agreement = Mage::getModel('sales/billing_agreement')->load($recurringDetailReference, 'reference_id');

                    if ($agreement && $agreement->getAgreementId() > 0 && $agreement->isValid()) {
                        $agreement->delete();
                    }

                    /*
                     * clear the cache for recurring payments so new card will be added
                     */
                    $recurringType = Mage::getStoreConfig('payment/adyen_abstract/recurringtypes', $storeId);
                    $customer = Mage::registry('current_customer');
                    $shopperReference = $customer->getId();

                    $cacheKey = $merchantAccount . "|" . $shopperReference . "|" . $recurringType;
                    Mage::app()->getCache()->remove($cacheKey);

                } else {
                    $this->_getSession()->addError(Mage::helper('adyen')->__('The card has not been deleted, please contact us.'));
                }
            }
        }

        $this->_title($this->__('Saved Cards'));
        $this->loadLayout();
        $this->_initLayoutMessages('customer/session');
        $block = $this->getLayout()->getBlock('adyen.savedCards');
        if ($block) {
            $block->setRefererUrl($this->_getRefererUrl());
        }
        $this->renderLayout();
    }

    /**
     * Retrieve customer session object
     *
     * @return Mage_Customer_Model_Session
     */
    protected function _getSession()
    {
        return Mage::getSingleton('customer/session');
    }


}