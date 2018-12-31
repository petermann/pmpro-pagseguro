		<tr class="pmpro_settings_divider gateway gateway_pagseguro" <?php if ($gateway != "pagseguro") { ?>style="display: none;"<?php 
																																																																																																																										} ?>>
			<td colspan="2">
				<?php _e('Configurações PagSeguro Express', 'paid-memberships-pro'); ?>
			</td>
		</tr>
	
		<tr class="gatewaygateway gateway_pagseguro" <?php if ($gateway != "pagseguro") { ?>style="display: none;"<?php 
																																																																																																										} ?>>
			<th scope="row" valign="top">
				<label for="pagseguro_email"><?php _e('Gateway Account Email', 'paid-memberships-pro'); ?>:</label>
			</th>
			<td>
				<input type="text" id="pagseguro_email" name="pagseguro_email" size="60" value="<?php echo esc_attr($values['pagseguro_email']) ?>" />
			</td>
		</tr>
		<tr class="gatewaygateway gateway_pagseguro" <?php if ($gateway != "pagseguro") { ?>style="display: none;"<?php 
																																																																																																										} ?>>
			<th scope="row" valign="top">
				<label for="pagseguro_sandbox_token"><?php _e('SANDBOX TOKEN', 'paid-memberships-pro'); ?>:</label>
			</th>
			<td>
				<input type="text" id="pagseguro_sandbox_token" name="pagseguro_sandbox_token" size="60" value="<?php echo esc_attr($values['pagseguro_sandbox_token']) ?>" />
			</td>
		</tr>
		<tr class="gatewaygateway gateway_pagseguro" <?php if ($gateway != "pagseguro") { ?>style="display: none;"<?php 
																																																																																																										} ?>>
			<th scope="row" valign="top">
				<label for="pagseguro_token"><?php _e('TOKEN', 'paid-memberships-pro'); ?>:</label>
			</th>
			<td>
				<input type="text" id="pagseguro_token" name="pagseguro_token" size="60" value="<?php echo esc_attr($values['pagseguro_token']) ?>" />
			</td>
		</tr>
		
		<tr class="gateway gateway_pagseguro" <?php if ($gateway != "pagseguro") { ?>style="display: none;"<?php 
																																																																																																			} ?>>
			<th scope="row" valign="top">
				<label><?php _e('Web Hook URL', 'paid-memberships-pro'); ?>:</label>
			</th>
			<td>
				<p><?php _e('Lembre-se de adicionar a url de notificação no site do PagSeguro', 'paid-memberships-pro'); ?> <pre><?php echo admin_url("admin-ajax.php") . "?action=pmpropagseguro"; ?></pre></p>
			</td>
		</tr>

		<tr class="gateway gateway_pagseguro" <?php if ($gateway != "pagseguro") { ?>style="display: none;"<?php 
																																																																																																			} ?>>
			<th><?php _e('PagSeguro for Paid Membership Pro Versão', 'paid-memberships-pro'); ?>:</th>
			<td><?php echo PMPRO_PAGSEGURO_VERSION; ?></td>
		</tr>