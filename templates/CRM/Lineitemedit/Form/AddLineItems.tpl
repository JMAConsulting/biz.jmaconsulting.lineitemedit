{* template block that contains the new field *}
<span id="totalAmountORaddLineitem">OR
  {if $form.add_item.html}
    <span class="label">{$form.add_item.label}</span>
    <span>{$form.add_item.html}</span>
  {else}
    <a href=# id='add-items' class="action-item crm-hover-button">{ts}Add Item(s){/ts}</a>
  {/if}
</span>
<div id="lineitem-add-block" class="status">
<table id='info'>
  <tr class="line-item-columnheader">
    <th>{ts}Items{/ts}</th>
    <th>{ts}Financial Type{/ts}</th>
    <th>{ts}Qty{/ts}</th>
    <th>{ts}Unit Price{/ts}</th>
    <th>{ts}Total Price{/ts}</th>
    <th></th>
  </tr>
  {section name='i' start=1 loop=10}
    {assign var='rowNumber' value=$smarty.section.i.index}
    <tr id="add-item-row-{$rowNumber}" class="line-item-row hiddenElement">
      <td>{$form.item_label.$rowNumber.html}</td>
      <td>{$form.item_financial_type_id.$rowNumber.html}</td>
      <td>{$form.item_qty.$rowNumber.html}</td>
      <td>{$form.item_unit_price.$rowNumber.html}</td>
      <td>{$form.item_line_total.$rowNumber.html}</td>
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
  var isSubmitted = false;
  var submittedRows = $.parseJSON('{/literal}{$lineItemSubmitted}{literal}');
  $.each(submittedRows, function(e, num) {
    isSubmitted = true;
    $('#add-item-row-' + num).removeClass('hiddenElement');
  });
  $('#lineitem-add-block').insertAfter('#totalAmountBlock').css('display', (isSubmitted ? 'block' : 'none'));
  $('#totalAmountORaddLineitem').insertBefore('#totalAmountBlock');

  $('#totalAmountORaddLineitem').insertBefore('.total_amount-section');
  $('#lineitem-add-block').insertAfter('.total_amount-section').css('display', (isSubmitted ? 'block' : 'none'));

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
      $('tr.hiddenElement:first, #lineitem-add-block').show().removeClass('hiddenElement');
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
        var pvid = $('input[id^="item_price_field_value_id"]', this).val();
        if (pvid == val && !found && $(this).hasClass('hiddenElement')) {
          $(this).removeClass('hiddenElement');
          $('#lineitem-add-block').css('display', 'block');
          found = true;
        }
      });
    }
  });

  $('.remove_item').on('click', function() {
    $(this).closest('tr').addClass('hiddenElement');
    $('#add-another-item').show();
  });
  var $form = $('form.{/literal}{$form.formClass}{literal}');
  $('select[id^="item_financial_type_id_"], input[id^="item_unit_price_"], input[id^="item_qty_"]', $form).on('change', function() {
    var row = $(this).closest('tr');
    var unit_price = $('input[id^="item_unit_price_"]', row).val();
    var qty = $('input[id^="item_qty_"]', row).val();
    var totalAmount = CRM.formatMoney((qty * unit_price), true);
    $('input[id^="item_line_total_"]', row).val(totalAmount);
    calculateTotalAmount();
  });

  $('input[id="total_amount"]', $form).on('change', calculateTotalAmount);

  function calculateTotalAmount() {
    var $form = $('form.{/literal}{$form.formClass}{literal}');
    var total_amount = parseFloat(($('input[id="total_amount"]').val() || 0));
    $.each($('.line-item-row'), function() {
      total_amount += parseFloat(($('input[id^="item_line_total_"]', this).val() || 0));
    });
    $('#line-total').text(CRM.formatMoney(total_amount));
  }

});
</script>
{/literal}
