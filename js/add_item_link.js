CRM.$(function($) {
  if (CRM.vars.lineitemedit.isQuickConfig && $('#totalAmount').length) {
    $('#lineitem-block').insertAfter('#totalAmount');
    $('#totalAmount').append(CRM.vars.lineitemedit.add_link);
  }
  else {
    $('div.total_amount-section').prepend(CRM.vars.lineitemedit.add_link);
    if (CRM.vars.lineitemedit.hideHeader) {
      $('tr.columnheader').find('th:last').html('');
    }
  }
});
