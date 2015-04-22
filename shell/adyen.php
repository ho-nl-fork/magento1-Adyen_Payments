<?php
/**
 * Adyen_Payments
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
 * @category  Adyen
 * @package   Adyen_Payments
 * @author    Paul Hachmang – H&O <info@h-o.nl>
 * @copyright 2015 Copyright © H&O (http://www.h-o.nl/)
 * @license   H&O Commercial License (http://www.h-o.nl/license)
 */

require_once 'abstract.php';

class Adyen_Payments_Shell extends Mage_Shell_Abstract
{

    /**
   	 * Run script
   	 *
   	 * @return void
   	 */
   	public function run() {
   		$action = $this->getArg('action');
   		if (empty($action)) {
   			echo $this->usageHelp();
   		} else {
   			$actionMethodName = $action.'Action';
   			if (method_exists($this, $actionMethodName)) {
   				$this->$actionMethodName();
   			} else {
   				echo "Action $action not found!\n";
   				echo $this->usageHelp();
   				exit(1);
   			}
   		}
   	}

    /**
   	 * Retrieve Usage Help Message
   	 *
   	 * @return string
   	 */
   	public function usageHelp() {
   		$help = 'Available actions: ' . "\n";
   		$methods = get_class_methods($this);
   		foreach ($methods as $method) {
   			if (substr($method, -6) == 'Action') {
   				$help .= '    -action ' . substr($method, 0, -6);
   				$helpMethod = $method.'Help';
   				if (method_exists($this, $helpMethod)) {
   					$help .= $this->$helpMethod();
   				}
   				$help .= "\n";
   			}
   		}
   		return $help;
   	}


    /**
	 * Method to load all billing agreements into Magento.
	 *
	 * configured in Magento and loops for that.
   	 * @return void
   	 */
   	public function loadBillingAgreementsAction()
	{
		$api = Mage::getSingleton('adyen/api');

		foreach (Mage::app()->getStores(true) as $store) {
			$customerCollection = Mage::getResourceModel('customer/customer_collection');
			$customerCollection->addFieldToFilter('store_id', $store->getId());

			$select = $customerCollection->getSelect();
			$select->reset(Varien_Db_Select::COLUMNS);
			$select->columns('e.entity_id');
			$customerCollection->joinAttribute(
				'adyen_customer_ref',
				'customer/adyen_customer_ref',
				'entity_id', null, 'left'
			);

			$customerReferences = $customerCollection->getConnection()->fetchPairs($select);
			foreach ($customerReferences as $customerId => $adyenCustomerRef) {
				$customerReference = $adyenCustomerRef ?: $customerId;

				$recurringContracts = $api->listRecurringContracts($customerReference, $store);

				$billingAgreementCollection = Mage::getResourceModel('adyen/billing_agreement_collection')
					->addFieldToFilter('customer_id', $customerId);

				//Update the billing agreements
				foreach ($recurringContracts as $recurringContract) {
					$billingAgreement = $billingAgreementCollection
						->getItemByColumnValue('reference_id', $recurringContract['recurringDetailReference']);

					if (! $billingAgreement) {
						$billingAgreement = Mage::getModel('adyen/billing_agreement');
						$billingAgreement->setCustomerId($customerId);
						$billingAgreement->setStoreId($store->getId());
						$billingAgreement->setStatus($billingAgreement::STATUS_ACTIVE);
					} else {
						$billingAgreementCollection->removeItemByKey($billingAgreement->getId());
					}
					$billingAgreement->addRecurringContractData($recurringContract);
					$billingAgreement->save();
				}

				foreach ($billingAgreementCollection as $billingAgreement) {
					$billingAgreement->setStatus($billingAgreement::STATUS_CANCELED);
					$billingAgreement->save();
				}
			}
		}
   	}


   	/**
   	 * Display extra help
   	 * @return string
   	 */
   	public function loadBillingAgreementsActionHelp() {
   		return "";
   	}
}


$shell = new Adyen_Payments_Shell();
$shell->run();
