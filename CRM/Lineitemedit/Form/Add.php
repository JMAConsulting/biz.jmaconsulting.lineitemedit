<?php

require_once 'CRM/Core/Form.php';

/**
 * Form controller class
 *
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC43/QuickForm+Reference
 */
class CRM_Lineitemedit_Form_Add extends CRM_Core_Form {

  protected $_contributionID;

  protected $_isQuickConfig;

  protected $_fieldNames;

  public function preProcess() {
    $this->_contributionID = CRM_Utils_Request::retrieve('contribution_id', 'Positive', $this);

    $this->_fieldNames = array('price_field_value_id') + CRM_Lineitemedit_Util::getLineitemFieldNames();
  }

  /**
   * Set default values.
   *
   * @return array
   */
  public function setDefaultValues() {
    return array(
      'line_total' => 0.00,
      'tax_amount' => 0.00,
    );
  }

  public function buildQuickForm() {
    foreach ($this->_fieldNames as $fieldName) {
      if ($fieldName == 'line_total') {
        $this->add('text', 'line_total', ts('Total amount'), array(
          'size' => 6,
          'maxlength' => 14,
          'readonly' => TRUE)
        );
        continue;
      }
      elseif ($fieldName == 'price_field_value_id') {
        $options = array('- select any price-field -') + CRM_Lineitemedit_Util::getPriceFieldLists();
        $this->add('select', $fieldName, ts('Price Field'), $options, TRUE);
        continue;
      }
      $properties = array(
        'entity' => 'LineItem',
        'name' => $fieldName,
        'context' => 'add',
        'action' => 'create',
      );
      if ($fieldName == 'financial_type_id') {
        CRM_Financial_BAO_FinancialType::getAvailableFinancialTypes($financialTypes);
        $properties['options'] = $financialTypes;
      }
      elseif ($fieldName == 'tax_amount') {
        $properties['readonly'] = TRUE;
      }
      $this->addField($fieldName, $properties, TRUE);
    }

    $this->assign('fieldNames', $this->_fieldNames);

    $this->assign('currency', CRM_Core_DAO::getFieldValue(
      'CRM_Financial_DAO_Currency',
      CRM_Core_DAO::getFieldValue('CRM_Contribute_DAO_Contribution', $this->_contributionID, 'currency'),
      'symbol',
      'name'
    ));

    if (in_array('tax_amount', $this->_fieldNames)) {
      $this->assign('taxRates', json_encode(CRM_Core_PseudoConstant::getTaxRates()));
    }

    $this->addButtons(array(
      array(
        'type' => 'submit',
        'name' => ts('Add Item'),
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
    $values = $this->exportValues();
    parent::postProcess();
  }

}
