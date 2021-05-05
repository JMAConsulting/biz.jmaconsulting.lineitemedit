<?php

/**
 * Collection of upgrade steps.
 */
class CRM_Lineitemedit_Upgrader extends CRM_Lineitemedit_Upgrader_Base {

  /**
  * Example: Run an external SQL script when the module is installed.
  *
  */
 public function install() {
   CRM_Lineitemedit_Util::generatePriceField();
 }

 public function uninstall() {
   CRM_Lineitemedit_Util::disableEnablePriceField();
 }

 public function disable() {
   CRM_Lineitemedit_Util::disableEnablePriceField();
 }

 public function enable() {
   CRM_Lineitemedit_Util::disableEnablePriceField(TRUE);
 }

  /**
   * Example: Run a couple simple queries.
   *
   * @return TRUE on success
   * @throws Exception
   *
   */
  public function upgrade_2000() {
    $this->ctx->log->info('Applying update 2000');
    CRM_Lineitemedit_Util::generatePriceField();
    return TRUE;
  }

  /**
   * Example: Run a couple simple queries.
   *
   * @return TRUE on success
   * @throws Exception
   *
   */
  public function upgrade_2400() {
    $this->ctx->log->info('Applying update 2400');
    CRM_Core_DAO::executeQuery('UPDATE civicrm_contribution SET net_amount = total_amount - fee_amount WHERE fee_amount IS NOT NULL AND fee_amount > 0');
    return TRUE;
  }

}
