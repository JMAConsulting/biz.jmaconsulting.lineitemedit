{literal}
  <script type="text/javascript">
    CRM.$(function($) {
      function changeTaxAmount(line_total, financial_type_id) {
        if ($('#tax_amount').length) {
          var tax_rates = {/literal}{$taxRates}{literal};
          var tax_amount = 0;
          if (financial_type_id in tax_rates) {
            tax_amount = (tax_rates[financial_type_id] / 100 ) * line_total;
            $('#tax_amount', $form).val(CRM.formatMoney(tax_amount, true));
          }
          else {
            $('#tax_amount', $form).val('');
          }
        }
      }

      var $form = $('form.{/literal}{$form.formClass}{literal}');
      $('#qty, #unit_price, #financial_type_id', $form).change( function() {
        var unit_price = $('#unit_price', $form).val();
        var qty = $('#qty', $form).val();
        var totalAmount = CRM.formatMoney((qty * unit_price), true);
        $('#line_total', $form).val(totalAmount);
        changeTaxAmount((qty * unit_price), $('#financial_type_id').val());
      });
    });

  </script>
{/literal}
