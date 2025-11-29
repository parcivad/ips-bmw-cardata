<?php

class BMWCarDataVehicle extends IPSModuleStrict {

    public function Create(): void {
        // Don't delete this line
        parent::Create();

        $this->GetCompatibleParents();

        $this->RegisterPropertyString("vin", null);

        $this->RegisterAttributeString("basicData", null);
        $this->RegisterAttributeString("image", null);
    }

    public function ApplyChanges(): void
    {
        // Don't delete this line
        parent::ApplyChanges();
    }

    public function GetCompatibleParents() {
        return '{"type": "require", "moduleIDs": ["{C23F025F-A4CE-7F31-CE14-0AE225778FE7}"]}';
    }

    public function testSendMethod() {
        $response = $this->SendDataToParent(json_encode([
                "DataID" => "{3F5C23F7-AC9B-6BFE-C27C-3336F73568B4}",
                "method" => "GET",
                "accept" => "*",
                "endpoint" => "/customers/vehicles/{$this->ReadPropertyString("vin")}/image",
                "body" => ""
            ]
        ));
        $this->WriteAttributeString("image", "data:image/jpeg;base64, " . base64_encode($response));
        IPS_LogMessage("image", "data:image/jpeg;base64, " . base64_encode($response));
    }

    public function GetConfigurationForm(): string {
        return json_encode([
            'elements' => [
                [
                    "type" => "Image",
                    "image" => $this->ReadAttributeString("image")
                ]
            ]
        ]);
    }
}
