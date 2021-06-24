<?php

namespace App\Ninja\Datatables;

use App\Models\Invoice;
use Auth;
use URL;
use Utils;

class PurchaseOrderDatatable extends EntityDatatable
{
    public $entityType = ENTITY_PURCHASE_ORDER;
    public $sortCol = 3;

    public function columns()
    {
        $entityType = $this->entityType;

        return [
            [
                $entityType == ENTITY_PURCHASE_ORDER ? 'purchase_order_number' : 'quote_number',
                function ($model) use ($entityType) {
                    // dd($model);
                    if(Auth::user()->viewModel($model, $entityType)) {
                        $str = link_to("purchase_orders/{$model->public_id}/edit", $model->invoice_number, ['class' => Utils::getEntityRowClass($model)])->toHtml();
                        // return $this->addNote($str, $model->public_notes);
                        return $str;
                    }
                    else
                        return $model->invoice_number;


                },
            ],
            [
                'supplier_name',
                function ($model) {
                    if(Auth::user()->can('view', [ENTITY_VENDOR, $model]))
                        return link_to("vendors/{$model->vendor_public_id}", Utils::getVendorDisplayName($model))->toHtml();
                    else
                        return Utils::getClientDisplayName($model);

                },
                ! $this->hideClient,
            ],
            [
                'date',
                function ($model) {
                    return Utils::fromSqlDate($model->invoice_date);
                },
            ],
            [
                'amount',
                function ($model) {
                    return Utils::formatMoney($model->amount, $model->currency_id, $model->country_id);
                },
            ],
            // [
            //     'balance',
            //     function ($model) {
            //         return $model->partial > 0 ?
            //             trans('texts.partial_remaining', [
            //                 'partial' => Utils::formatMoney($model->partial, $model->currency_id, $model->country_id),
            //                 'balance' => Utils::formatMoney($model->balance, $model->currency_id, $model->country_id), ]
            //             ) :
            //             Utils::formatMoney($model->balance, $model->currency_id, $model->country_id);
            //     },
            //     $entityType == ENTITY_PURCHASE_ORDER,
            // ],
            [
                $entityType == ENTITY_PURCHASE_ORDER ? 'due_date' : 'valid_until',
                function ($model) {
                    $str = '';
                    if ($model->partial_due_date) {
                        $str = Utils::fromSqlDate($model->partial_due_date);
                        if ($model->due_date_sql && $model->due_date_sql != '0000-00-00') {
                            $str .= ', ';
                        }
                    }
                    return $str . Utils::fromSqlDate($model->due_date_sql);
                },
            ],
            [
                'status',
                function ($model) use ($entityType) {
                    return self::getStatusLabel($model);
                },
            ],
        ];
    }

    public function actions()
    {
        $entityType = $this->entityType;

        return [
            [
                trans('texts.view_invoice'),
                function ($model) {
                    return URL::to("purchase_orders/{$model->public_id}/edit");
                },
            ]
        ];
    }

    private function getStatusLabel($model)
    {
        $class = Invoice::calcStatusClass($model->invoice_status_id, $model->balance, $model->partial_due_date ?: $model->due_date_sql, false);
        $label = Invoice::calcStatusLabel($model->invoice_status_name, $class, $this->entityType, false);

        return "<h4><div class=\"label label-default\">Draft</div></h4>";
    }

    public function bulkActions()
    {
        $actions = [];

        if ($this->entityType == ENTITY_INVOICE || $this->entityType == ENTITY_QUOTE) {
            $actions[] = [
                'label' => mtrans($this->entityType, 'download_' . $this->entityType),
                'url' => 'javascript:submitForm_'.$this->entityType.'("download")',
            ];
            if (auth()->user()->isTrusted()) {
                $actions[] = [
                    'label' => mtrans($this->entityType, 'email_' . $this->entityType),
                    'url' => 'javascript:submitForm_'.$this->entityType.'("emailInvoice")',
                ];
            }
            $actions[] = \DropdownButton::DIVIDER;
            $actions[] = [
                'label' => mtrans($this->entityType, 'mark_sent'),
                'url' => 'javascript:submitForm_'.$this->entityType.'("markSent")',
            ];
        }

        if ($this->entityType == ENTITY_INVOICE) {
            $actions[] = [
                'label' => mtrans($this->entityType, 'mark_paid'),
                'url' => 'javascript:submitForm_'.$this->entityType.'("markPaid")',
            ];
        }

        $actions[] = \DropdownButton::DIVIDER;
        $actions = array_merge($actions, parent::bulkActions());

        return $actions;
    }
}
