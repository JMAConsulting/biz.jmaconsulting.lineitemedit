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
class CRM_Lineitemedit_Form_EditTest extends CRM_Lineitemedit_Form_BaseTest {

  public function setUpHeadless() {
    // Civi\Test has many helpers, like install(), uninstall(), sql(), and sqlFile().
    // See: https://github.com/civicrm/org.civicrm.testapalooza/blob/master/civi-test.md
    return \Civi\Test::headless()
      ->installMe(__DIR__)
      ->apply();
  }

  public function setUp() {
    parent::setUp();
  }

  public function tearDown() {
    parent::tearDown();
  }

  /**
   * Example: Test that a version is returned.
   */
  public function testLineTotalIncrease() {
    // Contribution amount and status before LineItem edit
    $this->assertEquals('Completed', $this->_contribution['contribution_status']);
    $this->assertEquals(100.00, $this->_contribution['total_amount']);

    $form = new CRM_Lineitemedit_Form_Edit();
    $lineItemInfo = $this->callAPISuccessGetSingle('LineItem', array('contribution_id' => $this->_contributionID));
    $lineItemInfo['qty'] += 2; // increase lineitem qty to 3
    $lineItemInfo['line_total'] *= $lineItemInfo['qty'];
    $form->testSubmit($lineItemInfo);

    // Contribution amount and status after LineItem edit
    $contribution = $this->callAPISuccessGetSingle('Contribution', array('id' => $this->_contributionID));
    $this->assertEquals('Partially paid', $contribution['contribution_status']);
    $this->assertEquals(300.00, $contribution['total_amount']);
  }

  /**
   * Example: Test that a version is returned.
   */
  public function testLineTotalDecrease() {
    // Contribution amount and status before LineItem edit
    $this->assertEquals('Completed', $this->_contribution['contribution_status']);
    $this->assertEquals(100.00, $this->_contribution['total_amount']);

    $form = new CRM_Lineitemedit_Form_Edit();
    $lineItemInfo = $this->callAPISuccessGetSingle('LineItem', array(
      'contribution_id' => $this->_contributionID,
    ));
    $lineItemInfo['line_total'] = $lineItemInfo['unit_price'] -= 50;
    $form->testSubmit($lineItemInfo);

    // Contribution amount and status after LineItem edit
    $contribution = $this->callAPISuccessGetSingle('Contribution', array('id' => $this->_contributionID));
    $this->assertEquals('Pending refund', $contribution['contribution_status']);
    $this->assertEquals(50.00, $contribution['total_amount']);
  }

  public function testLineTotalChangeWithPriceSet() {
    $priceFieldValues = $this->createPriceSet();
    $priceFieldID = key($priceFieldValues);
    $contactID = $this->createDummyContact();
    $params = array(
      'total_amount' => 100,
      'financial_type_id' => CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'financial_type_id', 'Donation'),
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
    $this->assertEquals(100.00, $contribution['total_amount']);

    $form = new CRM_Lineitemedit_Form_Edit();
    $lineItemInfo = $this->callAPISuccessGetSingle('LineItem', array('contribution_id' => $contribution['id']));
    $lineItemInfo['line_total'] = $lineItemInfo['unit_price'] += 100;
    $lineItemInfo['qty'] += 1;
    $form->testSubmit($lineItemInfo);

    // Contribution amount and status after LineItem edit
    $contribution = $this->callAPISuccessGetSingle('Contribution', array('id' => $contribution['id']));
    $this->assertEquals('Partially paid', $contribution['contribution_status']);
    $this->assertEquals(200.00, $contribution['total_amount']);
  }

}
