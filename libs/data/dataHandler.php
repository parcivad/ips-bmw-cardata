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
function getClientId(): string {
    return getData()["client_id"];
}

function getStreamId(): string {
    return getData()["stream_id"];
}

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

function getDeviceCodeFlowExpiresAt(): int {
    return getData()["device_code_flow"]["expires_at"];
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

function getExpiresAt(): int {
    return getData()["car_data"]["expires_at"];
}

// Setter
function setClientId(string $clientId): void {
    $data = getData();
    $data["client_id"] = $clientId;
    saveData($data);
}

function setStreamId(string $streamId): void {
    $data = getData();
    $data["stream_id"] = $streamId;
    saveData($data);
}

function setCodeVerifier(string $codeVerifier): void {
    $data = getData();
    $data["code_verifier"] = $codeVerifier;
    saveData($data);
}

function setDeviceCodeFlowResponse($deviceCodeFlowResponse): void {
    $data = getData();
    $data["device_code_flow"] = [
        "user_code" => $deviceCodeFlowResponse["user_code"],
        "device_code" => $deviceCodeFlowResponse["device_code"],
        "interval" => $deviceCodeFlowResponse["interval"],
        "verification_uri_complete" => $deviceCodeFlowResponse["verification_uri_complete"],
        "verification_uri" => $deviceCodeFlowResponse["verification_uri"],
        "expires_at" => time() + $deviceCodeFlowResponse["expires_in"]
    ];
    saveData($data);
}

function setCarDataTokenResponse($carDataTokenResponse): void {
    $data = getData();
    $data["car_data"] = [
        "gcid" => $carDataTokenResponse["gcid"],
        "token_type" => $carDataTokenResponse["token_type"],
        "access_token" => $carDataTokenResponse["access_token"],
        "refresh_token" => $carDataTokenResponse["refresh_token"],
        "scope" => $carDataTokenResponse["scope"],
        "id_token" => $carDataTokenResponse["id_token"],
        "expires_at" => time() + $carDataTokenResponse["expires_in"]
    ];
    saveData($data);
}

// reset
function resetDeviceCodeFlowResponse(): void {
    $data = getData();
    $data["device_code_flow"] = [
        "user_code" => "",
        "device_code" => "",
        "interval" => "",
        "verification_uri_complete" => "",
        "verification_uri" => "",
        "expires_at" => 0
    ];
    saveData($data);
}

function resetCarDataTokenResponse(): void {
    $data = getData();
    $data["device_code_flow"] = [
        "user_code" => "",
        "device_code" => "",
        "interval" => "",
        "verification_uri_complete" => "",
        "verification_uri" => "",
        "expires_at" => 0
    ];
    saveData($data);
}