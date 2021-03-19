<?php
require_once 'BaseTest.php';

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
class CRM_Lineitemedit_Form_CancelTest extends CRM_Lineitemedit_Form_BaseTest {

  public function setUpHeadless() {
    return \Civi\Test::headless()
      ->installMe(__DIR__)
      ->apply();
  }

  public function setUp(): void {
    parent::setUp();
  }

  public function tearDown(): void {
    parent::tearDown();
  }

  public function testLineitemCancel(): void {
    // Contribution amount and status before LineItem cancel
    $this->assertEquals('Completed', $this->_contribution['contribution_status']);
    $this->assertEquals(100.00, $this->_contribution['total_amount']);

    $form = new CRM_Lineitemedit_Form_Cancel();
    $id = CRM_Core_DAO::getFieldValue('CRM_Price_BAO_LineItem', $this->_contributionID, 'id', 'contribution_id');
    $form->testSubmit($id);

    // Contribution amount and status after LineItem cancel
    $contribution = $this->callAPISuccessGetSingle('Contribution', array('id' => $this->_contributionID));
    $this->assertEquals('Pending refund', $contribution['contribution_status']);
    $this->assertEquals(0.00, $contribution['total_amount']);

    $actualFinancialItemEntries = $this->getFinancialItemsByLineItemID($id);
    $expectedFinancialItemEntries = array(
      array(
        'contact_id' => $this->_contactID,
        'description' => 'Contribution Amount',
        'amount' => 100.00,
        'status_id' => CRM_Core_PseudoConstant::getKey('CRM_Financial_DAO_FinancialItem', 'status_id', 'Paid'),
      ),
      array(
        'contact_id' => $this->_contactID,
        'description' => 'Contribution Amount',
        'amount' => -100.00,
        'status_id' => CRM_Core_PseudoConstant::getKey('CRM_Financial_DAO_FinancialItem', 'status_id', 'Unpaid'),
      ),
    );
    $this->checkArrayEqualsByAttributes($expectedFinancialItemEntries, $actualFinancialItemEntries);

    $check = CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'payment_instrument_id', 'Check');
    $actualFinancialTrxnEntries = $this->getFinancialTrxnsByLineItemID($id);
    $expectedFinancialTrxnEntries = array(
      array(
        'total_amount' => 100.00,
        'net_amount' => 100.00,
        'is_payment' => 1,
        'payment_instrument_id' => $check,
        'status_id' => CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Completed'),
      ),
      array(
        'total_amount' => -100.00,
        'net_amount' => -100.00,
        'is_payment' => 0,
        'payment_instrument_id' => $check,
        'status_id' => CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Pending refund'),
      ),
    );
    $this->checkArrayEqualsByAttributes($expectedFinancialTrxnEntries, $actualFinancialTrxnEntries);
  }

  public function testLineitemCancelWithPriceSet(): void {
    $priceFieldValues = $this->createPriceSet();
    $priceFieldID = key($priceFieldValues);
    $contactID = $this->createDummyContact();
    $check = CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'payment_instrument_id', 'Check');
    $params = array(
      'total_amount' => 100,
      'financial_type_id' => CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'financial_type_id', 'Donation'),
      'receive_date' => '2015-04-21 23:27:00',
      'contact_id' => $contactID,
      'price_set_id' => $this->_priceSetID,
      'contribution_status_id' => CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Completed'),
      'price_' . $priceFieldID => array(
        $priceFieldValues[$priceFieldID][0] => 1,
        $priceFieldValues[$priceFieldID][1] => 1,
      ),
    );
    $form = new CRM_Contribute_Form_Contribution();
    $form->_priceSet = current(CRM_Price_BAO_PriceSet::getSetDetail($this->_priceSetID));
    $form->testSubmit($params, CRM_Core_Action::ADD);

    $contribution = $this->callAPISuccessGetSingle('Contribution', array('contact_id' => $contactID));

    // Contribution amount and status before LineItem cancel
    $this->assertEquals('Completed', $contribution['contribution_status']);
    $this->assertEquals(300.00, $contribution['total_amount']);

    // fetch one of the line-item of amount $100 to cancel
    $form = new CRM_Lineitemedit_Form_Cancel();
    $id = $this->callAPISuccessGetValue('LineItem', array(
      'contribution_id' => $contribution['id'],
      'price_field_value_id' => $priceFieldValues[$priceFieldID][0],
      'return' => 'id',
    ));
    $form->testSubmit($id);

    // Contribution amount and status after LineItem edit
    $contribution = $this->callAPISuccessGetSingle('Contribution', array('id' => $contribution['id']));
    $this->assertEquals('Pending refund', $contribution['contribution_status']);
    $this->assertEquals(200.00, $contribution['total_amount']);

    $actualFinancialItemEntries = $this->getFinancialItemsByLineItemID($id);
    $expectedFinancialItemEntries = array(
      array(
        'contact_id' => $contactID,
        'description' => 'Price Field 1',
        'amount' => 100.00,
        'status_id' => CRM_Core_PseudoConstant::getKey('CRM_Financial_DAO_FinancialItem', 'status_id', 'Paid'),
      ),
      array(
        'contact_id' => $contactID,
        'description' => 'Price Field 1',
        'amount' => -100.00,
        'status_id' => CRM_Core_PseudoConstant::getKey('CRM_Financial_DAO_FinancialItem', 'status_id', 'Unpaid'),
      ),
    );
    $this->checkArrayEqualsByAttributes($expectedFinancialItemEntries, $actualFinancialItemEntries);

    $actualFinancialTrxnEntries = $this->getFinancialTrxnsByLineItemID($id);
    $expectedFinancialTrxnEntries = array(
      array(
        'total_amount' => 300.00,
        'net_amount' => 0.00,
        'net_amount' => 300.00,
        'is_payment' => 1,
        'payment_instrument_id' => $check,
        'status_id' => CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Completed'),
      ),
      array(
        'total_amount' => -100.00,
        'net_amount' => 0.00,
        'net_amount' => -100.00,
        'is_payment' => 0,
        'payment_instrument_id' => $check,
        'status_id' => CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Pending refund'),
      ),
    );
    $this->checkArrayEqualsByAttributes($expectedFinancialTrxnEntries, $actualFinancialTrxnEntries);
  }

}
