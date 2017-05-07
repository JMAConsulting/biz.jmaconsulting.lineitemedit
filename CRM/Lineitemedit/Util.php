<?php

class CRM_Lineitemedit_Util {

  /**
   * Function used to fetch associated line-item(s) of a contribution in tabular format
   *
   * @param array $order
   *   contribution infor with associated line items
   *
   * @return array $lineItemTable
   *   array of lineitems
   */
  public static function getLineItemTableInfo($order) {
    $lineItems = (array) $order['line_items'];
    $lineItemTable = array();
    $links = array(
      CRM_Core_Action::UPDATE => array(
        'name' => ts('Edit'),
        'url' => 'civicrm/lineitem/edit',
        'qs' => 'reset=1&id=%%id%%',
        'title' => ts('Edit Line item'),
      ),
      CRM_Core_Action::DELETE => array(
        'name' => ts('Cancel'),
        'url' => 'civicrm/lineitem/cancel',
        'qs' => 'reset=1&id=%%id%%',
        'title' => ts('Cancel Line item'),
      ),
    );

    $permissions = array(CRM_Core_Permission::VIEW);
    if (CRM_Core_Permission::check('edit contributions')) {
      $permissions[] = CRM_Core_Permission::EDIT;
    }
    if (CRM_Core_Permission::check('delete in CiviContribute')) {
      $permissions[] = CRM_Core_Permission::DELETE;
    }
    $mask = CRM_Core_Action::mask($permissions);

    foreach ($lineItems as $key => $lineItem) {
      $actions = array(
        'id' => $lineItem['id']
      );
      $lineItemTable[$key] = array(
        'id' => $lineItem['id'],
        'item' => $lineItem['label'],
        'financial_type' => CRM_Core_PseudoConstant::getLabel('CRM_Contribute_BAO_Contribution', 'financial_type_id', $lineItem['financial_type_id']),
        'qty' => $lineItem['qty'],
        'unit_price' => $lineItem['unit_price'],
        'total_price' => $lineItem['line_total'],
        'currency' => $order['currency'],
        'actions' => CRM_Core_Action::formLink($links, $mask, $actions),
      );
    }

    return $lineItemTable;
  }

}
