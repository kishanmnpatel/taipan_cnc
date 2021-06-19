<?php

namespace App\Http\Controllers;

use View;
use Utils;
use Session;
use App\Models\Vendor;
use App\Models\Product;
use App\Models\RawMaterial;
use Illuminate\Http\Request;
use App\Services\RawMaterialService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Input;
use App\Ninja\Datatables\RawMaterialDatatable;
use App\Http\Requests\UpdateRawMaterialRequest;
use App\Ninja\Repositories\RawMaterialRepository;

class RawMaterialController extends Controller
{
    /**
     * @var RawMaterialService
     */
    protected $rawMaterialService;

    /**
     * @var RawMaterialRepository
     */
    protected $rawMaterialRepository;

    /**
     * ProductController constructor.
     *
     * @param RawMaterialService $rawMaterialService
     */
    public function __construct(RawMaterialService $rawMaterialService, RawMaterialRepository $rawMaterialRepository)
    {
        //parent::__construct();

        $this->rawMaterialService = $rawMaterialService;
        $this->rawMaterialRepository = $rawMaterialRepository;
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return View::make('list_wrapper', [
            'entityType' => ENTITY_RAW_MATERIAL,
            'datatable' => new RawMaterialDatatable(),
            'title' => trans('texts.products'),
            'statuses' => RawMaterial::getStatuses(),
        ]);
    }
    
    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDatatable()
    {
        return $this->rawMaterialService->getDatatable(Auth::user()->account_id, Input::get('sSearch'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {

        $account = Auth::user()->account;

        $vendors = Vendor::where('vendors.user_id', '=', Auth::user()->id)->get()->pluck('name','id');
        $data = [
          'vendors' => $vendors,
          'product' => null,
          'method' => 'POST',
          'url' => 'raw_materials',
          'title' => trans('texts.create_raw_material'),
        ];

        return View::make('accounts.raw_material', $data);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $RawMaterial=new RawMaterial();
        $user = Auth::user();
            $account = Auth::user()->account;

        $RawMaterial->user_id = $user->id;
        $RawMaterial->account_id = $account->id;
        $RawMaterial->raw_material_key = $request->raw_material_key;
        $RawMaterial->supplier = $request->supplier;
        $RawMaterial->notes = $request->notes;
        $RawMaterial->cost = $request->cost;
        $RawMaterial->qty = $request->qty;

        // store references to the original user/account to prevent needing to reload them
        $RawMaterial->setRelation('user', $user);
        $RawMaterial->setRelation('account', $account);
        

        $RawMaterial->save();
        $RawMaterial->public_id=$RawMaterial->id;
        $RawMaterial->save();
        return redirect('raw_materials');
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\RawMaterial  $rawMaterial
     * @return \Illuminate\Http\Response
     */
    public function show(RawMaterial $rawMaterial)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\RawMaterial  $rawMaterial
     * @return \Illuminate\Http\Response
     */
    public function edit($rawMaterial_id)
    {

        $account = Auth::user()->account;
        $rawMaterial = RawMaterial::scope($rawMaterial_id)->withTrashed()->firstOrFail();
        $vendors = Vendor::where('vendors.user_id', '=', Auth::user()->id)->get()->pluck('name','id');
        
        $url = 'raw_materials/'.$rawMaterial_id;
        $method = 'PUT';

        $data = [
            'vendors' => $vendors,
            'product' => $rawMaterial,
            'entity' => $rawMaterial,
            'method' => $method,
            'url' => $url,
            'title' => trans('texts.edit_raw_material'),
        ];
        return View::make('accounts.raw_material', $data);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\RawMaterial  $rawMaterial
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateRawMaterialRequest $request, $publicId)
    {
        $action = request('action');
        if (in_array($action, ['delete'])) {
            // $ids = Input::get('public_id') ? Input::get('public_id') : Input::get('ids');
            $count = $this->rawMaterialService->bulk($publicId, $action);

            $message = Utils::pluralize($action.'d_raw_material', $count);
            Session::flash('message', $message);
            return redirect('raw_materials');
        }
        $rawMaterial = RawMaterial::scope($publicId)->withTrashed()->firstOrFail();
        $rawMaterial->raw_material_key = $request->raw_material_key;
        $rawMaterial->supplier = $request->supplier;
        $rawMaterial->notes = $request->notes;
        $rawMaterial->cost = $request->cost;
        $rawMaterial->qty = $request->qty;
        $rawMaterial->save();
        return redirect('raw_materials');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\RawMaterial  $rawMaterial
     * @return \Illuminate\Http\Response
     */
    public function destroy(RawMaterial $rawMaterial)
    {
        //
    }

    /**
     * @return \Illuminate\Http\RedirectResponse
     */
    public function bulk()
    {
        $action = Input::get('action');
        $ids = Input::get('public_id') ? Input::get('public_id') : Input::get('ids');

        $count = $this->rawMaterialService->bulk($ids, $action);

        $message = Utils::pluralize($action.'d_raw_material', $count);
        Session::flash('message', $message);

        return redirect('raw_materials');
    }
}
