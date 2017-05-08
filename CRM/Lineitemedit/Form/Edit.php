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


  public function preProcess() {
    $this->_id = CRM_Utils_Request::retrieve('id', 'Positive', $this);

    $lineItem = civicrm_api3('lineItem', 'getsingle', array('id' => $this->_id));
    foreach (CRM_Lineitemedit_Util::getLineitemFieldNames() as $attribute) {
      $this->_values[$attribute] = $lineItem[$attribute];
    }

    $this->_values['currency'] = CRM_Core_DAO::getFieldValue(
      'CRM_Financial_DAO_Currency',
      CRM_Core_DAO::getFieldValue('CRM_Contribute_DAO_Contribution', $lineItem['entity_id'], 'currency'),
      'symbol',
      'name'
    );
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
      if ($fieldName == 'line_total') {
        $this->add('text', 'line_total', ts('Total amount'))->freeze();
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
      $this->addField($fieldName, $properties, TRUE);
    }
    $this->assign('fieldNames', $fieldNames);

    $this->addButtons(array(
      array(
        'type' => 'submit',
        'name' => ts('Submit'),
        'isDefault' => TRUE,
      ),
    ));

    parent::buildQuickForm();
  }

  public function postProcess() {
    $values = $this->exportValues();
    parent::postProcess();
  }
}
