<?php

namespace Pudo\Common\Processor;

class APIProcessor
{
    private string $apiUrlBase;

    public const API_METHODS = [
        'get_all_lockers' => [
            'type'     => 'GET',
            'endpoint' => '/api/v1/lockers-data',
        ],
        'get_rates'       => [
            'type'     => 'POST',
            'endpoint' => '/api/v1/locker-rates-new'
        ],
        'booking_request' => [
            'type'     => 'POST',
            'endpoint' => '/api/v1/shipments'
        ],
        'get_waybill'     => [
            'type'     => 'GET',
            'endpoint' => '/generate/waybill'
        ],
        'checkout_rates'  => [
            'type'     => 'POST',
            'endpoint' => '/api/v1/locker-rates-new'
        ],
        'get_label'       => [
            'type'     => 'GET',
            'endpoint' => '/generate/sticker'
        ],
    ];

    /**
     * @param string $apiUrlBase
     */
    public function __construct(string $apiUrlBase)
    {
        $this->apiUrlBase = $apiUrlBase;
    }

    /**
     * @param $getLockersResponse
     *
     * @return array
     */
    public static function mapLockers($getLockersResponse): array
    {
        $getLockersResponse = json_decode($getLockersResponse, true);
        $mappedLockers      = [];
        array_map(function ($locker) use (&$mappedLockers){
            $mappedLockers[$locker['code']] = $locker;
        }, $getLockersResponse);

        return $mappedLockers;
    }

    /**
     * @param $method
     * @param $content
     *
     * @return array
     */
    public function getRequest($method, $content): array
    {
        $url  = $this->apiUrlBase . self::API_METHODS[$method]['endpoint'];
        $type = self::API_METHODS[$method]['type'];

        if ($type === 'GET' && $content) {
            $url .= $content;
        }

        return ['url' => $url, 'type' => $type];
    }
}
