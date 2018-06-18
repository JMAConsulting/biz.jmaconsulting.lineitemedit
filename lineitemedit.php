<?php

require_once 'lineitemedit.civix.php';

/**
 * Implements hook_civicrm_config().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function lineitemedit_civicrm_config(&$config) {
  _lineitemedit_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_xmlMenu().
 */
function lineitemedit_civicrm_xmlMenu(&$files) {
  _lineitemedit_civix_civicrm_xmlMenu($files);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function lineitemedit_civicrm_install() {
  _lineitemedit_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_uninstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_uninstall
 */
function lineitemedit_civicrm_uninstall() {
  _lineitemedit_civix_civicrm_uninstall();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function lineitemedit_civicrm_enable() {
  _lineitemedit_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_disable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_disable
 */
function lineitemedit_civicrm_disable() {
  _lineitemedit_civix_civicrm_disable();
}

/**
 * Implements hook_civicrm_upgrade().
 */
function lineitemedit_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _lineitemedit_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implements hook_civicrm_managed().
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_managed
 */
function lineitemedit_civicrm_managed(&$entities) {
  _lineitemedit_civix_civicrm_managed($entities);
}

/**
 * Implements hook_civicrm_caseTypes().
 */
function lineitemedit_civicrm_caseTypes(&$caseTypes) {
  _lineitemedit_civix_civicrm_caseTypes($caseTypes);
}

/**
 * Implements hook_civicrm_angularModules().
 *
 * Generate a list of Angular modules.
 *
 * Note: This hook only runs in CiviCRM 4.5+. It may
 * use features only available in v4.6+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function lineitemedit_civicrm_angularModules(&$angularModules) {
  _lineitemedit_civix_civicrm_angularModules($angularModules);
}

/**
 * Implements hook_civicrm_alterSettingsFolders().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_alterSettingsFolders
 */
function lineitemedit_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _lineitemedit_civix_civicrm_alterSettingsFolders($metaDataFolders);
}

function lineitemedit_civicrm_buildForm($formName, &$form) {
  if ($formName == 'CRM_Contribute_Form_Contribution') {
    $contributionID = NULL;
    if (!empty($form->_id) && ($form->_action & CRM_Core_Action::UPDATE)) {
      $contributionID = $form->_id;
      $pricesetFieldsCount = NULL;
      $isQuickConfig = empty($form->_lineItems) ? TRUE : FALSE;
      // Append line-item table only if current contribution has quick config lineitem
      if ($isQuickConfig) {
        $order = civicrm_api3('Order', 'getsingle', array('id' => $contributionID));
        $lineItemTable = CRM_Lineitemedit_Util::getLineItemTableInfo($order);
        $form->assign('lineItemTable', $lineItemTable);

        // Assumes templates are in a templates folder relative to this file
        $templatePath = realpath(dirname(__FILE__) . "/templates");
      }
      else {
        $pricesetFieldsCount = CRM_Core_Smarty::singleton()->get_template_vars('pricesetFieldsCount');
        CRM_Lineitemedit_Util::formatLineItemList($form->_lineItems, $pricesetFieldsCount);
        $form->assign('lineItem', $form->_lineItems);
        $form->assign('pricesetFieldsCount', TRUE);
      }
      CRM_Lineitemedit_Util::buildLineItemRows($form, $contributionID);
      // dynamically insert a template block in the page
      CRM_Core_Region::instance('page-body')->add(array(
        'template' => "CRM/Lineitemedit/Form/AddLineItems.tpl",
      ));
      if (!empty($form->_values['total_amount'])) {
        $form->setDefaults('total_amount', $form->_values['total_amount']);
      }
    }
    elseif ($form->_action & CRM_Core_Action::ADD) {
      CRM_Lineitemedit_Util::buildLineItemRows($form);
      CRM_Core_Region::instance('page-body')->add(array(
        'template' => "CRM/Lineitemedit/Form/AddLineItems.tpl",
      ));
    }
  }
}

function lineitemedit_civicrm_postProcess($formName, &$form) {
  if ($formName == 'CRM_Contribute_Form_Contribution' &&
    !empty($form->_id) &&
    ($form->_action & CRM_Core_Action::UPDATE)
  ) {
    $lineItems = CRM_Price_BAO_LineItem::getLineItemsByContributionID($form->_id);
    foreach ($lineItems as $id => $lineItem) {
      if ($lineItem['qty'] == 0 && $lineItem['line_total'] != 0) {
        $qtyRatio = ($lineItem['line_total'] / $lineItem['unit_price']);
        if ($lineItem['html_type'] == 'Text') {
          $qtyRatio = round($qtyRatio, 2);
        }
        else {
          $qtyRatio = (int) $qtyRatio;
        }
        civicrm_api3('LineItem', 'create', array(
          'id' => $id,
          'qty' => $qtyRatio ? $qtyRatio : 1,
        ));
      }
    }
  }
}

function lineitemedit_civicrm_pre($op, $entity, $entityID, &$params) {
  if ($entity == 'Contribution') {
    if ($op == 'create') {
      $lineItemParams = [];
      $taxEnabled = (bool) CRM_Utils_Array::value('invoicing', Civi::settings()->get('contribution_invoice_settings'));
      for ($i = 1; $i <= 10; $i++) {
        $lineItemParams[$i] = [];
        $notFound = TRUE;
        foreach (['item_label', 'item_financial_type_id', 'item_qty', 'item_unit_price', 'item_line_total', 'item_price_field_value_id'] as $attribute) {
          if (!empty($params[$attribute]) && !empty($params[$attribute][$i])) {
            $notFound = FALSE;
            $lineItemParams[$i][str_replace('item_', '', $attribute)] = $params[$attribute][$i];
            if ($attribute == 'item_price_field_value_id') {
              $lineItemParams[$i]['price_field_id'] = CRM_Core_DAO::getFieldValue('CRM_Price_DAO_PriceFieldValue', $params[$attribute][$i], 'price_field_id');
            }
          }
        }
        if ($notFound) {
          unset($lineItemParams[$i]);
        }
        else {
          if ($taxEnabled) {
            $lineItemParams[$i]['tax_amount'] = CRM_Utils_Array::value($i, $params['item_tax_amount'], 0.00);
            $params['tax_amount'] += $lineItemParams[$i]['tax_amount'];
          }
          $params['total_amount'] = $params['net_amount'] = $params['amount'] += ($lineItemParams[$i]['line_total'] + CRM_Utils_Array::value('tax_amount', $lineItemParams[$i], 0.00));
          if (!empty($lineItemParams[$i]['line_total']) && !empty($lineItemParams[$i]['price_field_id'])) {
            $priceSetID = CRM_Core_DAO::getFieldValue('CRM_Price_BAO_PriceField', $lineItemParams[$i]['price_field_id'], 'price_set_id');
            if (!empty($params['line_item'][$priceSetID])) {
              $params['line_item'][$priceSetID][$lineItemParams[$i]['price_field_id']] = $lineItemParams[$i];
            }
          }
        }
      }
    }
    elseif ($op == 'edit') {
      $lineItemParams = $newLineItem = [];
      for ($i = 1; $i <= 10; $i++) {
        $lineItemParams[$i] = [];
        $notFound = TRUE;
        foreach (['item_label', 'item_financial_type_id', 'item_qty', 'item_unit_price', 'item_line_total', 'item_price_field_value_id', 'item_tax_amount'] as $attribute) {
          if (!empty($params[$attribute]) && !empty($params[$attribute][$i])) {
            if ($attribute == 'item_line_total') {
              $notFound = FALSE;
            }
            $lineItemParams[$i][str_replace('item_', '', $attribute)] = $params[$attribute][$i];
            if ($attribute == 'item_price_field_value_id') {
              $lineItemParams[$i]['price_field_id'] = CRM_Core_DAO::getFieldValue('CRM_Price_DAO_PriceFieldValue', $params[$attribute][$i], 'price_field_id');
            }
          }
        }
        if ($notFound) {
          unset($lineItemParams[$i]);
        }
      }

      foreach ($lineItemParams as $key => $lineItem) {
        if ($lineItem['price_field_value_id'] == 'new') {
          list($lineItem['price_field_id'], $lineItem['price_field_value_id']) = CRM_Lineitemedit_Util::createPriceFieldByContributionID($entityID);
        }
        else {
          $lineItem['price_field_id'] = CRM_Core_DAO::getFieldValue('CRM_Price_DAO_PriceFieldValue', $lineItem['price_field_value_id'], 'price_field_id');
        }
        list($lineEntityTable, $lineEntityID) = CRM_Lineitemedit_Util::addEntity(
          $lineItem['price_field_value_id'],
          $entityID,
          $lineItem['qty']
        );

        $newLineItemParams = array(
          'entity_table' => $lineEntityTable,
          'entity_id' => $lineEntityID,
          'contribution_id' => $entityID,
          'price_field_id' => $lineItem['price_field_id'],
          'label' => $lineItem['label'],
          'qty' => $lineItem['qty'],
          'unit_price' => CRM_Utils_Rule::cleanMoney($lineItem['unit_price']),
          'line_total' => CRM_Utils_Rule::cleanMoney($lineItem['line_total']),
          'price_field_value_id' => $lineItem['price_field_value_id'],
          'financial_type_id' => $lineItem['financial_type_id'],
          'tax_amount' => CRM_Utils_Array::value('tax_amount', $lineitem),
        );
        $newLineItem[] = civicrm_api3('LineItem', 'create', $newLineItemParams)['id'];
      }

      if (!empty($lineItemParams)) {
        // calculate balance, tax and paidamount later used to adjust transaction
        $updatedAmount = CRM_Price_BAO_LineItem::getLineTotal($entityID);
        $taxAmount = CRM_Lineitemedit_Util::getTaxAmountTotalFromContributionID($entityID);

        // Record adjusted amount by updating contribution info and create necessary financial trxns
        list($trxn, $contriParams) = CRM_Lineitemedit_Util::recordAdjustedAmt(
          $updatedAmount,
          $entityID,
          $taxAmount,
          TRUE, TRUE
        );
        CRM_Core_BAO_Cache::setItem($contriParams, 'lineitem-editor', $entityID);

        // record financial item on addition of lineitem
        if ($trxn) {
          foreach ($newLineItem as $lineItemID) {
            $lineItem = civicrm_api3('LineItem', 'getsingle', array('id' => $lineItemID));
            CRM_Lineitemedit_Util::insertFinancialItemOnAdd($lineItem, $trxn);
          }
        }
      }
    }
  }
}

function lineitemedit_civicrm_post($op, $entity, $entityID, &$obj) {
  if ($entity == 'Contribution' && $op == 'edit') {
    $contriParams = CRM_Core_BAO_Cache::getItem('lineitem-editor', $entityID);
    if (!empty($contriParams)) {
      $obj->copyValues($contriParams);
      $obj->save();
      CRM_Core_BAO_Cache::deleteGroup('lineitem-editor', $entityID);
    }
  }
}

/**
 * Implements hook_civicrm_navigationMenu().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_navigationMenu
 *
function lineitemedit_civicrm_navigationMenu(&$menu) {
  _lineitemedit_civix_insert_navigation_menu($menu, NULL, array(
    'label' => ts('The Page', array('domain' => 'biz.jmaconsulting.lineitemedit')),
    'name' => 'the_page',
    'url' => 'civicrm/the-page',
    'permission' => 'access CiviReport,access CiviContribute',
    'operator' => 'OR',
    'separator' => 0,
  ));
  _lineitemedit_civix_navigationMenu($menu);
} // */
