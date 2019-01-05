<?php 
/**
 * PagSeguro for Paid Membership Pro
 * level-fields-js.php 
 * script responsavél por ajustar a criação de planos para o formato do pag seguro
 * alterando os campos do period de cobrança e periodo de degustação e revalidando o formulário
 * 
 * implementado em 03/01/2019
 */
?>
<script>
		
        jQuery(document).ready(function() {
            
            $("#cycle_number").hide();
            let trial = $(".trial_info");
            trial.html('<th scope="row" valign="top"> <label for="trial_amount"> Duração do Período de Degustação: </label> </th> <td> <input name="trial_amount" type="text" size="20" value="" style="display: none;"> <input name="trial_limit" type="number" min="1" max="100" value="<?php echo isset($level) ? $level->trial_limit : '' ?>"> <br><small>Dias de período de teste (sem gerar cobrança). <b>Máximo de 100 dias<b></small> </td>');
            let cycle = $(".recurring_info")[0]
            cycle.innerHTML = ' <th scope="row" valign="top"> <label for="billing_amount">Valor da parcela :</label> </th> <td> R$ <input name="billing_amount" type="text" size="20" value="<?php echo isset($level) ? $level->billing_amount : '' ?>"><input id="cycle_number" name="cycle_number" type="text" size="10" value="1" style="display: none;"> <br><small> Quantidade a ser cobrada um ciclo após o primeiro pagamento. </small>  </td>';
            $('<tr class="recurring_info"> <th scope="row" valign="top"> <label for="billing_amount">Frequência das Cobranças:</label> </th> <td> <select id="cycle_period" name="cycle_period"> <option value="1"<?php echo isset($levelperiod) ? ($levelperiod == '1' ? "selected" : '') : '' ?>>Semanal</option> <option value="2" <?php echo isset($levelperiod) ? ($levelperiod == '2' ? "selected" : '') : '' ?>>Mensal</option> <option value="3" <?php echo isset($levelperiod) ? ($levelperiod == '3' ? "selected" : '') : '' ?>>Bimestral</option> <option value="4" <?php echo isset($levelperiod) ? ($levelperiod == '4' ? "selected" : '') : '' ?>>Trimestral</option> <option value="5" <?php echo isset($levelperiod) ? ($levelperiod == '5' ? "selected" : '') : '' ?>>Semestral</option> <option value="6" <?php echo isset($levelperiod) ? ($levelperiod == '6' ? "selected" : '') : '' ?>>Anual</option> </select></td></tr>').insertAfter(cycle)
            $( "form" ).submit(function( event ) {
                event.preventDefault();
                let val = $( "#cycle_period" ).val();
                  switch(val) {
                    case '1':

                        $("#cycle_period option").each(function(index) {
                            $(this).val('Week');
                        });
                        $("#cycle_number").val('1');
                        break;
                    case '2':
                        $("#cycle_period option").each(function(index) {
                            $(this).val('Month');
                        });
                        
                        $("#cycle_number").val('1');
                        break;
                    case '3':
                        $("#cycle_period option").each(function(index) {
                            $(this).val('Month');
                        });
                        $("#cycle_number").val('2');
                        break;
                    case '4':
                        $("#cycle_period option").each(function(index) {
                            $(this).val('Month');
                        });$("#cycle_number").val('3');
                        break;
                    case '5':
                        $("#cycle_period option").each(function(index) {
                            $(this).val('Month');
                        });
                        $("#cycle_number").val('6');
                        break;
                    case '6':
                        $("#cycle_period option").each(function(index) {
                            $(this).val('Year');
                        });
                        $("#cycle_number").val('1');
                        break;
                }	
                console.log($("#cycle_period").val());
                var form$ = jQuery("form");
                form$.append("<input type='hidden' id='pagseguro_period' value='"+val+"' />");									
                form$.get(0).submit();

            });
        });

</script>
