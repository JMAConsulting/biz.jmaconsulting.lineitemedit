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
 *
 * @param array $files
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_xmlMenu
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
 *
 * @param $op string, the type of operation being performed; 'check' or 'enqueue'
 * @param $queue CRM_Queue_Queue, (for 'enqueue') the modifiable list of pending up upgrade tasks
 *
 * @return mixed
 *   Based on op. for 'check', returns array(boolean) (TRUE if upgrades are pending)
 *                for 'enqueue', returns void
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_upgrade
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
 *
 * Generate a list of case-types.
 *
 * @param array $caseTypes
 *
 * Note: This hook only runs in CiviCRM 4.4+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
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
  if ($formName == 'CRM_Contribute_Form_Contribution' &&
    !empty($form->_id) &&
    ($form->_action & CRM_Core_Action::UPDATE)
  ) {
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
        'template' => "CRM/Price/Form/LineItemInfo.tpl"
      ));
    }
    else {
      $pricesetFieldsCount = CRM_Core_Smarty::singleton()->get_template_vars('pricesetFieldsCount');
      CRM_Lineitemedit_Util::formatLineItemList($form->_lineItems, $pricesetFieldsCount);
      $form->assign('lineItem', $form->_lineItems);
      $form->assign('pricesetFieldsCount', TRUE);
    }
    CRM_Core_Resources::singleton()->addVars('lineitemedit', array(
      'add_link' => CRM_Lineitemedit_Util::getAddLineItemLink($contributionID),
      'isQuickConfig' => $isQuickConfig,
      'hideHeader' => !$pricesetFieldsCount,
    ));
    CRM_Core_Resources::singleton()->addScriptFile('biz.jmaconsulting.lineitemedit', 'js/add_item_link.js');
  }
}

function lineitemedit_civicrm_post($op, $objectName, $objectId, &$objectRef) {
}

/**
 * Functions below this ship commented out. Uncomment as required.
 *

/**
 * Implements hook_civicrm_preProcess().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_preProcess
 *
function lineitemedit_civicrm_preProcess($formName, &$form) {

} // */

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
