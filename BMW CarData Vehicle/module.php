<?php

class BMWCarDataVehicle extends IPSModuleStrict {

    public function Create(): void {
        // Don't delete this line
        parent::Create();

        $this->RegisterPropertyString("vin", null);
    }

    public function ApplyChanges(): void {
        // Don't delete this line
        parent::ApplyChanges();
    }

    private function FormElements(): array {
        return [];
    }
}
