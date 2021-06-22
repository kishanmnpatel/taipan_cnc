<?php

namespace App\Models;

use Laracasts\Presenter\PresentableTrait;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrderItem extends EntityModel
{
    use PresentableTrait;
    use SoftDeletes;

    /**
     * @var string
     */
    protected $presenter = 'App\Ninja\Presenters\PurchaseOrderItemPresenter';

    /**
     * @var array
     */
    protected $dates = ['deleted_at'];

    /**
     * @return mixed
     */
    public function getEntityType()
    {
        return ENTITY_PURCHASE_ORDER_ITEM;
    }

    /**
     * @var array
     */
    protected $fillable = [
        'tax_name1',
        'tax_rate1',
        'tax_name2',
        'tax_rate2',
        'invoice_item_type_id',
        'discount',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function purchaseOrder()
    {
        return $this->belongsTo('App\Models\PurchaseOrder')->withTrashed();
    }

    /**
     * @return mixed
     */
    public function user()
    {
        return $this->belongsTo('App\Models\User')->withTrashed();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function rawMaterial()
    {
        return $this->belongsTo('App\Models\RawMaterial');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function account()
    {
        return $this->belongsTo('App\Models\Account');
    }
}
