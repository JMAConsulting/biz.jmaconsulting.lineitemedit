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
    CRM_Core_Error::debug_var('count' ,$this->_multipleLineItem);
  }

  public function buildQuickForm() {
    $this->assign('message', ts('WARNING: Cancelling this lineitem will affect the related contribution and update the associated financial transactions. Do you want to continue?'));

    $this->addButtons(array(
      array(
        'type' => 'submit',
        'name' => ts('Cancel Item'),
        'isDefault' => TRUE,
      ),
      array(
        'type' => 'cancel',
        'name' => ts('Cancel'),
      ),
    ));

    parent::buildQuickForm();
  }

  public function postProcess() {
    //TODO: lineItem.Get API doesn't fetch tax amount
    $previousTaxAmount = CRM_Core_DAO::getFieldValue('CRM_Price_DAO_LineItem', $this->_id, 'tax_amount');

    // change total_price and qty of current line item to 0, on cancel
    civicrm_api3('LineItem', 'create', array(
      'id' => $this->_id,
      'qty' => 0,
      'line_total' => 0.00,
      'tax_amount' => NULL,
    ));

    // calculate balance, tax and paidamount later used to adjust transaction
    $updatedAmount = $this->_multipleLineItem ? CRM_Price_BAO_LineItem::getLineTotal($this->_prevContributionID) : 0;
    $taxAmount = $this->_multipleLineItem ? CRM_Lineitemedit_Util::getTaxAmountTotalFromContributionID($this->_prevContributionID) : "NULL";
    $paidAmount = CRM_Utils_Array::value(
      'paid',
      CRM_Contribute_BAO_Contribution::getPaymentInfo(
        $this->_prevContributionID,
        'contribution',
        FALSE,
        TRUE
      )
    );

    // Record adjusted amount by updating contribution info and create necessary financial trxns
    $trxn = CRM_Event_BAO_Participant::recordAdjustedAmt(
      $updatedAmount,
      $paidAmount,
      $this->_prevContributionID,
      $taxAmount,
      NULL
    );

    // record financial item on cancellation of lineitem
    if ($trxn) {
      CRM_Lineitemedit_Util::insertFinancialItemOnCancel($this->_id, $previousTaxAmount, $trxn);
    }

    parent::postProcess();
  }

}
