{* HEADER *}

{foreach from=$fieldNames item=fieldName}
<div class="crm-section">
    <div class="label">{$form.$fieldName.label}</div>
    <div class="content">
      {if in_array($fieldName, array('unit_price', 'line_total'))}
        {$currency}
      {/if}
      <span id="content-{$fieldName}">{$form.$fieldName.html}</span>
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
      $('#qty', $form).change( function() {
        var unit_price = $('#unit_price', $form).val();
        var qty = $('#qty', $form).val();
        var totalAmount = qty * unit_price;
        $('#line_total', $form).val(CRM.formatMoney(totalAmount, true));
        $('#content-line_total').html(CRM.formatMoney(totalAmount, true));
      });
      $('#unit_price', $form).change( function() {
        var unit_price = $('#unit_price', $form).val();
        var qty = $('#qty', $form).val();
        var totalAmount = qty * unit_price;
        $('#line_total', $form).val(CRM.formatMoney(totalAmount, true));
        $('#content-line_total').html(CRM.formatMoney(totalAmount, true));
      });
    });
  </script>
{/literal}
