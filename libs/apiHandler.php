<?php

/**
 * @throws \Random\RandomException
 */
function getDeviceCodeFlow(string $clientId): string {
    /*$headers = [
        "Content-Type" => "application/x-www-form-urlencoded",
        "Accept" => "application/json"
    ];

    $code_challenge = bin2hex(random_bytes(5));
    setCodeVerifier(hash('sha256', $code_challenge));

    $params = [
        "client_id" => $clientId,
        "response_type" => "device_code",
        "scope" => "authenticate_user openid cardata:streaming:read cardata:api:read",
        "code_challenge"
    ];
    $params = http_build_query($params);

    $curlOptions = array(
        CURLOPT_URL => "https://customer.bmwgroup.com/gcdm/oauth/device/code",
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $params,
        CURLOPT_RETURNTRANSFER => true
    );

    $ch = curl_init();
    curl_setopt_array($ch, $curlOptions);
    $response = curl_exec($ch);
    curl_close($ch);

    $query = json_decode($response, true);
    */

    // example data
    $query = json_decode('{
    "user_code": "test",
    "device_code": "test",
    "interval": 5,
    "verification_uri_complete": "https://customer.bmwgroup.com/oneid/link?user_code=test",
    "verification_uri": "https://customer.bmwgroup.com/oneid/link",
    "expires_in": 300
}', true);
    setDeviceCodeFlowResponse($query);
    return $query["verification_uri_complete"];
}