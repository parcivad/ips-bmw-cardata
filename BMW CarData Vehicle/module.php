<?php

const DEVICE_TX = "{3F5C23F7-AC9B-6BFE-C27C-3336F73568B4}";

class BMWCarDataVehicle extends IPSModuleStrict {

    public function Create(): void {
        // Don't delete this line
        parent::Create();

        $this->RegisterPropertyString("vin", null);
        $this->RegisterPropertyString("containerId", null);
        $this->RegisterPropertyBoolean("update", false);
        $this->RegisterPropertyInteger("updateInterval", 60);

        $this->RegisterAttributeString("basicData", null);
        $this->RegisterAttributeString("image", null);
        $this->RegisterAttributeString("variables", "{}");
        $this->RegisterAttributeString("telematicData", null);

        $this->RegisterTimer('update', 0, "getTelematicData($this->InstanceID);");

        $this->ConnectParent("{C23F025F-A4CE-7F31-CE14-0AE225778FE7}");
    }

    public function ApplyChanges(): void {
        // Don't delete this line
        parent::ApplyChanges();

        if ($this->ReadPropertyBoolean("automaticUpdate")) {
            $interval = $this->ReadPropertyInteger("updateInterval") * 60;
            $this->SetTimerInterval("update", $interval);
        } else {
            $this->SetTimerInterval("update", 0);
        }
    }

    public function updateVariables($telematicList): void {
        $variables = json_decode($this->ReadAttributeString("variables"), true);

        foreach ($telematicList as $telematic) {
            $key = $telematic["key"];
            $value = $telematic["value"];
            $variable = $telematic["variable"];
            $ident = str_replace(".", "", $key);

            if ($variable && !isset($variables[$key])) {
                switch (gettype(json_decode($value))) {
                    case "boolean":
                        $this->RegisterVariableBoolean($ident, $key);
                        break;
                    case "integer":
                        $this->RegisterVariableInteger($ident, $key);
                        break;
                    case "double":
                        $this->RegisterVariableFloat($ident, $key);
                        break;
                    default:
                        $this->RegisterVariableString($ident, $key);
                }
                $this->SetValue($ident, $value);
                $variables[$key] = true;
            }

            if (!$variable && isset($variables[$key])) {
                $this->UnregisterVariable($ident);
                unset($variables[$key]);
            }
        }

        $this->WriteAttributeString("variables", json_encode($variables));
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

    public function getImage(): mixed {
        $response = $this->SendDataToParent(json_encode([
                "DataID" => DEVICE_TX,
                "method" => "GET",
                "accept" => "*/*",
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

        // update variables
        $telematicData = json_decode($response, true)["telematicData"];
        $variables = json_decode($this->ReadAttributeString("variables"), true);
        foreach ($variables as $key => $value) {
            $value = $telematicData[$key]["value"];
            $ident = str_replace(".", "", $key);
            $this->SetValue($ident, $value);
        }


        $this->WriteAttributeString("telematicData", json_encode($telematicData));
        return $telematicData;
    }

    public function GetConfigurationForm(): string {
        if ($this->ReadAttributeString("basicData") == null || $this->ReadAttributeString("telematicData") == null) {
            $this->getBasicData();
            $this->getImage();
            $this->getTelematicData();
        }

        $basicData = json_decode($this->ReadAttributeString("basicData"), true);
        $image = $this->ReadAttributeString("image");
        $variables = json_decode($this->ReadAttributeString("variables"), true);

        $values = [];
        try {
            foreach (json_decode($this->ReadAttributeString("telematicData"), true) as $key => $value) {
                if ($value["value"] == null) continue;
                $values[] = [
                    "key" => $key,
                    "value" => $value["value"],
                    "unit" => $value["unit"] == null ? "" : $value["unit"],
                    "variable" => isset($variables[$key]),
                    "rowColor" => isset($variables[$key]) ? "#c0ffc0" : ""
                ];
            }
        } catch (Exception $exception) {}

        return json_encode([
            'elements' => [
                [
                    "type" => "RowLayout",
                    "width" => "auto",
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
                    "caption" => "🔄 Automatic updates",
                    "items" => [
                        [
                            "type" => "Label",
                            "label" => "Keep in mind that there is a daily Api-Rate-Limit of 50 request per day. Multiple cars on the same account share this limit!"
                        ],
                        [
                            "type" => "CheckBox",
                            "name" => "automaticUpdate",
                            "caption" => "Automatic updates"
                        ],
                        [
                            "type" => "NumberSpinner",
                            "name" => "updateInterval",
                            "caption" => "Update interval",
                            "suffix" => "minutes",
                            "minimum" => 30,
                            "maximum" => 1400,
                            "step" => 1
                        ]
                    ]
                ],
                [
                    "type" => "List",
                    "name" => "telematicList",
                    "caption" => "Vehicle Data",
                    "sort" => [
                        "column" => "key",
                        "direction" => "ascending"
                    ],
                    "columns" => [
                        [
                            "caption" => "Key",
                            "name" => "key",
                            "width" => "auto"
                        ],
                        [
                            "caption" => "Value",
                            "name" => "value",
                            "width" => "260px"
                        ],
                        [
                            "caption" => "Unit",
                            "name" => "unit",
                            "width" => "80px"
                        ],
                        [
                            "caption" => "Variable",
                            "name" => "variable",
                            "width" => "80px",
                            "edit" => [
                                "type" => "CheckBox"
                            ]
                        ]
                    ],
                    "values" => $values,
                    "onEdit" => 'BMW_updateVariables($id, $telematicList);'
                ]
            ],
            "actions" => [
                [
                    "type" => "Button",
                    "label" => "Refresh Telematic Data",
                    "onClick" => 'BMW_getTelematicData($id);'
                ]
            ]
        ]);
    }
}
