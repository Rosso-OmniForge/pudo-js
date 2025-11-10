<?php

namespace Pudo\Common\Request;

use stdClass;

class ApiRequestBuilder
{
    private array  $shippingDetails;
    private string $method;
    private array  $collectionDetails;
    private array  $parcels;

    /**
     * @param array $shippingDetails
     * @param array $collectionDetails
     * @param string $method
     * @param array $parcels
     */
    public function __construct(array $shippingDetails, array $collectionDetails, string $method, array $parcels)
    {
        $this->shippingDetails   = $shippingDetails;
        $this->method            = $method;
        $this->collectionDetails = $collectionDetails;
        $this->parcels           = $parcels;
    }

    /**
     * @param string $serviceLevelCode
     *
     * @return stdClass
     */
    public function buildBookingRequest(string $serviceLevelCode): stdClass
    {
        $requestBody = new stdClass();

        $requestBody->delivery_contact   = $this->buildDeliveryContact();
        $requestBody->collection_contact = $this->buildCollectionContact();

        $requestBody->delivery_address   = $this->buildDeliveryAddress();
        $requestBody->collection_address = $this->buildCollectionAddress();

        $requestBody->service_level_code = $serviceLevelCode;

        $requestBody->parcels = [$this->parcels];

        return $requestBody;
    }

    /**
     * @return stdClass
     */
    private function buildDeliveryContact(): stdClass
    {
        $deliveryContact                = new stdClass();
        $deliveryContact->name          = $this->shippingDetails['name'];
        $deliveryContact->email         = $this->shippingDetails['email'];
        $deliveryContact->mobile_number = $this->shippingDetails['mobile_number'];

        return $deliveryContact;
    }

    /**
     * @return stdClass
     */
    private function buildCollectionContact(): stdClass
    {
        $collectionContact                = new stdClass();
        $collectionContact->name          = $this->collectionDetails['name'];
        $collectionContact->email         = $this->collectionDetails['email'];
        $collectionContact->mobile_number = $this->collectionDetails['mobile_number'];

        return $collectionContact;
    }

    /**
     * @return stdClass
     */
    private function buildDeliveryAddress(): stdClass
    {
        $deliveryAddress = new stdClass();

        if ($this->method === 'L2L' || $this->method === 'D2L') {
            // Check if there is a destination locker name, if so extract only the code
            $pudoLockerDestination = $this->shippingDetails['terminal_id'];
            if (str_contains($pudoLockerDestination, ':')) {
                $pudoLockerDestinationArr = explode(':', $pudoLockerDestination);
                $pudoLockerDestination    = $pudoLockerDestinationArr[0];
            }
            $deliveryAddress->terminal_id = $pudoLockerDestination;
        } else {
            $company                          = $this->shippingDetails['company'] ?? null;
            $deliveryAddress->type            = 'residential';
            $deliveryAddress->street_address  = $this->shippingDetails['street_address'];
            $deliveryAddress->city            = $this->shippingDetails['city'];
            $deliveryAddress->local_area      = $this->shippingDetails['local_area'];
            $deliveryAddress->code            = $this->shippingDetails['code'];
            $deliveryAddress->zone            = $this->shippingDetails['zone'];
            $deliveryAddress->country         = $this->shippingDetails['country'];
            $deliveryAddress->entered_address = "$deliveryAddress->street_address, $deliveryAddress->city, $deliveryAddress->code";
            if ($company) {
                $deliveryAddress->company = $company;
            }
        }

        return $deliveryAddress;
    }

    /**
     * @return stdClass
     */
    private function buildCollectionAddress(): stdClass
    {
        $collectionAddress = new stdClass();

        if ($this->method === 'L2L' || $this->method === 'L2D') {
            // Check if there is an origin locker name, if so extract only the code
            $pudoLockerOrigin = $this->collectionDetails['terminal_id'];
            if (str_contains($pudoLockerOrigin, ':')) {
                $pudoLockerOriginArr = explode(':', $pudoLockerOrigin);
                $pudoLockerOrigin    = $pudoLockerOriginArr[0];
            }
            $collectionAddress->terminal_id = $pudoLockerOrigin;
        } else {
            $company                            = $this->collectionDetails['company'] ?? null;
            $collectionAddress->type            = 'residential';
            $collectionAddress->street_address  = $this->collectionDetails['street_address'];
            $collectionAddress->local_area      = $this->collectionDetails['local_area'];
            $collectionAddress->city            = $this->collectionDetails['city'];
            $collectionAddress->code            = $this->collectionDetails['code'];
            $collectionAddress->entered_address = "{$collectionAddress->street_address}, {$collectionAddress->city}, {$collectionAddress->code}";
            if ($company) {
                $collectionAddress->company = $company;
            }
        }

        return $collectionAddress;
    }

    /**
     * @return stdClass
     */
    public function buildRatesRequest(): stdClass
    {
        $requestBody = new stdClass();

        $requestBody->collection_address = $this->buildCollectionAddress();
        $requestBody->delivery_address   = $this->buildDeliveryAddress();

        $requestBody->parcels = [$this->parcels];

        return $requestBody;
    }
}
