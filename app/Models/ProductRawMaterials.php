<?php

namespace App\Models;

use Laracasts\Presenter\PresentableTrait;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductRawMaterials extends EntityModel
{
    use PresentableTrait,SoftDeletes;
    /**
     * @var array
     */
    protected $fillable = [
        'account_id',
        'user_id',
        'product_id',
        'raw_material_id',
        'product_raw_material_key',
        'notes',
        'cost',
        'total_cost',
        'qty',
        'public_id',
    ];

    /**
     * @var string
     */
    protected $presenter = 'App\Ninja\Presenters\ProductRawMaterialPresenter';

    /**
     * @return mixed
     */
    public function getEntityType()
    {
        return ENTITY_PRODUCT_RAW_MATERIAL;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function product()
    {
        return $this->belongsTo('App\Models\Product');
    }
}
