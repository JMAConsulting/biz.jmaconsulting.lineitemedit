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
class CRM_Lineitemedit_Form_BaseTest extends \PHPUnit\Framework\TestCase implements HeadlessInterface, HookInterface, TransactionalInterface {

  use \Civi\Test\Api3DocTrait;
  protected $_contactID;
  protected $_contributionID;
  protected $_contribution;
  protected $_priceSetID;
  protected $_createContri = TRUE;
  protected $_eventFeeBlock;

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

  protected function createPriceSet($priceFieldOptions = array(), $priceSetParams = []) {
    $paramsSet['title'] = 'Price Set' . substr(sha1(rand()), 0, 7);
    $paramsSet['name'] = CRM_Utils_String::titleToVar($paramsSet['title']);
    $paramsSet['is_active'] = TRUE;
    $paramsSet['financial_type_id'] = 4;
    $paramsSet['extends'] = 2;
    $priceSet = $this->callAPISuccess('price_set', 'create', array_merge($paramsSet, $priceSetParams));
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
       'invoice_due_date' => 10,
       'invoice_due_date_period' => 'days',
       'invoice_notes' => '',
       'is_email_pdf' => 1,
       'tax_term' => 'Sales Tax',
       'tax_display_settings' => 'Inclusive',
     )
    );
    foreach ($contributeSetting as $key => $value) {
      Civi::settings()->set($key, $value);
    }
  }

  /**
   * Enable Tax and Invoicing
   */
  protected function disableTaxAndInvoicing($params = array()) {
    return Civi::settings()->set('invoicing', 0);
  }

  /**
   * Create an Event.
   *
   * @param array $params
   *   Name-value pair for an event.
   *
   * @return array
   */
  public function eventCreate($params = array()) {
    $this->createContact();

    // set defaults for missing params
    $params = array_merge(array(
      'title' => 'Annual CiviCRM meet',
      'summary' => 'If you have any CiviCRM related issues or want to track where CiviCRM is heading, Sign up now',
      'description' => 'This event is intended to give brief idea about progess of CiviCRM and giving solutions to common user issues',
      'event_type_id' => 1,
      'is_public' => 1,
      'start_date' => 20081021,
      'end_date' => 20081023,
      'is_online_registration' => 1,
      'registration_start_date' => 20080601,
      'registration_end_date' => 20081015,
      'max_participants' => 100,
      'event_full_text' => 'Sorry! We are already full',
      'is_monetary' => 0,
      'is_active' => 1,
      'is_show_location' => 0,
    ), $params);
    $event = $this->callAPISuccess('Event', 'create', $params);

    $priceSetParams = [
      'extends' => 1,
      'financial_type_id' => CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'financial_type_id', 'Event Fee'),
    ];
    $priceFieldParams = [
      'is_enter_qty' => 0,
      'financial_type_id' => CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'financial_type_id', 'Event Fee'),
    ];
    $this->createPriceSet($priceFieldParams, $priceSetParams);
    CRM_Price_BAO_PriceSet::addTo('civicrm_event', $event['id'], $this->_priceSetID);
    $priceSet = CRM_Price_BAO_PriceSet::getSetDetail($this->_priceSetID, TRUE, FALSE);
    $priceSet = CRM_Utils_Array::value($this->_priceSetID, $priceSet);
    $this->_eventFeeBlock = CRM_Utils_Array::value('fields', $priceSet);

    return $event;
  }

  /**
   * Instantiate form object.
   *
   * We need to instantiate the form to run preprocess, which means we have to trick it about the request method.
   *
   * @param string $class
   *   Name of form class.
   *
   * @return \CRM_Core_Form
   */
  public function getFormObject($class) {
    $form = new $class();
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $form->controller = new CRM_Core_Controller();
    return $form;
  }

}
