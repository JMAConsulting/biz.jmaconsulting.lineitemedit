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

    // Record chapter and fund for reverse trxn
    // Get last inserted financial trxn if updated.
    $ft = CRM_EFT_BAO_EFT::getLastTrxnId($this->_prevContributionID);
    if (!empty($ft)) {
      $lastFt = CRM_Core_DAO::executeQuery("SELECT ce.chapter_code, ce.fund_code 
        FROM civicrm_contribution c
        INNER JOIN civicrm_entity_financial_trxn eft ON eft.entity_id = c.id AND eft.entity_table = 'civicrm_contribution'
        INNER JOIN civicrm_financial_trxn ft ON ft.id = eft.financial_trxn_id
        INNER JOIN civicrm_chapter_entity ce ON ce.entity_id = ft.id AND ce.entity_table = 'civicrm_financial_trxn'
        WHERE c.id = {$this->_prevContributionID} ORDER BY ft.id DESC LIMIT 1")->fetchAll()[0];
      if (!empty($lastFt)) {
        $params = [
          "entity_id" => $ft,
          "entity_table" => "civicrm_financial_trxn",
          "chapter" => $lastFt['chapter_code'],
          "fund" => $lastFt['fund_code'],
        ];
        CRM_EFT_BAO_EFT::saveChapterFund($params);
      }
    }
    $fi = CRM_Contribute_BAO_Contribution::getLastFinancialItemIds($this->_prevContributionID)[0];
    $fi = reset($fi);
    if ($fi) {
      $entry = CRM_Core_DAO::singleValueQuery("SELECT id FROM civicrm_chapter_entity WHERE entity_id = {$fi} AND entity_table = 'civicrm_financial_item'");
      if (!$entry) {
        $lastFi = CRM_Core_DAO::executeQuery("SELECT ce.chapter_code, ce.fund_code
          FROM civicrm_financial_item fi
          INNER JOIN civicrm_line_item li ON li.id = fi.entity_id and fi.entity_table = 'civicrm_line_item'
          INNER JOIN civicrm_chapter_entity ce ON ce.entity_id = fi.id AND ce.entity_table = 'civicrm_financial_item'
          WHERE li.contribution_id = {$this->_prevContributionID} ORDER BY fi.id DESC LIMIT 1")->fetchAll()[0];
        $params = [
          "entity_id" => $fi,
          "entity_table" => "civicrm_financial_item",
          "chapter" => $lastFi['chapter_code'],
          "fund" => $lastFi['fund_code'],
        ];
        CRM_EFT_BAO_EFT::saveChapterFund($params);
      }
    }
  }

  public function testSubmit($id) {
    $this->_id = $id;
    $this->assignFormVariables();
    $this->submit();
  }

}
