{* HEADER *}
{foreach from=$fieldNames item=fieldName}
<div class="crm-section">
    <div class="label">{$form.$fieldName.label}</div>
    <div class="content">
      {if in_array($fieldName, array('unit_price', 'line_total', 'tax_amount'))}
        {$currency}
      {/if}
      <span>{$form.$fieldName.html}</span>
    </div>
    <div class="clear"></div>
</div>
{/foreach}

{* FOOTER *}
<div class="crm-submit-buttons">
{include file="CRM/common/formButtons.tpl" location="bottom"}
</div>

{literal}
  <script type="text/javascript">
    CRM.$(function($) {
      var $form = $('form.{/literal}{$form.formClass}{literal}');
      $('#price_field_value_id', $form).change(function() {
        var price_field_value_id = $(this).val();
        var priceFieldDataURL =  CRM.url('civicrm/ajax/get-pricefield-info', { pfv_id:  price_field_value_id });
        $.ajax({
          url         : priceFieldDataURL,
          dataType    : "json",
          timeout     : 5000, //Time in milliseconds
          success     : function(data, status) {
            for (var ele in data) {
              if (ele == 'financial_type_id') {
                $('#' + ele).select2('val', data[ele]);
              }
              else {
                $('#' + ele, $form).val(data[ele]);
              }
            }
          }
        });
      });
    });
  </script>
{/literal}

{include file="CRM/Lineitemedit/Form/CalculateLineItemFields.tpl"}
