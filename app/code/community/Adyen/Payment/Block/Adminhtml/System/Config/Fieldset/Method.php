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

class Adyen_Payment_Block_Adminhtml_System_Config_Fieldset_Method
    extends Adyen_Payment_Block_Adminhtml_System_Config_Fieldset_Fieldset
{
    /**
     * Check whether current payment method is enabled
     *
     * @param Varien_Data_Form_Element_Abstract $element
     * @param callback|null $configCallback
     * @return bool
     */
    protected function _isPaymentEnabled($element, $configCallback = null)
    {
        $groupConfig = $this->getGroup($element)->asArray();
        $activityPath = isset($groupConfig['activity_path']) ? $groupConfig['activity_path'] : '';

        if (empty($activityPath)) {
            return false;
        }

        if ($configCallback && is_callable($configCallback)) {
            $isPaymentEnabled = call_user_func($configCallback, $activityPath);
        } else {
            $isPaymentEnabled = (bool)(string)$this->_getConfigDataModel()->getConfigDataValue($activityPath);
        }

        return (bool)$isPaymentEnabled;
    }

    /**
     * Return header title part of html for payment solution
     *
     * @param Varien_Data_Form_Element_Abstract $element
     * @return string
     */
    protected function _getHeaderTitleHtml($element)
    {
        $html = '<div class="entry-edit-head collapseable" ><a id="' . $element->getHtmlId()
                    . '-head" href="#" onclick="Fieldset.toggleCollapse(\'' . $element->getHtmlId() . '\', \''
                    . $this->getUrl('*/*/state') . '\'); return false;">';

        $html .= ' <img src="https://www.adyen.com/adyen-templating-kit/static/png/adyen-logo.png" height="20" style="vertical-align: text-bottom"/> ';
        $html .= $element->getLegend();
        if ($this->_isPaymentEnabled($element)) {
            $html .= ' <img src="'.$this->getSkinUrl('images/icon-enabled.png').'" style="vertical-align: middle"/> ';
        }

        $html .= '</a></div>';
        return $html;
    }
}
