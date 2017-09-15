CRM.$(function($) {

  function formatMoney (amount) {
    return CRM.formatMoney(amount, true, CRM.vars.lineitemedit.moneyFormat);
  }
});
