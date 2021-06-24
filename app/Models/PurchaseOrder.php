<?php

namespace App\Models;

use Laracasts\Presenter\PresentableTrait;
use Illuminate\Database\Eloquent\SoftDeletes;


class PurchaseOrder extends EntityModel
{
    use PresentableTrait,SoftDeletes;
    use OwnedByClientTrait;

    protected $with=['vendor','invoice_items'];
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
    protected $presenter = 'App\Ninja\Presenters\PurchaseOrderPresenter';

    /**
     * @return mixed
     */
    public function getEntityType()
    {
        return ENTITY_PURCHASE_ORDER;
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

    /**
     * @return mixed
     */
    public function invoice_items()
    {
        return $this->hasMany('App\Models\PurchaseOrderItem')->orderBy('id');
    }

    /**
     * @return mixed
     */
    public function vendor()
    {
        return $this->belongsTo('App\Models\Vendor')->withTrashed();
    }

    public function isSent()
    {
        return false;
    }
    
}
