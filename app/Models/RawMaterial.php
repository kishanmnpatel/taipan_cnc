<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laracasts\Presenter\PresentableTrait;

class RawMaterial extends EntityModel
{
    use PresentableTrait,SoftDeletes;
    /**
     * @var array
     */
    protected $fillable = [
        'account_id',
        'user_id',
        'raw_material_key',
        'notes',
        'cost',
        'qty',
        'supplier',
        'public_id',
    ];

    /**
     * @var string
     */
    protected $presenter = 'App\Ninja\Presenters\RawMaterialPresenter';

    /**
     * @return mixed
     */
    public function getEntityType()
    {
        return ENTITY_RAW_MATERIAL;
    }

    /**
     * @param $key
     *
     * @return mixed
     */
    public static function findProductByKey($key)
    {
        return self::scope()->where('raw_material_key', '=', $key)->first();
    }

    /**
     * @return mixed
     */
    public function user()
    {
        return $this->belongsTo('App\Models\User')->withTrashed();
    }
}
