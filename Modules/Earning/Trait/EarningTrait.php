<?php

namespace Modules\Earning\Trait;

trait EarningTrait
{
    public function getUnpaidAmount($data, $type = null)
    {
        $classData = new \stdClass();
        switch ($type) {
            case 'tip':
                return 0;
                break;
            case 'commission':
                return $data->commission_earning->where('commission_status', 'unpaid')->sum('commission_amount');
                break;
            default:
                $classData->total_commission_earn = $data->commission_earning->where('commission_status', 'unpaid')->sum('commission_amount');
                $classData->total_tips_earn = 0;
                $classData->total_pay = $classData->total_commission_earn;

                return $classData;
                break;
        }

        return 0;
    }
}
