<?php

require_once(dirname(__FILE__) . "/data/dataHandler.php");

function apiCall(string $endpoint): array {
    // refresh token if access token is expired
    if (getTokenExpiresAt() >= time()) {
        getRefreshToken();
    }

    $headers = [
        "Authorization: " . getTokenType() . " " . getAccessToken(),
        "Accept: application/json",
        "x-version: v1"
    ];

    $ch = curl_init();
    curl_setopt_array($ch, array(
        CURLOPT_URL => "https://api-cardata.bmwgroup.com" . $endpoint,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_RETURNTRANSFER => true
    ));
    $response = curl_exec($ch);
    curl_close($ch);

    IPS_LogMessage("BMWDiscovery", getTokenType() . " " . getAccessToken());
    IPS_LogMessage("BMWDiscovery", json_encode($headers));

    return json_decode($response, true);
}

/**
 * @throws \Random\RandomException
 */
function getDeviceCodeFlow(): string {
    $headers = [
        "Content-Type: application/x-www-form-urlencoded",
        "Accept: application/json"
    ];

    $code_challenge = bin2hex(random_bytes(2));
    setCodeVerifier(hash('sha256', $code_challenge));

    $params = [
        "client_id" => getClientId(),
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
        CURLOPT_TIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true,
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

function getToken(): void {
    $headers = [
        "Content-Type: application/x-www-form-urlencoded",
        "Accept: application/json"
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
        CURLOPT_TIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true,
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
        "Content-Type: application/x-www-form-urlencoded",
        "Accept: application/json"
    ];

    $params = [
        "client_id" => getClientId(),
        "grant_type" => "refresh_token",
        "refresh_token" => getRefreshToken(),
    ];
    $params = http_build_query($params);

    $curlOptions = array(
        CURLOPT_URL => "https://customer.bmwgroup.com/gcdm/oauth/token",
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $params,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true,
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