<?php

class BMWCarDataDiscovery extends IPSModuleStrict {

    public function Create(): void {
        // Don't delete this line
        parent::Create();

        $this->RegisterPropertyString("clientId", null);
        $this->RegisterPropertyString("dataStreamId", null);
        $this->RegisterPropertyBoolean("stream", false);

        $this->RegisterAttributeString("authUrl", null);

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

        $verification_complete_uri = getDeviceCodeFlow($clientId);
        $this->WriteAttributeString("authUrl", $verification_complete_uri);
        $this->ReloadForm();
    }

    private function FormElements(): array {

        return [
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
                                "required" => true,
                                "onChange" => 'BMWDiscovery_authorize($id);'
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
                                "enabled" => $this->ReadPropertyString("clientId") != null,
                                "link" => true,
                                "onClick" => "echo '" . $this->ReadAttributeString("authUrl") . "';"
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