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
WHERE fi.entity_id = {$lineItemID}
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
      $financialItemDAO = CRM_Financial_BAO_FinancialItem::create($previousFinancialItemInfoValues, NULL, $trxnId);

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

      $lineItem = civicrm_api3('LineItem', 'getsingle', array('id' => $lineItemID));
      $lineItem['financial_item_id'] = $financialItemDAO->id;
      self::createDeferredTrxn($lineItem['contribution_id'], $lineItem);
    }
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
    $trxnId = array('id' => $trxn->id);

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
   * @param money $taxAmount
   * @param CRM_Financial_DAO_FinancialTrxn $trxn
   * @param int $recordChangedAttributes
   * @param money $balanceAmount
   * @param money $balanceTaxAmount
   *
   */
  public static function insertFinancialItemOnEdit($lineItemID,
    $taxAmount,
    $trxn,
    $recordChangedAttributes,
    $balanceAmount,
    $balanceTaxAmount
  ) {

    $lineItem = civicrm_api3('LineItem', 'Getsingle', array(
      'id' => $lineItemID,
    ));

    $previousFinancialItem = CRM_Financial_BAO_FinancialItem::getPreviousFinancialItem($lineItemID);

    $trxnId = array('id' => $trxn->id);
    if ($recordChangedAttributes['amountChanged']) {
      $financialItemDAO = NULL;
      unset($previousFinancialItem['id']);
      unset($previousFinancialItem['created_date']);
      $previousFinancialItem['transaction_date'] = date('YmdHis');
      $previousFinancialItem['description'] = $lineItem['label'];
      $previousFinancialItem['amount'] = $balanceAmount;
      $previousFinancialItem['status_id'] = CRM_Core_PseudoConstant::getKey('CRM_Financial_DAO_FinancialItem', 'status_id', 'Unpaid');
      if ($recordChangedAttributes['financialTypeChanged']) {
        $previousFinancialItem['financial_account_id'] = CRM_Contribute_PseudoConstant::getRelationalFinancialAccount(
          $lineItem['financial_type_id'],
          'Accounts Receivable Account is'
        );
      }

      $financialItemDAO = CRM_Financial_BAO_FinancialItem::create($previousFinancialItem, NULL, $trxnId);
      $lineItem['financial_item_id'] = $financialItemDAO->id;
      self::createDeferredTrxn($lineItem['contribution_id'], $lineItem);
    }
    // if only financial type is changed
    elseif ($recordChangedAttributes['financialTypeChanged']) {
      $params = array(
        'entity_id' => $lineItemID,
        'entity_table' => 'civicrm_line_item',
        'amount' => $lineItem['line_total'],
      );
      $salesTaxFinancialAccounts = civicrm_api3('FinancialAccount', 'get', array('is_tax' => 1));
      if ($salesTaxFinancialAccounts['count']) {
        $params['financial_account_id'] = array('NOT IN' => array_keys($salesTaxFinancialAccounts['values']));
      }
      $previousFinancialItems = CRM_Utils_Array::value('values', civicrm_api3('FinancialItem', 'get', $params));
      foreach ($previousFinancialItems as $previousFinancialItem) {
        // reverse financial trxn
        $financialTrxn = self::getRelatedCancelFinancialTrxn($previousFinancialItem['id']);
        $trxn = CRM_Core_BAO_FinancialTrxn::create($financialTrxn, array('entity_table' => $financialTrxn['entity_table'], 'entity_id' => $financialTrxn['entity_id']));
        $tempTrxnID = array('id' => $trxn->id);

        // reverse financial item
        unset($previousFinancialItem['id']);
        $previousFinancialItem['transaction_date'] = date('YmdHis');
        $previousFinancialItem['amount'] = -$lineItem['line_total'];
        CRM_Financial_BAO_FinancialItem::create($previousFinancialItem, NULL, $tempTrxnID);

        // create new financial trxn
        $financialTrxn['total_amount'] = $financialTrxn['net_amount'] = $lineItem['line_total'];
        $financialTrxn['to_financial_account_id'] = CRM_Contribute_PseudoConstant::getRelationalFinancialAccount($lineItem['financial_type_id'], 'Income Account is');
        $trxn = CRM_Core_BAO_FinancialTrxn::create($financialTrxn, array('entity_table' => $financialTrxn['entity_table'], 'entity_id' => $financialTrxn['entity_id']));
        $tempTrxnID = array('id' => $trxn->id);

        // create new financial item
        $previousFinancialItem['amount'] = $lineItem['line_total'];
        $financialTrxn['to_financial_account_id'] = CRM_Contribute_PseudoConstant::getRelationalFinancialAccount($lineItem['financial_type_id'], 'Accounts Receivable Account is');
        CRM_Financial_BAO_FinancialItem::create($previousFinancialItem, NULL, $tempTrxnID);
      }
    }

    // create financial item to record changed tax amount on edit
    if (!empty($recordChangedAttributes['taxAmountChanged'])) {
      $taxTerm = CRM_Utils_Array::value('tax_term', Civi::settings()->get('contribution_invoice_settings'));
      // insert new financial item related to sale tax
      $taxFinancialItemInfo = array_merge($previousFinancialItem, array(
        'amount' => $taxAmount,
        'description' => $taxTerm,
        'financial_account_id' => CRM_Contribute_BAO_Contribution::getFinancialAccountId($lineItem['financial_type_id']),
        'status_id' => CRM_Core_PseudoConstant::getKey('CRM_Financial_DAO_FinancialItem', 'status_id', 'Unpaid'),
      ));
      unset($taxFinancialItemInfo['id']);

      // update the financial item related to Sale tax if any
      $previousTaxableFinancialItem = self::getPreviousTaxableFinancialItem($lineItemID);
      if (!empty($previousTaxableFinancialItem['id'])) {
        $taxFinancialItemInfo = $previousTaxableFinancialItem['values'][$previousTaxableFinancialItem['id']];
        $taxFinancialItemInfo['amount'] = $taxAmount;
        if ($taxFinancialItemInfo['status_id'] == 1) {
          $taxFinancialItemInfo['status_id'] = 2;
        }
      }
      // w/o id the sale tax related financial item is updated or inserted respectively
      CRM_Financial_BAO_FinancialItem::create($taxFinancialItemInfo, NULL, $trxnId);
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
WHERE  pfv.id NOT IN (
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

    // fetch the price-set that belong to the contribution's line_item's price field
    $priceSetID = CRM_Core_DAO::singleValueQuery("SELECT ps.id
      FROM civicrm_line_item AS li
      INNER JOIN civicrm_price_field AS pf ON li.price_field_id = pf.id
      INNER JOIN civicrm_price_set AS ps ON pf.price_set_id = ps.id
      WHERE li.contribution_id = {$contributionID}"
    );

    $priceFields = array();
    while ($dao->fetch()) {
      // exclude price fields which belong to other price-set that the existing contribution
      //  doesn't have any lineitem
      if (($dao->set_id != $priceSetID) || in_array($dao->pf_id, $excludePriceFields)) {
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
        $priceFieldValueInfo['tax_amount'] = CRM_Contribute_BAO_Contribution_Utils::calculateTaxAmount(
          $priceFieldValueInfo['amount'],
          $taxRate
        );
      }

      return CRM_Utils_JSON::output(array(
        'qty' => 1,
        'label' => $priceFieldValueInfo['label'],
        'financial_type_id' => $priceFieldValueInfo['financial_type_id'],
        'unit_price' => $priceFieldValueInfo['amount'],
        'line_total' => $priceFieldValueInfo['amount'],
        'tax_amount' => CRM_Utils_Array::value('tax_amount', $priceFieldValueInfo, 0),
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
   * @param int $paidAmount
   * @param int $contributionId
   *
   * @param int $taxAmount
   * @param money $previousTaxAmount
   *
   * @return bool|\CRM_Core_BAO_FinancialTrxn
   */
  public static function recordAdjustedAmt($updatedAmount, $paidAmount, $contributionId, $taxAmount = NULL, $previousTaxAmount = NULL) {
    $pendingAmount = CRM_Core_BAO_FinancialTrxn::getBalanceTrxnAmt($contributionId);
    $pendingAmount = CRM_Utils_Array::value('total_amount', $pendingAmount, 0);

    // deduct the taxamount from contribution total on cancelling a taxable line item
    if ($previousTaxAmount) {
      $updatedAmount -= $previousTaxAmount;
    }
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
      // adjusted amount financial_trxn creation
      $updatedContribution = civicrm_api3('Contribution', 'getsingle', array('id' =>$contributionId));
      $toFinancialAccount = CRM_Contribute_PseudoConstant::getRelationalFinancialAccount($updatedContribution['financial_type_id'], 'Accounts Receivable Account is');
      $adjustedTrxnValues = array(
        'from_financial_account_id' => NULL,
        'to_financial_account_id' => $toFinancialAccount,
        'total_amount' => $balanceAmt,
        'net_amount' => $balanceAmt,
        'status_id' => $updatedContribution['contribution_status_id'],
        'payment_instrument_id' => $updatedContribution['payment_instrument_id'],
        'contribution_id' => $contributionId,
        'trxn_date' => date('YmdHis'),
        'currency' => $updatedContribution['currency'],
      );
      $adjustedTrxn = CRM_Core_BAO_FinancialTrxn::create($adjustedTrxnValues);
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
        $cancelStatusID =  array_search('Cancelled', CRM_Member_PseudoConstant::membershipStatus(NULL, " name = 'Cancelled' ", 'name', FALSE, TRUE));
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

}
