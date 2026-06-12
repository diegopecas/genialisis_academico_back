<?php 

Class BusinessController{

	static public function responseBusiness($idConversation,$getApiWS,$phone_message,$order_message){

		$url = "messages?linkTo=id_conversation_message&equalTo=".$idConversation."&startAt=0&endAt=1&orderBy=id_message&orderMode=DESC";
		$method = "GET";
		$fields = array();

		$getMessage = CurlController::request($url,$method,$fields);

		if($getMessage->status == 200){

			$message = $getMessage->results[0];

			/*=============================================
			Pedimos datos domicilio
			=============================================*/	

			if($message->template_message == '{"type":"bot","title":"delivery"}'){

				$responseBots = BotsController::responseBots("name",$getApiWS,$phone_message,$order_message,null);
            	echo '<pre>$responseBots '; print_r($responseBots); echo '</pre>';
			}

			/*=============================================
			Confirmar orden
			=============================================*/	

			if($message->template_message == '{"type":"bot","title":"process"}'){

				$responseBots = BotsController::responseBots("confirmation",$getApiWS,$phone_message,$order_message,$message->id_conversation_message);
            	echo '<pre>$responseBots '; print_r($responseBots); echo '</pre>';
			}

		}

	}

}