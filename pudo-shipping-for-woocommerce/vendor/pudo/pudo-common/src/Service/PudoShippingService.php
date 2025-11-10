<?php

namespace Pudo\Common\Service;

class PudoShippingService
{
    /**
     * @param $options
     * @param $rateLabel
     * @param $ratePrice
     * @param $serviceLevelCode
     *
     * @return void
     */
    public function applyRateOverrides($options, &$rateLabel, &$ratePrice, $serviceLevelCode): void
    {
        $labelOverrides = $options['label_overrides'] ?? [];
        $priceOverrides = $options['price_overrides'] ?? [];

        if (!empty($labelOverrides[$serviceLevelCode])) {
            $rateLabel = $labelOverrides[$serviceLevelCode];
        }

        if (!empty($priceOverrides[$serviceLevelCode])) {
            $ratePrice = (float)$priceOverrides[$serviceLevelCode];
        }
    }

    /**
     * @return string[]
     */
    public static function getRateOptions(): array
    {
        return [
            "ECO"       => "Economy",
            "LOX"       => "Local Overnight Parcel",
            "OVN"       => "Overnight",
            "L2LXS-ECO" => "PUDO L2LXS - ECO",
            "L2LS-ECO"  => "PUDO L2LS - ECO",
            "L2LM-ECO"  => "PUDO L2LM - ECO",
            "L2LL-ECO"  => "PUDO L2LL - ECO",
            "L2LXL-ECO" => "PUDO L2LXL - ECO",
            "K2LXS-ECO" => "PUDO K2LXS - ECO",
            "K2LS-ECO"  => "PUDO K2LS - ECO",
            "K2LM-ECO"  => "PUDO K2LM - ECO",
            "K2LL-ECO"  => "PUDO K2LL - ECO",
            "K2LXL-ECO" => "PUDO K2LXL - ECO",
            "K2KXS-ECO" => "PUDO K2KXS - ECO",
            "K2KS-ECO"  => "PUDO K2KS - ECO",
            "K2KM-ECO"  => "PUDO K2KM - ECO",
            "K2KL-ECO"  => "PUDO K2KL - ECO",
            "K2KXL-ECO" => "PUDO K2KXL - ECO",
            "K2DXS-ECO" => "PUDO K2DXS - ECO",
            "K2DS-ECO"  => "PUDO K2DS - ECO",
            "K2DM-ECO"  => "PUDO K2DM - ECO",
            "K2DL-ECO"  => "PUDO K2DL - ECO",
            "K2DXL-ECO" => "PUDO K2DXL - ECO",
            "L2DXS-ECO" => "PUDO L2DXS - ECO",
            "L2DS-ECO"  => "PUDO L2DS - ECO",
            "L2DM-ECO"  => "PUDO L2DM - ECO",
            "L2DL-ECO"  => "PUDO L2DL - ECO",
            "L2DXL-ECO" => "PUDO L2DXL - ECO",
            "L2KXS-ECO" => "PUDO L2KXS - ECO",
            "L2KS-ECO"  => "PUDO L2KS - ECO",
            "L2KM-ECO"  => "PUDO L2KM - ECO",
            "L2KL-ECO"  => "PUDO L2KL - ECO",
            "L2KXL-ECO" => "PUDO L2KXL - ECO",
            "D2LXS-ECO" => "PUDO D2LXS - ECO",
            "D2LS-ECO"  => "PUDO D2LS - ECO",
            "D2LM-ECO"  => "PUDO D2LM - ECO",
            "D2LL-ECO"  => "PUDO D2LL - ECO",
            "D2LXL-ECO" => "PUDO D2LXL - ECO",
            "D2KXS-ECO" => "PUDO D2KXS - ECO",
            "D2KS-ECO"  => "PUDO D2KS - ECO",
            "D2KM-ECO"  => "PUDO D2KM - ECO",
            "D2KL-ECO"  => "PUDO D2KL - ECO",
            "D2KXL-ECO" => "PUDO D2KXL - ECO",
        ];
    }
}
