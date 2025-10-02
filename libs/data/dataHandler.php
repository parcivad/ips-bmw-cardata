<?php

// Data Handler
function getData(): array {
    return json_decode(file_get_contents(dirname(__FILE__)."/data.json"), true);
}

function saveData(array $data): void {
    file_put_contents(
        dirname(__FILE__)."/data.json",
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    );
}

// Getter
function getCodeVerifier(): string {
    return getData()['code_verifier'];
}

function getUserCode(): string {
    return getData()["device_code_flow"]["user_code"];
}

function getDeviceCode(): string {
    return getData()["device_code_flow"]["device_code"];
}

function getInterval(): int {
    return getData()["device_code_flow"]["interval"];
}

function getVerificationUriComplete(): string {
    return getData()["device_code_flow"]["verification_uri_complete"];
}

function getVerificationUri(): string {
    return getData()["device_code_flow"]["verification_uri"];
}

function getDeviceCodeFlowExpires(): int {
    return getData()["device_code_flow"]["expires_in"];
}

function getGcid(): string {
    return getData()["car_data"]["gcid"];
}

function getTokenType(): string {
    return getData()["car_data"]["token_type"];
}

function getAccessToken(): string {
    return getData()["car_data"]["access_token"];
}

function getRefreshToken(): string {
    return getData()["car_data"]["refresh_token"];
}

function getScope(): string {
    return getData()["car_data"]["scope"];
}

function getIdToken(): string {
    return getData()["car_data"]["id_token"];
}

function getExpiresIn(): int {
    return getData()["car_data"]["expires_in"];
}

// Setter
function setCodeVerifier(string $codeVerifier): void {
    $data = getData();
    $data["code_verifier"] = $codeVerifier;
    saveData($data);
}

function setDeviceCodeFlowResponse($deviceCodeFlowResponse): void {
    $data = getData();
}

function setCarDataTokenResponse($carDataTokenResponse): void {
    $data = getData();
    $data["device_code_flow"] = [
        "user_code" => $carDataTokenResponse["user_code"],
        "device_code" => $carDataTokenResponse["device_code"],
        "interval" => $carDataTokenResponse["interval"],
        "verification_uri_complete" => $carDataTokenResponse["verification_uri_complete"],
        "verification_uri" => $carDataTokenResponse["verification_uri"],
        "expires_in" => $carDataTokenResponse["expires_in"],
    ];
    saveData($data);
}