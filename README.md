# IPS BMW CarData
![Symcon](https://img.shields.io/badge/Symcon-IPSModuleStrict-blue?style=flat) ![Symcon Version](https://img.shields.io/badge/Symcon%20Version->7.0-green?style=flat) ![Version](https://img.shields.io/badge/Version-Beta%20v1.0-yellow?style=flat) ![Build](https://img.shields.io/badge/Build-testing-important?style=flat)

This is a IP-Symcon third party module for the public BMW CarData integration to use it in your Symcon system.
With the module you can access up to 250+ data points of your vehicle depending on capability of the vehicle.
The module provides you with a selector so you can choose with telematic data you want to include in your Symcon and handles all the authorization for you.
Additional to that there is an automatic update function so you can keep your data up to date. 

![Example of the Vehicle Instance](/exampleVehicleInstance.png)

## Getting Started
Get started in two easy steps. After that you can continue in the BMW CarData Communicator instance to finish your setup.

1. Install the **BMW CarData** Module via the IP-Symcon Module Store that you can find in the Management Console. Do not auto create a Vehicle instance as suggested from the installation.
2. Create the **BMW CarData Communicator** in the Splitter Category. From there on follow the guide on how to add your BMW Vehicles.

## Automatic updates
You can set a automatic update in the vehicle configuration to update the telematic data variables with a given time interval. Account for the API rate limit on one or more vehicles when using this feature!

## Methods
### BMW_getBasicData()
Request basic vehicle data of the vehicle containing brand, model, series and other standard information.

```php
BMW_getBasicData($instanceID);
```

<details>
  <summary>Response</summary>

```json
{
  "vin": "string",
  "brand": "BMW",
  "isTelematicsCapable": true,
  "puStep": "string",
  "modelRange": "string",
  "series": "string",
  "modelName": "string",
  "modelKey": "string",
  "bodyType": "string",
  "numberOfDoors": 0,
  "hasNavi": true,
  "headUnit": "string",
  "hasSunRoof": true,
  "countryCodeISO": "string",
  "steering": "string",
  "engine": "string",
  "driveTrain": "string",
  "chargingModes": [
    "string"
  ],
  "propulsionType": "string",
  "colourCode": "string",
  "colourCodeRaw": "string",
  "constructionDate": "string",
  "fullSAList": "string",
  "hvsMaxEnergyAbsolute": "string",
  "simStatus": "string"
}
```

</details>

### BMW_getChargingHistory($form, $to)
Request the vehicle's Charging History sessions with a given time frame from where the sessions took place. These will contain more detailed information about the charging.

```php
BMW_getChargingHistory($instanceID, "2025-01-01T00:00:00.000Z", "2025-01-20T00:00:00.000Z");
```

<details>
  <summary>Response</summary>

```json
Response:

{
  "data": [
    {
      "publicChargingPoint": {
        "potentialChargingPointMatches": [
          {
            "postalCode": "string",
            "streetAddress": "string",
            "providerName": "string",
            "city": "string"
          }
        ]
      },
      "displayedSoc": 0,
      "displayedStartSoc": 0,
      "businessErrors": [
        {
          "creationTime": "2025-12-28T11:52:43.257Z",
          "hint": "string"
        }
      ],
      "timeZone": "string",
      "startTime": 0,
      "endTime": 0,
      "totalChargingDurationSec": 0,
      "energyConsumedFromPowerGridKwh": 0.1,
      "isPreconditioningActivated": true,
      "mileage": 0,
      "mileageUnits": "MileageUnits.KM",
      "chargingCostInformation": {
        "currency": "string",
        "calculatedChargingCost": 0.1,
        "calculatedSavings": 0.1
      },
      "chargingLocation": {
        "municipality": "string",
        "formattedAddress": "string",
        "streetAddress": "string",
        "mapMatchedLatitude": 0,
        "mapMatchedLongitude": 0
      },
      "chargingBlocks": [
        {
          "startTime": 0,
          "endTime": 0,
          "averagePowerGridKw": 0.1
        }
      ]
    }
  ],
  "next_token": "string"
}
```

</details>

### BMW_getImage()
Request the Image of the vehicle encoded as a data uri base64 string of a png image.

```php
BMW_getImage($instanceID);
```
```json
Response:

data:image/png;base64,iVBOR...
```

### BMW_getLocationBasedSettings()
Request vehicles location based charging settings

```php
BMW_getLocationBasedSettings($instanceID);
```
<details>
  <summary>Response</summary>

```json
Response:

{
  "nextToken": "string",
  "data": [
    {
      "id": "string",
      "lastUpdated": "string",
      "clusterLocationId": "string",
      "latitude": 0.1,
      "longitude": 0.1,
      "lastVisit": "string",
      "visits": 0,
      "chargingMode": "string",
      "optimizedChargingPreference": "string",
      "startChargingTimePeriodHour": 0,
      "startChargingTimePeriodMinute": 0,
      "stopChargingTimePeriodHour": 0,
      "stopChargingTimePeriodMinute": 0,
      "vehicleIdWithGcid": "string",
      "chargingTimeWindows": [
        {
          "startChargingTimePeriodHour": 0,
          "startChargingTimePeriodMinute": 0,
          "stopChargingTimePeriodHour": 0,
          "stopChargingTimePeriodMinute": 0
        }
      ],
      "acCurrentLimitFlag": "string",
      "acCurrentLimit": 0.1,
      "acousticLimit": "string",
      "flapLock": "string",
      "chargingPlug": "string"
    }
  ]
}
```
</details>

### BMW_getTelematicData()
Request all telematic data of the vehicle. The vehicle instance will also **update the selected variables** when this function is called. *Could be an alternative to the automatic updating function with a fixed time interval*

```php
BMW_getTelematicData($instanceID);
```
```json
{
  "key": {
    "value": "string",
    "unit": "string",
    "timestamp": "string"
  },
  ...
}
```

More information on the telematic data keys and their values is descripted in the [Telematic Data Catalogoue](https://www.bmw.co.uk/en-gb/mybmw/public/cardata-telematic-catalogue/).

## API Rate Limit
The use of the CarData APIs is subject to a daily rate limit of 50 requests. This limit is shared by multiple cars on the same account. If the limit is reached, it will be displayed as an error code in the communicator. You have to wait until the next day.


These 50 requests are in most cases not ideal or not enough to keep your vehicle information up to date. There is a better solution with a live stream that is giving real-time data of the vehicle as soon as it gets sent to BMW servers, but this function is **still in the making**.