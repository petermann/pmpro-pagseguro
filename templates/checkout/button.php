<!-- 
	PagSeguro for Paid Membership Pro
	button.php

	Sobre escrita do botão de pagamento para planos não recorrentes
	
	* implementado em 26/12/2018
 -->
<span id="pmpro_pagseguro_checkout" <?php if (($gateway != "pagseguro")) { ?>style="display: none;"<?php } ?>>
	<input type="hidden" name="submit-checkout" value="1" />
	<input type="image" class="pmpro_btn-submit-checkout" alt="<?php _e('Faça o Pagamento com o Pag Seguro', 'paid-memberships-pro'); ?> &raquo;" src="<?php echo apply_filters("pmpro_pagseguro_button_image", "https://stc.pagseguro.uol.com.br/public/img/botoes/pagamentos/209x48-comprar-assina.gif"); ?>" />
</span>
