<?php

class BMWCarDataDiscovery extends IPSModuleStrict {

    public function Create(): void {
        // Don't delete this line
        parent::Create();

        $this->RegisterAttributeInteger("authorizeStep", 1);
    }

    public function ApplyChanges(): void {
        // Don't delete this line
        parent::ApplyChanges();
    }

    public function nextAuthorizeStep(): void {
        $nextAuthorizeStep = $this->ReadAttributeInteger("authorizeStep") + 1;
        $nextAuthorizeStep > 5 ? $this->WriteAttributeInteger("authorizeStep", 1)
            : $this->WriteAttributeInteger("authorizeStep", $nextAuthorizeStep);
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
        $authorizeStep = $this->ReadAttributeInteger("authorizeStep");

        return [
            $authorizeStep == 2 ? [
                "type" => "PopupAlert",
                "popup" => [
                    "closeCaption" => "Cancel",
                    "items" => [
                        [
                            "type" => "Label",
                            "caption" => "test"
                        ]
                    ]
                ]
            ] : [
                "type" => "Label",
                "caption" => "test"
            ],
            [
                "type" => "PopupButton",
                "caption" => "Grant access",
                "popup" => [
                    "caption" => "Grant access for vehicles",
                    "items" => [
                        [
                            "type" => "ProgressBar",
                            "name" => "AuthorizeProgress",
                            "caption" => "Schritt " . $authorizeStep . "/5",
                            "indeterminate" => false,
                            "minimum" => 0,
                            "maximum" => 5,
                            "current" => $authorizeStep,
                            "width" => "100%"
                        ],
                        [
                            "type" => "Label",
                            "caption" => "Log into your MyBMW Account",
                            "bold" => true
                        ],
                        [
                            "type" => "Label",
                            "caption" => "Damit das IP-Symcon Modul auf deine BMW Daten zugreifen kann musst du zuerst einen CarData Client erstellen und diesen für das Modul autorisieren."
                        ],
                        [
                            "type" => "Label",
                            "color" => "c72100",
                            "caption" => "Melde dich mit dem Hauptnutzer-Konto von dem Auto an!"
                        ],
                        [
                            "type" => "Label",
                            "caption" => "https://www.bmw.de/de-de/mybmw/vehicle-overview",
                            "link" => true
                        ]
                    ],
                    "buttons" => [
                        [
                            "type" => "PopupButton",
                            "caption" => "Grant access",
                            "popup" => [
                                "caption" => "Grant access for vehicles",
                                "items" => [
                                    [
                                        "type" => "ProgressBar",
                                        "name" => "AuthorizeProgress",
                                        "caption" => "Schritt " . $authorizeStep . "/5",
                                        "indeterminate" => false,
                                        "minimum" => 0,
                                        "maximum" => 5,
                                        "current" => $authorizeStep,
                                        "width" => "100%"
                                    ],
                                    [
                                        "type" => "Label",
                                        "caption" => "Log into your MyBMW Account",
                                        "bold" => true
                                    ],
                                    [
                                        "type" => "Label",
                                        "caption" => "Damit das IP-Symcon Modul auf deine BMW Daten zugreifen kann musst du zuerst einen CarData Client erstellen und diesen für das Modul autorisieren."
                                    ],
                                    [
                                        "type" => "Label",
                                        "color" => "c72100",
                                        "caption" => "Melde dich mit dem Hauptnutzer-Konto von dem Auto an!"
                                    ],
                                    [
                                        "type" => "Label",
                                        "caption" => "https://www.bmw.de/de-de/mybmw/vehicle-overview",
                                        "link" => true
                                    ]
                                ],
                                "buttons" => [
                                    [
                                        "type" => "Button",
                                        "caption" => "Continue",
                                        "onClick" => 'BMWDiscovery_nextAuthorizeStep( $id );'
                                    ]
                                ]
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