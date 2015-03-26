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
class Adyen_Payment_Block_Adminhtml_System_Config_TestWebserverConfiguration extends Mage_Adminhtml_Block_System_Config_Form_Field
{

    /**
     * @var string
     */
    protected $_wizardTemplate = 'adyen/system/config/test_webserver_configuration.phtml';

    /**
     * Set template to itself
     */
    protected function _prepareLayout()
    {
        parent::_prepareLayout();
        if (!$this->getTemplate()) {
            $this->setTemplate($this->_wizardTemplate);
        }
        return $this;
    }

    /**
     * Unset some non-related element parameters
     *
     * @param Varien_Data_Form_Element_Abstract $element
     * @return string
     */
    public function render(Varien_Data_Form_Element_Abstract $element)
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    /**
     * Get the button and scripts contents
     *
     * @param Varien_Data_Form_Element_Abstract $element
     * @return string
     */
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        $originalData = $element->getOriginalData();
        $elementHtmlId = $element->getHtmlId();
        $this->addData(array_merge(
            $this->_getButtonData($elementHtmlId, $originalData)
        ));
        return $this->_toHtml();
    }

    /**
     * Prepare button data
     *
     * @param string $elementHtmlId
     * @param array $originalData
     * @return array
     */
    protected function _getButtonData($elementHtmlId, $originalData)
    {
        return array(
            'button_label' => Mage::helper('adyen')->__($originalData['button_label']),
            'modus' => $originalData['modus'],
            'html_id' => $elementHtmlId,
            'url_webserver_validation' => Mage::helper('adminhtml')->getUrl('adminhtml/ValidateWebserverSettings')
        );
    }



}
