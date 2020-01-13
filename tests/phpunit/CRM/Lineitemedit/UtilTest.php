<?php

use CRM_Lineitemedit_ExtensionUtil as E;
use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;

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
class CRM_Lineitemedit_UtilTest extends \PHPUnit\Framework\TestCase implements HeadlessInterface, HookInterface {

  use Civi\Test\Api3TestTrait;
  use Civi\Test\ContactTestTrait;

  public function setUpHeadless() {
    // Civi\Test has many helpers, like install(), uninstall(), sql(), and sqlFile().
    // See: https://docs.civicrm.org/dev/en/latest/testing/phpunit/#civitest
    return \Civi\Test::headless()
      ->installMe(__DIR__)
      ->apply();
  }

  public function setUp() {
    parent::setUp();
    $this->_individualId = $this->individualCreate();
    $paramsSet['title'] = 'Price Set' . substr(sha1(rand()), 0, 7);
    $paramsSet['name'] = CRM_Utils_String::titleToVar($paramsSet['title']);
    $paramsSet['is_active'] = TRUE;
    $paramsSet['financial_type_id'] = 'Donation';
    $paramsSet['extends'] = 1;
    $priceSet = $this->callAPISuccess('price_set', 'create', $paramsSet);
    $this->_priceSetId = $priceSet['id'];
    $this->_financialTypeId = $this->callAPISuccess('FinancialType', 'get', ['name' => 'Donation'])['id'];
    $paramsField = [
      'label' => 'Price Field',
      'name' => CRM_Utils_String::titleToVar('Price Field'),
      'html_type' => 'CheckBox',
      'option_label' => ['1' => 'Price Field 1'],
      'option_value' => ['1' => 100],
      'option_name' => ['1' => 'Price Field 1'],
      'option_weight' => ['1' => 1],
      'option_amount' => ['1' => 100],
      'is_display_amounts' => 1,
      'weight' => 1,
      'options_per_line' => 1,
      'is_active' => ['1' => 1],
      'price_set_id' => $this->_priceSetId,
      'is_enter_qty' => 1,
      'financial_type_id' => $this->_financialTypeId,
    ];
    $priceField = CRM_Price_BAO_PriceField::create($paramsField);
    $this->_priceFieldId = $priceField->id;
    $priceFields = $this->callAPISuccess('PriceFieldValue', 'get', ['price_field_id' => $priceField->id]);
    $p = [
      'contact_id' => $this->_individualId,
      'receive_date' => '2010-01-20',
      'total_amount' => 100,
      'financial_type_id' => $this->_financialTypeId,
      'contribution_status_id' => 'Pending',
    ];
    foreach ($priceFields['values'] as $key => $priceField) {
      $lineItems[1][$key] = [
        'price_field_id' => $priceField['price_field_id'],
        'price_field_value_id' => $priceField['id'],
        'label' => $priceField['label'],
        'field_title' => $priceField['label'],
        'qty' => 1,
        'unit_price' => $priceField['amount'],
        'line_total' => $priceField['amount'],
        'financial_type_id' => $priceField['financial_type_id'],
      ];
    }
    $p['line_item'] = $lineItems;
    $this->_order = $this->callAPISuccess('Order', 'create', $p);
  }

  public function tearDown() {
    parent::tearDown();
    $this->callAPISuccess('Contribution', 'delete', ['id' => $this->_order['id']]);
    $priceOptions = $this->callAPISuccess('PriceFieldValue', 'get', ['price_field_id' => $this->_priceFieldId]);
    foreach ($priceOptions['values'] as $priceOption) {
      $this->callAPISuccess('PriceFieldValue', 'delete', ['id' => $priceOption['id']]);
    }
    $this->callAPISuccess('PriceField', 'delete', ['id' => $this->_priceFieldId]);
    $this->callAPISuccess('PriceSet', 'delete', ['id' => $this->_priceSetId]);
    $this->callAPISuccess('Contact', 'delete', ['id' => $this->_individualId, 'skip_undelete' => TRUE]);
    
  }

  public function testGeneratePriceField() {
    CRM_Financial_BAO_FinancialType::setIsActive($this->_financialTypeId, 0);
    $this->callAPISuccess('System', 'flush', []);
    $result = CRM_Lineitemedit_Util::createPriceFieldByContributionID($this->_order['id']);
    $newPriceField = $this->callAPISuccess('PriceField', 'getsingle', ['id' => $result[0]]);
    $this->assertEquals('additional_lineitem_1', $newPriceField['name']);
    $newPriceFieldValue = $this->callAPISuccess('PriceFieldValue', 'getsingle', ['id' => $result[1]]);
    $this->assertEquals('additional_lineitem_1', $newPriceFieldValue['name']);
    $this->assertEquals($this->_financialTypeId, $newPriceFieldValue['financial_type_id']);
    CRM_Financial_BAO_FinancialType::setIsActive($this->_financialTypeId, 1);
    $this->callAPISuccess('System', 'flush', []);
    $this->callAPISuccess('PriceFieldValue', 'delete', ['id' => $newPriceFieldValue['id']]);
    $this->callAPISuccess('PriceField', 'delete', ['id' => $newPriceField['id']]);
  }

}
