<?php

namespace App\Ninja\Datatables;

use Str;
use URL;
use Auth;
use Utils;
use App\Models\Vendor;

class RawMaterialDatatable extends EntityDatatable
{
    public $entityType = ENTITY_RAW_MATERIAL;
    public $sortCol = 4;

    public function columns()
    {
        $account = Auth::user()->account;

        return [
            [
                'raw_material_key',
                function ($model) {
                    return link_to('raw_materials/'.$model->public_id.'/edit', $model->raw_material_key)->toHtml();
                },
            ],
            [
                'notes',
                function ($model) {
                    return $this->showWithTooltip($model->notes);
                },
            ],
            [
                'cost',
                function ($model) {
                    return Utils::roundSignificant($model->cost);
                },
            ],
            [
                'qty',
                function ($model) {
                    return Utils::roundSignificant($model->qty);
                },
            ],
            [
                'supplier',
                function ($model) {
                    $vendor=Vendor::scope($model->supplier)->withTrashed()->first();
                    return $vendor->name;
                },
            ]
        ];
    }

    public function actions()
    {
        return [
            [
                uctrans('texts.edit_raw_material'),
                function ($model) {
                    return URL::to("raw_materials/{$model->public_id}/edit");
                },
            ],
        ];
    }
}
