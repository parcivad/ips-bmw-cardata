<?php

class BMWCarDataCommunicator extends IPSModuleStrict {

    public function Create(): void {
        parent::Create();

        $this->RegisterPropertyString("clientId", null);

        $this->RegisterAttributeString("containerId", null);

        $this->RegisterAttributeString("codeVerifier", null);
        $this->RegisterAttributeString("userCode", null);
        $this->RegisterAttributeString("deviceCode", null);
        $this->RegisterAttributeString("interval", null);
        $this->RegisterAttributeString("verificationUri", null);
        $this->RegisterAttributeInteger("deviceCodeExpiresAt", null);

        $this->RegisterAttributeString("gcid", null);
        $this->RegisterAttributeString("tokenType", null);
        $this->RegisterAttributeString("accessToken", null);
        $this->RegisterAttributeString("refreshToken", null);
        $this->RegisterAttributeString("scope", null);
        $this->RegisterAttributeString("idToken", null);
        $this->RegisterAttributeInteger("carDataExpiresAt", null);
    }

    public function ApplyChanges(): void {
        // Don't delete this line
        parent::ApplyChanges();

        if (empty($this->ReadPropertyString("clientId"))) {
            $this->WriteAttributeString("gcid", null);
            $this->WriteAttributeString("tokenType", null);
            $this->WriteAttributeString("accessToken", null);
            $this->WriteAttributeString("refreshToken", null);
            $this->WriteAttributeString("scope", null);
            $this->WriteAttributeString("idToken", null);
            $this->WriteAttributeInteger("carDataExpiresAt", null);
            $this->WriteAttributeString("userCode", null);
            $this->WriteAttributeString("deviceCode", null);
            $this->WriteAttributeString("interval", null);
            $this->WriteAttributeString("verificationUri", null);
            $this->WriteAttributeInteger("deviceCodeExpiresAt", null);
        }
    }

    public function ForwardData(string $JSONString): string {
        // check if token is expired
        if ($this->ReadAttributeString("refreshToken") != null
            && $this->ReadAttributeInteger("carDataExpiresAt") <= time()) {
            $this->refreshToken();
        }

        // get data
        $data = json_decode($JSONString, true);
        $tokenType = $this->ReadAttributeString("tokenType");
        $accessToken = $this->ReadAttributeString("accessToken");

        // log
        IPS_LogMessage("BMWCommunicator", "request " . $data["endpoint"]);

        $headers = [
            "Authorization: " . $tokenType . " " . $accessToken,
            "Accept: {$data["accept"]}",
            "x-version: v1"
        ];

        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => "https://api-cardata.bmwgroup.com" . $data["endpoint"],
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => $data["method"],
            CURLOPT_POSTFIELDS => $data["body"],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_BINARYTRANSFER => true
        ));
        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // checking on errors
        $this->SetStatus($statusCode == 200 ? 102 : $statusCode);

        if (isset($data["image"])) {
            $rawBinary = mb_convert_encoding($response, 'ISO-8859-1', 'utf-8');
            $base64 = base64_encode($rawBinary);
            return "data:image/png;base64," . $base64;
        }

        return $response;
    }

    public function authorize(): void {
        if ($this->ReadPropertyString("clientId") == null) return;

        $headers = [
            "Content-Type: application/x-www-form-urlencoded",
            "Accept: application/json"
        ];

        $code_challenge = bin2hex(random_bytes(2));
        $this->WriteAttributeString("codeVerifier", hash('sha256', $code_challenge));
        //TODO use it fr

        $params = [
            "client_id" => $this->ReadPropertyString("clientId"),
            "response_type" => "device_code",
            "scope" => "authenticate_user openid cardata:streaming:read cardata:api:read",
            "code_challenge" => "6xSQkAzH8oEmFMieIfFjAlAsYMS23uhOCXg70Gf13p8", //$code_challenge,
            "code_challenge_method" => "S256",
        ];
        $params = http_build_query($params);

        $curlOptions = array(
            CURLOPT_URL => "https://customer.bmwgroup.com/gcdm/oauth/device/code",
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $params,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_RETURNTRANSFER => true
        );

        $ch = curl_init();
        curl_setopt_array($ch, $curlOptions);
        $response = curl_exec($ch);
        curl_close($ch);

        // example data
        $query = json_decode($response, true);

        if (isset($query["error"])) {
            echo $query["error_description"];
        }

        $this->WriteAttributeString("userCode", $query["user_code"]);
        $this->WriteAttributeString("deviceCode", $query["device_code"]);
        $this->WriteAttributeString("interval", $query["interval"]);
        $this->WriteAttributeString("verificationUri", $query["verification_uri"]);
        $this->WriteAttributeInteger("deviceCodeExpiresAt", time() + $query["expires_in"]);

        echo $query["verification_uri"] . "?user_code=" . $query["user_code"];
        $this->ReloadForm();
    }

    public function token(): void {
        if ($this->ReadAttributeString("deviceCode") == null) return;

        $headers = [
            "Content-Type: application/x-www-form-urlencoded",
            "Accept: application/json"
        ];

        $params = [
            "client_id" => $this->ReadPropertyString("clientId"),
            "device_code" => $this->ReadAttributeString("deviceCode"),
            "grant_type" => "urn:ietf:params:oauth:grant-type:device_code",
            "code_verifier" => "Lc-kVofs3uj2Aj5Yrpd8X8Sa0N6tGmp4VIjflKSbFSQ"
        ];
        $params = http_build_query($params);

        $curlOptions = array(
            CURLOPT_URL => "https://customer.bmwgroup.com/gcdm/oauth/token",
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $params,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_RETURNTRANSFER => true
        );

        $ch = curl_init();
        curl_setopt_array($ch, $curlOptions);
        $response = curl_exec($ch);
        curl_close($ch);
        $query = json_decode($response, true);

        // on error
        if (isset($query["error"])) {
            setCodeVerifier($query["error_description"]);
            return;
        }

        $this->WriteAttributeString("gcid", $query["gcid"]);
        $this->WriteAttributeString("tokenType", $query["token_type"]);
        $this->WriteAttributeString("accessToken", $query["access_token"]);
        $this->WriteAttributeString("refreshToken", $query["refresh_token"]);
        $this->WriteAttributeString("scope", $query["scope"]);
        $this->WriteAttributeString("idToken", $query["id_token"]);
        $this->WriteAttributeInteger("carDataExpiresAt", time() + $query["expires_in"]);
        $this->WriteAttributeString("userCode", null);
        $this->WriteAttributeString("deviceCode", null);
        $this->WriteAttributeString("interval", null);
        $this->WriteAttributeString("verificationUri", null);
        $this->WriteAttributeInteger("deviceCodeExpiresAt", null);

        $this->ReloadForm();
    }

    private function refreshToken(): void {
        $headers = [
            "Content-Type: application/x-www-form-urlencoded",
            "Accept: application/json"
        ];

        $params = [
            "client_id" => $this->ReadPropertyString("clientId"),
            "grant_type" => "refresh_token",
            "refresh_token" => $this->ReadAttributeString("refreshToken")
        ];
        $params = http_build_query($params);

        $curlOptions = array(
            CURLOPT_URL => "https://customer.bmwgroup.com/gcdm/oauth/token",
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $params,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_RETURNTRANSFER => true
        );

        $ch = curl_init();
        curl_setopt_array($ch, $curlOptions);
        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $query = json_decode($response, true);

        $this->SetStatus($statusCode == 200 ? 102 : $statusCode);

        $this->WriteAttributeString("gcid", $query["gcid"]);
        $this->WriteAttributeString("tokenType", $query["token_type"]);
        $this->WriteAttributeString("accessToken", $query["access_token"]);
        $this->WriteAttributeString("refreshToken", $query["refresh_token"]);
        $this->WriteAttributeString("scope", $query["scope"]);
        $this->WriteAttributeString("idToken", $query["id_token"]);
        $this->WriteAttributeInteger("carDataExpiresAt", time() + $query["expires_in"]);
    }

    private function getContainer(): void {
        // check for existing telematic container because of limitation
        $containers = json_decode($this->ForwardData(json_encode([
                "DataID" => "{3F5C23F7-AC9B-6BFE-C27C-3336F73568B4}",
                "method" => "GET",
                "accept" => "application/json",
                "endpoint" => "/customers/containers",
                "body" => ""
            ]
        )), true)["containers"];

        foreach ($containers as $container) {
            if ($container["name"] == "ips-bmw-cardata") {
                $this->WriteAttributeString("containerId", $container["containerId"]);
                return;
            }
        }

        // Create IPS telematic Container
        $container = json_decode($this->ForwardData(json_encode([
                "DataID" => "{3F5C23F7-AC9B-6BFE-C27C-3336F73568B4}",
                "method" => "POST",
                "accept" => "application/json",
                "endpoint" => "/customers/containers",
                "body" => json_encode([
                    "name" => "ips-bmw-cardata",
                    "purpose" => "IPS BMW Cardata public api module",
                    "technicalDescriptors" => [
                        "vehicle.vehicle.antiTheftAlarmSystem.alarm.activationTime",
                        "vehicle.vehicle.antiTheftAlarmSystem.alarm.armStatus",
                        "vehicle.vehicle.antiTheftAlarmSystem.alarm.isOn",
                        "vehicle.channel.teleservice.status",
                        "vehicle.electricalSystem.battery.voltage",
                        "vehicle.drivetrain.electricEngine.charging.profile.mode",
                        "vehicle.cabin.convertible.roofRetractableStatus",
                        "vehicle.drivetrain.internalCombustionEngine.engine.ect",
                        "vehicle.channel.ngtp.timeVehicle",
                        "vehicle.status.serviceTime.inspectionDateLegal",
                        "vehicle.vehicle.deepSleepModeActive",
                        "vehicle.cabin.hvac.preconditioning.configuration.defaultSettings.seat.row1.driverSide.cooling",
                        "vehicle.cabin.hvac.preconditioning.configuration.defaultSettings.seat.row1.driverSide.heating",
                        "vehicle.cabin.hvac.preconditioning.configuration.defaultSettings.seat.row1.passengerSide.cooling",
                        "vehicle.cabin.hvac.preconditioning.configuration.defaultSettings.seat.row1.passengerSide.heating",
                        "vehicle.cabin.hvac.preconditioning.configuration.defaultSettings.seat.row2.driverSide.cooling",
                        "vehicle.cabin.hvac.preconditioning.configuration.defaultSettings.seat.row2.driverSide.heating",
                        "vehicle.cabin.hvac.preconditioning.configuration.defaultSettings.seat.row2.passengerSide.cooling",
                        "vehicle.cabin.hvac.preconditioning.configuration.defaultSettings.seat.row2.passengerSide.heating",
                        "vehicle.cabin.hvac.preconditioning.configuration.defaultSettings.steeringWheel.heating",
                        "vehicle.cabin.hvac.preconditioning.configuration.defaultSettings.seat.row3.driverSide.cooling",
                        "vehicle.cabin.hvac.preconditioning.configuration.defaultSettings.seat.row3.driverSide.heating",
                        "vehicle.cabin.hvac.preconditioning.configuration.defaultSettings.seat.row3.passengerSide.cooling",
                        "vehicle.cabin.hvac.preconditioning.configuration.defaultSettings.seat.row3.passengerSide.heating",
                        "vehicle.cabin.hvac.preconditioning.configuration.directStartSettings.targetTemperature",
                        "vehicle.cabin.infotainment.displayUnit.distance",
                        "vehicle.status.serviceDistance.yellow",
                        "vehicle.cabin.infotainment.navigation.destinationSet.distance",
                        "vehicle.status.serviceDistance.next",
                        "vehicle.cabin.door.status",
                        "vehicle.cabin.hvac.preconditioning.status.isExteriorMirrorHeatingActive",
                        "vehicle.electronicControlUnit.diagnosticTroubleCodes.raw",
                        "vehicle.cabin.door.row1.driver.position",
                        "vehicle.cabin.seat.row1.driverSide.cooling",
                        "vehicle.cabin.hvac.preconditioning.configuration.directStartSettings.seat.row1.driverSide.cooling",
                        "vehicle.cabin.hvac.preconditioning.configuration.directStartSettings.seat.row1.driverSide.heating",
                        "vehicle.cabin.seat.row1.driverSide.heating",
                        "vehicle.cabin.hvac.preconditioning.configuration.directStartSettings.seat.row1.passengerSide.cooling",
                        "vehicle.cabin.hvac.preconditioning.configuration.directStartSettings.seat.row1.passengerSide.heating",
                        "vehicle.cabin.door.row1.passenger.position",
                        "vehicle.cabin.seat.row1.passengerSide.cooling",
                        "vehicle.cabin.seat.row1.passengerSide.heating",
                        "vehicle.electricalSystem.battery.serviceDemand.replace",
                        "vehicle.cabin.hvac.statusAirPurification",
                        "vehicle.channel.teleservice.lastBreakdownCallTime",
                        "vehicle.channel.teleservice.lastManualCallTime",
                        "vehicle.electricalSystem.battery.stateOfCharge",
                        "vehicle.electricalSystem.battery.stateOfChargePlausibility",
                        "vehicle.cabin.infotainment.navigation.pointsOfInterests.max",
                        "vehicle.vehicle.travelledDistance",
                        "vehicle.cabin.infotainment.isMobilePhoneConnected",
                        "vehicle.isMoving",
                        "vehicle.cabin.infotainment.navigation.destinationSet.latitude",
                        "vehicle.cabin.infotainment.navigation.destinationSet.longitude",
                        "vehicle.electricalSystem.battery.serviceDemand.recharge",
                        "vehicle.status.conditionBasedServicesCount",
                        "vehicle.cabin.infotainment.navigation.pointsOfInterests.available",
                        "vehicle.cabin.infotainment.navigation.currentLocation.heading",
                        "vehicle.vehicle.preConditioning.isRemoteEngineStartAllowed",
                        "vehicle.cabin.hvac.preconditioning.status.comfortState",
                        "vehicle.cabin.hvac.preconditioning.status.progress",
                        "vehicle.cabin.hvac.preconditioning.status.remainingRunningTime",
                        "vehicle.vehicle.preConditioning.activity",
                        "vehicle.sevice.preferredSevicePartner",
                        "vehicle.cabin.hvac.preconditioning.status.rearDefrostActive",
                        "vehicle.cabin.hvac.preconditioning.configuration.directStartSettings.seat.row2.driverSide.cooling",
                        "vehicle.cabin.hvac.preconditioning.configuration.directStartSettings.seat.row2.driverSide.heating",
                        "vehicle.cabin.door.row2.driver.position",
                        "vehicle.cabin.seat.row2.driverSide.cooling",
                        "vehicle.cabin.seat.row2.driverSide.heating",
                        "vehicle.cabin.hvac.preconditioning.configuration.directStartSettings.seat.row2.passengerSide.cooling",
                        "vehicle.cabin.hvac.preconditioning.configuration.directStartSettings.seat.row2.passengerSide.heating",
                        "vehicle.cabin.door.row2.passenger.position",
                        "vehicle.cabin.seat.row2.passengerSide.cooling",
                        "vehicle.cabin.seat.row2.passengerSide.heating",
                        "vehicle.body.trunk.window.isOpen",
                        "vehicle.vehicle.preConditioning.error",
                        "vehicle.vehicle.preConditioning.remainingTime",
                        "vehicle.cabin.infotainment.navigation.remainingRange",
                        "vehicle.cabin.hvac.preconditioning.configuration.isRemoteEngineStartDisclaimer",
                        "vehicle.serviceDemand.defect.id",
                        "vehicle.drivetrain.engine.isActive",
                        "vehicle.channel.ista.obfcm.lastTransmissionStatus",
                        "vehicle.body.trunk.isOpen",
                        "vehicle.cabin.convertible.roofStatus",
                        "vehicle.cabin.door.lock.status",
                        "vehicle.drivetrain.engine.isIgnitionOn",
                        "vehicle.cabin.door.row1.driver.isOpen",
                        "vehicle.cabin.window.row1.driver.status",
                        "vehicle.cabin.door.row1.passenger.isOpen",
                        "vehicle.cabin.window.row1.passenger.status",
                        "vehicle.body.hood.isOpen",
                        "vehicle.body.lights.isRunningOn",
                        "vehicle.cabin.door.row2.driver.isOpen",
                        "vehicle.cabin.window.row2.driver.status",
                        "vehicle.cabin.door.row2.passenger.isOpen",
                        "vehicle.cabin.window.row2.passenger.status",
                        "vehicle.cabin.sunroof.status",
                        "vehicle.cabin.hvac.preconditioning.configuration.directStartSettings.steeringWheel.heating",
                        "vehicle.cabin.steeringWheel.heating",
                        "vehicle.cabin.sunroof.overallStatus",
                        "vehicle.cabin.sunroof.relativePosition",
                        "vehicle.cabin.sunroof.shade.position",
                        "vehicle.drivetrain.fuelSystem.remainingFuel",
                        "vehicle.drivetrain.fuelSystem.level",
                        "vehicle.cabin.hvac.preconditioning.configuration.defaultSettings.targetTemperature",
                        "vehicle.cabin.hvac.preconditioning.configuration.directStartSettings.seat.row3.driverSide.cooling",
                        "vehicle.cabin.hvac.preconditioning.configuration.directStartSettings.seat.row3.driverSide.heating",
                        "vehicle.cabin.seat.row3.driverSide.cooling",
                        "vehicle.cabin.seat.row3.driverSide.heating",
                        "vehicle.cabin.hvac.preconditioning.configuration.directStartSettings.seat.row3.passengerSide.cooling",
                        "vehicle.cabin.hvac.preconditioning.configuration.directStartSettings.seat.row3.passengerSide.heating",
                        "vehicle.cabin.seat.row3.passengerSide.cooling",
                        "vehicle.cabin.seat.row3.passengerSide.heating",
                        "vehicle.cabin.sunroof.tiltStatus",
                        "vehicle.status.serviceTime.hUandAuServiceYellow",
                        "vehicle.status.serviceTime.yellow",
                        "vehicle.cabin.infotainment.navigation.destinationSet.arrivalTime",
                        "vehicle.vehicle.timeSetting",
                        "vehicle.body.trunk.door.isOpen",
                        "vehicle.body.trunk.left.door.isOpen",
                        "vehicle.body.trunk.isLocked",
                        "vehicle.body.trunk.lower.door.isOpen",
                        "vehicle.body.trunk.right.door.isOpen",
                        "vehicle.body.trunk.upper.door.isOpen",
                        "vehicle.vehicle.preConditioning.isRemoteEngineRunning",
                        "vehicle.cabin.infotainment.navigation.currentLocation.altitude",
                        "vehicle.cabin.infotainment.navigation.currentLocation.latitude",
                        "vehicle.cabin.infotainment.navigation.currentLocation.longitude",
                        "vehicle.status.conditionBasedServicesAverageDistancePerDay",
                        "vehicle.vehicle.averageWeeklyDistanceShortTerm",
                        "vehicle.vehicle.averageWeeklyDistanceLongTerm",
                        "vehicle.status.checkControlMessages",
                        "vehicle.status.conditionBasedServices",
                        "vehicle.privacySettings.dataCollection.regulations.obfcm",
                        "vehicle.drivetrain.lastRemainingRange",
                        "vehicle.drivetrain.fuelSystem.consumptionOverLifeTime.inChargeIncreasing.referenceDistance",
                        "vehicle.drivetrain.fuelSystem.consumptionOverLifeTime.inChargeDepleting.referenceDistanceEngineOn",
                        "vehicle.drivetrain.fuelSystem.consumptionOverLifeTime.inChargeDepleting.referenceDistanceEngineOff",
                        "vehicle.drivetrain.fuelSystem.consumptionOverLifeTime.overall.referenceDistance",
                        "vehicle.drivetrain.fuelSystem.consumptionOverLifeTime.inChargeIncreasing.fuel",
                        "vehicle.drivetrain.fuelSystem.consumptionOverLifeTime.inChargeDepleting.fuel",
                        "vehicle.vehicle.speedRange.lowerBound",
                        "vehicle.vehicle.speedRange.upperBound",
                        "vehicle.drivetrain.fuelSystem.consumptionOverLifeTime.overall.fuel",
                        "vehicle.channel.teleservice.lastAutomaticServiceCallTime",
                        "vehicle.channel.teleservice.lastTeleserviceReportTime",
                        "vehicle.electricalSystem.battery48V.stateOfHealth.displayed",
                        "vehicle.drivetrain.electricEngine.charging.acAmpere",
                        "vehicle.drivetrain.electricEngine.charging.acRestriction.isChosen",
                        "vehicle.drivetrain.electricEngine.charging.acRestriction.factor",
                        "vehicle.drivetrain.electricEngine.charging.acVoltage",
                        "vehicle.powertrain.electric.battery.charging.acousticLimit",
                        "vehicle.trip.segment.accumulated.drivetrain.transmission.setting.fractionDriveEcoPro",
                        "vehicle.trip.segment.accumulated.drivetrain.transmission.setting.fractionDriveEcoProPlus",
                        "vehicle.powertrain.electric.battery.preconditioning.automaticMode.statusFeedback",
                        "vehicle.vehicle.avgAuxPower",
                        "vehicle.drivetrain.avgElectricRangeConsumption",
                        "vehicle.vehicle.avgSpeed",
                        "vehicle.powertrain.electric.battery.charging.batteryCarePersisted.isPreservingChargingMode",
                        "vehicle.powertrain.electric.battery.charging.batteryCarePersisted.isActive",
                        "vehicle.powertrain.tractionBattery.charging.port.anyPosition.flap.isAutomaticOpenAndCloseActive",
                        "vehicle.powertrain.tractionBattery.charging.port.anyPosition.flap.isOpen",
                        "vehicle.powertrain.tractionBattery.charging.port.anyPosition.isPlugged",
                        "vehicle.powertrain.tractionBattery.charging.port.frontLeft.flap.isAutomaticOpenAndCloseActive",
                        "vehicle.powertrain.tractionBattery.charging.port.frontLeft.flap.isOpen",
                        "vehicle.powertrain.tractionBattery.charging.port.frontLeft.isPlugged",
                        "vehicle.powertrain.tractionBattery.charging.port.frontMiddle.flap.isAutomaticOpenAndCloseActive",
                        "vehicle.powertrain.tractionBattery.charging.port.frontMiddle.flap.isOpen",
                        "vehicle.powertrain.tractionBattery.charging.port.frontMiddle.isPlugged",
                        "vehicle.powertrain.tractionBattery.charging.port.frontRight.flap.isAutomaticOpenAndCloseActive",
                        "vehicle.powertrain.tractionBattery.charging.port.frontRight.flap.isOpen",
                        "vehicle.powertrain.tractionBattery.charging.port.frontRight.isPlugged",
                        "vehicle.powertrain.tractionBattery.charging.port.rearLeft.flap.isAutomaticOpenAndCloseActive",
                        "vehicle.powertrain.tractionBattery.charging.port.rearLeft.flap.isOpen",
                        "vehicle.powertrain.tractionBattery.charging.port.rearLeft.isPlugged",
                        "vehicle.powertrain.tractionBattery.charging.port.rearMiddle.flap.isAutomaticOpenAndCloseActive",
                        "vehicle.powertrain.tractionBattery.charging.port.rearMiddle.flap.isOpen",
                        "vehicle.powertrain.tractionBattery.charging.port.rearMiddle.isPlugged",
                        "vehicle.powertrain.tractionBattery.charging.port.rearRight.flap.isAutomaticOpenAndCloseActive",
                        "vehicle.powertrain.tractionBattery.charging.port.rearRight.flap.isOpen",
                        "vehicle.powertrain.tractionBattery.charging.port.rearRight.isPlugged",
                        "vehicle.powertrain.electric.battery.biDirectionalCharging.availability",
                        "vehicle.powertrain.electric.battery.charging.cableCheckVoltage",
                        "vehicle.drivetrain.electricEngine.charging.timeToFullyCharged",
                        "vehicle.drivetrain.electricEngine.charging.authentication.status",
                        "vehicle.powertrain.electric.battery.charging.authenticationStatus",
                        "vehicle.drivetrain.electricEngine.charging.connectorStatus",
                        "vehicle.powertrain.electric.battery.charging.acLimit.selected",
                        "vehicle.drivetrain.electricEngine.charging.method",
                        "vehicle.drivetrain.electricEngine.charging.chargingMode",
                        "vehicle.drivetrain.electricEngine.charging.modeDeviation",
                        "vehicle.body.chargingPort.combinedStatus",
                        "vehicle.body.chargingPort.lockedStatus",
                        "vehicle.body.chargingPort.plugEventId",
                        "vehicle.body.chargingPort.statusClearText",
                        "vehicle.powertrain.electric.battery.charging.power",
                        "vehicle.drivetrain.electricEngine.charging.connectionType",
                        "vehicle.drivetrain.electricEngine.charging.phaseNumber",
                        "vehicle.drivetrain.electricEngine.charging.profile.preference",
                        "vehicle.body.chargingPort.isoSessionId",
                        "vehicle.drivetrain.electricEngine.charging.status",
                        "vehicle.trip.segment.end.drivetrain.batteryManagement.hvSoc",
                        "vehicle.drivetrain.batteryManagement.header",
                        "vehicle.powertrain.electric.chargingDuration.displayControl",
                        "vehicle.drivetrain.electricEngine.charging.profile.timerType",
                        "vehicle.drivetrain.electricEngine.charging.windowSelection",
                        "vehicle.drivetrain.electricEngine.charging.profile.climatizationActive",
                        "vehicle.drivetrain.electricEngine.charging.level",
                        "vehicle.powertrain.electric.battery.charging.dcChargingModeActive",
                        "vehicle.powertrain.electric.departureTime.displayControl",
                        "vehicle.drivetrain.electricEngine.charging.profile.settings.biDirectionalCharging.departureTimeRelevant",
                        "vehicle.drivetrain.electricEngine.charging.profile.settings.biDirectionalCharging.dischargeAllowed",
                        "vehicle.trip.segment.accumulated.acceleration.starsAverage",
                        "vehicle.trip.segment.accumulated.chassis.brake.starsAverage",
                        "vehicle.trip.segment.accumulated.drivetrain.electricEngine.energyConsumptionComfort",
                        "vehicle.trip.segment.accumulated.drivetrain.transmission.setting.fractionDriveElectric",
                        "vehicle.drivetrain.batteryManagement.maxEnergy",
                        "vehicle.trip.segment.accumulated.drivetrain.electricEngine.recuperationTotal",
                        "vehicle.drivetrain.electricEngine.charging.smeEnergyDeltaFullyCharged",
                        "vehicle.drivetrain.electricEngine.remainingElectricRange",
                        "vehicle.drivetrain.electricEngine.charging.timeRemaining",
                        "vehicle.drivetrain.totalRemainingRange",
                        "vehicle.powertrain.electric.battery.stateOfHealth.displayed",
                        "vehicle.drivetrain.electricEngine.charging.hvStatus",
                        "vehicle.drivetrain.electricEngine.charging.isImmediateChargingSystemReason",
                        "vehicle.drivetrain.electricEngine.charging.isSingleImmediateCharging",
                        "vehicle.drivetrain.electricEngine.charging.lastChargingReason",
                        "vehicle.drivetrain.electricEngine.charging.lastChargingResult",
                        "vehicle.body.chargingPort.isHospitalityActive",
                        "vehicle.body.flap.isPermanentlyUnlocked",
                        "vehicle.powertrain.electric.battery.preconditioning.manualMode.statusFeedback",
                        "vehicle.powertrain.electric.battery.charging.acLimit.max",
                        "vehicle.trip.segment.end.travelledDistance",
                        "vehicle.powertrain.electric.battery.charging.acLimit.min",
                        "vehicle.drivetrain.electricEngine.charging.consumptionOverLifeTime.overall.referenceDistance",
                        "vehicle.powertrain.electric.battery.preconditioning.state",
                        "vehicle.drivetrain.electricEngine.charging.profile.isRcpConfigComplete",
                        "vehicle.drivetrain.electricEngine.charging.reasonChargingEnd",
                        "vehicle.drivetrain.electricEngine.charging.hvpmFinishReason",
                        "vehicle.powertrain.electric.battery.charging.batteryCarePersisted.isReducedTargetSoe",
                        "vehicle.drivetrain.electricEngine.charging.consumptionOverLifeTime.engineOn.referenceDistance",
                        "vehicle.drivetrain.electricEngine.charging.consumptionOverLifeTime.engineOff.referenceDistance",
                        "vehicle.powertrain.electric.range.target",
                        "vehicle.drivetrain.electricEngine.kombiRemainingElectricRange",
                        "vehicle.drivetrain.electricEngine.charging.routeOptimizedChargingStatus",
                        "vehicle.powertrain.electric.battery.charging.preferenceSmartCharging",
                        "vehicle.body.flap.isLocked",
                        "vehicle.powertrain.electric.battery.charging.acLimit.isActive",
                        "vehicle.body.chargingPort.status",
                        "vehicle.body.chargingPort.dcStatus",
                        "vehicle.powertrain.electric.battery.stateOfCharge.target",
                        "vehicle.powertrain.electric.battery.stateOfCharge.targetMin",
                        "vehicle.powertrain.electric.battery.stateOfCharge.targetSoCForProfessionalMode",
                        "vehicle.trip.segment.end.time",
                        "vehicle.drivetrain.electricEngine.charging.consumptionOverLifeTime.engineOn.gridEnergy",
                        "vehicle.drivetrain.electricEngine.charging.consumptionOverLifeTime.engineOff.gridEnergy",
                        "vehicle.drivetrain.electricEngine.charging.consumptionOverLifeTime.overall.gridEnergy",
                        "vehicle.cabin.climate.timers.overwriteTimer.action",
                        "vehicle.cabin.climate.timers.overwriteTimer.hour",
                        "vehicle.cabin.climate.timers.overwriteTimer.minute",
                        "vehicle.powertrain.electric.range.displayControl",
                        "vehicle.cabin.infotainment.hmi.distanceUnit",
                        "vehicle.cabin.infotainment.navigation.currentLocation.fixStatus",
                        "vehicle.cabin.infotainment.navigation.currentLocation.numberOfSatellites",
                        "vehicle.cabin.climate.timers.weekdaysTimer1.action",
                        "vehicle.cabin.climate.timers.weekdaysTimer1.hour",
                        "vehicle.cabin.climate.timers.weekdaysTimer1.minute",
                        "vehicle.cabin.climate.timers.weekdaysTimer2.action",
                        "vehicle.cabin.climate.timers.weekdaysTimer2.hour",
                        "vehicle.cabin.climate.timers.weekdaysTimer2.minute",
                        "vehicle.chassis.axle.row1.wheel.left.tire.pressure",
                        "vehicle.chassis.axle.row1.wheel.right.tire.pressure",
                        "vehicle.chassis.axle.row2.wheel.left.tire.pressure",
                        "vehicle.chassis.axle.row2.wheel.right.tire.pressure",
                        "vehicle.chassis.axle.row1.wheel.left.tire.pressureTarget",
                        "vehicle.chassis.axle.row1.wheel.right.tire.pressureTarget",
                        "vehicle.chassis.axle.row2.wheel.left.tire.pressureTarget",
                        "vehicle.chassis.axle.row2.wheel.right.tire.pressureTarget",
                        "vehicle.chassis.axle.row1.wheel.left.tire.temperature",
                        "vehicle.chassis.axle.row1.wheel.right.tire.temperature",
                        "vehicle.chassis.axle.row2.wheel.left.tire.temperature",
                        "vehicle.chassis.axle.row2.wheel.right.tire.temperature",
                        "vehicle.vehicleIdentification.connectedDriveContractList"
                    ]
                ])
            ]
        )), true);
        $this->WriteAttributeString("containerId", $container["containerId"]);
    }

    private function getVehicleMapping(): array {
        if (empty($this->ReadPropertyString("clientId")))
            return [[ "vin" => "Follow Step 1 Instructions"]];
        else if (empty($this->ReadAttributeString("userCode")) && empty($this->ReadAttributeString("accessToken")))
            return [[ "vin" => "Follow Step 2 Instructions"]];
        else if (empty($this->ReadAttributeString("accessToken")))
            return [[ "vin" => "Follow Step 2 Instructions"]];

        $configList = [];

        $response = json_decode($this->ForwardData(json_encode([
                "DataID" => "{3F5C23F7-AC9B-6BFE-C27C-3336F73568B4}",
                "method" => "GET",
                "accept" => "application/json",
                "endpoint" => "/customers/vehicles/mappings",
                "body" => ""
            ]
        )), true);

        if ($this->GetStatus() != 102) return $configList;

        $containerId = $this->ReadAttributeString("containerId");
        foreach ($response as $vehicle) {
            $vin = $vehicle["vin"];
            $moduleGUID = "{E147D4C3-A21A-F7C9-EE09-F54D5FD91B86}";

            $id = 0;
            $instanceIDs = IPS_GetInstanceListByModuleID($moduleGUID);
            foreach ($instanceIDs as $instanceID) {
                if (IPS_GetProperty($instanceID, 'vin') == $vin) {
                    $id = $instanceID;
                }
            }

            $configList[] = [
                "vin" => $vin,
                "createdAt" => $vehicle["mappedSince"],
                "instanceID" => $id,
                "create" => [
                    "moduleID" => $moduleGUID,
                    "configuration"=> [
                        "vin" => $vin,
                        "containerId" => $containerId
                    ]
                ]
            ];
        }

        return $configList;
    }

    public function GetConfigurationForm(): string {
        if ($this->ReadAttributeString("accessToken") != null && $this->ReadAttributeString("containerId") == null) {
            $this->getContainer();
        }

        return json_encode([
            "elements" => [
                [
                    "type" => "ExpansionPanel",
                    "caption" => "So verbindest du dein MyBMW Fahrzeug",
                    "items" => [
                        [
                            "type" => "Label",
                            "caption" => 'Lass den Browser Tab für die Erstellung von dem BMW CarData Client die ganze Zeit geöffnet bis du alle Schritte beendet hast!',
                        ],
                        [
                            "type" => "Label",
                            "caption" => '1. BMW CarData Client erstellen',
                            "bold" => true
                        ],
                        [
                            "type" => "Label",
                            "caption" => 'Nutzte für die folgenden Schritte den Hauptbenutzer des Fahrzeugs. Klicke auf den Knopf "BMW Fahrzeuge" und melde dich bei BMW an. Wählen dann bei einem beliebigen Fahrzeug die BMW CarData Option und klicke in dem Menu auf CarData Client erstellen. Wähle beide Option CarData und CarData Stream aus und achte darauf das diese ausgewählt bleiben (möglicher Fehler auf der BMW-Website).',
                        ],
                        [
                            "type" => "Image",
                            "image" => "data:image/png;base64, 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rbLKKtalSxdbaaWVUpZx7ty57rUVVlihyuvLli0zvR5MjRs3tlzXbdSoUdr9BvNctGiR6V+zZs0Si3/77Te3LLEgzYOmTZtagwYNEq9mqk9ipTQPMm2bykIO8ginhQsXWsOGDVM6hdflOQIIIIAAAgggEJVArAPVX375xQYMGGATJ060Hj16WKtWrWz69Ok2adIkGzhwoB199NFJDpWVlda3b1876KCDbPjw4Umv6cmMGTOsT58+1rZt28Rrf/zxhylo23jjje3ss8+2tdde272Wat158+aZgtVddtnFzj33XGvevHkin+CD888/35588kl78803bdVVV3UvjRgxwp566qnEatrvr7/+am3atEks04Pbb7/d1ltvPbcsW32SNgw9ybZtqvqpTPXr17d9993XzjjjDFtxxRVdrsOGDbPNN9/c9t9//9BeeIoAAggggAACCFSfQL3lQdqyfLJXAFRRUZHPJgWve+KJJ1qTJk3smmuuSWplXLJkic2ePbtKkHf99dfbjz/+aC+88IKNGTOmSuuggtxDDjnE3njjjaQyKUB76KGH7M4773TbqvUw3brK/8orr7TvvvvORo4cmVQuZTp//nwXDCsg7tSpk51yyilJ+/JPxo8fb+ecc449/fTTflGVv9nqU2WDwIJs26arn1zPOussW2eddWzw4MEuR5WTQDWAy0MEEEAAAQQQiEwgU2wZ2z6qM2fOtAkTJlQJUqWiW+PhlkjF20888YQpuN1kk03s+eefzxlQt7wPPfRQd2v7yy+/zLhdu3bt7Oqrr7aff/7ZBbXhlZ999lnbbrvt7KijjrJHH300/HLOz4upTzHbqtV66NChzjLnwrIiAggggAACCCBQDQKxDVTVIqrb8cH+mpnq/84779gaa6xhHTt2tAMOOMAee+yxTKtXeU2tqt9//721b9++ymvhBSqTWmbfeuut8EtuvwceeKDrqqD+ph988EGVdXJZUEx9itlWZVPXCAXiJAQQQAABBBBAoJQCsQ1U1T9VA6dyTWq9VICopH6q6sc6bdq0XDe36667zjbbbDNTi2kuSX1Pp0yZkrSqnv/www8uH72g8hTaqlpMfYrZVuVWPXzfWj0nIYAAAggggAACpRCIbaA6Z84c1z81FxQNcvr3v/9tu+66q1tdfUz32Wcfe/zxx6tsrpkDpk6dmvRPfU2fe+45u/zy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CCCAAAIIIBAXAQLVuBwJyoEAAggggAACCCCQJECgmsTBEwQQQAABBBBAAIG4CBCoxuVIUA4EEEAAAQQQQACBJAEC1SQOniCAAAIIIIAAAgjERaBhXApCORBAAAEEEEAAAQT+K3DxxRf/90maR9ttt5316dMnzavlv5gW1fI/htQAAQQQQAABBGqhwLBhwzLWSq/37dvXcgloM2YU4xfrLVue8ilfZWWlVVRU5LMJ6yKAAAIIIIAAAgjkKVCvXj3LFKa9/vrrLkj1LaoXXXRRnnuIx+qZYsuyCFQnT55s3377rS1evNhWWWUV69Kli6200kpOd+nSpbZw4UJr2rSpe75gwQJr1KiRNWxY870a/vjjD1fGxo0bu7LozTV37tykd4HK5sue9AJPEEAAAQQQQACBgEC2QFWr+nV8q2o5BqtlG6j+8ssvNmDAAJs4caL16NHDWrVqZdOnT7dJkybZwIED7eijj3avDR482J5++ml3aI899ljbf//9bffddw8c6pp5+MQTT9g777xjV199tduhyqqrnLZt2yYKMG/ePBdI77LLLnbuueda8+bNE6/xAAEEEEAAAQQQ8AI+CPXPU/0NrlOuwWqmQLXmmx1TKadZdt5551mHDh3snnvusQYNGiTWWrJkic2ePTvxPPhALan160ff9fahhx6yjTfe2LXmBveX7fGqq65qb7zxRtJqP/74o1155ZV23HHH2ciRI5PqlrQiTxBAAAEEEEAAgRwF1JqqYFX/yrFlNVU1Yxuozpw50yZMmGCvvPJKlUBOQWubNm1S1cf+9re/pVxe7MLXXnst0e2g2LzatWvnWl132203e+GFF0rS+ltsHdgeAQQQQAABBOIh4FtSfWnUd1WpNgSr0Tc9Opri/xszZoxrwQy2pOaS6y233GJjx45NrKo+rNddd53tvffettFGG1n//v3tueeeS7yuB+pGoFbP0047zbbZZhvr1auXnXTSSVX6lyqfo446yoYMGZK0fSFPVK9DDjnE3nrrrUI2ZxsEEEAAAQQQQMA08j+cFKj6YDX8Wrk9j22LqvqnauBUvmncuHHWvXv3xGZXXXWV68d68803m27Df/rppy4gbdmypW299dZuvbffftvUgnvooYfapZde6vqQnnHGGXbvvffaySefnMhrjz32sA022MD8YKnECwU+UHlefPHFArdmMwQQQAAB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+cUGDBhgEydOtB49elirVq1s+vTpNmnSJBs4cKAdffTR2TPJsMawYcPsxRdfdMGpVluyZInpuK2zzjo2aNAg23777TNsXXMvPf7443bOOefYddddZ/vss0+VHY8aNcqGDh1qLVq0SLz2+++/W/Pmze2UU06xAw88MLFcdd58883dezOxMPDgrLPOcq/tsMMOgaU8RAABBBAotYB+capUk/lrCqrqTtqHAlXV8axDK8rqF6xiG6j+9a9/TXncZsyY4QIKBaqkwgXOO+8869Chg91zzz1JLXwKKGfPnl14xoEtFeAFj5PyfvXVV+3888+3q666yrbddtvA2tkfXnnllTZ48GDXapl97dzWePTRR61///6mv6kCVeWy++6729VXX52U4VdffWXHHXecqRV6m222SXqtnJ5Uh2k51Z+yIoAAAhLQz6KWImkyf03iX91J+9C+9KMAqutevdtU9y4jy7/qvc7Iso4+IwU6p512mmvt23DDDaPfQR3JcebMmTZhwgS75pprkoJUVV+35tu0qZ43sPLeaaed7Nxzz7Xhw4fnrf3ggw9m7A6Sb4bq8qALnwsuuMC+/PJL++6773LOQl0kTjrpJHviiSdy3iaOK0ZtGsc6UiYEEEAgm8AH4+ZmW6VaXtcvTkWVjrz0S8tUD7+vTOtEVZYo84lti2qqSt58882mvqnHH3984mVNEnvXXXe527OJhcsf3HrrrbbllluaD2jV3/Wiiy6yt956y7UYdurUybbaaivbe++93a1vv+0zzzxjf//7323q1KmuxXHTTTe1fffd1zbbbDO3yiuvvGJqTVPQ9dxzz7nHum2uVsJddtnFZ2MKBhWQff755y640u11tSAqL7VkhtO7777r9hterue6Nd29e3e76aabrE+fPrb++usnVtPt+ttvv91021lJ5Zs1a5Z169bNRo4caWuttZadfPLJ7jX/35gxY2zjjTeuEqT614N/c/HItr9gfnosc5X3+++/dy2SWqa+otdee62NHj3afvrpJ1tzzTVtu+22cy2ynTt31iounXDCCa4vad++fe3www/PeTu/ffCvbvurxVfvqb322ssFneoOkWtq166dqQtFPunXX3+1G2+80Z588km3rd6fumAIXhx8+umn7v07duxYa9KkiXvv6f2l7gY+6UJD72d11VCfWb0nVAe9B9VPVkG3PgPqiqB6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tuOOOyaW8QABBBBAAAEEchd4/aNoYorc9/jnmprMX5P4V3fSPrQv/SiA6rpX7zbVvcvI8o9tH9WZM2fahAkT7JprrkkKUlVz3Zpv06Z6kJX3TjvtZOeee64NHz48b+gHH3zQdVHIe8PQBu+//74dcsghoaVmjRo1snXXXTexfNy4cfbhhx8mnvMAAQQQQAABBPIT+GDc3Pw2iGht/eJUVOnIS7+0TPXw+8q0TlRliTKf4pr9oixJKK8xY8bYxhtvXCVIDa3mnj7zzDP297//3aZOnepaYDfddFPbd999bbPNNnOvv/LKKzZr1izr1q2bjRw50tZaay13Wz1VXn7Z3nvvbeoa8P3337t+sVqubgFqaVVgqL6t6iKw88472z777OO6JfhtTzjhBFN/1759+9rhhx9uS5cutWuvvdZGjx5tP/30k6255pru9r1actXnNFVq166dTZkyxdq2bZvq5aRlyldBvZK6BMybN8/+8Y9/2HHHHWd33XWXzZgxw2666SZTVwoF3yr/ggULrFevXnbaaadZ9+7dE/nlUkd1uzjwwAPtoYcesg8++MBZHHDAAa4rxu233+7qqf6/W221lbvQaNq0aSJ/HiCAAAIIIBA3gfFTSvPrU/pZ1CiSglSlTXs0T5ud31ep6pq2YFleiG2LqoIqDZzKll5++WW77rrr3G36jz/+2O69914XQKp/p0/fffedC+DOPPNM22CDDaxfv37+pbR/69Wr5/avYNGnI444wtZee20XiCmQPv30023UqFGmQDmYjj32WDvxxBMT3QbUhUCDohQkf/TRRy5oVeCs4DVd0vb6p8Dvxx///Dm0dOsq4PTrN2nSxObPn29PPPGEKXjUoDN1H1Af16OOOsrV6ZFHHnHdG3bZZRc75phjXPDs886ljm+//baNGDHCFMy/8cYb9sILL9jzzz/vXFdbbTV79tlnTV0SFMxrXyQEEEAAAQTiLDDth99LUrwobvv7IPWuoetkrIPfV6nqmrFwGV6MbYuqWvYUdGVL999/v51zzjnWs2dPt6qCWwWiakEMpvXWW89uvfXWvEbMt2/f3rVOKp9PPvnEDWxScOrTJpts4lol/XP/Vy26flYCBWuPPvqoG7TluytocJT6nz755JN+kyp/99tvP6uoqLA777zTbrzxRttoo41cy+0ee+yRyNtvtOqqq9rmm2/un7q/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7rVmzZpZ/fqluRaaO3euK6/KpLIpLVy40P744w9X9pVWWsmVU+UNruNWLOK/uNS/iCqwKQIlFaisrLQpU6bY77//bu3bt7cuXbpYkyZNIiuTzmM6PyjpHKXPrM4NOlfpeRzTokWL7LfffotFGcPn/KCXbCdMmGAtWrSwdu3a5V3mTHkH98PjuiVQmiiibhmXbW3fffdd22GHHWz33Xe3Y4891o477jjbc889bf3117ebb77Z1evUU0+1DTfc0C688EL3/IsvvnDPtUzBbanSlltu6crx9NNPJ4pw/vnnu2VXXnmlW3bQQQe5548++mhinWIfxKX+xdaD7RGoaQGdL/r37299+vSxI4880k444QTbb7/9bIMNNnDnoJ9//jmSIs2ePTtxjlJQPGrUKPd87733jiT/6sjk7rvvdmXUubjUKXzOV3kU6N9yyy22xRZbuO+Ijz76yAopc6q8S11f9l96AVpUS38MYlmCzz//3H1ZqAVSSa0NatWYN2+ea0H98ccfS1LugQMHmloXjjjiCFMwSkIAgfIX0F2Yww47zLWk+to0b97c1MKmFs8vv/zSBUP+ter+++2339oVV1zhdnP55ZdbmzZtqnuXLn81AOjcq3ObznGlTPmca8855xx79tlnXXF1ty3KFvBSGrDveAgQqMbjOMSuFDphKkjVCXrEiBG28cYbu1vmunL+5ptv0pZ3rbXWsnvuuce9rls/UadXX33VfWHttNNOeWd94oknWr9+/Vz3BW182WWXuS9C3VokIYBA6QRuv/12F6Q2aNDALrroIteSqmBHQerUqVNdoNqqVatICqgA2J+jOnToYFtvvbV7ri5APs2ZM8deeukl9/SCCy7wi6v978cff2yvv/66qYylTunOtWeeeaYdffTRJjslBfU+SJWVWsUVrHbv3t169Ohh6maVawrnnet2rFe7BQhUa/fxLah2ajXVSUpJt/uDLZc6mffs2TNtvmpp/cc//uFeX2eddWzFFVd0j3UbSLfY1N+1ZcuW7gQ2ZMgQ69ixo3v9+uuvt08//dQU6Cpp/7NmzXK3kq6++mpbeeWV3XL/3z//+U+Xn57feeedfnHGvwqcVTbtR7cZu3XrZn379k2qn74Uhw8fbmPHjrVffvnF7Vdl2nfffU1dBZTUv03dB/RFpserrLKKrbfeenbuuecm7f++++6zDz74wNVZ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",
                            "width" => "75%"
                        ],
                        [
                            "type" => "Label",
                            "caption" => '2. Modul für BMW autorisieren',
                            "bold" => true
                        ],
                        [
                            "type" => "Label",
                            "caption" => 'Kopiere die Client ID von dem CarData Client und füge sie hier ein. Klicke dann auf "Autorisieren", es öffnet sich dann wieder die BMW-Website auf der das Modul autorisiert wird. Sobald dort "Anmeldung Erfolgreich" steht ist das Modul autorisiert.',
                        ],
                        [
                            "type" => "Label",
                            "caption" => '3. Autorisierung abschließen',
                            "bold" => true
                        ],
                        [
                            "type" => "Label",
                            "caption" => 'Klicke innerhalb von 3 Minuten auf den Knopf "Autorisierung abschließen", damit das Modul die Autorisierung abschließen kann und zugriff auf deine MyBMW Autos hat.',
                        ],
                        [
                            "type" => "Label",
                            "caption" => '4. Datastream konfigurieren TODO',
                            "bold" => true
                        ],
                        [
                            "type" => "Label",
                            "caption" => 'Den Datastream zu nutzen ist für das Modul optional. Die BMW CarData API hat aber ein Limit von 50 Aufrufen pro Tag für dein MyBMW Account. Der Datenstream bietet dafür Real-Time telemetrie Daten...',
                        ]
                    ]
                ],
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
                                            "width" => "400px",
                                            "caption" => "Client ID",
                                            "required" => true,
                                            "onChange" => ""
                                        ],
                                        [
                                            "type" => "ValidationTextBox",
                                            "name" => "accessToken",
                                            "width" => "400px",
                                            "caption" => "accessToken",
                                            "value" => $this->ReadAttributeString("accessToken"),
                                            "required" => true,
                                            "onChange" => ""
                                        ],
                                        [
                                            "type" => "Button",
                                            "caption" => "BMW Fahrzeuge",
                                            "link" => true,
                                            "onClick" => "echo 'https://www.bmw.de/de-de/mybmw/vehicle-overview';"
                                        ],
                                        [
                                            "type" => "Button",
                                            "caption" => "Autorisieren",
                                            "enabled" => !empty($this->ReadPropertyString("clientId")) && empty($this->ReadAttributeString("refreshToken")),
                                            "link" => true,
                                            "onClick" => 'BMWCommunicator_authorize($id);'
                                        ],
                                        [
                                            "type" => "Button",
                                            "caption" => "Autorisierung abschließen",
                                            "enabled" => !empty($this->ReadAttributeString("userCode")),
                                            "onClick" => 'BMWCommunicator_token($id);'
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                [
                    "type" => "Configurator",
                    "name" => "BMWCarDataDiscovery",
                    "caption" => "BMW CarData Fahrzeuge",
                    "rowCount" => 4,
                    "add" => false,
                    "delete" => true,
                    "columns" => [
                        [
                            "caption" => "VIN",
                            "name" => "vin",
                            "width" => "50%",
                            "add" => false,
                        ],
                        [
                            "caption" => "Hinzugefügt am",
                            "name" => "createdAt",
                            "width" => "50%",
                            "add" => false,
                        ]
                    ],
                    "sort" => [
                        "column" => "createdAt",
                        "direction" => "descending"
                    ],
                    "values" => $this->getVehicleMapping(),
                ]
            ],
            "status" => [
                ["code" => 400, "icon" => "error", "caption" => "Bad request"],
                ["code" => 401, "icon" => "error", "caption" => "Value of a input parameter is invalid"],
                ["code" => 402, "icon" => "error", "caption" => "Telematic key can not be found"],
                ["code" => 403, "icon" => "inactive", "caption" => "Authentication Failed"],
                ["code" => 404, "icon" => "error", "caption" => "Not Found"],
                ["code" => 429, "icon" => "inactive", "caption" => "Daily API rate limit reached!"],
                ["code" => 500, "icon" => "inactive", "caption" => "A permanent server error occurred on BMWGroup endpoint!"],
                ["code" => 503, "icon" => "inactive", "caption" => "A temporary server error occurred on BMWGroup endpoint!"]
            ]
        ]);
    }
}