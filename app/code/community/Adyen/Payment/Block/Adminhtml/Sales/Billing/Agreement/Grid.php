<?php
/**
 * ${Namespace}_${Module}
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
 * @category  ${Namespace}
 * @package   ${Namespace}_${Module}
 * @author    Paul Hachmang – H&O <info@h-o.nl>
 * @copyright 2015 Copyright © H&O (http://www.h-o.nl/)
 * @license   H&O Commercial License (http://www.h-o.nl/license)
 *
 * ${Description}
 */
 
class Adyen_Payment_Block_Adminhtml_Sales_Billing_Agreement_Grid
    extends Mage_Sales_Block_Adminhtml_Billing_Agreement_Grid {

    /**
     * Prepare collection for grid
     *
     * @return Mage_Adminhtml_Block_Widget_Grid
     */
    protected function _prepareCollection()
    {

        /** @var Adyen_Payment_Model_Resource_Billing_Agreement_Collection $collection */
        $collection = Mage::getResourceModel('sales/billing_agreement_collection')
            ->addCustomerDetails();
        $collection->addNameToSelect();
        $this->setCollection($collection);

        call_user_func(array(get_parent_class(get_parent_class($this)), __FUNCTION__));
        return $this;
    }
    /**
     * Add columns to grid
     *
     * @return Mage_Adminhtml_Block_Widget_Grid
     */
    protected function _prepareColumns()
    {
        parent::_prepareColumns();

        $this->removeColumn('customer_firstname');
        $this->removeColumn('customer_lastname');
        $this->addColumnAfter('agreement_label', array(
            'header'            => Mage::helper('sales')->__('Agreement Label'),
            'index'             => 'agreement_label',
            'type'              => 'text',
        ), 'status');

        $this->addColumnAfter('name', array(
            'header'            => Mage::helper('customer')->__('Name'),
            'index'             => 'name',
            'type'              => 'text',
        ), 'customer_email');

//        $status = $this->getColumn('status');
//        $status->setData('frame_callback', [$this, 'decorateStatus']);

        $createdAt = $this->getColumn('created_at');
        $createdAt->setData('index', 'created_at');

        $createdAt = $this->getColumn('updated_at');
        $createdAt->setData('index', 'updated_at');

        $this->sortColumnsByOrder();


        return $this;
    }

//    /**
//     * Decorate status column values
//     *
//     * @param string $value
//     * @param Mage_Index_Model_Process $row
//     * @param Mage_Adminhtml_Block_Widget_Grid_Column $column
//     * @param bool $isExport
//     *
//     * @return string
//     */
//    public function decorateStatus($value, $row, $column, $isExport)
//    {
//        $class = '';
//        switch ($row->getStatus()) {
//            case Adyen_Payment_Model_Billing_Agreement::STATUS_CANCELED :
//                $class = 'grid-severity-notice';
//                break;
//            case Adyen_Payment_Model_Billing_Agreement::STATUS_ACTIVE :
//                $class = 'grid-severity-notice';
//                break;
//        }
//        return '<span class="'.$class.'"><span>'.$value.'</span></span>';
//    }
}
