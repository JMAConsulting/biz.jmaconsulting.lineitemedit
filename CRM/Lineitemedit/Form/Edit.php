<?php

require_once 'CRM/Core/Form.php';

/**
 * Form controller class
 *
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC43/QuickForm+Reference
 */
class CRM_Lineitemedit_Form_Edit extends CRM_Core_Form {

  /**
   * The line-item values of an existing contribution
   */
  public $_values;

  public $_isQuickConfig = FALSE;

  public $_priceFieldInfo = array();

  protected $_lineitemInfo;

  public function preProcess() {
    $this->_id = CRM_Utils_Request::retrieve('id', 'Positive', $this);

    $this->_lineitemInfo = civicrm_api3('lineItem', 'getsingle', array('id' => $this->_id));
    foreach (CRM_Lineitemedit_Util::getLineitemFieldNames() as $attribute) {
      $this->_values[$attribute] = $this->_lineitemInfo[$attribute];
    }

    $this->_values['currency'] = CRM_Core_DAO::getFieldValue(
      'CRM_Financial_DAO_Currency',
      CRM_Core_DAO::getFieldValue('CRM_Contribute_DAO_Contribution', $this->_lineitemInfo['entity_id'], 'currency'),
      'symbol',
      'name'
    );

    $this->_isQuickConfig = (bool) CRM_Core_DAO::getFieldValue(
      'CRM_Price_DAO_PriceSet',
      CRM_Core_DAO::getFieldValue('CRM_Price_DAO_PriceField', $this->_lineitemInfo['price_field_id'], 'price_set_id'),
      'is_quick_config'
    );

    $this->_priceFieldInfo = civicrm_api3('PriceField', 'getsingle', array('id' => $this->_lineitemInfo['price_field_id']));
  }

  /**
   * Set default values.
   *
   * @return array
   */
  public function setDefaultValues() {
    return $this->_values;
  }

  public function buildQuickForm() {
    $fieldNames = array_keys($this->_values);
    foreach ($fieldNames as $fieldName) {
      $required = TRUE;
      if ($fieldName == 'line_total') {
        $this->add('text', 'line_total', ts('Total amount'), array(
          'size' => 6,
          'maxlength' => 14,
          'readonly' => TRUE)
        );
        continue;
      }
      elseif ($fieldName == 'currency') {
        $this->assign('currency', $this->_values['currency']);
        continue;
      }
      $properties = array(
        'entity' => 'LineItem',
        'name' => $fieldName,
        'context' => 'edit',
        'action' => 'create',
      );
      if ($fieldName == 'financial_type_id') {
        CRM_Financial_BAO_FinancialType::getAvailableFinancialTypes($financialTypes);
        $properties['options'] = $financialTypes;
      }
      // In case of quickconfig price field we cannot change quantity
      if ($fieldName == 'qty') {
        if ($this->_isQuickConfig || $this->_priceFieldInfo['is_enter_qty'] == 0) {
          $properties['readonly'] = TRUE;
        }
      }
      // In case of text non-quickconfig price field we cannot change the unit price
      elseif (($this->_priceFieldInfo['is_enter_qty'] == 1 && $fieldName == 'unit_price') || $fieldName == 'tax_amount') {
        $properties['readonly'] = TRUE;
        $required = FALSE;
      }

      $ele = $this->addField($fieldName, $properties, $required);
      if ($this->_lineitemInfo['entity_table'] != 'civicrm_contribution' && $fieldName == 'financial_type_id') {
        $ele->freeze();
      }
    }
    $this->assign('fieldNames', $fieldNames);

    $this->assign('taxRates', json_encode(CRM_Core_PseudoConstant::getTaxRates()));


    $this->addFormRule(array(__CLASS__, 'formRule'), $this);

    $this->addButtons(array(
      array(
        'type' => 'submit',
        'name' => ts('Submit'),
        'isDefault' => TRUE,
      ),
      array(
        'type' => 'cancel',
        'name' => ts('Cancel'),
      ),
    ));

    parent::buildQuickForm();
  }

  public static function formRule($fields, $files, $self) {
    $errors = array();

    if ($fields['line_total'] == 0) {
      $errors['line_total'] = ts('Line Total amount should not be empty');
    }
    if ($fields['qty'] == 0) {
      $errors['qty'] = ts('Line quantity cannot be zero');
    }

    return $errors;
  }

  public function postProcess() {
    $values = $this->exportValues();

    $recordChangedAttributes = array(
      'financialTypeChanged' => ($values['financial_type_id'] != $this->_values['financial_type_id']),
      'amountChanged' => ($values['line_total'] != $this->_values['line_total']),
    );

    if (!empty($values['tax_amount']) && $values['tax_amount'] != 0) {
      $recordChangedAttributes['taxAmountChanged'] = ($values['tax_amount'] != $this->_values['tax_amount']);
    }

    $balanceAmount = ($values['line_total'] - $this->_lineitemInfo['line_total']);

    civicrm_api3('LineItem', 'create', array(
      'id' => $this->_id,
      'financial_type_id' => $values['financial_type_id'],
      'label' => $values['label'],
      'qty' => $values['qty'],
      'unit_price' => $values['unit_price'],
      'line_total' => $values['line_total'],
      'tax_amount' => CRM_Utils_Array::value('tax_amount', $values, NULL),
    ));

    // calculate balance, tax and paidamount later used to adjust transaction
    $updatedAmount = CRM_Price_BAO_LineItem::getLineTotal($this->_lineitemInfo['contribution_id']);
    $taxAmount = CRM_Lineitemedit_Util::getTaxAmountTotalFromContributionID($this->_lineitemInfo['contribution_id']);
    $balanceTaxAmount = NULL;
    if (!empty($values['tax_amount']) && $values['tax_amount'] != 0) {
      $balanceTaxAmount = ($values['tax_amount'] - CRM_Utils_Array::value('tax_amount', $this->_lineitemInfo, 0));
    }
    $paidAmount = CRM_Utils_Array::value(
      'paid',
      CRM_Contribute_BAO_Contribution::getPaymentInfo(
        $this->_lineitemInfo['contribution_id'],
        'contribution',
        FALSE,
        TRUE
      )
    );

    $previousFinancialItem = CRM_Financial_BAO_FinancialItem::getPreviousFinancialItem($this->_id);

    if ($balanceAmount != 0 && $recordChangedAttributes['financialTypeChanged']) {
      // cancel any previous financial item
      $previousFinancialItem = civicrm_api3('FinancialItem', 'getsingle', array(
        'entity_table' => 'civicrm_line_item',
        'entity_id' => $this->_id,
        'amount' => $this->_lineitemInfo['line_total'],
        'options' => array(
          'limit' => 1,
          'sort' => 'id DESC',
        ),
      ));

      // insert a new financial trxn with cancelled amount
      unset($previousFinancialItem['id']);
      civicrm_api3('FinancialItem', 'create', array_merge($previousFinancialItem, array(
        'amount' => -$this->_lineitemInfo['line_total'],
      )));

      // insert a new financial item with updated Amount
      civicrm_api3('FinancialItem', 'create', array_merge($previousFinancialItem, array(
        'amount' => $values['line_total'],
        'description' => $values['label'],
        'financial_account_id' => CRM_Contribute_PseudoConstant::getRelationalFinancialAccount(
          $values['financial_type_id'],
            'Accounts Receivable Account is'
        ),
      )));
    }

    // Record adjusted amount by updating contribution info and create necessary financial trxns
    $trxn = CRM_Lineitemedit_Util::recordAdjustedAmt(
      $updatedAmount,
      $paidAmount,
      $this->_lineitemInfo['contribution_id'],
      $taxAmount,
      NULL
    );

    // Record financial item on edit of lineitem
    if ($trxn) {
      CRM_Lineitemedit_Util::insertFinancialItemOnEdit(
        $this->_id,
        CRM_Utils_Array::value('tax_amount', $values),
        $trxn,
        $recordChangedAttributes,
        $balanceAmount,
        $balanceTaxAmount
      );
    }

    parent::postProcess();
  }
}
