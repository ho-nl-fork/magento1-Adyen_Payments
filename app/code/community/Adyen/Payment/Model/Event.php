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
class Adyen_Payment_Model_Event extends Mage_Core_Model_Abstract {

    const ADYEN_EVENT_AUTHORISATION = 'AUTHORISATION';
    const ADYEN_EVENT_PENDING = 'PENDING';
    const ADYEN_EVENT_AUTHORISED = 'AUTHORISED';
    const ADYEN_EVENT_CANCELLED = 'CANCELLED';
    const ADYEN_EVENT_REFUSED = 'REFUSED';
    const ADYEN_EVENT_ERROR = 'ERROR';
    const ADYEN_EVENT_REFUND = 'REFUND';
    const ADYEN_EVENT_REFUND_FAILED = 'REFUND_FAILED';
    const ADYEN_EVENT_CANCEL_OR_REFUND  = 'CANCEL_OR_REFUND';
    const ADYEN_EVENT_CAPTURE = 'CAPTURE';
    const ADYEN_EVENT_CAPTURE_FAILED = 'CAPTURE_FAILED';
    const ADYEN_EVENT_CANCELLATION = 'CANCELLATION';
    const ADYEN_EVENT_POSAPPROVED = 'POS_APPROVED';
    const ADYEN_EVENT_HANDLED_EXTERNALLY  = 'HANDLED_EXTERNALLY';
    const ADYEN_EVENT_MANUAL_REVIEW_ACCEPT = 'MANUAL_REVIEW_ACCEPT';
    const ADYEN_EVENT_MANUAL_REVIEW_REJECT = 'MANUAL_REVIEW_REJECT ';
    const ADYEN_EVENT_RECURRING_CONTRACT = "RECURRING_CONTRACT";
    const ADYEN_EVENT_REPORT_AVAILABLE = "REPORT_AVAILABLE";

    /**
     * Initialize resources
     */
    protected function _construct() {
        $this->_init('adyen/adyen_event');
    }

    /**
     * Check if the Adyen Notification is already stored in the system
     * @param type $dbPspReference
     * @param type $dbEventCode
     * @return boolean true if the event is a duplicate
     */
    public function isDuplicate($pspReference, $event, $success) {
        $success = (trim($success) == "true") ? true : false;
        $result = $this->getResource()->getEvent(trim($pspReference), trim($event), $success);
        return (empty($result)) ? false : true;
    }

    public function getEvent($pspReference, $event) {
        return $this->getResource()->getEvent($pspReference, $event);
    }

    public function saveData() {
        $this->getResource()->saveData($this);
    }

    public function getOriginalPspReference($incrementId) {
        $originalReference = $this->getResource()->getOriginalPspReference($incrementId);
        return (!empty($originalReference)) ? $originalReference['psp_reference'] : false;
    }
}