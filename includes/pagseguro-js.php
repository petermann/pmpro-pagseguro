<?php

function pmpro_pagseguro_javascript()
{
    global $pagsegurojs;
    ?>
		<script>
			PagSeguroDirectPayment.setSessionId(<?php echo "'" . $pagsegurojs['session_id'] . "'" ?>);


			function PagSeguroBuscaHashCliente() {
                console.log('Hash Cliente: ' + PagSeguroDirectPayment.getSenderHash());
				return PagSeguroDirectPayment.getSenderHash();		
			}
            //from receita federal 
            function validaCPF(strCPF) {
                var Soma;
                var Resto;
                Soma = 0;
                if (strCPF == "00000000000") return false;
                    
                for (i=1; i<=9; i++) Soma = Soma + parseInt(strCPF.substring(i-1, i)) * (11 - i);
                Resto = (Soma * 10) % 11;
                if ((Resto == 10) || (Resto == 11))  Resto = 0;
                if (Resto != parseInt(strCPF.substring(9, 10)) ) return false;
                Soma = 0;
                for (i = 1; i <= 10; i++) Soma = Soma + parseInt(strCPF.substring(i-1, i)) * (12 - i);
                Resto = (Soma * 10) % 11;
            
                if ((Resto == 10) || (Resto == 11))  Resto = 0;
                if (Resto != parseInt(strCPF.substring(10, 11) ) ) return false;
                return true;
            }
            function validateForm(data){
                if(data.cep.length != 8 && isNaN(data.cep))
                    return {valid :false, msg : "Cep Inválido", id :"#cep"};
                if(data.endereco.length == 0)
                    return {valid :false, msg : "Endereço necessário", id :"#endereco"};
                if(data.numero.length == 0)
                    return {valid :false, msg : "Numero do endereço necessário",id :"#numero"};
                if(data.bairro.length == 0)
                    return {valid :false, msg : "Bairro necessário",id :"#bairro"};   
                if(data.cidade.length == 0)
                    return {valid :false, msg : "Cidade necessária",id :"#cidade"};
                if(data.estado.length == 0 )
                    return {valid :false, msg : "Estado necessário",id :"#estado"};
                if(data.telefoneddd.length != 2)
                    return {valid :false, msg : "DDD inválido", id :"#telefoneddd"};
                if(data.telefonenumber.length < 8 || data.telefonenumber.length > 9)
                    return {valid :false, msg : "Telefone inválido", id :"#telefonenumber"};
                if(data.cardholdername.length == 0)
                    return {valid :false, msg : "Nome do Titular inválido", id :"#cardholdername"};
                if(data.cpfholder.length != 11 || !validaCPF(data.cpfholder))
                    return {valid :false, msg : "CPF do Titular inválido", id :"#cpfholder"};
                if(data.cpfsender.length != 11 || !validaCPF(data.cpfsender))
                    return {valid :false, msg : "CPF do Comprador inválido", id :"#cpfsender"};
                if(data.cardexpmonth.length == 0)
                    return {valid :false, msg : "Mês de expiração do cartão inválido", id :"#cardexpmonth"};
                if(data.cardexpyear.length == 0)
                    return {valid :false, msg : "Ano de expiração do cartão inválido", id :"#cardexpyear"};
                if(data.cvv.length == 0 || isNaN(data.cvv) || data.cvv.length > 4)
                    return {valid :false, msg : "Código de segurança do cartão inválido", id :"#cvv"};
                if(data.cardnumber.length == 0 || isNaN(data.cardnumber))
                    return {valid :false, msg : "Numero do cartão inválido", id :"#cardnumber"};
                return {valid :true};
            }

            function pagSeguroAddErrorMessage( error ) {
                
		        jQuery("#pmpro_message, .pmpro_error").text(error.msg);
                jQuery('#pmpro_message').addClass('pmpro_error');
                jQuery("#pmpro_message, .pmpro_error").show();
                jQuery('.pmpro_btn-submit-checkout,.pmpro_btn-submit').removeAttr("disabled");
                jQuery('#pmpro_processing_message').css('visibility', 'hidden');
                    
                if(error.id){
                    jQuery(error.id).css('border', 'red solid 1px');
                    jQuery(error.id).on("focus",function(){
                        jQuery(error.id).css('border', '');
                    });
                    jQuery('html, body').animate({
                        scrollTop: $(error.id).offset().top - 300
                    }, 2000);
                }else{
                    alert(error.msg);
                }
		    }

			jQuery(document).ready(function() {
                jQuery(".pmpro_form").submit(function(event) {  
                    event.preventDefault();
                    let formData = {
                        cep : jQuery("#cep").val(),
                        endereco : jQuery("#endereco").val(),
                        numero : jQuery("#numero").val(),
                        complemento : jQuery("#complemento").val(),
                        bairro : jQuery("#bairro").val(),
                        cidade : jQuery("#cidade").val(),
                        estado : jQuery("#estado").val(),
                        telefoneddd : jQuery("#telefoneddd").val(),
                        telefonenumber : jQuery("#telefonenumber").val(),
                        cardholdername : jQuery("#cardholdername").val(),
                        cpfholder : jQuery('#cpfholder').val(),
                        cpfsender : jQuery("#cpfsender").val(),
                        cardnumber : jQuery("#cardnumber").val(),
                        cardexpmonth : jQuery("#cardexpmonth").val(),
                        cardexpyear : jQuery("#cardexpyear").val(),
                        cvv : jQuery("#CVV").val()
                    };

                    let status = validateForm(formData);
                    if(!status.valid){
                        pagSeguroAddErrorMessage(status);
                        
                        
                    }else{

                        let hash = PagSeguroBuscaHashCliente();

                        PagSeguroDirectPayment.getBrand({
                            cardBin: jQuery('#cardnumber').val(), 
                            success: function(response) {
                                console.log('Bandeira: ' + response.brand.name); 
                                

                                PagSeguroDirectPayment.createCardToken({
                                    cardNumber: jQuery('#cardnumber').val(),
                                    brand: response.brand.name,
                                    cvv: jQuery('#CVV').val(),
                                    expirationMonth: jQuery('#cardexpmonth').val(),
                                    expirationYear: jQuery('#cardexpyear').val(),
                                    success: function(response) { 
                                        console.log('Token: ' + response.card.token); 
                                        var form$ = jQuery("#pmpro_form, .pmpro_form");
                                        form$.append("<input type='hidden' name='client_hash' value='" + hash + "'/>");									
                                        form$.append("<input type='hidden' name='card_token' value='" +  response.card.token + "'/>");	
                                        form$.get(0).submit();
                                        return false;
                                    },
                                    error: function(response) { 
                                        pagSeguroAddErrorMessage({msg:"Dados do Cartão inválidos, verifique os dados e tente novamente"});
                                    }	
                                });
                                
                            },
                            error: function(response) { 
                                pagSeguroAddErrorMessage({msg:"Dados do Cartão inválidos, verifique os dados e tente novamente"});
                            }
                        });
                        
					
                    }				
				});

                jQuery("#warning").hide();
                jQuery("#cep").on("focus",function(){
                    jQuery("#warning").hide();
                });
                let cep = jQuery("#cep").val();
                if(cep.length == 8 && !isNaN(cep)){
                    jQuery(".pmpro_checkout-field-endereco").show();
                    jQuery(".pmpro_checkout-field-numero").show();
                    jQuery(".pmpro_checkout-field-complemento").show();
                    jQuery(".pmpro_checkout-field-cidade").show();
                    jQuery(".pmpro_checkout-field-estado").show();
                    jQuery(".pmpro_checkout-field-bairro").show();
                    jQuery(".pmpro_checkout-field-telefone").show();
                    jQuery("#warning").hide();

                }
                jQuery("#showfields").click(function(){
                    let cep = jQuery("#cep").val();
                    if(cep.length == 8 && !isNaN(cep)){
                        jQuery.ajax({
                            url: "https://api.postmon.com.br/v1/cep/"+cep,
                            type: 'GET',
                            success: function(response) {
                                    jQuery("#warning").hide();
                                    jQuery("#endereco").val(response.logradouro || "");
                                    jQuery("#complemento").val(response.complemento || "");
                                    jQuery("#cidade").val(response.cidade || "");
                                    jQuery("#bairro").val(response.bairro || "");
                                    jQuery("#estado").val(response.estado || "");

                                    jQuery("#numero").focus();
                                    console.log(response);
                                    jQuery(".pmpro_checkout-field-endereco").show();
                                    jQuery(".pmpro_checkout-field-numero").show();
                                    jQuery(".pmpro_checkout-field-complemento").show();
                                    jQuery(".pmpro_checkout-field-cidade").show();
                                    jQuery(".pmpro_checkout-field-estado").show();
                                    jQuery(".pmpro_checkout-field-telefone").show();
                                    jQuery(".pmpro_checkout-field-bairro").show();
                                    //jQuery("#showfields").hide();
                                
                            },
                            error : function(response){
                                jQuery("#warning").show();
                                setTimeout(() => {
                                    jQuery("#warning").hide();
                                }, 3000);
                            }
                        });  
                    }else{
                        jQuery("#warning").show();
                        
                    }
                    
                });
              
				

			});


            
			</script>
	<?php

}
add_action("wp_head", "pmpro_pagseguro_javascript");