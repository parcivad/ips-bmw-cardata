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

    public function testSendMethod() {
        $result = $this->SendDataToParent(json_encode([
                "DataID" => "{1FD5C2E8-43BD-F09C-CAC5-4A1E7CE08F24}",
                "Buffer" => utf8_decode("test")
            ]
        ));
        IPS_LogMessage("Send from Vehicle, received: ", utf8_decode($result->Buffer));
    }

    public function ReceiveData(string $JSONString): string {
        $data = json_decode($JSONString, true);
        //IPS_LogMessage("ReceiveData Vehicle", $JSONString);

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
