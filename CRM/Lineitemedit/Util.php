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
    $membershipID = CRM_Core_DAO::getFieldValue('CRM_Member_DAO_MembershipPayment', $order['contribution_id'], 'membership_id', 'contribution_id');

    $lineItemTable = array(
      'rows' => array(),
    );
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
        'id' => $lineItem['id'],
      );
      $lineItemTable['rows'][$key] = array(
        'id' => $lineItem['id'],
        'item' => $lineItem['label'],
        'financial_type' => CRM_Core_PseudoConstant::getLabel('CRM_Contribute_BAO_Contribution', 'financial_type_id', $lineItem['financial_type_id']),
        'qty' => $lineItem['qty'],
        'unit_price' => $lineItem['unit_price'],
        'total_price' => $lineItem['line_total'],
        'currency' => $order['currency'],
        'actions' => empty($membershipID) ? CRM_Core_Action::formLink($links, $mask, $actions) : '',
      );
    }

    return $lineItemTable;
  }

  /**
   * Function used to return 'Add Item(s)' link, later added above total amount by jquery
   *
   * @param int $contributionID
   *
   * @return string
   *   HTML anchor tag of 'Add Item(s)' action
   */
  public static function getAddLineItemLink($contributionID) {
    $permissions = array(CRM_Core_Permission::VIEW);
    if (CRM_Core_Permission::check('edit contributions')) {
      $permissions[] = CRM_Core_Permission::EDIT;
    }
    if (CRM_Core_Permission::check('delete in CiviContribute')) {
      $permissions[] = CRM_Core_Permission::DELETE;
    }
    $mask = CRM_Core_Action::mask($permissions);

    $links = array(
      CRM_Core_Action::ADD => array(
        'name' => ts('Add Item(s)'),
        'url' => 'civicrm/lineitem/add',
        'qs' => 'reset=1&contribution_id=%%contribution_id%%',
        'title' => ts('Add Line-item(s)'),
      ),
    );

    return sprintf('<b>%s</b>',
      CRM_Core_Action::formLink(
        $links, $mask,
        array(
          'contribution_id' => $contributionID,
        )
      )
    );
  }

  /**
   * Function used to format lineItem lists by appending edit and cancel item action links with label
   *
   * @param array $lineitems
   *   list of lineitems
   *
   */
  public static function formatLineItemList(&$lineItems) {
    foreach ($lineItems as $priceSetID => $records) {
      if ($records != 'skip') {
        foreach ($records as $lineItemID => $lineItem) {
          // do not show cancel and edit actions on membership
          if ($lineItem['entity_table'] == 'civicrm_membership') {
            continue;
          }
          $actionlinks = sprintf("
            <a href=%s title='Edit Item'><i class='crm-i fa-pencil'></i></a>&nbsp;
            <a href=%s title='Cancel Item'><i class='crm-i fa-times'></i></a>&nbsp;",
            CRM_Utils_System::url('civicrm/lineitem/edit', 'reset=1&id=' . $lineItemID),
            CRM_Utils_System::url('civicrm/lineitem/cancel', 'reset=1&id=' . $lineItemID)
          );
          if ($lineItem['field_title'] && $lineItem['html_type'] != 'Text') {
            $lineItems[$priceSetID][$lineItemID]['field_title'] = $actionlinks . $lineItems[$priceSetID][$lineItemID]['field_title'];
          }
          else {
            $lineItems[$priceSetID][$lineItemID]['label'] = $actionlinks . $lineItems[$priceSetID][$lineItemID]['label'];
          }
        }
      }
    }
  }

  /**
   * Function used to return lineItem fieldnames used for edit/add
   *
   * @return array
   *   array of field names
   */
  public static function getLineitemFieldNames() {
    return array(
      'label',
      'financial_type_id',
      'qty',
      'unit_price',
      'line_total',
    );
  }

}
