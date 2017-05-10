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

    // TODO: order.get API doesn't fetch cancelled line_items
    if (empty($lineItems)) {
      $lineItems = civicrm_api3('LineItem', 'Get', array('contribution_id' => $order['contribution_id']));
      $lineItems = $lineItems['values'];
    }

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
        'actions' => empty($membershipID || $lineItem['qty'] == 0) ? CRM_Core_Action::formLink($links, $mask, $actions) : '',
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
    // don't show Add Item(s) if the contribution is related to membership payment
    if (CRM_Core_DAO::getFieldValue(
      'CRM_Member_DAO_MembershipPayment',
      $contributionID,
      'membership_id',
      'contribution_id'
    )) {
      return '';
    }

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
          // do not show cancel and edit actions on membership OR if the item is already cancelled
          if ($lineItem['entity_table'] == 'civicrm_membership' || $lineItem['qty'] == 0) {
            continue;
          }
          $actionlinks = sprintf("
            <a class='action-item crm-hover-button' href=%s title='Edit Item'><i class='crm-i fa-pencil'></i></a>
            <a class='action-item crm-hover-button' href=%s title='Cancel Item'><i class='crm-i fa-times'></i></a>",
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
   * Function used to return total tax amount of a contribution, calculated from associated line item records
   *
   * @param int $contributionID
   *
   * @return money
   *       total tax amount in money format
   */
  public static function getTaxamountTotalFromContributionID($contributionID) {
    $taxAmount = CRM_Core_DAO::singleValueQuery("SELECT SUM(COALESCE(tax_amount,0)) FROM civicrm_line_item WHERE contribution_id = $contributionID AND qty > 0 ");
    return CRM_Utils_Money::format($taxAmount, NULL, NULL, TRUE);
  }

  /**
   * Function used to enter financial records upon cancellation of lineItem
   *
   * @param int $lineItemID
   * @param money $previousLineItemTaxAmount
   * @param obj $trxn
   *
   */
  public static function insertFinancialItemOnCancel($lineItemID, $previousLineItemTaxAmount, $trxn) {
    // gathering necessary info to record negative (deselected) financial_item
    $getPreviousFinancialItemSQL = "
SELECT fi.*
  FROM civicrm_financial_item fi
    LEFT JOIN civicrm_line_item li ON (li.id = fi.entity_id AND fi.entity_table = 'civicrm_line_item')
WHERE (li.entity_table = 'civicrm_line_item' AND li.entity_id = {$lineItemID})
GROUP BY li.entity_table, li.entity_id, price_field_value_id, fi.id
    ";

    $previousFinancialItemDAO = CRM_Core_DAO::executeQuery($getPreviousFinancialItemSQL);
    $trxnId = array('id' => $trxn->id);
    while ($previousFinancialItemDAO->fetch()) {
      $previousFinancialItemInfoValues = (array) $previousFinancialItemDAO;

      $previousFinancialItemInfoValues['transaction_date'] = date('YmdHis');
      $previousFinancialItemInfoValues['amount'] = -$previousFinancialItemInfoValues['amount'];

      // the below params are not needed
      unset($previousFinancialItemInfoValues['id']);
      unset($previousFinancialItemInfoValues['created_date']);

      // create financial item for deselected or cancelled line item
      CRM_Financial_BAO_FinancialItem::create($previousFinancialItemInfoValues, NULL, $trxnId);

      // insert financial item related to tax
      if (!empty($previousLineItemTaxAmount)) {
        $taxTerm = CRM_Utils_Array::value('tax_term', Civi::settings()->get('contribution_invoice_settings'));
        $taxFinancialItemInfo = array_merge($previousFinancialItemInfoValues, array(
          'amount' => -$previousLineItemTaxAmount,
          'description' => $taxTerm,
        ));
        // create financial item for tax amount related to deselected or cancelled line item
        CRM_Financial_BAO_FinancialItem::create($taxFinancialItemInfo, NULL, $trxnId);
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
