<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Vendor;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\RawMaterial;
use Illuminate\Http\Request;
use App\Models\InvoiceDesign;
use App\Services\PaymentService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Input;
use App\Services\PurchaseOrderService;
use Illuminate\Support\Facades\Session;
use App\Http\Requests\PurchaseOrderRequest;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Ninja\Repositories\VendorRepository;
use App\Ninja\Datatables\PurchaseOrderDatatable;
use App\Ninja\Repositories\PurchaseOrderRepository;

class PurchaseOrderController extends Controller
{
    protected $purchaseOrderRepository;
    protected $vendorRepository;
    protected $purchaseOrderService;
    protected $paymentService;
    protected $entityType = ENTITY_PURCHASE_ORDER;

    public function __construct(PurchaseOrderRepository $purchaseOrderRepository, VendorRepository $vendorRepository, PurchaseOrderService $purchaseOrderService, PaymentService $paymentService)
    {
        // parent::__construct();

        $this->purchaseOrderRepository = $purchaseOrderRepository;
        $this->vendorRepository = $vendorRepository;
        $this->purchaseOrderService = $purchaseOrderService;
        $this->paymentService = $paymentService;
    }
    
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $data = [
            'title' => trans('texts.invoices'),
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

        $entityType = ENTITY_PURCHASE_ORDER;
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
            'title' => trans('texts.new_invoice'),
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
        if ($invoice->exists && !$invoice->deleted_at) {
            foreach ($invoice->getTaxes() as $key => $rate) {
                $key = '0 ' . $key; // mark it as a standard exclusive rate option
                if (isset($taxRateOptions[$key])) {
                    continue;
                }
                $taxRateOptions[$key] = $rate['name'] . ' ' . $rate['rate'] . '%';
            }
        }

        return [
            'data' => Input::old('data'),
            'account' => Auth::user()->account->load('country'),
            'products' => RawMaterial::scope()->orderBy('raw_material_key')->get(),
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
        return $data;
        $user_id = Auth::user()->id;
        $account_id = Auth::user()->account->id;
        $purchaseOrder=new PurchaseOrder;
        $purchaseOrder->vendor_id=Input::get('client_id');
        $purchaseOrder->user_id=$user_id;
        $purchaseOrder->account_id=$account_id;
        $purchaseOrder->purchase_order_number=Input::get('invoice_number');
        $purchaseOrder->quote_number=Input::get('po_number');
        $purchaseOrder->purchase_order_date=Input::get('invoice_date');
        $purchaseOrder->due_date=Input::get('due_date');
        $purchaseOrder->public_notes=Input::get('public_notes');
        $purchaseOrder->save();

        $purchaseOrder->public_id=$purchaseOrder->id;
        $purchaseOrder->save();
        foreach ($request->invoice_items as $key => $value) {
            $purchaseOrderItems=new PurchaseOrderItem();
            $purchaseOrderItems->account_id=$account_id;
            $purchaseOrderItems->user_id=$user_id;
            $purchaseOrderItems->purchase_order_id=$purchaseOrder->id;
            $purchaseOrderItems->raw_material_id=;
            $purchaseOrderItems->raw_material_key=;
            $purchaseOrderItems->notes=Input::get('public_notes');
            $purchaseOrderItems->cost=Input::get('public_notes');
            $purchaseOrderItems->qty=Input::get('public_notes');
            $purchaseOrderItems->public_id=Input::get('public_notes');
            $purchaseOrderItems->save();
        }
        

        $data['documents'] = $request->file('documents');

        $action = Input::get('action');
        $entityType = Input::get('entityType');

        $invoice = $this->invoiceService->save($data);
        $entityType = $invoice->getEntityType();
        $message = trans("texts.created_{$entityType}");

        $input = $request->input();
        $clientPublicId = isset($input['client']['public_id']) ? $input['client']['public_id'] : false;
        if ($clientPublicId == '-1') {
            $message = $message.' '.trans('texts.and_created_client');
        }

        Session::flash('message', $message);

        if ($action == 'email') {
            $this->emailInvoice($invoice);
        }

        return url($invoice->getRoute());
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
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
        //
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
}
