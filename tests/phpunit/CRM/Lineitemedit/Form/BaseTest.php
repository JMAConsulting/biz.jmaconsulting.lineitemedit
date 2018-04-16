<?php

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
class CRM_Lineitemedit_Form_BaseTest extends \PHPUnit_Framework_TestCase implements HeadlessInterface, HookInterface, TransactionalInterface {

  protected $_contactID;
  protected $_contributionID;
  protected $_contribution;
  protected $_priceSetID;
  protected $_createContri = TRUE;

  public function setUpHeadless() {
    // Civi\Test has many helpers, like install(), uninstall(), sql(), and sqlFile().
    // See: https://github.com/civicrm/org.civicrm.testapalooza/blob/master/civi-test.md
    return \Civi\Test::headless()
      ->installMe(__DIR__)
      ->apply();
  }

  public function setUp() {
    parent::setUp();
    $this->createContact();
    if ($this->_createContri) {
      $this->createContribution();
    }
  }

  public function tearDown() {
    parent::tearDown();
  }

  /**
   * wrap api functions.
   * so we can ensure they succeed & throw exceptions without litterering the test with checks
   *
   * @param string $entity
   * @param string $action
   * @param array $params
   * @param mixed $checkAgainst
   *   Optional value to check result against, implemented for getvalue,.
   *   getcount, getsingle. Note that for getvalue the type is checked rather than the value
   *   for getsingle the array is compared against an array passed in - the id is not compared (for
   *   better or worse )
   *
   * @return array|int
   */
  public function callAPISuccess($entity, $action, $params, $checkAgainst = NULL) {
    $params = array_merge(array(
        'debug' => 1,
      ),
      $params
    );
    switch (strtolower($action)) {
      case 'getvalue':
        return $this->callAPISuccessGetValue($entity, $params, $checkAgainst);

      case 'getsingle':
        return $this->callAPISuccessGetSingle($entity, $params, $checkAgainst);

      case 'getcount':
        return $this->callAPISuccessGetCount($entity, $params, $checkAgainst);
    }
    $result = civicrm_api3($entity, $action, $params);
    return $result;
  }

  public function callAPISuccessGetValue($entity, $params, $type = NULL) {
    $params += array(
      'debug' => 1,
    );
    $result = civicrm_api3($entity, 'getvalue', $params);
    if ($type) {
      if ($type == 'integer') {
        // api seems to return integers as strings
        $this->assertTrue(is_numeric($result), "expected a numeric value but got " . print_r($result, 1));
      }
      else {
        $this->assertType($type, $result, "returned result should have been of type $type but was ");
      }
    }
    return $result;
  }

  public function callAPISuccessGetSingle($entity, $params, $checkAgainst = NULL) {
    $params += array(
      'debug' => 1,
    );
    $result = civicrm_api3($entity, 'getsingle', $params);
    if (!is_array($result) || !empty($result['is_error']) || isset($result['values'])) {
      throw new Exception('Invalid getsingle result' . print_r($result, TRUE));
    }
    if ($checkAgainst) {
      // @todo - have gone with the fn that unsets id? should we check id?
      $this->checkArrayEquals($result, $checkAgainst);
    }
    return $result;
  }

  public function callAPISuccessGetCount($entity, $params, $count = NULL) {
    $params += array(
      'debug' => 1,
    );
    $result = $this->civicrm_api3($entity, 'getcount', $params);
    if (!is_int($result) || !empty($result['is_error']) || isset($result['values'])) {
      throw new Exception('Invalid getcount result : ' . print_r($result, TRUE) . " type :" . gettype($result));
    }
    if (is_int($count)) {
      $this->assertEquals($count, $result, "incorrect count returned from $entity getcount");
    }
    return $result;
  }

  /**
   * Create contact.
   */
  public function createContact() {
    if (!empty($this->_contactID)) {
      return;
    }
    $results = $this->callAPISuccess('Contact', 'create', array(
      'contact_type' => 'Individual',
      'first_name' => 'Jose',
      'last_name' => 'Lopez',
    ));
    $this->_contactID = $results['id'];
  }

  /**
   * Create dummy contact.
   */
  public function createDummyContact() {
    $results = $this->callAPISuccess('Contact', 'create', array(
      'contact_type' => 'Individual',
      'first_name' => 'Adam' . substr(sha1(rand()), 0, 7),
      'last_name' => 'Cooper' . substr(sha1(rand()), 0, 7),
    ));

    return $results['id'];
  }

  /**
   * Create contact.
   */
  public function createContribution($params = array()) {
    if (empty($this->_contactID)) {
      $this->createContact();
    }

    $p = array_merge(array(
      'contact_id' => $this->_contactID,
      'receive_date' => '2010-01-20',
      'total_amount' => 100.00,
      'financial_type_id' => CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'financial_type_id', 'Donation'),
      'non_deductible_amount' => 10.00,
      'fee_amount' => 0.00,
      'net_amount' => 100.00,
      'trxn_id' => 23456,
      'invoice_id' => 78910,
      'source' => 'SSF',
      'contribution_status_id' => 1,
    ), $params);
    $contribution = $this->callAPISuccess('contribution', 'create', $p);
    $this->_contributionID = $contribution['id'];
    $this->_contribution = $this->callAPISuccessGetSingle('Contribution', array('id' => $this->_contributionID));
  }

  protected function createPriceSet($priceFieldOptions = array()) {
    $paramsSet['title'] = 'Price Set' . substr(sha1(rand()), 0, 7);
    $paramsSet['name'] = CRM_Utils_String::titleToVar($paramsSet['title']);
    $paramsSet['is_active'] = TRUE;
    $paramsSet['financial_type_id'] = 4;
    $paramsSet['extends'] = 2;
    $priceSet = $this->callAPISuccess('price_set', 'create', $paramsSet);
    $this->_priceSetID = $priceSet['id'];

    $paramsField = array_merge(array(
      'label' => 'Price Field',
       'name' => CRM_Utils_String::titleToVar('Price Field'),
       'html_type' => 'CheckBox',
       'option_label' => array('1' => 'Price Field 1', '2' => 'Price Field 2', '3' => 'Price Field 3'),
       'option_value' => array('1' => 100, '2' => 200, '3' => 0),
       'option_name' => array('1' => 'Price Field 1', '2' => 'Price Field 2'),
       'option_weight' => array('1' => 1, '2' => 2, '3' => 3),
       'option_amount' => array('1' => 100, '2' => 200, '3' => 0),
       'is_display_amounts' => 1,
       'weight' => 1,
       'options_per_line' => 1,
       'is_active' => array('1' => 1, '2' => 1, '3' => 1),
       'price_set_id' => $this->_priceSetID,
       'is_enter_qty' => 1,
       'financial_type_id' => CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'financial_type_id', 'Donation'),
    ), $priceFieldOptions);

    $priceField = CRM_Price_BAO_PriceField::create($paramsField);
    $result = $this->callAPISuccess('PriceFieldValue', 'get', array('price_field_id' => $priceField->id));

    $priceFieldOptions = array($priceField->id => array());
    foreach (array_keys($result['values']) as $pfvid) {
      $priceFieldOptions[$priceField->id][] = $pfvid;
    }

    return $priceFieldOptions;
  }

  public function getFinancialItemsByLineItemID($lineItemID) {
    $result = $this->callAPISuccess('FinancialItem', 'get', array(
     'entity_table' => 'civicrm_line_item',
     'entity_id' => $lineItemID,
    ));

    return array_values($result['values']);
  }

  public function getFinancialItemsByContributionID($contributionID) {
    $sql = "SELECT fi.*
    FROM civicrm_financial_item fi
    INNER JOIN civicrm_line_item li ON li.id = fi.entity_id AND fi.entity_table = 'civicrm_line_item'
    WHERE li.contribution_id = {$contributionID}
    ORDER BY fi.id ASC
    ";

    return CRM_Core_DAO::executeQuery($sql)->fetchAll();
  }

  public function getFinancialTrxnsByLineItemID($lineItemID) {
    $sql = "SELECT ft.*, li.id as lineitem_id
    FROM civicrm_financial_trxn ft
    INNER JOIN civicrm_entity_financial_trxn eft ON eft.financial_trxn_id = ft.id
    INNER JOIN civicrm_line_item li ON eft.entity_id = li.contribution_id AND eft.entity_table = 'civicrm_contribution'
    WHERE li.id = {$lineItemID}
    ORDER BY ft.id ASC
    ";

    return CRM_Core_DAO::executeQuery($sql)->fetchAll();
  }

  public function getFinancialTrxnsByContributionID($contributionID) {
    $sql = "SELECT ft.*
    FROM civicrm_financial_trxn ft
    INNER JOIN civicrm_entity_financial_trxn eft ON eft.financial_trxn_id = ft.id
    WHERE eft.entity_table = 'civicrm_contribution' AND eft.entity_id = {$contributionID}
    ORDER BY ft.id ASC
    ";

    return CRM_Core_DAO::executeQuery($sql)->fetchAll();
  }

  public function checkArrayEqualsByAttributes($expectedEntries, $actualEntries) {
    foreach ($expectedEntries as $key => $expectedEntry) {
      foreach ($expectedEntry as $attribute => $expectedValue) {
        $this->assertEquals($actualEntries[$key][$attribute], $expectedValue, "mismatch found for $attribute attribute");
      }
    }
  }

  protected function createFinancialType($params = array()) {
    $params = array_merge($params,
     array(
       'name' => 'Financial-Type -' . substr(sha1(rand()), 0, 7),
       'is_active' => 1,
     )
    );
    return $this->callAPISuccess('FinancialType', 'create', $params);
  }

  /**
   * Add Sales Tax relation for financial type with financial account.
   *
   * @param int $financialTypeId
   *
   * @return obj
   */
  protected function relationForFinancialTypeWithFinancialAccount($financialTypeId) {
    $params = array(
      'name' => 'Sales tax account ' . substr(sha1(rand()), 0, 4),
      'financial_account_type_id' => key(CRM_Core_PseudoConstant::accountOptionValues('financial_account_type', NULL, " AND v.name LIKE 'Liability' ")),
      'is_deductible' => 1,
      'is_tax' => 1,
      'tax_rate' => 10,
      'is_active' => 1,
    );
    $account = CRM_Financial_BAO_FinancialAccount::add($params);
    $entityParams = array(
      'entity_table' => 'civicrm_financial_type',
      'entity_id' => $financialTypeId,
      'account_relationship' => key(CRM_Core_PseudoConstant::accountOptionValues('account_relationship', NULL, " AND v.name LIKE 'Sales Tax Account is' ")),
    );

    //CRM-20313: As per unique index added in civicrm_entity_financial_account table,
    //  first check if there's any record on basis of unique key (entity_table, account_relationship, entity_id)
    $dao = new CRM_Financial_DAO_EntityFinancialAccount();
    $dao->copyValues($entityParams);
    $dao->find();
    if ($dao->fetch()) {
      $entityParams['id'] = $dao->id;
    }
    $entityParams['financial_account_id'] = $account->id;

    return CRM_Financial_BAO_FinancialTypeAccount::add($entityParams);
  }

  /**
   * Enable Tax and Invoicing
   */
  protected function enableTaxAndInvoicing($params = array()) {
    // Enable component contribute setting
    $contributeSetting = array_merge($params,
     array(
       'invoicing' => 1,
       'invoice_prefix' => 'INV_',
       'credit_notes_prefix' => 'CN_',
       'due_date' => 10,
       'due_date_period' => 'days',
       'notes' => '',
       'is_email_pdf' => 1,
       'tax_term' => 'Sales Tax',
       'tax_display_settings' => 'Inclusive',
     )
    );
    return Civi::settings()->set('contribution_invoice_settings', $contributeSetting);
  }

  /**
   * Enable Tax and Invoicing
   */
  protected function disableTaxAndInvoicing($params = array()) {
    // Enable component contribute setting
    $contributeSetting = array_merge($params,
     array(
       'invoicing' => 0,
     )
    );
    return Civi::settings()->set('contribution_invoice_settings', $contributeSetting);
  }

}
