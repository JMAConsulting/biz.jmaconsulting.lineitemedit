{* HEADER *}

{foreach from=$fieldNames item=fieldName}
<div class="crm-section" {if $fieldName == 'tax_amount'}id="crm-section-tax-amount"{/if} {if $fieldName == 'tax_amount' and not $isTaxEnabled}style="display: none;"{/if}>
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

{include file="CRM/Lineitemedit/Form/CalculateLineItemFields.tpl"}

{include file="CRM/EFT/AddChapterFundCode.tpl"}
