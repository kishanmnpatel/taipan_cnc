<?php

namespace App\Ninja\Presenters;

use DropdownButton;
use App\Libraries\Skype\HeroCard;

class RawMaterialPresenter extends EntityPresenter
{
    public function user()
    {
        return $this->entity->user->getDisplayName();
    }

    public function skypeBot($account)
    {
        $product = $this->entity;

        $card = new HeroCard();
        $card->setTitle($product->raw_material_key);
        $card->setSubitle($account->formatMoney($product->cost));
        $card->setText($product->notes);
        $card->setText($product->qty);
        $card->setText($product->supplier);

        return $card;
    }

    public function moreActions()
    {
        $product = $this->entity;
        $actions = [];

        if ($product->trashed()) {
            $actions[] = ['url' => 'javascript:submitAction("restore")', 'label' => trans("texts.restore_raw_material")];
        } 
        if (! $product->is_deleted) {
            $actions[] = ['url' => 'javascript:onDeleteClick()', 'label' => trans("texts.delete_raw_material")];
        }

        return $actions;
    }

}
