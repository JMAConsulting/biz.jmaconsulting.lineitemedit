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

    $lineItemTable = array(
      'rows' => array(),
    );
    $links = array(
      CRM_Core_Action::UPDATE => array(
        'name' => ts(''),
        'url' => 'civicrm/lineitem/edit',
        'qs' => 'reset=1&id=%%id%%',
        'title' => ts('Edit Line item'),
        'ref' => ' crm-i fa-pencil ',
      ),
      CRM_Core_Action::DELETE => array(
        'name' => ts(''),
        'url' => 'civicrm/lineitem/cancel',
        'qs' => 'reset=1&id=%%id%%',
        'title' => ts('Cancel Line item'),
        'ref' => ' crm-i fa-undo ',
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
        'actions' => ($lineItem['qty'] != 0) ? CRM_Core_Action::formLink($links, $mask, $actions) : '',
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

    // don't show 'Add Item' action link if all the non-quick-config price-field options are used or
    //   quick-config price field is used for the existing contribution
    if (self::getPriceFieldLists($contributionID, TRUE) == 0) {
      return '';
    }

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
  public static function formatLineItemList(&$lineItems, $isParticipantCount) {
    foreach ($lineItems as $priceSetID => $records) {
      if ($records != 'skip') {
        foreach ($records as $lineItemID => $lineItem) {
          // do not show cancel and edit actions on membership OR if the item is already cancelled
          if ($lineItem['qty'] == 0) {
            continue;
          }

          $actionlinks = sprintf("
            <a class='action-item crm-hover-button' href=%s title='Edit Item'><i class='crm-i fa-pencil'></i></a>
            <a class='action-item crm-hover-button' href=%s title='Cancel Item'><i class='crm-i fa-undo'></i></a>",
            CRM_Utils_System::url('civicrm/lineitem/edit', 'reset=1&id=' . $lineItemID),
            CRM_Utils_System::url('civicrm/lineitem/cancel', 'reset=1&id=' . $lineItemID)
          );
          if (!$isParticipantCount) {
            $lineItems[$priceSetID][$lineItemID]['participant_count'] = '';
          }
          $lineItems[$priceSetID][$lineItemID]['participant_count'] = $lineItems[$priceSetID][$lineItemID]['participant_count'] . "</td><td>{$actionlinks}</td>" ;
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
  public static function getTaxAmountTotalFromContributionID($contributionID) {
    $taxAmount = CRM_Core_DAO::singleValueQuery("SELECT SUM(COALESCE(tax_amount,0)) FROM civicrm_line_item WHERE contribution_id = $contributionID AND qty > 0 ");
    return CRM_Utils_Money::format($taxAmount, NULL, NULL, TRUE);
  }

  /**
   * Function used to enter financial records upon addition of lineItem
   *
   * @param int $lineItemID
   * @param CRM_Financial_DAO_FinancialTrxn $trxn
   *
   */
  public static function insertFinancialItemOnAdd($lineItem, $trxn) {
    $contribution = civicrm_api3('Contribution', 'getsingle', array('id' => $lineItem['contribution_id']));

    $revenueFinancialAccountID = CRM_Contribute_PseudoConstant::getRelationalFinancialAccount(
      $lineItem['financial_type_id'],
      'Income Account is'
    );

    if (!empty($contribution['revenue_recognition_date'])) {
      $revenueFinancialAccountID = CRM_Contribute_PseudoConstant::getRelationalFinancialAccount(
        $lineItem['financial_type_id'],
        'Deferred Revenue Account is'
      );
    }

    $newFinancialItem = array(
      'transaction_date' => date('YmdHis'),
      'contact_id' => $contribution['contact_id'],
      'description' => ($lineItem['qty'] != 1 ? $lineItem['qty'] . ' of ' : '') . $lineItem['label'],
      'amount' => $lineItem['line_total'],
      'currency' => $contribution['currency'],
      'financial_account_id' => $revenueFinancialAccountID,
      'status_id' => array_search('Unpaid', CRM_Core_PseudoConstant::get('CRM_Financial_DAO_FinancialItem', 'status_id')),
      'entity_table' => 'civicrm_line_item',
      'entity_id' => $lineItem['id'],
    );
    $trxnId = array('id' => $trxn);

    // create financial item for added line item
    $newFinancialItemDAO = CRM_Financial_BAO_FinancialItem::create($newFinancialItem, NULL, $trxnId);

      if (!empty($lineItem['tax_amount']) && $lineItem['tax_amount'] != 0) {
      $taxTerm = CRM_Utils_Array::value('tax_term', Civi::settings()->get('contribution_invoice_settings'));
      $taxFinancialItemInfo = array_merge($newFinancialItem, array(
        'amount' => $lineItem['tax_amount'],
        'description' => $taxTerm,
        'financial_account_id' => CRM_Contribute_BAO_Contribution::getFinancialAccountId($lineItem['financial_type_id']),
      ));
      // create financial item for tax amount related to added line item
      CRM_Financial_BAO_FinancialItem::create($taxFinancialItemInfo, NULL, $trxnId);
    }

    $lineItem['financial_item_id'] = $newFinancialItemDAO->id;
    self::createDeferredTrxn($contribution['id'], $lineItem);
  }

  /**
   * Function used to enter deferred revenue records upon add/edit/cancel of lineitem
   *
   * @param int $contributionID
   * @param array $lineItem
   *
   */
  public static function createDeferredTrxn($contributionID, $lineItem) {
    if (CRM_Contribute_BAO_Contribution::checkContributeSettings('deferred_revenue_enabled')) {
      $contributionDAO = new CRM_Contribute_DAO_Contribution();
      $contributionDAO->id = $contributionID;
      $contributionDAO->find(TRUE);
      $revenueRecognitionDate = $contributionDAO->revenue_recognition_date;
      if (!CRM_Utils_System::isNull($revenueRecognitionDate)) {
        $results = civicrm_api3('EntityFinancialAccount', 'get', array(
          'entity_table' => 'civicrm_financial_type',
          'entity_id' => $lineItem['financial_type_id'],
          'account_relationship' => array('IN' => array('Income Account is', 'Deferred Revenue Account is')),
        ));
        if ($results['count'] != 2) {
          return;
        }
        $trxnParams = array(
          'contribution_id' => $contributionDAO->id,
          'fee_amount' => '0.00',
          'currency' => $contributionDAO->currency,
          'trxn_id' => $contributionDAO->trxn_id,
          'status_id' => $contributionDAO->contribution_status_id,
          'payment_instrument_id' => $contributionDAO->payment_instrument_id,
          'check_number' => $contributionDAO->check_number,
          'total_amount' => $lineItem['line_total'],
          'trxn_date' => CRM_Utils_Date::isoToMysql($revenueRecognitionDate),
        );
        $accountRel = key(CRM_Core_PseudoConstant::accountOptionValues('account_relationship', NULL, " AND v.name LIKE 'Income Account is' "));
        foreach ($results['values'] as $result) {
          if ($result['account_relationship'] == $accountRel) {
            $trxnParams['to_financial_account_id'] = $result['financial_account_id'];
          }
          else {
            $trxnParams['from_financial_account_id'] = $result['financial_account_id'];
          }
        }
        $financialTxn = CRM_Core_BAO_FinancialTrxn::create($trxnParams);
        $entityParams = array(
          'entity_id' => $lineItem['financial_item_id'],
          'entity_table' => 'civicrm_financial_item',
          'amount' => $lineItem['line_total'],
          'financial_trxn_id' => $financialTxn->id,
        );
        civicrm_api3('EntityFinancialTrxn', 'create', $entityParams);
      }
    }
  }

  /**
   * Function used to enter/update financial records upon edit of lineItem
   *
   * @param int $lineItemID
   * @param money $balanceTaxAmount
   *
   */
  public static function insertFinancialItemOnEdit($lineItemID,
    $previousLineItem
  ) {

    $lineItem = civicrm_api3('LineItem', 'Getsingle', array(
      'id' => $lineItemID,
    ));
    $lineItem['tax_amount'] = $taxAmount = CRM_Utils_Array::value('tax_amount', $lineItem, 0);
    $newLineTotal = $lineItem['line_total'] + $lineItem['tax_amount'];
    $oldLineTotal = $previousLineItem['line_total'] + CRM_Utils_Array::value('tax_amount', $previousLineItem, 0);
    $recordChangedAttributes = array(
      'financialTypeChanged' => ($lineItem['financial_type_id'] != $previousLineItem['financial_type_id']),
      'amountChanged' => ($newLineTotal != $oldLineTotal),
      'taxAmountChanged' => ($lineItem['tax_amount'] != CRM_Utils_Array::value('tax_amount', $previousLineItem, 0)),
    );

    $previousFinancialItem = CRM_Financial_BAO_FinancialItem::getPreviousFinancialItem($lineItemID);
    $financialItem = array(
      'transaction_date' => date('YmdHis'),
      'contact_id' => $previousFinancialItem['contact_id'],
      'description' => $previousFinancialItem['description'],
      'currency' => $previousFinancialItem['currency'],
      'financial_account_id' => $previousFinancialItem['financial_account_id'],
      'entity_id' => $lineItemID,
      'entity_table' => 'civicrm_line_item',
    );

    $balanceTaxAmount = $lineItem['tax_amount'] - CRM_Utils_Array::value('tax_amount', $previousLineItem, 0);
    $balanceAmount = $lineItem['line_total'] - $previousLineItem['line_total'];
    if ($recordChangedAttributes['financialTypeChanged']) {
      self::recordChangeInFT(
        $lineItem,
        $previousLineItem,
        $financialItem
      );
    }

    // if amount is changed
    if ($recordChangedAttributes['amountChanged']) {
      $financialItem['description'] = ($lineItem['qty'] > 1 ? $lineItem['qty'] . ' of ' : '') . $lineItem['label'];
      self::recordChangeInAmount(
        $lineItem['contribution_id'],
        $financialItem,
        $balanceAmount,
        $balanceTaxAmount,
        $recordChangedAttributes['taxAmountChanged']
      );
    }
  }

  public static function getRelatedCancelFinancialTrxn($financialItemID) {
       $query = "SELECT ft.*
   FROM civicrm_financial_trxn ft
   INNER JOIN civicrm_entity_financial_trxn eft ON eft.financial_trxn_id = ft.id AND eft.entity_table = 'civicrm_financial_item'
   WHERE eft.entity_id = %1
   ORDER BY eft.id DESC
   LIMIT 1; ";

       $dao = CRM_Core_DAO::executeQuery($query, array(1 => array($financialItemID, 'Integer')));
       $financialTrxn = array();
       while ($dao->fetch()) {
         $financialTrxn = $dao->toArray();
         unset($financialTrxn['id']);
         $financialTrxn = array_merge($financialTrxn, array(
           'trxn_date' => date('YmdHis'),
           'total_amount' => -$financialTrxn['total_amount'],
           'net_amount' => -$financialTrxn['net_amount'],
           'entity_table' => 'civicrm_financial_item',
           'entity_id' => $financialItemID,
         ));
       }

       return $financialTrxn;
     }

  /**
   * Function used fetch the latest Sale tax related financial item
   *
   * @param int $entityId
   *
   * @return array
   *       FinancialItem.Get API results
   */
  public static function getPreviousTaxableFinancialItem($entityId) {
    $params = array(
      'entity_id' => $entityId,
      'entity_table' => 'civicrm_line_item',
      'options' => array('limit' => 1, 'sort' => 'id DESC'),
    );
    $salesTaxFinancialAccounts = civicrm_api3('FinancialAccount', 'get', array('is_tax' => 1));
    if ($salesTaxFinancialAccounts['count']) {
      $params['financial_account_id'] = array('IN' => array_keys($salesTaxFinancialAccounts['values']));
    }
    return civicrm_api3('FinancialItem', 'get', $params);
  }

  /**
   * Function used to return list of price fields,
   *   later used in 'Add item' form
   *
   * @return array|int $priceFields
   *      list of price fields OR count of price fields
   */
  public static function getPriceFieldLists($contributionID, $getCount = FALSE) {
    // fetch the component that the price-set is used for
    $usedForComponent = CRM_Core_DAO::singleValueQuery("SELECT ps.extends
      FROM civicrm_line_item AS li
      INNER JOIN civicrm_price_field AS pf ON li.price_field_id = pf.id
      INNER JOIN civicrm_price_set AS ps ON pf.price_set_id = ps.id
      WHERE li.contribution_id = {$contributionID}"
    );

    $sql = "
SELECT    pfv.id as pfv_id,
          pfv.label as pfv_label,
          pf.id as pf_id,
          ps.title as ps_label,
          ps.is_quick_config as is_quick,
          ps.id as set_id
FROM      civicrm_price_field_value as pfv
LEFT JOIN civicrm_price_field as pf ON (pf.id = pfv.price_field_id)
LEFT JOIN civicrm_price_set as ps ON (ps.id = pf.price_set_id AND ps.is_active = 1)
WHERE  ps.extends = {$usedForComponent} AND ps.is_quick_config = 0 AND pfv.id NOT IN (
   SELECT li.price_field_value_id
    FROM civicrm_line_item as li
    WHERE li.contribution_id = {$contributionID} AND li.qty != 0
  )
ORDER BY  ps.id, pf.weight ;
";
    $dao = CRM_Core_DAO::executeQuery($sql);

    $dao1 = CRM_Core_DAO::executeQuery("
    SELECT pf.id
     FROM civicrm_line_item as li
     INNER JOIN civicrm_price_field pf ON pf.id = li.price_field_id
     WHERE li.contribution_id = {$contributionID} AND li.qty != 0 AND pf.html_type <> 'Checkbox'
    ");
    $excludePriceFields = array();
    while ($dao1->fetch()) {
      $excludePriceFields[] = $dao1->id;
    }

    $priceFields = array();
    while ($dao->fetch()) {
      // exclude price fields which belong to other price-set that the existing contribution
      //  doesn't have any lineitem
      if (in_array($dao->pf_id, $excludePriceFields)) {
        continue;
      }
      $isQuickConfigSpecialChar = ($dao->is_quick == 1) ? '<b>*</b>' : '';
      $priceFields[$dao->pfv_id] = sprintf("%s%s :: %s", $isQuickConfigSpecialChar, $dao->ps_label, $dao->pfv_label);
    }

    if ($getCount) {
      return count($priceFields);
    }

    return $priceFields;
  }

  /**
   * AJAX function used to return list of price field information
   *   on given price field value id as 'pfv_id'
   *
   * @return json
   *      list of price field information
   */
  public static function getPriceFieldInfo() {
    if (!empty($_GET['pfv_id'])) {
      $priceFieldValueID = $_GET['pfv_id'];
      $priceFieldValueInfo = civicrm_api3('PriceFieldValue', 'getsingle', array('id' => $priceFieldValueID));

      // calculate tax amount
      $contributeSettings = Civi::settings()->get('contribution_invoice_settings');
      $taxRates = CRM_Core_PseudoConstant::getTaxRates();
      if (!empty($contributeSettings['invoicing']) &&
        array_key_exists($priceFieldValueInfo['financial_type_id'], $taxRates)
      ) {
        $taxRate = $taxRates[$priceFieldValueInfo['financial_type_id']];
        $priceFieldValueInfo['tax_amount'] = CRM_Utils_Array::value('tax_amount', CRM_Contribute_BAO_Contribution_Utils::calculateTaxAmount(
          $priceFieldValueInfo['amount'],
          $taxRate
        ), 0.00);
      }

      return CRM_Utils_JSON::output(array(
        'qty' => 1,
        'label' => $priceFieldValueInfo['label'],
        'financial_type_id' => $priceFieldValueInfo['financial_type_id'],
        'unit_price' => $priceFieldValueInfo['amount'],
        'line_total' => $priceFieldValueInfo['amount'],
        'tax_amount' => CRM_Utils_Array::value('tax_amount', $priceFieldValueInfo, 0.00),
      ));
    }
  }

  /**
   * Function used to return lineItem fieldnames used for edit/add
   *
   * @param bool $isAddItem
   *
   * @return array
   *   array of field names
   */
  public static function getLineitemFieldNames($isAddItem = FALSE) {
    $fieldNames =  array(
      'label',
      'financial_type_id',
      'qty',
      'unit_price',
      'line_total',
    );

    if ($isAddItem) {
      array_unshift($fieldNames, "price_field_value_id");
    }

    // if tax is enabled append tax_amount field name
    $contributeSettings = Civi::settings()->get('contribution_invoice_settings');
    if (!empty($contributeSettings['invoicing'])) {
      $fieldNames = array_merge($fieldNames, array('tax_amount'));
    }

    return $fieldNames;
  }

  /**
   * Record adjusted amount, copied from CRM_Event_BAO_Participant::recordAdjustedAmt(..) to tackle a core bug
   *
   * @param int $updatedAmount
   * @param int $contributionId
   * @param int $taxAmount
   * @param money $previousTaxAmount
   *
   * @return bool|\CRM_Core_BAO_FinancialTrxn
   */
  public static function recordAdjustedAmt($updatedAmount, $contributionId, $taxAmount = NULL, $createTrxn = TRUE) {
    $pendingAmount = CRM_Core_BAO_FinancialTrxn::getBalanceTrxnAmt($contributionId);
    $pendingAmount = CRM_Utils_Array::value('total_amount', $pendingAmount, 0);

    $paidAmount = CRM_Utils_Array::value(
      'paid',
      CRM_Contribute_BAO_Contribution::getPaymentInfo(
        $contributionId,
        'contribution',
        FALSE,
        TRUE
      )
    );

    $balanceAmt = $updatedAmount - $paidAmount - $pendingAmount;

    $contributionStatuses = CRM_Contribute_PseudoConstant::contributionStatus(NULL, 'name');
    $partiallyPaidStatusId = array_search('Partially paid', $contributionStatuses);
    $pendingRefundStatusId = array_search('Pending refund', $contributionStatuses);
    $completedStatusId = array_search('Completed', $contributionStatuses);

    $updatedContributionDAO = new CRM_Contribute_BAO_Contribution();
    $adjustedTrxn = $skip = FALSE;
    if ($balanceAmt) {
      if ($updatedAmount > $paidAmount) {
        $contributionStatusVal = $partiallyPaidStatusId;
      }
      elseif ($balanceAmt < $paidAmount) {
        $contributionStatusVal = $pendingRefundStatusId;
      }
      elseif ($balanceAmt = $paidAmount) {
        //skip updating the contribution status if no payment is made
        $skip = TRUE;
      }
      // update contribution status and total amount without trigger financial code
      // as this is handled in current BAO function used for change selection
      $updatedContributionDAO->id = $contributionId;
      if (!$skip) {
        $updatedContributionDAO->contribution_status_id = $contributionStatusVal;
      }
      $updatedContribution = civicrm_api3(
        'Contribution',
        'getsingle',
        array(
          'id' => $contributionId,
          'return' => array('fee_amount'),
        )
      );
      $updatedContributionDAO->total_amount = $updatedAmount;
      $updatedContributionDAO->net_amount = $updatedAmount - CRM_Utils_Array::value('fee_amount', $updatedContribution, 0);
      if ($taxAmount) {
        $updatedContributionDAO->tax_amount = $taxAmount;
      }
      $updatedContributionDAO->save();
      if (!$createTrxn) {
        return NULL;
      }
      $adjustedTrxn = self::createFinancialTrxnEntry($contributionId, $balanceAmt);
    }

    return $adjustedTrxn;
  }

  /**
   * Function used to tell that given price field ID support variable Qty or not
   *
   * @param int $priceFieldValueID
   *
   * @return bool
   *
   */
  public static function isPriceFieldSupportQtyChange($priceFieldValueID) {
    $is_enter_qty = CRM_Core_DAO::singleValueQuery("SELECT pf.is_enter_qty
      FROM civicrm_price_field pf
      INNER JOIN civicrm_price_field_value pfv ON pfv.price_field_id = pf.id
      WHERE pfv.id = {$priceFieldValueID}
    ");
    return (bool) $is_enter_qty;
  }

  /**
   * Function used to cancel membership or participant registration
   *
   * @param int $entityID
   * @param string $entityTable
   *
   */
  public static function cancelEntity($entityID, $entityTable) {
    switch ($entityTable) {
      case 'civicrm_membership':
        $cancelStatusID =  CRM_Core_PseudoConstant::getKey('CRM_Member_BAO_Membership', 'status_id', 'Cancelled');
        civicrm_api3('Membership', 'create', array(
          'id' => $entityID,
          'status_id' => $cancelStatusID,
        ));
        break;

      case 'civicrm_participant':
        civicrm_api3('Participant', 'create', array(
          'id' => $entityID,
          'status_id' => CRM_Core_PseudoConstant::getKey('CRM_Event_BAO_Participant', 'status_id', 'Cancelled'),
        ));
        break;

      default:
        break;
    }
  }

  /**
   * Function used to add membership or participant record on adding related line-item
   *
   * @param int $priceFieldValueID
   * @param int $contributionID
   * @param int $qty
   *
   * @return array
   */
  public static function addEntity($priceFieldValueID, $contributionID, $qty) {
    $entityInfo = $eventID = NULL;
    $entityTable = 'civicrm_contribution';
    $entityID = $contributionID;

    $sql = "
    SELECT pf.price_set_id as ps_id, pfv.membership_type_id mt_id, pfv.membership_num_terms m_nt, pfv.label as pfv_label, pfv.amount as pfv_amount
    FROM civicrm_price_field pf
    INNER JOIN civicrm_price_field_value pfv ON pfv.price_field_id = pf.id
    WHERE pfv.id = %1
     ";

     $dao = CRM_Core_DAO::executeQuery($sql, array(1 => array($priceFieldValueID, 'Integer')));
     while ($dao->fetch()) {
       $entityInfo = $dao->toArray();
       break;
     }

     if (!empty($entityInfo['mt_id'])) {
       $entityTable = 'civicrm_membership';
     }
     else {
       $eventID = CRM_Core_DAO::singleValueQuery("
        SELECT entity_id FROM civicrm_price_set_entity
          WHERE `entity_table` = 'civicrm_event' AND `price_set_id` = " . $entityInfo['ps_id']
       );
       $entityTable = $eventID ? 'civicrm_participant' : $entityTable;
     }

     switch ($entityTable) {
       case 'civicrm_membership':
        $memTypeNumTerms = CRM_Utils_Array::value('m_nt', $entityInfo, 1);
        $memTypeNumTerms = $qty * $memTypeNumTerms;
        // NOTE: membership.create API already calculate membership dates
        $membership = civicrm_api3('Membership', 'create', array(
          'membership_type_id' => $entityInfo['mt_id'],
          'contact_id' => CRM_Core_DAO::getFieldValue('CRM_Contribute_DAO_Contribution', $contributionID, 'contact_id'),
          'num_terms' => $memTypeNumTerms,
        ));
        $entityID = $membership['id'];
        civicrm_api3('MembershipPayment', 'create', array(
          'membership_id' => $entityID,
          'contribution_id' => $contributionID,
        ));
        break;

       case 'civicrm_participant':
        $roleIDs = CRM_Core_DAO::getFieldValue('CRM_Event_DAO_Event', $eventID, 'default_role_id');
        $roleIDs = (array) explode(CRM_Core_DAO::VALUE_SEPARATOR, $roleIDs);
        $feeLevel = sprintf("%s - %d", $entityInfo['pfv_label'], (int) $qty);
        $participant = civicrm_api3('Participant', 'create', array(
          'event_id' => $eventID,
          'contact_id' => CRM_Core_DAO::getFieldValue('CRM_Contribute_DAO_Contribution', $contributionID, 'contact_id'),
          'role_id' => $roleIDs,
          'fee_amount' => $entityInfo['pfv_amount'],
          'fee_level' => $feeLevel,
          'is_pay_later' => 1,
        ));
        $entityID = $participant['id'];
        civicrm_api3('ParticipantPayment', 'create', array(
          'participant_id' => $entityID,
          'contribution_id' => $contributionID,
        ));
        break;
     }

     return array($entityTable, $entityID);
  }

  public static function recordChangeInAmount(
    $contributionId,
    $financialItem,
    $balanceAmount,
    $balanceTaxAmount,
    $taxAmountChanged
  ) {

    $trxnId = self::createFinancialTrxnEntry($contributionId, $balanceAmount + $balanceTaxAmount);
    $trxnId = array('id' => $trxnId);
    $contribution = civicrm_api3(
      'Contribution',
      'getsingle',
      array(
        'id' => $contributionId,
        'return' => array('revenue_recognition_date'),
      )
    );
    $lineItem = civicrm_api3(
      'lineItem',
      'getsingle',
      array(
        'id' => $financialItem['entity_id'],
        'return' => array('financial_type_id'),
      )
    );
    $financialItem['amount'] = $balanceAmount;
    $financialItem['status_id'] = CRM_Core_PseudoConstant::getKey('CRM_Financial_DAO_FinancialItem', 'status_id', 'Unpaid');
    $accountRelName = 'Income Account is';
    if (CRM_Utils_Array::value('revenue_recognition_date', $contribution) &&
      strtotime($contribution['revenue_recognition_date']) > strtotime(date('Ymt'))
    ) {
      $accountRelName = 'Deferred Revenue Account is';
    }
    $financialItem['financial_account_id'] = CRM_Contribute_PseudoConstant::getRelationalFinancialAccount($lineItem['financial_type_id'], $accountRelName);
    CRM_Financial_BAO_FinancialItem::create($financialItem, NULL, $trxnId);
    if ($taxAmountChanged && $balanceTaxAmount != 0) {
      $taxTerm = CRM_Utils_Array::value('tax_term', Civi::settings()->get('contribution_invoice_settings'));
      $taxFinancialItemInfo = array_merge($financialItem, array(
        'amount' => $balanceTaxAmount,
        'description' => $taxTerm,
        'financial_account_id' => CRM_Contribute_PseudoConstant::getRelationalFinancialAccount($lineItem['financial_type_id'], 'Sales Tax Account is'),
      ));
      // create financial item for tax amount related to added line item
      CRM_Financial_BAO_FinancialItem::create($taxFinancialItemInfo, NULL, $trxnId);
    }
  }

  public static function recordChangeInFT(
    $newLineItem,
    $prevLineItem,
    $financialItem
  ) {
    $contributionId = $newLineItem['contribution_id'];
    $contribution = civicrm_api3(
      'Contribution',
      'getsingle',
      array(
        'id' => $contributionId,
        'return' => array('revenue_recognition_date', 'payment_instrument_id'),
      )
    );

    $accountRelName = 'Income Account is';
    if (CRM_Utils_Array::value('revenue_recognition_date', $contribution) &&
      strtotime($contribution['revenue_recognition_date']) > strtotime(date('Ymt'))
    ) {
      $accountRelName = 'Deferred Revenue Account is';
    }

    $trxnArray[1] = array(
      'ft_amount' => -($prevLineItem['line_total'] + $prevLineItem['tax_amount']),
      'fi_amount' => -$prevLineItem['line_total'],
      'tax_amount' => -$prevLineItem['tax_amount'],
      'tax_ft' => $prevLineItem['financial_type_id'],
    );
    $taxRates = CRM_Core_PseudoConstant::getTaxRates();
    $taxRates = CRM_Utils_Array::value($newLineItem['financial_type_id'], $taxRates, 0);
    $newtax = CRM_Contribute_BAO_Contribution_Utils::calculateTaxAmount($prevLineItem['line_total'], $taxRates);
    $trxnArray[2] = array(
      'ft_amount' => ($prevLineItem['line_total'] + $newtax['tax_amount']),
      'fi_amount' => $prevLineItem['line_total'],
      'tax_amount' => $newtax['tax_amount'],
      'tax_ft' => $newLineItem['financial_type_id'],
    );
    $trxnArray[2]['financial_account_id'] = CRM_Contribute_PseudoConstant::getRelationalFinancialAccount($newLineItem['financial_type_id'], $accountRelName);

    $trxnArray[1]['to_financial_account_id'] = $trxnArray[2]['to_financial_account_id'] = CRM_Financial_BAO_FinancialTypeAccount::getInstrumentFinancialAccount($contribution['payment_instrument_id']);

    $financialItem['status_id'] = CRM_Core_PseudoConstant::getKey('CRM_Financial_DAO_FinancialItem', 'status_id', 'Paid');
    foreach ($trxnArray as $values) {
      $trxnId = self::createFinancialTrxnEntry($contributionId, $values['ft_amount'], $values['to_financial_account_id']);
      if (!empty($values['financial_account_id'])) {
        $financialItem['financial_account_id'] = $values['financial_account_id'];
      }
      $financialItem['amount'] = $values['fi_amount'];
      $trxnId = array('id' => $trxnId);
      CRM_Financial_BAO_FinancialItem::create($financialItem, NULL, $trxnId);
      if ($values['tax_amount'] != 0) {
        $taxTerm = CRM_Utils_Array::value('tax_term', Civi::settings()->get('contribution_invoice_settings'));
        $taxFinancialItemInfo = array_merge($financialItem, array(
          'amount' => $values['tax_amount'],
          'description' => $taxTerm,
          'financial_account_id' => CRM_Contribute_PseudoConstant::getRelationalFinancialAccount($values['tax_ft'], 'Sales Tax Account is'),
        ));
        // create financial item for tax amount related to added line item
        CRM_Financial_BAO_FinancialItem::create($taxFinancialItemInfo, NULL, $trxnId);
      }
    }
  }

  public static function createFinancialTrxnEntry($contributionId, $amount, $toFinancialAccount = NULL) {
    $contribution = civicrm_api3('Contribution', 'getsingle', array('id' => $contributionId));
    $isPayment = TRUE;
    if (!$toFinancialAccount) {
      $toFinancialAccount = CRM_Contribute_PseudoConstant::getRelationalFinancialAccount($contribution['financial_type_id'], 'Accounts Receivable Account is');
      $isPayment = FALSE;
    }
    $adjustedTrxnValues = array(
      'from_financial_account_id' => NULL,
      'to_financial_account_id' => $toFinancialAccount,
      'total_amount' => $amount,
      'net_amount' => $amount,
      // TODO: What should be status incase of FT change?
      'status_id' => $contribution['contribution_status_id'],
      'payment_instrument_id' => $contribution['payment_instrument_id'],
      'contribution_id' => $contributionId,
      'trxn_date' => date('YmdHis'),
      'currency' => $contribution['currency'],
      'is_payment' => $isPayment,
    );
    $adjustedTrxn = CRM_Core_BAO_FinancialTrxn::create($adjustedTrxnValues);
    return $adjustedTrxn->id;
  }

}
