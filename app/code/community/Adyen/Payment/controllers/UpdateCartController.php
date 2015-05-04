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

class Adyen_Payment_UpdateCartController extends Mage_Core_Controller_Front_Action {

    public function indexAction()
    {

        $params = $this->getRequest()->getParams();
        $code = (isset($params['code'])) ? $params['code'] : "";
        $customCode = (isset($params['customcode'])) ? $params['customcode'] : "";

        // check if barcdoe is from scanner or filled in manually
        if($code != "") {
            $sku = $code;
        } elseif($customCode != "") {
            $sku = $customCode;
        } else {
            // no barcode
            $sku = "";
        }

        if($sku != "") {
            $productId = Mage::getModel('catalog/product')
                ->getIdBySku(trim($sku));

            if($productId > 0)
            {
                // Initiate product model
                $product = Mage::getModel('catalog/product');

                // Load specific product whose tier price want to update
                $product ->load($productId);

                if($product)
                {
                    $cart = Mage::getSingleton('checkout/cart');
                    $cart->addProduct($product, array('qty'=>'1'));
                    $cart->save();
                }
            }
        }

        // render the content so ajax call can update template
        $this->loadLayout();
        $layout = $this->getLayout();
        $block = $layout->getBlock("content");

        $this->getResponse()->setBody($block->toHtml());
    }

}