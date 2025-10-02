<?php

require_once(dirname(__FILE__) . "/data/dataHandler.php");

/**
 * @throws \Random\RandomException
 */
function getDeviceCodeFlow(string $clientId): string {
    $headers = [
        "Content-Type" => "application/x-www-form-urlencoded",
        "Accept" => "application/json"
    ];

    $code_challenge = bin2hex(random_bytes(2));
    setCodeVerifier(hash('sha256', $code_challenge));

    $params = [
        "client_id" => $clientId,
        "response_type" => "device_code",
        "scope" => "authenticate_user openid cardata:streaming:read cardata:api:read",
        "code_challenge" => "6xSQkAzH8oEmFMieIfFjAlAsYMS23uhOCXg70Gf13p8", //$code_challenge,
        "code_challenge_method" => "S256",
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

    // example data
    $query = json_decode($response, true);

    if (isset($query["error"])) {
        echo $query["error_description"];
    }

    setDeviceCodeFlowResponse($query);
    return $query["verification_uri_complete"];
}

function getToken(string $clientId): void {
    $headers = [
        "Content-Type" => "application/x-www-form-urlencoded",
        "Accept" => "application/json"
    ];

    $params = [
        "client_id" => $clientId,
        "device_code" => getDeviceCode(),
        "grant_type" => "urn:ietf:params:oauth:grant-type:device_code",
        "code_verifier" => "Lc-kVofs3uj2Aj5Yrpd8X8Sa0N6tGmp4VIjflKSbFSQ"  //getCodeVerifier()
    ];
    $params = http_build_query($params);

    $curlOptions = array(
        CURLOPT_URL => "https://customer.bmwgroup.com/gcdm/oauth/token",
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

    // on error
    if (isset($query["error"])) {
        setCodeVerifier($query["error_description"]);
        return;
    }

    setCarDataTokenResponse($query);
    resetDeviceCodeFlowResponse();
}

function refreshToken(): void {
    $headers = [
        "Content-Type" => "application/x-www-form-urlencoded",
        "Accept" => "application/json"
    ];

    $params = [
        "client_id" => getClientId(),
        "device_code" => getDeviceCode(),
        "grant_type" => "urn:ietf:params:oauth:grant-type:device_code",
        "code_verifier" => "Lc-kVofs3uj2Aj5Yrpd8X8Sa0N6tGmp4VIjflKSbFSQ"  //getCodeVerifier()
    ];
    $params = http_build_query($params);

    $curlOptions = array(
        CURLOPT_URL => "https://customer.bmwgroup.com/gcdm/oauth/token",
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

    // on error
    if (isset($query["error"])) {
        setCodeVerifier($query["error_description"]);
        return;
    }

    setCarDataTokenResponse($query);
}