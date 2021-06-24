<?php

namespace App\Http\Controllers;

use Utils;
use App\Models\Client;
use App\Models\Vendor;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\RawMaterial;
use Illuminate\Http\Request;
use App\Models\InvoiceDesign;
use App\Models\PurchaseOrder;
use App\Services\PaymentService;
use App\Models\PurchaseOrderItem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use App\Http\Requests\InvoiceRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Input;
use App\Services\PurchaseOrderService;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Redirect;
use App\Http\Requests\PurchaseOrderRequest;
use App\Ninja\Repositories\VendorRepository;
use App\Ninja\Datatables\PurchaseOrderDatatable;
use App\Ninja\Repositories\PurchaseOrderRepository;

class PurchaseOrderController extends BaseController
{
    protected $purchaseOrderRepository;
    protected $vendorRepository;
    protected $purchaseOrderService;
    protected $paymentService;
    protected $entityType = ENTITY_PURCHASE_ORDER;

    public function __construct(PurchaseOrderRepository $purchaseOrderRepository, VendorRepository $vendorRepository, PurchaseOrderService $purchaseOrderService, PaymentService $paymentService)
    {
        // parent::__construct();
        Session::put('balance_to_be_paid', true);
        $this->purchaseOrderRepository = $purchaseOrderRepository;
        $this->vendorRepository = $vendorRepository;
        $this->purchaseOrderService = $purchaseOrderService;
        $this->paymentService = $paymentService;
    }

    public function __destruct()
    {
        Session::forget('balance_to_be_paid');
    }
    
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $data = [
            'title' => trans('texts.purchase_orders'),
            'entityType' => ENTITY_PURCHASE_ORDER,
            'statuses' => Invoice::getStatuses(),
            'datatable' => new PurchaseOrderDatatable(),
        ];

        return response()->view('list_wrapper', $data);
    }

    public function getDatatable($clientPublicId = null)
    {
        $accountId = Auth::user()->account_id;
        $search = Input::get('sSearch');

        return $this->purchaseOrderService->getDatatable($accountId, $clientPublicId, ENTITY_PURCHASE_ORDER, $search);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(PurchaseOrderRequest $request, $clientPublicId = 0, $isRecurring = false)
    {
        $account = Auth::user()->account;

        $entityType = ENTITY_INVOICE;
        $clientId = null;

        if ($request->client_id) {
            $clientId = Vendor::getPrivateId($request->client_id);
        }

        $invoice = $account->createInvoice($entityType, $clientId);
        $invoice->public_id = 0;
        $invoice->loadFromRequest();

        $vendors = Vendor::scope()->with('country')->orderBy('name');
        if (! Auth::user()->hasPermission('view_client')) {
            $vendors = $vendors->where('vendors.user_id', '=', Auth::user()->id);
        }

        $data = [
            'vendors' => $vendors->get(),
            'entityType' => $invoice->getEntityType(),
            'invoice' => $invoice,
            'method' => 'POST',
            'countries' => Cache::get('countries'),
            'url' => 'purchase_orders',
            'title' => trans('texts.new_purchase_order'),
        ];
        $data = array_merge($data, self::getViewModel($invoice));
        // dd($vendors->get());
        return View::make('purchase.edit', $data);
    }

    private static function getViewModel($invoice)
    {
        $account = Auth::user()->account;

        $recurringHelp = '';
        $recurringDueDateHelp = '';
        $recurringDueDates = [];

        foreach (preg_split("/((\r?\n)|(\r\n?))/", trans('texts.recurring_help')) as $line) {
            $parts = explode('=>', $line);
            if (count($parts) > 1) {
                $line = $parts[0].' => '.Utils::processVariables($parts[0]);
                $recurringHelp .= '<li>'.strip_tags($line).'</li>';
            } else {
                $recurringHelp .= $line;
            }
        }

        foreach (preg_split("/((\r?\n)|(\r\n?))/", trans('texts.recurring_due_date_help')) as $line) {
            $parts = explode('=>', $line);
            if (count($parts) > 1) {
                $line = $parts[0].' => '.Utils::processVariables($parts[0]);
                $recurringDueDateHelp .= '<li>'.strip_tags($line).'</li>';
            } else {
                $recurringDueDateHelp .= $line;
            }
        }

        // Create due date options
        $recurringDueDates = [
            trans('texts.use_client_terms') => ['value' => '', 'class' => 'monthly weekly'],
        ];

        $ends = ['th', 'st', 'nd', 'rd', 'th', 'th', 'th', 'th', 'th', 'th'];
        for ($i = 1; $i < 31; $i++) {
            if ($i >= 11 && $i <= 13) {
                $ordinal = $i. 'th';
            } else {
                $ordinal = $i . $ends[$i % 10];
            }

            $dayStr = str_pad($i, 2, '0', STR_PAD_LEFT);
            $str = trans('texts.day_of_month', ['ordinal' => $ordinal]);

            $recurringDueDates[$str] = ['value' => "1998-01-$dayStr", 'data-num' => $i, 'class' => 'monthly'];
        }
        $recurringDueDates[trans('texts.last_day_of_month')] = ['value' => '1998-01-31', 'data-num' => 31, 'class' => 'monthly'];

        $daysOfWeek = [
            trans('texts.sunday'),
            trans('texts.monday'),
            trans('texts.tuesday'),
            trans('texts.wednesday'),
            trans('texts.thursday'),
            trans('texts.friday'),
            trans('texts.saturday'),
        ];
        foreach (['1st', '2nd', '3rd', '4th'] as $i => $ordinal) {
            foreach ($daysOfWeek as $j => $dayOfWeek) {
                $str = trans('texts.day_of_week_after', ['ordinal' => $ordinal, 'day' => $dayOfWeek]);

                $day = $i * 7 + $j + 1;
                $dayStr = str_pad($day, 2, '0', STR_PAD_LEFT);
                $recurringDueDates[$str] = ['value' => "1998-02-$dayStr", 'data-num' => $day, 'class' => 'weekly'];
            }
        }

        // Check for any taxes which have been deleted
        $taxRateOptions = $account->present()->taxRateOptions;
        // if ($invoice->exists && !$invoice->deleted_at) {
        //     foreach ($invoice->getTaxes() as $key => $rate) {
        //         $key = '0 ' . $key; // mark it as a standard exclusive rate option
        //         if (isset($taxRateOptions[$key])) {
        //             continue;
        //         }
        //         $taxRateOptions[$key] = $rate['name'] . ' ' . $rate['rate'] . '%';
        //     }
        // }
        $products=RawMaterial::scope()->orderBy('raw_material_key')->get();
        if(isset($invoice->vendor_id)){
            $products=RawMaterial::scope()->where('supplier',$invoice->vendor_id)->orderBy('raw_material_key')->get();
        }

        return [
            'data' => Input::old('data'),
            'account' => Auth::user()->account->load('country'),
            'products' => $products,
            'taxRateOptions' => $taxRateOptions,
            'sizes' => Cache::get('sizes'),
            'invoiceDesigns' => InvoiceDesign::getDesigns(),
            'invoiceFonts' => Cache::get('fonts'),
            'frequencies' => \App\Models\Frequency::selectOptions(),
            'recurringDueDates' => $recurringDueDates,
            'recurringHelp' => $recurringHelp,
            'recurringDueDateHelp' => $recurringDueDateHelp,
            'invoiceLabels' => Auth::user()->account->getInvoiceLabels(),
            'tasks' => Session::get('tasks') ? Session::get('tasks') : null,
            'expenseCurrencyId' => Session::get('expenseCurrencyId') ?: null,
            'expenses' => Expense::scope(Session::get('expenses'))->with('documents', 'expense_category')->get(),
        ];
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $data = $request->input();
        $user_id = Auth::user()->id;
        $account_id = Auth::user()->account->id;
        $purchaseOrder=new PurchaseOrder;
        $purchaseOrder->vendor_id=Input::get('client_id');
        $purchaseOrder->user_id=$user_id;
        $purchaseOrder->account_id=$account_id;
        $purchaseOrder->invoice_number=Input::get('invoice_number');
        $purchaseOrder->po_number=Input::get('po_number');
        $purchaseOrder->invoice_date=Input::get('invoice_date');
        $purchaseOrder->due_date=Input::get('due_date');
        $purchaseOrder->public_notes=Input::get('public_notes');
        $purchaseOrder->save();

        $purchaseOrder->public_id=$purchaseOrder->id;
        $purchaseOrder->save();
        $purchaseOrder->amount=0;
        foreach ($request->invoice_items as $key => $value) {
            if ($value['raw_material_key'] != null || $value['raw_material_key'] != '') {
                $rawMaterial=RawMaterial::where(['raw_material_key'=>$value['raw_material_key'],'supplier'=>Input::get('client_id')])->first();
                $purchaseOrderItems=new PurchaseOrderItem();
                $purchaseOrderItems->account_id=$account_id;
                $purchaseOrderItems->user_id=$user_id;
                $purchaseOrderItems->purchase_order_id=$purchaseOrder->id;
                $purchaseOrderItems->raw_material_id=$rawMaterial->public_id;
                $purchaseOrderItems->raw_material_key=$value['raw_material_key'];
                $purchaseOrderItems->notes=$value['notes'];
                $purchaseOrderItems->cost=$value['cost'];
                $purchaseOrderItems->qty=$value['qty'];
                $purchaseOrderItems->save();
                $purchaseOrderItems->public_id=$purchaseOrderItems->id;
                $purchaseOrderItems->save();
                $purchaseOrder->amount+=floatval($value['cost'])*floatval($value['qty']);
            }
        }
        $purchaseOrder->save();
        return url('purchase_orders/'.$purchaseOrder->id.'/edit');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        Session::reflash();

        return Redirect::to("purchase_orders/$id/edit");
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    

    public function edit(InvoiceRequest $request, $publicId, $clone = false)
    {
        $account = Auth::user()->account;

        $entityType = ENTITY_PURCHASE_ORDER;
        $clientId = null;

        if ($request->client_id) {
            $clientId = Vendor::getPrivateId($request->client_id);
        }

        // $invoice = $account->createInvoice($entityType, $clientId);
        // $invoice->public_id = 0;
        // $invoice->loadFromRequest();

        $vendors = Vendor::scope()->with('country')->orderBy('name');
        if (! Auth::user()->hasPermission('view_client')) {
            $vendors = $vendors->where('vendors.user_id', '=', Auth::user()->id);
        }
        $invoice=PurchaseOrder::where('id',$publicId)->withTrashed()->first();
        // dd($invoice);
        $data = [
            'vendors' => $vendors->get(),
            'entityType' => ENTITY_PURCHASE_ORDER,
            'invoice' => $invoice,
            'showBreadcrumbs' => false,
            'method' => 'PUT',
            'countries' => Cache::get('countries'),
            'url' => url('purchase_orders/'.$invoice->id),
            'title' => trans('texts.edit_purchase_order'),
        ];
        $data = array_merge($data, self::getViewModel($invoice));
        // dd($data);
        return View::make('purchase.edit', $data);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        // return $request->all();
        $user_id=Auth::user()->id;
        $account_id=Auth::user()->account->id;
        $purchaseOrder=PurchaseOrder::where('id',$id)->first();
        $purchaseOrder->invoice_number=Input::get('invoice_number');
        $purchaseOrder->po_number=Input::get('po_number');
        $purchaseOrder->invoice_date=Input::get('invoice_date');
        $purchaseOrder->due_date=Input::get('due_date');
        $purchaseOrder->public_notes=Input::get('public_notes');
        $purchaseOrder->save();

        $purchaseOrder->public_id=$purchaseOrder->id;
        $purchaseOrder->save();
        $oldPurchaseOrderItem=PurchaseOrderItem::where('purchase_order_id',$id)->get();
        $oldPurchaseOrderItem->each(function(PurchaseOrderItem $purchaseOrderItem){
            $purchaseOrderItem->forceDelete();
        });
        $purchaseOrder->amount=0;
        foreach ($request->invoice_items as $key => $value) {
            if ($value['raw_material_key'] != null || $value['raw_material_key'] != '') {
                $rawMaterial=RawMaterial::where(['raw_material_key'=>$value['raw_material_key'],'supplier'=>Input::get('client_id')])->first();
                
                $purchaseOrderItems=new PurchaseOrderItem();
                $purchaseOrderItems->account_id=$account_id;
                $purchaseOrderItems->user_id=$user_id;
                $purchaseOrderItems->purchase_order_id=$purchaseOrder->id;
                $purchaseOrderItems->raw_material_id=$rawMaterial->public_id;
                $purchaseOrderItems->raw_material_key=$value['raw_material_key'];
                $purchaseOrderItems->notes=$value['notes'];
                $purchaseOrderItems->cost=$value['cost'];
                $purchaseOrderItems->qty=$value['qty'];
                $purchaseOrderItems->save();
                $purchaseOrderItems->public_id=$purchaseOrderItems->id;
                $purchaseOrderItems->save();
                $purchaseOrder->amount+=floatval($value['cost'])*floatval($value['qty']);
            }
        }
        $purchaseOrder->save();
        return url('purchase_orders/'.$purchaseOrder->id.'/edit');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int   $id
     * @param mixed $entityType
     *
     * @return Response
     */
    public function bulk($entityType = ENTITY_PURCHASE_ORDER)
    {
        $action = Input::get('bulk_action') ?: Input::get('action');
        $ids = Input::get('bulk_public_id') ?: (Input::get('public_id') ?: Input::get('ids'));
        $count = $this->purchaseOrderService->bulk($ids, $action);

        if ($count > 0) {
            if ($action == 'markSent') {
                $key = 'marked_sent_invoice';
            } elseif ($action == 'emailInvoice') {
                $key = 'emailed_' . $entityType;
            } elseif ($action == 'markPaid') {
                $key = 'created_payment';
            } elseif ($action == 'download') {
                $key = 'downloaded_invoice';
            } else {
                $key = "{$action}d_{$entityType}";
            }
            $message = Utils::pluralize($key, $count);
            Session::flash('message', $message);
        }

        return $this->returnBulk($entityType, $action, $ids);
    }
}
