<?php

require_once 'CRM/Core/Form.php';

/**
 * Form controller class
 *
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC43/QuickForm+Reference
 */
class CRM_Lineitemedit_Form_Cancel extends CRM_Core_Form {

  public $_lineitemInfo = NULL;

  protected $_multipleLineItem;

  public $_prevContributionID = NULL;

  public function preProcess() {
    $this->_id = CRM_Utils_Request::retrieve('id', 'Positive', $this);
    $this->assignFormVariables();
  }

  public function assignFormVariables() {
    // fetch line item information of given ID
    $this->_lineitemInfo = civicrm_api3('LineItem', 'getsingle', array('id' => $this->_id));

    // store related contribution ID
    $this->_prevContributionID = $this->_lineitemInfo['contribution_id'];

    // fetch related active lineitems of contribution
    $count = civicrm_api3('LineItem', 'getcount', array(
      'contribution_id' => $this->_prevContributionID,
      'line_total' => array('>' => 0),
    ));

    $this->_multipleLineItem = ($count > 1) ? TRUE : FALSE;
  }

  public function buildQuickForm() {
    $this->assign('message', ts('WARNING: Cancelling this line item will affect the related contribution and update the associated financial transactions. Do you want to continue?'));

    $this->addButtons(array(
      array(
        'type' => 'submit',
        'name' => ts('Cancel Item'),
        'isDefault' => TRUE,
      ),
      array(
        'type' => 'cancel',
        'name' => ts('Close'),
      ),
    ));

    parent::buildQuickForm();
  }

  public function postProcess() {
    $this->submit();
    if ($this->_lineitemInfo['entity_table'] == 'civicrm_membership') {
      $contactId = CRM_Core_DAO::getFieldValue('CRM_Contribute_BAO_Contribution',
        $this->_lineitemInfo['contribution_id'],
        'contact_id'
      );
      $this->ajaxResponse['updateTabs']['#tab_member'] = CRM_Contact_BAO_Contact::getCountComponent('membership', $contactId);
    }

    parent::postProcess();
  }

  public function submit() {
    CRM_Lineitemedit_Util::cancelEntity($this->_lineitemInfo['entity_id'], $this->_lineitemInfo['entity_table']);

    // change total_price and qty of current line item to 0, on cancel
    civicrm_api3('LineItem', 'create', array(
      'id' => $this->_id,
      'qty' => 0,
      'participant_count' => 0,
      'line_total' => 0.00,
      'tax_amount' => 0.00,
    ));

    // calculate balance, tax and paidamount later used to adjust transaction
    $updatedAmount = CRM_Price_BAO_LineItem::getLineTotal($this->_prevContributionID);
    $taxAmount = CRM_Lineitemedit_Util::getTaxAmountTotalFromContributionID($this->_prevContributionID);

    // Record adjusted amount by updating contribution info and create necessary financial trxns
    CRM_Lineitemedit_Util::recordAdjustedAmt(
      $updatedAmount,
      $this->_prevContributionID,
      $taxAmount,
      FALSE
    );

    // Record financial item on cancel of lineitem
    CRM_Lineitemedit_Util::insertFinancialItemOnEdit(
      $this->_id,
      $this->_lineitemInfo
    );
  }

  public function testSubmit($id) {
    $this->_id = $id;
    $this->assignFormVariables();
    $this->submit();
  }
}
