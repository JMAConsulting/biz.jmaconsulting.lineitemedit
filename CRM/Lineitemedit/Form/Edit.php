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
    if (!CRM_Utils_Rule::integer($fields['qty'])) {
      if ($self->_isQuickConfig || $self->_priceFieldInfo['is_enter_qty'] == 0) {
        $errors['qty'] = ts('Please enter a whole number quantity');
      }
    }

    return $errors;
  }

  public function postProcess() {
    $values = $this->exportValues();
    $values['line_total'] = CRM_Utils_Rule::cleanMoney($values['line_total']);
    $recordChangedAttributes = array(
      'financialTypeChanged' => ($values['financial_type_id'] != $this->_values['financial_type_id']),
      'amountChanged' => ($values['line_total'] != $this->_values['line_total']),
    );


    $balanceAmount = ($values['line_total'] - $this->_lineitemInfo['line_total']);

    $lineItem = civicrm_api3('LineItem', 'create', array(
      'id' => $this->_id,
      'financial_type_id' => $values['financial_type_id'],
      'label' => $values['label'],
      'qty' => $values['qty'],
      'unit_price' => CRM_Utils_Rule::cleanMoney($values['unit_price']),
      'line_total' => $values['line_total'],
    ));
    $values['tax_amount'] = CRM_Utils_Array::value('tax_amount', $lineItem['values'][$lineItem['id']], 0);
    $recordChangedAttributes['taxAmountChanged'] = ($values['tax_amount'] != CRM_Utils_Array::value('tax_amount', $this->_lineitemInfo, 0));

    if ($this->_lineitemInfo['entity_table'] == 'civicrm_membership') {
      $memberNumTerms = CRM_Core_DAO::getFieldValue('CRM_Price_DAO_PriceFieldValue', $this->_lineitemInfo['price_field_value_id'], 'membership_num_terms');
      $memberNumTerms = empty($memberNumTerms) ? 1 : $memberNumTerms;
      $memberNumTerms = $values['qty'] * $memberNumTerms;
      civicrm_api3('Membership', 'create', array(
        'id' => $this->_lineitemInfo['entity_id'],
        'num_terms' => $memberNumTerms,
      ));
    }

    //TODO:: return if no financial changes


    // calculate balance, tax and paidamount later used to adjust transaction
    $updatedAmount = CRM_Price_BAO_LineItem::getLineTotal($this->_lineitemInfo['contribution_id']);
    $taxAmount = CRM_Lineitemedit_Util::getTaxAmountTotalFromContributionID($this->_lineitemInfo['contribution_id']);
    $balanceTaxAmount = ($values['tax_amount'] - CRM_Utils_Array::value('tax_amount', $this->_lineitemInfo, 0));
    // Record adjusted amount by updating contribution info and create necessary financial trxns
    CRM_Lineitemedit_Util::recordAdjustedAmt(
      $updatedAmount,
      $this->_lineitemInfo['contribution_id'],
      $taxAmount,
      FALSE
    );

    // Record financial item on edit of lineitem
    CRM_Lineitemedit_Util::insertFinancialItemOnEdit(
      $this->_id,
      $recordChangedAttributes,
      $balanceAmount,
      CRM_Utils_Array::value('tax_amount', $values),
      $balanceTaxAmount,
      $this->_lineitemInfo
    );

    parent::postProcess();
  }
}
