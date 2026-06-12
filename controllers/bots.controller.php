<?php 

Class BotsController{

	static public function responseBots($bot,$getApiWS,$phone_message,$order_message){

		/*=============================================
      	Traemos la plantilla Bot
      	=============================================*/

      	$url = "bots?linkTo=title_bot&equalTo=".$bot;
      	$method = "GET";
      	$fields = array();

      	$getBot = CurlController::request($url,$method,$fields);

      	if($getBot->status == 200){

      		$getBot = $getBot->results[0];

      		if($getBot->type_bot == "text"){

      			$json = '{
				  "messaging_product": "whatsapp",
				  "recipient_type": "individual",
				  "to": "'.$phone_message.'",
				  "type": "text",
				  "text": {
				    "preview_url": true,
				    "body": "'.$getBot->body_text_bot.'"
				  }
				}';

				$business_message = $getBot->body_text_bot;
				$template_message = '{"type":"bot","title":"'.$bot.'"}';
      		
      		}
      	
      	}

      	/*=============================================
		Llevar el orden de los mensajes
		=============================================*/

		$url = "messages?linkTo=phone_message&equalTo=".$phone_message."&startAt=0&endAt=1&orderBy=id_message&orderMode=DESC";

		$getMessages = CurlController::request($url,$method,$fields);
		
		if($getMessages->status == 200){

			$order_message = $getMessages->results[0]->order_message + 1;
		
		}

	 	/*=============================================
      	Guardamos la respuesta del negocio
      	=============================================*/

		$url = "messages?token=no&except=id_message";
		$method = "POST";
		$fields = array(
			"type_message" => "business",
			"id_whatsapp_message" => $getApiWS->id_whatsapp,
			"business_message" => $business_message,
			"phone_message" => $phone_message,
			"order_message" => $order_message,
			"template_message" => $template_message,
			"date_created_message" => date("Y-m-d")
		);

		$saveMessage = CurlController::request($url,$method,$fields);

		if($saveMessage->status == 200){

			/*=============================================
      		Enviamos datos JSON a la API de WhatsApp
      		=============================================*/

      		$apiWS = CurlController::apiWS($getApiWS,$json);
      		echo '<pre>$apiWS '; print_r($apiWS); echo '</pre>';
		
		}

	}

}

?>