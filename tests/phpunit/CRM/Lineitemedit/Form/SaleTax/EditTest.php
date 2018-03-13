<?php

require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . '../BaseTest.php';
use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;

/**
 * FIXME - Add test description.
 *
 * Tips:
 *  - With HookInterface, you may implement CiviCRM hooks directly in the test class.
 *    Simply create corresponding functions (e.g. "hook_civicrm_post(...)" or similar).
 *  - With TransactionalInterface, any data changes made by setUp() or test****() functions will
 *    rollback automatically -- as long as you don't manipulate schema or truncate tables.
 *    If this test needs to manipulate schema or truncate tables, then either:
 *       a. Do all that using setupHeadless() and Civi\Test.
 *       b. Disable TransactionalInterface, and handle all setup/teardown yourself.
 *
 * @group headless
 */
class CRM_Lineitemedit_Form_SaleTax_EditTest extends CRM_Lineitemedit_Form_BaseTest {

  protected $_financialTypeID;
  protected $_financialTypeName;

  public function setUpHeadless() {
    return \Civi\Test::headless()
      ->installMe(__DIR__)
      ->apply();
  }

  public function setUp() {
    $this->_createContri = FALSE;
    parent::setUp();

    $this->enableTaxAndInvoicing();
    $this->_financialTypeName = 'Financial-Type -' . substr(sha1(rand()), 0, 7);
    $financialType = $this->createFinancialType(array('name' => $this->_financialTypeName));
    $this->_financialTypeID = $financialType['id'];
    $financialAccount = $this->relationForFinancialTypeWithFinancialAccount($financialType['id']);
  }

  public function tearDown() {
    $this->disableTaxAndInvoicing();
    parent::tearDown();
  }

  public function testLineTotalIncrease() {
    $this->createContribution(array(
      'financial_type_id' => $this->_financialTypeID,
    ));

    // Contribution amount and status before LineItem edit
    $this->assertEquals('Completed', $this->_contribution['contribution_status']);
    $this->assertEquals(110.00, $this->_contribution['total_amount']);

    $form = new CRM_Lineitemedit_Form_Edit();
    $lineItemInfo = $this->callAPISuccessGetSingle('LineItem', array('contribution_id' => $this->_contributionID));
    $lineItemInfo['qty'] += 2; // increase lineitem qty to 3
    $lineItemInfo['line_total'] *= $lineItemInfo['qty'];
    $lineItemInfo['tax_amount'] *= $lineItemInfo['qty'];
    $form->testSubmit($lineItemInfo);

    // Contribution amount and status after LineItem edit
    $contribution = $this->callAPISuccessGetSingle('Contribution', array('id' => $this->_contributionID));
    $this->assertEquals('Partially paid', $contribution['contribution_status']);
    $this->assertEquals(330.00, $contribution['total_amount']);

    $check = CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'payment_instrument_id', 'Check');
    $financialAccountID = CRM_Contribute_PseudoConstant::getRelationalFinancialAccount($this->_financialTypeID, 'Income Account is');
    $salesTaxFinancialAccountID = CRM_Lineitemedit_Util::getFinancialAccountId($this->_financialTypeID);
    $actualFinancialItemEntries = $this->getFinancialItemsByContributionID($this->_contributionID);
    $expectedFinancialItemEntries = array(
      array(
        'contact_id' => $this->_contactID,
        'description' => 'Contribution Amount',
        'amount' => 100.00,
        'financial_account_id' => $financialAccountID,
        'status_id' => CRM_Core_PseudoConstant::getKey('CRM_Financial_DAO_FinancialItem', 'status_id', 'Paid'),
      ),
      array(
        'contact_id' => $this->_contactID,
        'description' => 'Sales Tax',
        'amount' => 10.00,
        'financial_account_id' => $salesTaxFinancialAccountID,
        'status_id' => CRM_Core_PseudoConstant::getKey('CRM_Financial_DAO_FinancialItem', 'status_id', 'Paid'),
      ),
      array(
        'contact_id' => $this->_contactID,
        'description' => '3.00 of Contribution Amount',
        'amount' => 200.00,
        'financial_account_id' => $financialAccountID,
        'status_id' => CRM_Core_PseudoConstant::getKey('CRM_Financial_DAO_FinancialItem', 'status_id', 'Unpaid'),
      ),
      array(
        'contact_id' => $this->_contactID,
        'description' => 'Sales Tax',
        'amount' => 20.00,
        'financial_account_id' => $salesTaxFinancialAccountID,
        'status_id' => CRM_Core_PseudoConstant::getKey('CRM_Financial_DAO_FinancialItem', 'status_id', 'Unpaid'),
      ),
    );
    $this->checkArrayEqualsByAttributes($expectedFinancialItemEntries, $actualFinancialItemEntries);

    $actualFinancialTrxnEntries = $this->getFinancialTrxnsByContributionID($this->_contributionID);
    $expectedFinancialTrxnEntries = array(
      array(
        'total_amount' => 110.00,
        'net_amount' => 100.00, // @TODO this is suppose to be 110
        'is_payment' => 1,
        'payment_instrument_id' => $check,
        'status_id' => CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Completed'),
      ),
      array(
        'total_amount' => 220.00,
        'net_amount' => 220.00,
        'is_payment' => 0,
        'payment_instrument_id' => $check,
        'status_id' => CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Partially paid'),
      ),
    );
    $this->checkArrayEqualsByAttributes($expectedFinancialTrxnEntries, $actualFinancialTrxnEntries);
  }

  public function testLineTotalDecrease() {
    $this->createContribution(array(
      'financial_type_id' => $this->_financialTypeID,
    ));

    // Contribution amount and status before LineItem edit
    $this->assertEquals('Completed', $this->_contribution['contribution_status']);
    $this->assertEquals(110.00, $this->_contribution['total_amount']);

    $form = new CRM_Lineitemedit_Form_Edit();
    $lineItemInfo = $this->callAPISuccessGetSingle('LineItem', array(
      'contribution_id' => $this->_contributionID,
    ));
    $lineItemInfo['line_total'] = $lineItemInfo['unit_price'] -= 50;
    $lineItemInfo['tax_amount'] -= 5;
    $form->testSubmit($lineItemInfo);

    // Contribution amount and status after LineItem edit
    $contribution = $this->callAPISuccessGetSingle('Contribution', array('id' => $this->_contributionID));
    $this->assertEquals('Pending refund', $contribution['contribution_status']);
    $this->assertEquals(55.00, $contribution['total_amount']);

    $check = CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'payment_instrument_id', 'Check');
    $financialAccountID = CRM_Contribute_PseudoConstant::getRelationalFinancialAccount($this->_financialTypeID, 'Income Account is');
    $salesTaxFinancialAccountID = CRM_Lineitemedit_Util::getFinancialAccountId($this->_financialTypeID);
    $actualFinancialItemEntries = $this->getFinancialItemsByContributionID($this->_contributionID);
    $expectedFinancialItemEntries = array(
      array(
        'contact_id' => $this->_contactID,
        'description' => 'Contribution Amount',
        'amount' => 100.00,
        'financial_account_id' => $financialAccountID,
        'status_id' => CRM_Core_PseudoConstant::getKey('CRM_Financial_DAO_FinancialItem', 'status_id', 'Paid'),
      ),
      array(
        'contact_id' => $this->_contactID,
        'description' => 'Sales Tax',
        'amount' => 10.00,
        'financial_account_id' => $salesTaxFinancialAccountID,
        'status_id' => CRM_Core_PseudoConstant::getKey('CRM_Financial_DAO_FinancialItem', 'status_id', 'Paid'),
      ),
      array(
        'contact_id' => $this->_contactID,
        'description' => 'Contribution Amount',
        'amount' => -50.00,
        'financial_account_id' => $financialAccountID,
        'status_id' => CRM_Core_PseudoConstant::getKey('CRM_Financial_DAO_FinancialItem', 'status_id', 'Unpaid'),
      ),
      array(
        'contact_id' => $this->_contactID,
        'description' => 'Sales Tax',
        'amount' => -5.00,
        'financial_account_id' => $salesTaxFinancialAccountID,
        'status_id' => CRM_Core_PseudoConstant::getKey('CRM_Financial_DAO_FinancialItem', 'status_id', 'Unpaid'),
      ),
    );
    $this->checkArrayEqualsByAttributes($expectedFinancialItemEntries, $actualFinancialItemEntries);

    $actualFinancialTrxnEntries = $this->getFinancialTrxnsByContributionID($this->_contributionID);
    $expectedFinancialTrxnEntries = array(
      array(
        'total_amount' => 110.00,
        'net_amount' => 100.00, // @TODO this is suppose to be 110
        'is_payment' => 1,
        'payment_instrument_id' => $check,
        'status_id' => CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Completed'),
      ),
      array(
        'total_amount' => -55.00,
        'net_amount' => -55.00,
        'is_payment' => 0,
        'payment_instrument_id' => $check,
        'status_id' => CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Pending refund'),
      ),
    );
    $this->checkArrayEqualsByAttributes($expectedFinancialTrxnEntries, $actualFinancialTrxnEntries);
  }

  public function testLineTotalChangeWithPriceSet() {
    $priceFieldValues = $this->createPriceSet(array('financial_type_id' => $this->_financialTypeID));
    $priceFieldID = key($priceFieldValues);
    $contactID = $this->createDummyContact();
    $params = array(
      'total_amount' => 110,
      'fee_amount' => 0.00,
      'net_amount' => 110,
      'tax_amount' => 10,
      'financial_type_id' => $this->_financialTypeID,
      'receive_date' => '04/21/2015',
      'receive_date_time' => '11:27PM',
      'contact_id' => $contactID,
      'price_set_id' => $this->_priceSetID,
      'contribution_status_id' => CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Completed'),
      'price_' . $priceFieldID => array($priceFieldValues[$priceFieldID][0] => 1),
    );
    $form = new CRM_Contribute_Form_Contribution();
    $form->_priceSet = current(CRM_Price_BAO_PriceSet::getSetDetail($this->_priceSetID));
    $form->testSubmit($params, CRM_Core_Action::ADD);

    $contribution = $this->callAPISuccessGetSingle('Contribution', array('contact_id' => $contactID));

    // Contribution amount and status before LineItem edit
    $this->assertEquals('Completed', $contribution['contribution_status']);
    $this->assertEquals(110.00, $contribution['total_amount']);

    $form = new CRM_Lineitemedit_Form_Edit();
    $lineItemInfo = $this->callAPISuccessGetSingle('LineItem', array('contribution_id' => $contribution['id']));
    $lineItemInfo['qty'] += 1;
    $lineItemInfo['line_total'] = $lineItemInfo['unit_price'] *= $lineItemInfo['qty'];
    $lineItemInfo['tax_amount'] *= $lineItemInfo['qty'];
    $form->testSubmit($lineItemInfo);

    // Contribution amount and status after LineItem edit
    $contribution = $this->callAPISuccessGetSingle('Contribution', array('id' => $contribution['id']));
    $this->assertEquals('Partially paid', $contribution['contribution_status']);
    $this->assertEquals(220.00, $contribution['total_amount']);

    $check = CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'payment_instrument_id', 'Check');
    $financialAccountID = CRM_Contribute_PseudoConstant::getRelationalFinancialAccount($this->_financialTypeID, 'Income Account is');
    $salesTaxFinancialAccountID = CRM_Lineitemedit_Util::getFinancialAccountId($this->_financialTypeID);
    $actualFinancialItemEntries = $this->getFinancialItemsByContributionID($contribution['id']);
    $expectedFinancialItemEntries = array(
      array(
        'contact_id' => $contactID,
        'description' => 'Price Field 1',
        'amount' => 100.00,
        'financial_account_id' => $financialAccountID,
        'status_id' => CRM_Core_PseudoConstant::getKey('CRM_Financial_DAO_FinancialItem', 'status_id', 'Paid'),
      ),
      array(
        'contact_id' => $contactID,
        'description' => 'Sales Tax',
        'amount' => 10.00,
        'financial_account_id' => $salesTaxFinancialAccountID,
        'status_id' => CRM_Core_PseudoConstant::getKey('CRM_Financial_DAO_FinancialItem', 'status_id', 'Paid'),
      ),
      array(
        'contact_id' => $contactID,
        'description' => '2.00 of Price Field 1',
        'amount' => 100.00,
        'financial_account_id' => $financialAccountID,
        'status_id' => CRM_Core_PseudoConstant::getKey('CRM_Financial_DAO_FinancialItem', 'status_id', 'Unpaid'),
      ),
      array(
        'contact_id' => $contactID,
        'description' => 'Sales Tax',
        'amount' => 10.00,
        'financial_account_id' => $salesTaxFinancialAccountID,
        'status_id' => CRM_Core_PseudoConstant::getKey('CRM_Financial_DAO_FinancialItem', 'status_id', 'Unpaid'),
      ),
    );
    $this->checkArrayEqualsByAttributes($expectedFinancialItemEntries, $actualFinancialItemEntries);

    $actualFinancialTrxnEntries = $this->getFinancialTrxnsByContributionID($contribution['id']);
    $expectedFinancialTrxnEntries = array(
      array(
        'total_amount' => 110.00,
        'net_amount' => 110.00,
        'is_payment' => 1,
        'payment_instrument_id' => $check,
        'status_id' => CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Completed'),
      ),
      array(
        'total_amount' => 110.00,
        'net_amount' => 110.00,
        'is_payment' => 0,
        'payment_instrument_id' => $check,
        'status_id' => CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Partially paid'),
      ),
    );
    $this->checkArrayEqualsByAttributes($expectedFinancialTrxnEntries, $actualFinancialTrxnEntries);
  }

}
