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

class Adyen_Payment_Block_Adminhtml_System_Config_Fieldset_Gettingstarted
    extends Adyen_Payment_Block_Adminhtml_System_Config_Fieldset_Fieldset
{

    /**
     * Return header comment part of html for fieldset
     * Add the Export Settings button
     *
     * @param Varien_Data_Form_Element_Abstract $element
     * @return string
     */
    protected function _getHeaderCommentHtml($element)
    {
        $html =  $element->getComment()
            ? '<div class="comment">' . $element->getComment() . '</div>'
            : '';
        $url = Mage::helper('adminhtml')->getUrl('adminhtml/ExportAdyenSettings');
        $html .= <<<HTML
<div class="button-container">
    <button type="button" class="button" id="{$element->getHtmlId()}-export" onclick="location.href='{$url}'">
        {$this->__('Export Settings')}
    </button>
</div>
HTML;

        return $html;
    }

}
