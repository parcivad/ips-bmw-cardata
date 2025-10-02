<?php

require_once(dirname(__FILE__, 2) . "/libs/apiHandler.php");
require_once(dirname(__FILE__, 2) . "/libs/data/dataHandler.php");


class BMWCarDataDiscovery extends IPSModuleStrict {

    public function Create(): void {
        // Don't delete this line
        parent::Create();

        $this->RegisterPropertyString("clientId", null);
        $this->RegisterPropertyString("dataStreamId", null);
        $this->RegisterPropertyBoolean("stream", false);

    }

    public function ApplyChanges(): void {
        // Don't delete this line
        parent::ApplyChanges();
    }

    public function GetConfigurationForm(): string {
        return json_encode([
            'elements' => $this->FormElements(),
            'actions'  => $this->FormActions(),
            'status'   => $this->FormStatus(),
        ]);
    }

    public function authorize(): void {
        $clientId = $this->ReadPropertyString("clientId");
        if ($clientId == null) return;

        echo getDeviceCodeFlow($clientId);
    }

    public function token(): void {
        if (getDeviceCode() == null) return;

        $clientId = $this->ReadPropertyString("clientId");
        getToken($clientId);
    }

    private function FormElements(): array {
        return [
            [
                "type" => "RowLayout",
                "items" => [
                    [
                        "type" => "ColumnLayout",
                        "items" => [
                            [
                                "type" => "RowLayout",
                                "items" => [
                                    [
                                        "type" => "ValidationTextBox",
                                        "name" => "clientId",
                                        "caption" => "Client ID",
                                        "required" => true
                                    ],
                                    [
                                        "type" => "Button",
                                        "caption" => "BMW Vehicles",
                                        "link" => true,
                                        "onClick" => "echo 'https://www.bmw.de/de-de/mybmw/vehicle-overview';"
                                    ],
                                    [
                                        "type" => "Button",
                                        "caption" => "Authorize",
                                        "enabled" => $this->ReadPropertyString("clientId") != null
                                            && getDeviceCodeFlowExpiresAt() < time(),
                                        "link" => true,
                                        "onClick" => 'BMWDiscovery_authorize($id);'
                                    ],
                                    [
                                        "type" => "Button",
                                        "caption" => "Finish authorization",
                                        "enabled" => !empty(getUserCode()),
                                        "onClick" => 'BMWDiscovery_token($id);'
                                    ]
                                ]
                            ],
                            [
                                "type" => "RowLayout",
                                "items" => [
                                    [
                                        "type" => "ValidationTextBox",
                                        "caption" => "DataStream ID",
                                        "required" => true
                                    ],
                                    [
                                        "type" => "CheckBox",
                                        "caption" => "Stream",
                                        "enabled" => false,
                                        "value" => false
                                    ]
                                ]
                            ]
                        ]
                    ],
                    [
                        "type" => "Label",
                        "caption" => '"' . json_encode(getData()) . '"',
                        "width" => "40%"
                    ]
                ]
            ]
        ];
    }
    private function FormActions(): array {
        return [];
    }

    private function FormStatus(): array {
        return [];
    }
}