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
class CRM_Lineitemedit_Form_SaleTax_AddTest extends CRM_Lineitemedit_Form_BaseTest {
  public function setUpHeadless() {
    // Civi\Test has many helpers, like install(), uninstall(), sql(), and sqlFile().
    // See: https://github.com/civicrm/org.civicrm.testapalooza/blob/master/civi-test.md
    return \Civi\Test::headless()
      ->installMe(__DIR__)
      ->apply();
  }

  public function setUp() {
    $this->_createContri = FALSE;
    parent::setUp();
  }

  public function tearDown() {
    $this->disableTaxAndInvoicing();
    parent::tearDown();
  }

  public function testLineTotalChangeWithPriceSet() {
    $contactId = $this->createDummyContact();
    $this->enableTaxAndInvoicing();
    $FTname = 'Financial-Type -' . substr(sha1(rand()), 0, 7);
    $financialType = $this->createFinancialType(array('name' => $FTname));
    $financialAccount = $this->relationForFinancialTypeWithFinancialAccount($financialType['id']);
    $priceFieldValues = $this->createPriceSet(array('financial_type_id' => $financialType['id']));
    $priceFieldID = key($priceFieldValues);

    $contactID = $this->createDummyContact();
    $check = CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'payment_instrument_id', 'Check');
    $params = array(
      'total_amount' => 110,
      'fee_amount' => 0.00,
      'net_amount' => 110,
      'tax_amount' => 10,
      'financial_type_id' => $financialType['id'],
      'receive_date' => '04/21/2015',
      'receive_date_time' => '11:27PM',
      'contact_id' => $contactID,
      'price_set_id' => $this->_priceSetID,
      'payment_instrument_id' => $check,
      'contribution_status_id' => CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Completed'),
      'price_' . $priceFieldID => array($priceFieldValues[$priceFieldID][0] => 1),
    );
    $form = new CRM_Contribute_Form_Contribution();
    $form->_priceSet = current(CRM_Price_BAO_PriceSet::getSetDetail($this->_priceSetID));
    $form->testSubmit($params, CRM_Core_Action::ADD);

    $contribution = $this->callAPISuccessGetSingle('Contribution', array('contact_id' => $contactID));

    // Contribution amount and status before LineItem add
    $this->assertEquals('Completed', $contribution['contribution_status']);
    $this->assertEquals(110.00, $contribution['total_amount']);

    $form = new CRM_Lineitemedit_Form_Add();
    $lineItemInfo = $this->callAPISuccessGetSingle('LineItem', array('contribution_id' => $contribution['id']));
    $lineItemInfo['line_total'] = $lineItemInfo['unit_price'] += 100;
    $lineItemInfo['tax_amount'] += 10;
    $lineItemInfo['price_field_value_id'] = $priceFieldValues[$priceFieldID][1];
    $form->testSubmit($lineItemInfo);

    // Contribution amount and status after LineItem edit
    $contribution = $this->callAPISuccessGetSingle('Contribution', array('id' => $contribution['id']));
    $this->assertEquals('Partially paid', $contribution['contribution_status']);
    $this->assertEquals(330.00, $contribution['total_amount']);

    $financialAccountID = CRM_Contribute_PseudoConstant::getRelationalFinancialAccount($financialType['id'], 'Income Account is');
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
        'financial_account_id' => CRM_Lineitemedit_Util::getFinancialAccountId($financialType['id']),
        'status_id' => CRM_Core_PseudoConstant::getKey('CRM_Financial_DAO_FinancialItem', 'status_id', 'Paid'),
      ),
      array(
        'contact_id' => $contactID,
        'description' => 'Price Field 1',
        'amount' => 200.00,
        'financial_account_id' => $financialAccountID,
        'status_id' => CRM_Core_PseudoConstant::getKey('CRM_Financial_DAO_FinancialItem', 'status_id', 'Unpaid'),
      ),
      array(
        'contact_id' => $contactID,
        'description' => 'Sales Tax',
        'amount' => 20.00,
        'financial_account_id' => CRM_Lineitemedit_Util::getFinancialAccountId($financialType['id']),
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
        'total_amount' => 220.00,
        'net_amount' => 220.00,
        'is_payment' => 0,
        'payment_instrument_id' => $check,
        'status_id' => CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Partially paid'),
      ),
    );
    $this->checkArrayEqualsByAttributes($expectedFinancialTrxnEntries, $actualFinancialTrxnEntries);
  }

  /**
   * Function to create contribution with tax.
   */
  public function createContributionWithTax() {
    $contactId = $this->createDummyContact();
    $this->enableTaxAndInvoicing();
    $financialType = $this->createFinancialType();
    $financialAccount = $this->relationForFinancialTypeWithFinancialAccount($financialType['id']);
    $form = new CRM_Contribute_Form_Contribution();

    $form->testSubmit(array(
       'total_amount' => 100,
        'financial_type_id' => $financialType['id'],
        'receive_date' => '04/21/2015',
        'receive_date_time' => '11:27PM',
        'contact_id' => $contactId,
        'contribution_status_id' => 1,
        'price_set_id' => 0,
      ),
      CRM_Core_Action::ADD
    );
    $contribution = $this->callAPISuccessGetSingle('Contribution',
      array(
        'contact_id' => $contactId,
        'return' => array('tax_amount', 'total_amount'),
      )
    );
    return array($contribution, $financialAccount);
  }

}
