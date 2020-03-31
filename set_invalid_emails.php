<?php
$in_file = "invalid_emails.csv";

$base_url = "http://localhost/demo910ent/rest/v10";
$username = "jim";
$password = "jim";
$migrator = "1"; //PROD

ini_set('max_execution_time', 0);
$script_start = time();
$time_start = time();
$DEBUG = "";

//////////////////////////////////////////////////////////
//Login - POST /oauth2/token
//////////////////////////////////////////////////////////

$login_url = $base_url . "/oauth2/token";
$logout_url = $base_url . "/oauth2/logout";

$oauth2_token_arguments = array(
    "grant_type" => "password",
    //client id/secret you created in Admin > OAuth Keys
    "client_id" => "sugar",
    "client_secret" => "",
    "username" => $username,
    "password" => $password,
    "platform" => "mobile"
);

$oauth2_token_response = call($login_url, '', 'POST', $oauth2_token_arguments);
$DEBUG .= print_r($oauth2_token_response,true) . "</br>\n";
$time_max = $oauth2_token_response->expires_in - 60;

//////////////////////////////////////////////////////////
//READ CSV file and set EmailAddress to invalid
//////////////////////////////////////////////////////////

$row = 0;

if (($handle = fopen($in_file, "r")) !== FALSE) {
    while (($data = fgetcsv($handle, 1000, ';', '"')) !== FALSE) {

		if ((time()-$time_start)>$time_max) {
            call($logout_url, '', 'POST', $oauth2_token_arguments);
			$oauth2_token_response = call($login_url, '', 'POST', $oauth2_token_arguments);
			$DEBUG .= print_r($oauth2_token_response,true) . "</br>\n";
            $time_start = time();
		}
		
		$row++;

//EXIT			
//		if ($row > 2) die();	// STOP for TEST
//EXIT

		$num = count($data);
        $DEBUG .= "$num|$row|";
        for ($c=0; $c < $num; $c++) {
            $DEBUG .= $data[$c] . "|";
        }
		$DEBUG .= "|</br>\n";		


//Header
		if ($row == 1) continue;	

        $email_upper = strtoupper($data[0]);
			
        //////////////////////////////////////////////////////////   			
		//Search email_address record - GET /<module>/
        //////////////////////////////////////////////////////////   			
		$url = $base_url . '/EmailAddresses';

		$email_arguments = array(
		    "filter" => array(
			               array(
						      "email_address_caps" => $email_upper					   
						   )
						),
			"max_num" => 100,
			"offset" => 0,
			"fields" => "",
		);
		$email_response = call($url, $oauth2_token_response->access_token, 'GET', $email_arguments);
		$DEBUG .= print_r($email_response,true) . "##</br>";
		
		if (count($email_response->records) > 0) {
			foreach($email_response->records as $idx => $eadr) {
				$email_addr_id = $eadr->id;
				$email_addr_inv = $eadr->invalid_email;
				if (!$email_addr_inv) {
					$DEBUG .= "SET EMAIL_ADDR to INVALID " . $email_addr_id. " ##</br>";
					
					//////////////////////////////////////////////////////////   			
					//Update email address record - PUT /<module>/:record
					//////////////////////////////////////////////////////////   			
					$url = $base_url . "/EmailAddresses/" . $email_addr_id;
					
					$email_arguments2 = array(
						"invalid_email" => 1,
						"opt_out" => 1,
					);
					$email_response2 = call($url, $oauth2_token_response->access_token, 'PUT', $email_arguments2);
					$DEBUG .= print_r($email_response2,true) . "##</br>";									
				}
			}
		}
        echo $DEBUG; $DEBUG="";						
    }
    fclose($handle);
}

$script_runtime = time()-$script_start;
$DEBUG .= "TIME needed: ".$script_runtime."<br>\n";
echo $DEBUG; $DEBUG="";


//////////////////////////////////////////////////////////
// END OF MAIN
//////////////////////////////////////////////////////////


/**
 * Generic function to make cURL request.
 * @param $url - The URL route to use.
 * @param string $oauthtoken - The oauth token.
 * @param string $type - GET, POST, PUT, DELETE. Defaults to GET.
 * @param array $arguments - Endpoint arguments.
 * @param array $encodeData - Whether or not to JSON encode the data.
 * @param array $returnHeaders - Whether or not to return the headers.
 * @return mixed
 */
function call(
    $url,
    $oauthtoken='',
    $type='GET',
    $arguments=array(),
    $encodeData=true,
    $returnHeaders=false
)
{
    $type = strtoupper($type);

    if ($type == 'GET')
    {
        $url .= "?" . http_build_query($arguments);
    }

    $curl_request = curl_init($url);

    if ($type == 'POST')
    {
        curl_setopt($curl_request, CURLOPT_POST, 1);
    }
    elseif ($type == 'PUT')
    {
        curl_setopt($curl_request, CURLOPT_CUSTOMREQUEST, "PUT");
    }
    elseif ($type == 'DELETE')
    {
        curl_setopt($curl_request, CURLOPT_CUSTOMREQUEST, "DELETE");
    }

    curl_setopt($curl_request, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
    curl_setopt($curl_request, CURLOPT_HEADER, $returnHeaders);
    curl_setopt($curl_request, CURLOPT_SSL_VERIFYHOST, 0);  // wichtig
    curl_setopt($curl_request, CURLOPT_SSL_VERIFYPEER, 0);  // wichtig
    curl_setopt($curl_request, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl_request, CURLOPT_FOLLOWLOCATION, 0);

    if (!empty($oauthtoken)) 
    {
        curl_setopt($curl_request, CURLOPT_HTTPHEADER, array("oauth-token: {$oauthtoken}","Content-Type: application/json"));
    }
    else
    {
        curl_setopt($curl_request, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));
    }

    if (!empty($arguments) && $type !== 'GET')
    {
        if ($encodeData)
        {
            //encode the arguments as JSON
            $arguments = json_encode($arguments);
        }
        curl_setopt($curl_request, CURLOPT_POSTFIELDS, $arguments);
    }

    $result = curl_exec($curl_request);
	
    if ($returnHeaders)
    {
        //set headers from response
        list($headers, $content) = explode("\r\n\r\n", $result ,2);
        foreach (explode("\r\n",$headers) as $header)
        {
            header($header);
        }

        //return the nonheader data
        return trim($content);
    }

    curl_close($curl_request);

    //decode the response from JSON
    $response = json_decode($result);

    return $response;
}
?>