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
class CRM_Lineitemedit_Form_AddTest extends CRM_Lineitemedit_Form_BaseTest {

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

  public function testLineItemAdditionOnBackofficeForm() {
    $contactID = $this->createDummyContact();
    $form = new CRM_Contribute_Form_Contribution();

    $pvIDs = [];
    $itemLabels = [
      'Contribution Amount' . substr(sha1(rand()), 0, 4),
      'Additional Amount 1' . substr(sha1(rand()), 0, 4),
      'Additional Amount 2' . substr(sha1(rand()), 0, 4),
      'Additional Amount 3' . substr(sha1(rand()), 0, 4),
    ];
    $unitQuantities = [1.00, 2.00, 3.00, 4.00];
    $unitPrices = [1.00, 2.00, 3.00, 4.00];
    $lineTotals = [1.00, 4.00, 9.00, 16.00];
    $financialTypes = [
      CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'financial_type_id', 'Donation'),
      CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'financial_type_id', 'Campaign Contribution'),
      CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'financial_type_id', 'Event Fee'),
      CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'financial_type_id', 'Member Dues'),
    ];
    $expectedTotalAmount = array_sum($lineTotals);
    for ($rowNumber = 0; $rowNumber <= 3; $rowNumber++) {
      if ($rowNumber == 0) {
        $pvIDs[$rowNumber] = $this->callAPISuccess('PriceFieldValue', 'get', ['name' => 'contribution_amount'])['id'];
      }
      else {
        $pvIDs[$rowNumber] = $this->callAPISuccess('PriceFieldValue', 'get', ['name' => 'additional_item_' . $rowNumber])['id'];
      }
    }

    $form->testSubmit(array(
      'total_amount' => 0,
      'financial_type_id' => $financialTypes[0],
      'contact_id' => $contactID,
      'payment_instrument_id' => CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'payment_instrument_id', 'Check'),
      'contribution_status_id' => 1,
      'item_price_field_value_id' => $pvIDs,
      'item_label' => $itemLabels,
      'item_financial_type_id' => $financialTypes,
      'item_qty' => $unitQuantities,
      'item_unit_price' => $unitPrices,
      'item_line_total' => $lineTotals,
    ),
      CRM_Core_Action::ADD);
    $contribution = $this->callAPISuccessGetSingle('Contribution', array('contact_id' => $contactID));

    $this->assertEquals($expectedTotalAmount, $contribution['total_amount']);
    $this->assertEquals($expectedTotalAmount, $contribution['net_amount']);

    for ($rowNumber = 0; $rowNumber <= 3; $rowNumber++) {
      $lineItem = $this->callAPISuccessGetSingle('LineItem', array('contribution_id' => $contribution['id'], 'price_field_value_id' => $pvIDs[$rowNumber]));
      $this->assertEquals($unitQuantities[$rowNumber], $lineItem['qty']);
      $this->assertEquals($itemLabels[$rowNumber], $lineItem['label']);
      $this->assertEquals($financialTypes[$rowNumber], $lineItem['financial_type_id']);
      $this->assertEquals($unitPrices[$rowNumber], $lineItem['unit_price']);
      $this->assertEquals($lineTotals[$rowNumber], $lineItem['line_total']);
    }
  }

  public function testLineTotalChangeWithPriceSet() {
    $priceFieldValues = $this->createPriceSet();
    $priceFieldID = key($priceFieldValues);
    $contactID = $this->createDummyContact();
    $check = CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'payment_instrument_id', 'Check');
    $params = array(
      'total_amount' => 100,
      'financial_type_id' => CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'financial_type_id', 'Donation'),
      'receive_date' => date('Ymd'),
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
    $this->assertEquals(100.00, $contribution['total_amount']);

    $form = new CRM_Lineitemedit_Form_Add();
    $lineItemInfo = $this->callAPISuccessGetSingle('LineItem', array('contribution_id' => $contribution['id']));
    $lineItemInfo['line_total'] = $lineItemInfo['unit_price'] += 100;
    $lineItemInfo['price_field_value_id'] = $priceFieldValues[$priceFieldID][1];
    $lineItemInfo['label'] = 'Price Field 2';
    $form->testSubmit($lineItemInfo);

    // Now select price option that has 0 amount
    $form = new CRM_Lineitemedit_Form_Add();
    $lineItemInfo = $this->callAPISuccessGetSingle('LineItem', array('contribution_id' => $contribution['id'], 'options' => ['sort' => 'id DESC', 'limit' => 1]));
    $lineItemInfo['line_total'] = $lineItemInfo['unit_price'] = 0;
    $lineItemInfo['price_field_value_id'] = $priceFieldValues[$priceFieldID][2];
    $lineItemInfo['label'] = 'Price Field 3';
    $form->testSubmit($lineItemInfo);

    // Contribution amount and status after LineItem edit
    $contribution = $this->callAPISuccessGetSingle('Contribution', array('id' => $contribution['id']));
    $this->assertEquals('Partially paid', $contribution['contribution_status']);
    $this->assertEquals(300.00, $contribution['total_amount']);

    $actualFinancialItemEntries = $this->getFinancialItemsByContributionID($contribution['id']);
    $expectedFinancialItemEntries = array(
      array(
        'contact_id' => $contactID,
        'description' => 'Price Field 1',
        'amount' => 100.00,
        'status_id' => CRM_Core_PseudoConstant::getKey('CRM_Financial_DAO_FinancialItem', 'status_id', 'Paid'),
      ),
      array(
        'contact_id' => $contactID,
        'description' => 'Price Field 2',
        'amount' => 200.00,
        'status_id' => CRM_Core_PseudoConstant::getKey('CRM_Financial_DAO_FinancialItem', 'status_id', 'Unpaid'),
      ),
      array(
        'contact_id' => $contactID,
        'description' => 'Price Field 3',
        'amount' => 0.00,
        'status_id' => CRM_Core_PseudoConstant::getKey('CRM_Financial_DAO_FinancialItem', 'status_id', 'Unpaid'),
      ),
    );
    $this->checkArrayEqualsByAttributes($expectedFinancialItemEntries, $actualFinancialItemEntries);

    $actualFinancialTrxnEntries = $this->getFinancialTrxnsByContributionID($contribution['id']);
    $expectedFinancialTrxnEntries = array(
      array(
        'total_amount' => 100.00,
        'net_amount' => 100.00,
        'is_payment' => 1,
        'payment_instrument_id' => $check,
        'status_id' => CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Completed'),
      ),
      array(
        'total_amount' => 200.00,
        'net_amount' => 200.00,
        'is_payment' => 0,
        'payment_instrument_id' => $check,
        'status_id' => CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Partially paid'),
      ),
      array(
        'total_amount' => 0.00,
        'net_amount' => 0.00,
        'is_payment' => 0,
        'payment_instrument_id' => $check,
        'status_id' => CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Partially paid'),
      ),
    );
    $this->checkArrayEqualsByAttributes($expectedFinancialTrxnEntries, $actualFinancialTrxnEntries);
  }

}
