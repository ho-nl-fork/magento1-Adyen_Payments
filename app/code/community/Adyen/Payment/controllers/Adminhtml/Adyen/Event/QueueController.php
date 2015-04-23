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

class Adyen_Payment_Adminhtml_Adyen_Event_QueueController extends Mage_Adminhtml_Controller_Action {

    public function indexAction() {

        Mage::getSingleton('adminhtml/session')->addNotice(Mage::helper('catalogrule')->__('If you are using Adyen CreditCard payment method it could be that the notifcation that is send from the Adyen Platform is faster then Magento saves the order. The notification is saved and when a new notification is send it will try to update the previous notification as well. You can see here what notifications did not processed yet and you can proccess it here manual if you want to by selecting "Execute" under the Actions column '));

        $this->_title(Mage::helper('sales')->__('Sales'))->_title(Mage::helper('adyen')->__('Adyen Event Queue'))
            ->loadLayout()
            ->_setActiveMenu('sales/adyen_event_queue')
            ->renderLayout();
        return $this;
    }

    /**
     * Event queue ajax grid
     */
    public function gridAction()
    {
        try {
            $this->loadLayout()->renderLayout();
            return;
        } catch (Mage_Core_Exception $e) {
            $this->_getSession()->addError($e->getMessage());
        } catch (Exception $e) {
            Adyen_Payment_Exception::logException($e);
        }
        $this->_redirect('*/*/');
    }

    /**
     * This tries to process the notification again
     */
    public function executeAction() {
        // get event queue id
        $eventQueueId = $this->getRequest()->getParam('event_queue_id');
        $eventQueue = Mage::getModel('adyen/event_queue')->load($eventQueueId);

        $incrementId = $eventQueue->getIncrementId();
        $varienObj = unserialize($eventQueue->getResponse());

        $orderExist = Mage::getResourceModel('adyen/order')->orderExist($incrementId);
        if (!empty($orderExist)) {
            $order = Mage::getModel('sales/order');
            $order->loadByIncrementId($incrementId);

            // process it
            Mage::getModel('adyen/process')->_processNotifications($order, $varienObj);

            // remove it from queue
            $eventQueue->delete();
        } else {
            // add this
            $currentAttempt = $eventQueue->getAttempt();
            $eventQueue->setAttempt(++$currentAttempt);
            $eventQueue->save();

            $this->_getSession()->addError($this->__('The order does not exist.'));
        }
        // return back to the view
        $this->_redirect('*/*/');
    }

    public function deleteAction() {

        $eventQueueId = $this->getRequest()->getParam('event_queue_id');
        $eventQueue = Mage::getModel('adyen/event_queue')->load($eventQueueId);
        $eventQueue->delete();
        // return back to the view
        $this->_redirect('*/*/');
    }

}