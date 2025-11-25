<?php

class BMWCarDataVehicle extends IPSModuleStrict {

    public function Create(): void {
        // Don't delete this line
        parent::Create();

        $this->RegisterPropertyString("vin", null);
    }

    public function ApplyChanges(): void
    {
        // Don't delete this line
        parent::ApplyChanges();
    }

    public function ReceiveData(string $JSONString): string {
        $data = json_decode($JSONString, true);
        IPS_LogMessage("ReceiveData Vehicle", utf8_decode($data->Buffer));

        return "OK von Vehicle: " . $this->InstanceID;
    }


    private function FormElements(): array {
        return [
            [
                "type" => "RowLayout",
                "items" => [
                    [
                        "type" => "ColumnLayout",
                        "items" => [
                            //TODO: Add Vehicle Information
                        ]
                    ],
                    [
                        "type" => "Image",
                        "image" => "" // TODO: Data image of the vehicle
                    ]
                ]
            ]
        ];
    }
}
