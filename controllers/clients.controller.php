<?php 

Class ClientsController{

	static public function responseClients($getApiWS,$phone_message,$order_message,$type_conversation){
		
		/*=============================================
		Orden de la conversación
		=============================================*/

		if($order_message == 0){

			/*=============================================
            Buscamos el contacto
            =============================================*/

            $url = "contacts?linkTo=phone_contact&equalTo=".$phone_message;
            $method = "GET";
            $fields = array();

            $getContact = CurlController::request($url,$method,$fields);

            if($getContact->status != 200){

            	/*=============================================
            	Creamos el contacto
            	=============================================*/

            	$url = "contacts?token=no&except=id_contact";
            	$method = "POST";
            	$fields = array(
            		"phone_contact" => $phone_message,
            		"ai_contact" => $getApiWS->ai_whatsapp,
            		"date_created_contact" => date("Y-m-d")
            	);

            	$createContact = CurlController::request($url,$method,$fields);
            
            }else{

            	/*=============================================
            	Actualizamos la fecha de la última conversación con el contacto
            	=============================================*/
            	$url = "contacts?id=".$getContact->results[0]->id_contact."&nameId=id_contact&token=no&except=id_contact";
            	$method = "PUT";
            	$fields = array(
            		"date_updated_contact" => date("Y-m-d H:i:s")
            	);

            	$fields = http_build_query($fields);

            	$updateContact = CurlController::request($url,$method,$fields);

            }

            /*=============================================
            Respuesta con el chatbot
            =============================================*/

            if($getApiWS->ai_whatsapp == 0){

            	/*=============================================
            	Respuesta con Plantilla Bot
            	=============================================*/

            	$responseBots = BotsController::responseBots("conversation",$getApiWS,$phone_message,$order_message);
            	echo '<pre>$responseBots '; print_r($responseBots); echo '</pre>';
            }
		}
	}

}



 ?>