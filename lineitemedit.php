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
  $priceField = civicrm_api3('PriceField',
    'getsingle',
    [
      'price_set_id' => civicrm_api3('PriceSet', 'getvalue', ['name' => 'default_contribution_amount', 'return' => 'id']),
      'options' => ['limit' => 1],
    ]
  );
  $priceFieldParams = $priceField;
  unset($priceFieldParams['id'], $priceFieldParams['name'], $priceFieldParams['weight'], $priceFieldParams['is_required']);
  $priceFieldValue = civicrm_api3('PriceFieldValue',
    'getsingle',
    [
      'price_field_id' => $priceField['id'],
      'options' => ['limit' => 1],
    ]
  );
  $priceFieldValueParams = $priceFieldValue;
  unset($priceFieldValueParams['id'], $priceFieldValueParams['name'], $priceFieldValueParams['weight']);
  for ($i = 1; $i <= 10; ++$i) {
    $p = civicrm_api3('PriceField', 'create', array_merge(['label' => $priceField['label'] . " $i"],  $priceFieldParams));
    civicrm_api3('PriceFieldValue', 'create', array_merge(
      [
        'label' => $priceField['label'] . " $i",
        'price_field_id' => $p['id'],
      ],
      $priceFieldValueParams
    ));
  }
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
        // dynamically insert a template block in the page
        CRM_Core_Region::instance('page-header')->add(array(
          'template' => "CRM/Price/Form/LineItemInfo.tpl",
        ));
      }
      else {
        $pricesetFieldsCount = CRM_Core_Smarty::singleton()->get_template_vars('pricesetFieldsCount');
        CRM_Lineitemedit_Util::formatLineItemList($form->_lineItems, $pricesetFieldsCount);
        $form->assign('lineItem', $form->_lineItems);
        $form->assign('pricesetFieldsCount', TRUE);
      }
      CRM_Lineitemedit_Util::buildLineItemRows($form, $form->_id);
      CRM_Core_Region::instance('page-body')->add(array(
        'template' => "CRM/Lineitemedit/Form/AddLineItems.tpl",
      ));
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
  if ($entity == 'Contribution' && $op == 'create') {
    $lineItemParams = [];
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
        $params['total_amount'] = $params['amount'] += $lineItemParams[$i]['line_total'];
        if (!empty($lineItemParams[$i]['line_total']) && !empty($lineItemParams[$i]['price_field_id'])) {
          $priceSetID = CRM_Core_DAO::getFieldValue('CRM_Price_BAO_PriceField', $lineItemParams[$i]['price_field_id'], 'price_set_id');
          if (!empty($params['line_item'][$priceSetID])) {
            $params['line_item'][$priceSetID][$lineItemParams[$i]['price_field_id']] = $lineItemParams[$i];
          }
        }
      }
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
