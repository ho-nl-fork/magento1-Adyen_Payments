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
class Adyen_Payment_Model_Resource_Adyen_Event
    extends Mage_Core_Model_Resource_Db_Abstract
{

    const COLLECTION_LIMIT = 1000;

    protected function _construct() {
        $this->_init('adyen/event', 'event_id');
    }

    /**
     * Retrieve back events
     * @param type $pspReference
     * @param type $adyenEventCode
     * @return type 
     */
    public function getEvent($pspReference, $adyenEventCode, $success = null) {
        $db = $this->_getReadAdapter();

        if($success == null) {
            $sql = $db->select()
                ->from($this->getMainTable(), array('*'))
                ->where('adyen_event_code = ?', $adyenEventCode)
                ->where('psp_reference = ?', $pspReference)
            ;
        } else {
            $sql = $db->select()
                ->from($this->getMainTable(), array('*'))
                ->where('adyen_event_code = ?', $adyenEventCode)
                ->where('psp_reference = ?', $pspReference)
                ->where('success = ?', $success)
            ;
        }

        $stmt = $db->query($sql);
        return $stmt->fetch();
    }
    
    /**
     * Get Event by order id
     * @param type $incrementId
     * @param type $adyenEventCode
     * @return type event id
     */
    public function getEventById($incrementId, $adyenEventCode = Adyen_Payment_Model_Event::ADYEN_EVENT_AUTHORISATION) {
        $db = $this->_getReadAdapter();
        $sql = $db->select()
                ->from($this->getMainTable(), array('*'))
                ->where('increment_id = ?', $incrementId)
                ->where('adyen_event_result = ?',$adyenEventCode)
        ;
        return $db->fetchOne($sql);
    }    

    public function saveData($obj) {
        $db = $this->_getWriteAdapter();
        $db->insert($this->getMainTable(), $obj->getData());
    }
    
    /**
     * Event Status
     * @param type $incrementId
     * @return type 
     */
    public function getLatestStatus($incrementId) {
        $db = $this->_getReadAdapter();
        $sql = $db->select()
                ->from($this->getMainTable(), array('adyen_event_result','created_at'))
                ->where('increment_id = ?', $incrementId)
                ->order('created_at desc')
        ;
        $stmt = $db->query($sql);
        return $stmt->fetch();       
    }
    
    public function getOriginalPspReference($incrementId) {
        $db = $this->_getReadAdapter();
        $sql = $db->select()
                ->from($this->getMainTable(), array('psp_reference'))
                ->where('increment_id = ?', $incrementId)
                ->where("adyen_event_result LIKE '%AUTHORISATION%'")
                ->order('psp_reference asc')
        ;
        $stmt = $db->query($sql);
        return $stmt->fetch();        
    }
    
    public function getAllDistinctEvents() {
        $db = $this->_getReadAdapter();
        $sql = $db->select()
            ->from($this->getMainTable(), array('adyen_event_result'))
            ->group('adyen_event_result');
        return $db->fetchAll($sql);
    }

}