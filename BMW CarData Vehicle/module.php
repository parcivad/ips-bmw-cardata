<?php

const DEVICE_TX = "{3F5C23F7-AC9B-6BFE-C27C-3336F73568B4}";

class BMWCarDataVehicle extends IPSModuleStrict {

    public function Create(): void {
        // Don't delete this line
        parent::Create();

        $this->RegisterPropertyString("vin", null);
        $this->RegisterPropertyString("containerId", null);

        $this->RegisterAttributeString("basicData", null);
        $this->RegisterAttributeString("image", null);
        $this->RegisterAttributeString("variables", null);
        $this->RegisterAttributeString("telematicData", null);

        $this->ConnectParent("{C23F025F-A4CE-7F31-CE14-0AE225778FE7}");
    }

    public function ApplyChanges(): void {
        // Don't delete this line
        parent::ApplyChanges();
    }

    public function updateVariables(array $telematicVariablesList): void {
        foreach ($telematicVariablesList as $telematicVariable) {
            if ($telematicVariable["variable"]) echo $telematicVariable["name"];
        }
    }

    public function getBasicData(): array {
        $response = $this->SendDataToParent(json_encode([
                "DataID" => DEVICE_TX,
                "method" => "GET",
                "accept" => "application/json",
                "endpoint" => "/customers/vehicles/" . $this->ReadPropertyString("vin") . "/basicData",
                "body" => ""
            ]
        ));
        $this->WriteAttributeString("basicData", $response);
        return json_decode($response, true);
    }

    public function getChargingHistory(string $from, string $to): array {
        return json_decode($this->SendDataToParent(json_encode([
                "DataID" => DEVICE_TX,
                "method" => "GET",
                "accept" => "application/json",
                "endpoint" => "/customers/vehicles/" . $this->ReadPropertyString("vin") . "/chargingHistory?from=" . $from . "&to=" . $to,
                "body" => ""
            ]
        )), true);
    }

    public function getImage(): string {
        $response = $this->SendDataToParent(json_encode([
                "DataID" => DEVICE_TX,
                "method" => "GET",
                "accept" => "*",
                "endpoint" => "/customers/vehicles/" . $this->ReadPropertyString("vin") . "/image",
                "body" => ""
            ]
        ));
        $this->WriteAttributeString("image", $response);
        return $response;
    }

    public function getLocationBasedSettings(): array {
        return json_decode($this->SendDataToParent(json_encode([
                "DataID" => DEVICE_TX,
                "method" => "GET",
                "accept" => "application/json",
                "endpoint" => "/customers/vehicles/" . $this->ReadPropertyString("vin") . "/locationBasedChargingSettings",
                "body" => ""
            ]
        )), true);
    }

    public function getTelematicData(): array {
        $response = $this->SendDataToParent(json_encode([
                "DataID" => DEVICE_TX,
                "method" => "GET",
                "accept" => "application/json",
                "endpoint" => "/customers/vehicles/" . $this->ReadPropertyString("vin")
                    . "/telematicData?containerId=" . $this->ReadPropertyString("containerId"),
                "body" => ""
            ]
        ));
        $this->WriteAttributeString("telematicData", $response);
        return json_decode($response, true)["telematicData"];
    }

    public function GetConfigurationForm(): string {
        if ($this->ReadAttributeString("basicData") == null) $this->getBasicData();
        if ($this->ReadAttributeString("image") == null) $this->getImage();
        if ($this->ReadAttributeString("telematicData") == null) $this->getTelematicData();

        $basicData = json_decode($this->ReadAttributeString("basicData"), true);
        $image = $this->ReadAttributeString("image");
        $variables = json_decode($this->ReadAttributeString("variables"), true);

        $values = [];
        foreach (json_decode($this->ReadAttributeString("telematicData"), true)["telematicData"] as $key => $value) {
            if ($value["value"] == null) continue;
            $values[] = [
                "name" => $key,
                "value" => $value["value"],
                "unit" => $value["unit"],
                "variable" => isset($variables[$key]),
                "rowColor" => isset($variables[$key]) ? "#c0ffc0" : ""
            ];
        }

        return json_encode([
            'elements' => [
                [
                    "type" => "RowLayout",
                    "items" => [
                        [
                            "type" => "Image",
                            "width" => "350px",
                            "image" => $image
                        ],
                        [
                            "type" => "List",
                            "name" => "basicData",
                            "caption" => "BMW CarData Vehicle",
                            "rowCount" => 1,
                            "add" => false,
                            "delete" => false,
                            "columns" => [
                                [
                                    "caption" => "Brand",
                                    "name" => "brand",
                                    "width" => "80px"
                                ],
                                [
                                    "caption" => "VIN",
                                    "name" => "vin",
                                    "width" => "200px"
                                ],
                                [
                                    "caption" => "Model",
                                    "name" => "model",
                                    "width" => "140px"
                                ],
                                [
                                    "caption" => "Drive Train",
                                    "name" => "driveTrain",
                                    "width" => "100px"
                                ],
                                [
                                    "caption" => "SIM Status",
                                    "name" => "simStatus",
                                    "width" => "110px"
                                ],
                                [
                                    "caption" => "Construction Date",
                                    "name" => "constructionDate",
                                    "width" => "250px"
                                ]
                            ],
                            "values" => [
                                [
                                    "vin" => $this->ReadPropertyString("vin"),
                                    "brand" => $basicData["brand"],
                                    "model" => $basicData["modelName"],
                                    "driveTrain" => $basicData["driveTrain"],
                                    "simStatus" => $basicData["simStatus"],
                                    "constructionDate" => $basicData["constructionDate"],
                                ]
                            ]
                        ]
                    ]
                ],
                [
                    "type" => "ExpansionPanel",
                    "items" => []
                ],
                [
                    "type" => "List",
                    "name" => "telematicVariablesList",
                    "caption" => "Vehicle Data",
                    "rowCount" => 0,
                    "add" => false,
                    "delete" => false,
                    "sort" => [
                        "column" => "name",
                        "direction" => "ascending"
                    ],
                    "columns" => [
                        [
                            "caption" => "Name",
                            "name" => "name",
                            "width" => "auto"
                        ],
                        [
                            "caption" => "Value",
                            "name" => "value",
                            "width" => "350px"
                        ],
                        [
                            "caption" => "Unit",
                            "name" => "unit",
                            "width" => "100px"
                        ],
                        [
                            "caption" => "Represent as variable",
                            "name" => "variable",
                            "width" => "100px",
                            "editable" => true,
                            "edit" => [
                                "type" => "CheckBox"
                            ]
                        ]
                    ],
                    "values" => $values,
                    "onEdit" => 'BMW_updateVariables($id, $telematicVariables);'
                ]
            ]
        ]);
    }
}
