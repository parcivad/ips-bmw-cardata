<?php

class BMWCarDataDiscovery extends IPSModuleStrict {

    public function Create(): void {
        // Don't delete this line
        parent::Create();
    }

    public function ApplyChanges(): void {
        // Don't delete this line
        parent::ApplyChanges();
    }

    public function GetConfigurationForm(): string {
        // return current form
        $Form = json_encode([
            'elements' => $this->FormElements(),
            'actions'  => $this->FormActions(),
            'status'   => $this->FormStatus(),
        ]);
        $this->SendDebug('FORM', $Form, 0);
        $this->SendDebug('FORM', json_last_error_msg(), 0);

        return $Form;
    }

    private function FormElements(): array {
        return [];
    }

    private function FormActions(): array {
        return [];
    }

    private function FormStatus(): array {
        return [];
    }
}