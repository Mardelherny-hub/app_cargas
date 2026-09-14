<?php

namespace App\Http\Controllers\Company;

use App\Models\BillOfLading;

class BillOfLadingControllerCompat extends BillOfLadingController
{
    public function show(BillOfLading $billOfLading)
    {
        $response = parent::show($billOfLading);

        if (!request()->boolean('show_all_items')) {
            return $response;
        }

        return view(
            'company.bills-of-lading.show-all-items',
            $response->getData()
        );
    }
}
