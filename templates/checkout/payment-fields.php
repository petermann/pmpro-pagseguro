<?php
/**
 * PagSeguro for Paid Membership Pro
 * payment-fields.php 
 * 
 * campos utilizados no checkout transparente para os dados do cartão e do comprador
 *  
 * implementado em 01/01/2019
 */
?>
<div id="pmpro_payment_information_fields" class="pmpro_checkout">
    <h3>
        <span class="pmpro_checkout-h3-name"><?php _e('Payment Information', 'paid-memberships-pro'); ?></span>
    </h3>
    <?php 
        $sslseal = pmpro_getOption("sslseal"); 
        if (!empty($sslseal)) : 
    ?>
	    <div class="pmpro_checkout-fields-display-seal">
    <?php endif; ?>
    <div class="pmpro_checkout-field pmpro_checkout-field-cpfsender">
        <label for="cpfsender">CPF do Comprador </label>
        <input value="<?php echo isset($_REQUEST['cpfsender']) ? $_REQUEST['cpfsender'] : ''  ?>" require id="cpfsender" name="cpfsender" type="text" class="input pmpro_required" size="14" placeholder="000.000.000-00">
    </div>
    <div class="pmpro_checkout-field pmpro_checkout-field-cpfholder">
        <label for="cpfholder">CPF do Titular do Cartão </label>
        <input value="<?php echo isset($_REQUEST['cpfholder']) ? $_REQUEST['cpfholder'] : ''  ?>" require id="cpfholder" name="cpfholder" type="text" class="input pmpro_required" size="14" placeholder="000.000.000-00">
    </div>
    <div class="pmpro_checkout-field pmpro_checkout-field-cardname">
        <label for="cardholdername">Nome Completo do Titular (Escrito no Cartão) </label>
        <input value="<?php echo isset($_REQUEST['cardholdername']) ? $_REQUEST['cardholdername'] : ''  ?>" require id="cardholdername" name="cardholdername" type="text" class="input pmpro_required" size="30">
    </div> 
    <div class="pmpro_checkout-field pmpro_payment-birthholder pmpro_required">
        <label for="cardexpmonth">Data de Nascimento (Titular do Cartão) </label>
        <select name="birthday" id="birthday" required>
            <option value="">Dia</option>
            <?php 
             $day =isset($_REQUEST['birthday']) ? $_REQUEST['birthday'] : '-1';
             $month =isset($_REQUEST['birthmonth']) ? $_REQUEST['birthmonth'] : '-1';
             $year =isset($_REQUEST['birthyear']) ? $_REQUEST['birthyear'] : '-1';
                for($i = 1 ; $i <=31 ; $i++): ?>
                <option <?php echo 'value="'.($i < 10 ? '0'.$i : $i).'"'.  (intval($day) == $i ? 'selected' : '')  ?> >
                <?php echo ($i < 10 ? '0'.$i : $i) ?>
                </option>
            <?php endfor; ?>
        </select>
        <select name="birthmonth" id="birthmonth" required>
            <option value="">Mês</option>
            <?php for($i = 1 ; $i <=12 ; $i++): ?>
                <option <?php echo 'value="'.($i < 10 ? '0'.$i : $i).'"'.  (intval($month) == $i ? 'selected' : '')  ?> >
                    <?php echo ($i < 10 ? '0'.$i : $i) ?>
                </option>
            <?php endfor; ?>
        </select>
        <select name="birthyear" id="birthyear" required>
            <option value="">Ano</option>
            <?php 
                $date = (int) date('Y');
                $numYears = 100;
                for ($i=$date; $i >= $date - $numYears; $i--) : ?>
                <option <?php echo 'value="'.$i.'"'.  (intval($year) == $i ? 'selected' : '')  ?> >
                    <?php echo $i ?>
                </option>
            <?php endfor; ?>
        </select>
	<div class="pmpro_checkout-fields<?php if (!empty($sslseal))echo 'pmpro_checkout-fields-leftcol'?>">					
		<div class="pmpro_checkout-field pmpro_payment-cardnumber">
			<label for="cardnumber"><?php _e('Card Number', 'paid-memberships-pro'); ?></label>
			<input  value="<?php echo isset($_REQUEST['cardnumber']) ? $_REQUEST['cardnumber'] : ''  ?>" require id="cardnumber" name ="cardnumber" class="input pmpro_required" type="text" size="25" autocomplete="off"  placeholder="&bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull;" >
		</div>
		<div class="pmpro_checkout-field pmpro_payment-cardexp pmpro_required">
			<label for="cardexpmonth">Data de Expiração </label>
			<select require id="cardexpmonth" name="cardexpmonth" class="pmpro_payment-cardexpmonth">
                <?php 
                    $expm =isset($_REQUEST['cardexpmonth']) ? $_REQUEST['cardexpmonth'] : '-1';
                    $expy =isset($_REQUEST['cardexpyear']) ? $_REQUEST['cardexpyear'] : '-1';
                    for($i = 1 ; $i <= 12 ; $i++): ?>
				    <option <?php echo 'value="'.$i.'"'.  (intval($expm) == $i ? 'selected' : '')  ?> >
                        <?php echo $i ?>
                    </option>
                <?php endfor; ?>
            </select>/
                <select require id="cardexpyear" name="cardexpyear" class="pmpro_payment-cardexpyear">
		        	<?php for ($i = date_i18n("Y"); $i < date_i18n("Y") + 13; $i++) : ?>
                        <option <?php echo 'value="'.$i.'"'.  (intval($expy) == $i ? 'selected' : '')  ?> >
                            <?php echo $i ?>
                        </option>
                    <?php endfor; ?>
				</select>
		</div>
		<div class="pmpro_checkout-field pmpro_payment-cvv">
			<label for="CVV">Código de Segurança (CVV)</label>
            <input value="<?php echo isset($_REQUEST['CVV']) ? $_REQUEST['CVV'] : ''  ?>" require id="CVV" name="CVV" type="text" size="4" class="input pmpro_required">
             <small>(<a href="javascript:void(0);" onclick="javascript:window.open('<?php echo pmpro_https_filter(PMPRO_URL) ?>/pages/popup-cvv.html','cvv','toolbar=no, location=no, directories=no, status=no, menubar=no, scrollbars=yes, resizable=yes, width=600, height=475');"><?php _e("what's this?", 'paid-memberships-pro'); ?></a>)</small>
		</div>
		<?php if ($pmpro_show_discount_code) : ?>
		<div class="pmpro_checkout-field pmpro_payment-discount-code">
			<label for="discount_code"><?php _e('Discount Code', 'paid-memberships-pro'); ?></label>
			<input class="input <?php echo pmpro_getClassForField("discount_code"); ?>" id="discount_code" name="discount_code" type="text" size="10" value="<?php echo esc_attr($discount_code) ?>" />
			<input type="button" id="discount_code_button" name="discount_code_button" value="<?php _e('Apply', 'paid-memberships-pro'); ?>" />
			<p id="discount_code_message" class="pmpro_message" style="display: none;"></p>
		</div>
		<?php endif; ?>
	</div> <!-- end pmpro_checkout-fields -->
    <?php if (!empty($sslseal)) : ?>
        <div class="pmpro_checkout-fields-rightcol pmpro_sslseal">
            <?php echo stripslashes($sslseal); ?>
        </div>
    <?php endif; ?>
    <?php if($gateway == "pagseguro"): ?>
		<input type='hidden' id='pagseguro_cartao_token' />
		<input type='hidden' id='pagseguro_cliente_hash'/>
        <input type='hidden' id='pagseguro_cartao_bandeira' />
    <?php endif; ?>
</div>
