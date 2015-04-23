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
 
class Adyen_Payment_Exception extends Exception
{
    /**
     * Throw an Adyen_Payment_Exception and log it.
     * @param      $message
     * @param null $messageStorage
     *
     * @throws Adyen_Payment_Exception
     */
    public static function throwException($message, $messageStorage = null)
    {
        if ($messageStorage && ($storage = Mage::getSingleton($messageStorage))) {
            $storage->addError($message);
        }
        $exception = new Adyen_Payment_Exception($message);
        self::logException($exception);

        throw $exception;
    }


    /**
     * Log an Adyen_Payment_Exception
     * @param Exception $e
     */
    public static function logException(Exception $e)
    {
        Mage::log("\n" . $e->__toString(), Zend_Log::ERR, 'adyen_exception.log');
    }
}
