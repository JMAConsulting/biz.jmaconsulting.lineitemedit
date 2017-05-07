{* template block that contains the new field *}
<table id='info'>
  <tr class="columnheader">
    <th>{ts}Items{/ts}</th>
    <th>{ts}Financial Type{/ts}</th>
    <th>{ts}Qty{/ts}</th>
    <th>{ts}Unit Price{/ts}</th>
    <th>{ts}Total Price{/ts}</th>
    <th></th>
  </tr>
  {foreach from=$lineItemTable.rows item=row}
    <tr>
      <td>{$row.item}</td>
      <td>{$row.financial_type}</td>
      <td>{$row.qty}</td>
      <td>{$row.unit_price|crmMoney:$row.currency}</td>
      <td>{$row.total_price|crmMoney:$row.currency}</td>
      <td>{$row.actions}</td>
    </tr>
  {/foreach}
</table>
