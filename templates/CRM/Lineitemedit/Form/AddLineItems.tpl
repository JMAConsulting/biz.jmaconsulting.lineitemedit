{* template block that contains the new field *}
<span id="totalAmountORaddLineitem">&nbsp;OR
  {if $form.add_item.html}
    <span class="label">{$form.add_item.label}</span>
    <span>{$form.add_item.html}</span>
  {else}
    <a href=# id='add-items' class="action-item crm-hover-button">{ts}Add Item(s){/ts}</a>
  {/if}
</span>
<div id="lineitem-add-block" class="status">
  {if $action eq 1}
    <span id='choose-manual'><a href=# class="action-item crm-hover-button">{ts}Choose manual contribution amount{/ts}</a></span>
  {/if}
<table id='info'>
  <tr class="line-item-columnheader">
    <th>{ts}Item{/ts}</th>
    <th>{ts}Financial Type{/ts}</th>
    <th>{ts}Qty{/ts}</th>
    <th>{ts}Unit Price{/ts}</th>
    <th>{ts}Total Price{/ts}</th>
    {if $taxEnabled}<th>{ts}Tax Amount{/ts}</th>{/if}
    <th></th>
  </tr>
  {if !empty($lineItemTable)}
    {foreach from=$lineItemTable.rows item=row}
      <tr class="lineitem-info-row">
        <td>{$row.item}</td>
        <td>{$row.financial_type}</td>
        <td>{$row.qty}</td>
        <td>{$row.unit_price|crmMoney:$row.currency}</td>
        <td>{$row.total_price|crmMoney:$row.currency}</td>
        {if $taxEnabled}<td>{$row.tax_amount|crmMoney:$row.currency}</td>{/if}
        <td>{$row.actions}</td>
      </tr>
    {/foreach}
  {/if}
  {section name='i' start=0 loop=10}
    {assign var='rowNumber' value=$smarty.section.i.index}
    <tr id="add-item-row-{$rowNumber}" class="line-item-row hiddenElement">
      <td>{$form.item_label.$rowNumber.html}</td>
      <td>{$form.item_financial_type_id.$rowNumber.html}</td>
      <td>{$form.item_qty.$rowNumber.html}</td>
      <td>{$form.item_unit_price.$rowNumber.html}</td>
      <td>{$form.item_line_total.$rowNumber.html}</td>
      {if $taxEnabled}<td>{$form.item_tax_amount.$rowNumber.html}</td>{/if}
      <td>{$form.item_price_field_value_id.$rowNumber.html}<a href=# class="remove_item crm-hover-button" title='add-items'><i class="crm-i fa-times"></i></a></td>
    </tr>
  {/section}
</table>
<span id="add-another-item" class="crm-hover-button"><a href=#>{ts}Add another item{/ts}</a></span>
</br>
<div>
  <span class="label"><strong>{ts}Total Amount{/ts}:</strong>&nbsp;</span>
  <span id="line-total"></span></div>
</div>
{literal}
<script type="text/javascript">
CRM.$(function($) {
  calculateTotalAmount();
  var isSubmitted = false,
  submittedRows = $.parseJSON('{/literal}{$lineItemSubmitted}{literal}'),
  action = '{/literal}{$action}{literal}'
  isNotQuickConfig = '{/literal}{$pricesetFieldsCount}{literal}';

  if (!isNotQuickConfig && action == 2) {
    $('#totalAmountORaddLineitem, #add_item').hide();
  }

  // after form rule validation when page reloads then show only those line-item which were chosen and hide others
  $.each(submittedRows, function(e, num) {
    isSubmitted = true;
    $('#add-item-row-' + num).removeClass('hiddenElement');
  });

  // if you choose manual amount, then hide all other option and line-item add block
  $('#choose-manual').on('click', function() {
    $('.crm-contribution-form-block-financial_type_id, #totalAmount, #totalAmountORaddLineitem, #totalAmountORPriceSet, #price_set_id').show();
    reset();
  });

  // if total amount element is present which is on edit contribution form, when contribution is not done by using price set then append and show necessary links
  if ($('input[id="total_amount"]').length) {
    $('#totalAmountORaddLineitem').insertAfter('#totalAmount');
    $('#lineitem-add-block').insertBefore('#totalAmountBlock').css('display', ((isSubmitted || $('.lineitem-info-row').length) ? 'block' : 'none'));
  }
  else {
    $('#totalAmountORaddLineitem').insertBefore('.total_amount-section');
    $('#lineitem-add-block').insertBefore('#totalAmountORaddLineitem').css('display', ((isSubmitted || $('.lineitem-info-row').length) ? 'block' : 'none'));
  }

  $('#totalAmountBlock span').text(ts('Alternatively, you can use a price set or add ad-hoc item(s).'));
  $('#price_set_id').on('change', function() {
    var show = ($(this).val() === '');
    $('#totalAmountORaddLineitem').toggle(show);
    if (!show) {
      $('#lineitem-add-block').addClass("hiddenElement");
    }
  });
  $('#add-items, #add-another-item').on('click', function() {
    if ($('tr.line-item-row').hasClass("hiddenElement")) {
      var row = $('#lineitem-add-block tr.hiddenElement:first');
      $('tr.hiddenElement:first, #lineitem-add-block').show().removeClass('hiddenElement');
      fillLineItemRow($('input[id^="item_price_field_value_id"]', row).val(), row);
      if (action == 1) {
        $('.crm-contribution-form-block-financial_type_id, #totalAmount, #totalAmountORaddLineitem, #totalAmountORPriceSet, #price_set_id').hide();
        $( "#total_amount").val(0);
      }
    }
    else {
      $('#add-another-item').hide();
    }
  });
  $('#add_item').on('change', function() {
    var val = $(this).val();
    if (val !== '') {
      var found = false;
      $.each($('.line-item-row'), function() {
        var row = this;
        var pvid = $('input[id^="item_price_field_value_id"]', this).val();
        if (pvid == val && !found && $(this).hasClass('hiddenElement')) {
          $(this).removeClass('hiddenElement').show();
          $('#lineitem-add-block').css('display', 'block');
          found = true;
          fillLineItemRow(pvid, row);
        }
      });
    }
    else {
      reset();
    }
  });

  $('.remove_item').on('click', function() {
    var row = $(this).closest('tr');
    $('#add-another-item').show();
    $('input[id^="item_label"]', row).val('');
    $('select[id^="item_financial_type_id"]', row).select2('val', '');
    $('input[id^="item_qty"]', row).val('');
    $('input[id^="item_unit_price"], input[id^="item_line_total"], input[id^="item_tax_amount"]', row).val('');
    row.addClass('hiddenElement').hide();
    calculateTotalAmount();
  });

  var $form = $('form.{/literal}{$form.formClass}{literal}');
  $('select[id^="item_financial_type_id_"], input[id^="item_unit_price_"], input[id^="item_qty_"]', $form).on('change', function() {
    var row = $(this).closest('tr');
    var unit_price = $('input[id^="item_unit_price_"]', row).val();
    var qty = $('input[id^="item_qty_"]', row).val();
    var totalAmount = CRM.formatMoney((qty * unit_price), true);
    $('input[id^="item_line_total_"]', row).val(totalAmount);
    if ($('input[id^="item_tax_amount"]', row).length) {
      var tax_amount = calculateTaxAmount($('select[id^="item_financial_type_id_"]', row).val(), totalAmount);
      $('input[id^="item_tax_amount"]', row).val(CRM.formatMoney(tax_amount, true));
    }
    calculateTotalAmount();
  });

  $('input[id="total_amount"]', $form).on('change', calculateTotalAmount);

  function calculateTotalAmount() {
    var thousandMarker = "{/literal}{$config->monetaryThousandSeparator}{literal}";
    let total_amount = 0;
    if ($('input[id="total_amount"]').length) {
      total_amount = parseFloat(($('input[id="total_amount"]').val().replace(thousandMarker,'') || 0));
    }

    if (!$("#total_amount").is(":hidden")) {
      total_amount += calculateTaxAmount($('select[id="financial_type_id"]').val(), total_amount);
    }

    $.each($('.line-item-row'), function() {
      total_amount += parseFloat(($('input[id^="item_line_total_"]', this).val().replace(thousandMarker,'') || 0));
      if ($('input[id^="item_tax_amount"]', this).length) {
        total_amount += parseFloat(($('input[id^="item_tax_amount"]', this).val().replace(thousandMarker,'') || 0));
      }
    });
    $('#line-total').text(CRM.formatMoney(total_amount));

    return total_amount;
  }

  function fillLineItemRow(pvid, row) {
    var total_amount = 0;
    if (pvid == 'new') {
      $('input[id^="item_label"]', row).val(ts('Additional line item'));
      $('select[id^="item_financial_type_id"]', row).select2('val', $('#financial_type_id').val());
      $('input[id^="item_qty"]', row).val(1);
      total_amount = CRM.formatMoney(1, true);
      $('input[id^="item_unit_price"], input[id^="item_line_total"]', row).val(total_amount);
      if ($('input[id^="item_tax_amount"]', row).length) {
        var tax_amount = calculateTaxAmount($('select[id^="item_financial_type_id_"]', row).val(), 1);
        total_amount += tax_amount;
        $('input[id^="item_tax_amount"]', row).val(CRM.formatMoney(tax_amount, true));
      }
      $('#line-total').text(CRM.formatMoney(parseFloat(calculateTotalAmount())));
    }
    else {
      CRM.api3('PriceFieldValue', 'getsingle', {"id": pvid}).done(function(result) {
        if ($('#financial_type_id').length && result.name == 'contribution_amount') {
          $('#financial_type_id').val(result.financial_type_id);
        }
        $('input[id^="item_label"]', row).val(result.label);
        $('select[id^="item_financial_type_id"]', row).select2('val', result.financial_type_id);
        $('input[id^="item_qty"]', row).val(1);
        total_amount = CRM.formatMoney(result.amount, true);
        $('input[id^="item_unit_price"], input[id^="item_line_total"]', row).val(total_amount);
        if ($('input[id^="item_tax_amount"]', row).length) {
          var tax_amount = calculateTaxAmount($('select[id^="item_financial_type_id_"]', row).val(), result.amount);
          total_amount += tax_amount;
          $('input[id^="item_tax_amount"]', row).val(CRM.formatMoney(tax_amount, true));
        }
        $('#line-total').text(CRM.formatMoney(parseFloat(calculateTotalAmount())));
      });
    }
    $('#net_amount, #fee_amount, #non_deductible_amount').val('');
  }

  function calculateTaxAmount(financial_type_id, line_total) {
    var tax_amount = 0;
    var tax_rates = {/literal}{$taxRates}{literal};
    if (financial_type_id in tax_rates) {
      tax_amount = (tax_rates[financial_type_id] / 100 ) * line_total;
    }
    return tax_amount;
  }

  function reset() {
    $('#lineitem-add-block').hide();
    $.each($('.line-item-row'), function() {
      var row = $(this);
      if (!(row.hasClass('hiddenElement'))) {
        $('input[id^="item_label"]', row).val('');
        $('select[id^="item_financial_type_id"]', row).select2('val', '');
        $('input[id^="item_qty"]', row).val('');
        $('input[id^="item_unit_price"], input[id^="item_line_total"], input[id^="item_tax_amount"]', row).val('');
        row.addClass('hiddenElement').hide();
      }
    });
    calculateTotalAmount();
  }

});
</script>
{/literal}
