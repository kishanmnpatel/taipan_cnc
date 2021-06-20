<?php

namespace App\Http\Controllers;

use URL;
use Auth;
use View;
use Input;
use Utils;
use Session;
use Redirect;
use Datatable;
use App\Models\Vendor;
use App\Models\Product;
use App\Models\TaxRate;
use App\Models\RawMaterial;
use App\Http\Requests\Request;
use App\Services\ProductService;
use App\Models\ProductRawMaterials;
use App\Http\Requests\ProductRequest;
use Illuminate\Support\Facades\Cache;
use App\Ninja\Datatables\ProductDatatable;
use Google\Cloud\BigQuery\Connection\Rest;
use App\Http\Requests\CreateProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Ninja\Repositories\ProductRepository;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Class ProductController.
 */
class ProductController extends BaseController
{
    /**
     * @var ProductService
     */
    protected $productService;

    /**
     * @var ProductRepository
     */
    protected $productRepo;

    /**
     * ProductController constructor.
     *
     * @param ProductService $productService
     */
    public function __construct(ProductService $productService, ProductRepository $productRepo)
    {
        //parent::__construct();

        $this->productService = $productService;
        $this->productRepo = $productRepo;
    }

    /**
     * @return \Illuminate\Http\RedirectResponse
     */
    public function index()
    {
        return View::make('list_wrapper', [
            'entityType' => ENTITY_PRODUCT,
            'datatable' => new ProductDatatable(),
            'title' => trans('texts.products'),
            'statuses' => Product::getStatuses(),
        ]);
    }

    public function show($publicId)
    {
        Session::reflash();

        return Redirect::to("products/$publicId/edit");
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDatatable()
    {
        return $this->productService->getDatatable(Auth::user()->account_id, Input::get('sSearch'));
    }

    public function cloneProduct(ProductRequest $request, $publicId)
    {
        return self::edit($request, $publicId, true);
    }

    /**
     * @param $publicId
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function edit(ProductRequest $request, $publicId, $clone = false)
    {
        Auth::user()->can('view', [ENTITY_PRODUCT, $request->entity()]);

        $account = Auth::user()->account;
        $product = Product::scope($publicId)->withTrashed()->firstOrFail();

        if ($clone) {
            $product->id = null;
            $product->public_id = null;
            $product->deleted_at = null;
            $url = 'products';
            $method = 'POST';
        } else {
            $url = 'products/'.$publicId;
            $method = 'PUT';
        }
        $bom_cost=ProductRawMaterials::where(['user_id'=>Auth::user()->id,'account_id'=>Auth::user()->account->id,'product_id'=>$product->id])->sum('total_cost');

        $data = [
          'account' => $account,
          'taxRates' => $account->invoice_item_taxes ? TaxRate::scope()->whereIsInclusive(false)->get() : null,
          'product' => $product,
          'entity' => $product,
          'method' => $method,
          'url' => $url,
          'products' => RawMaterial::scope()->orderBy('raw_material_key')->get(),
          'product_raw_material_id' => Input::get('product_raw_material_id'),
          'bom_cost'=>$bom_cost,
          'title' => trans('texts.edit_product'),
        ];

        return View::make('accounts.product', $data);
    }

    public function getDatatableRawMaterials(ProductRequest $request)
    {
        $user = Auth::user();
        $account = Auth::user()->account;
        $tableData=ProductRawMaterials::where(['user_id'=>$user->id,'account_id'=>$account->id,'product_id'=>null])->get();

        if (Input::get('getId') != '' || Input::get('getId') != null) {
            $tableData=ProductRawMaterials::where(['user_id'=>$user->id,'account_id'=>$account->id,'product_id'=>Input::get('getId')])->get();
        }
        
        return Datatable::collection($tableData)
                        ->addColumn('part_name',function($model)
                            {
                                return $model->product_raw_material_key;
                            }
                        )
                        ->addColumn('cost',function($model)
                            {
                                return $model->cost;
                            }
                        )
                        ->addColumn('supplier',function($model)
                            {
                                $supplier_id=RawMaterial::scope($model->raw_material_id)->first()->supplier;
                                $vendor=Vendor::scope($supplier_id)->withTrashed()->first();
                                return $vendor->name;
                            }
                        )
                        ->addColumn('qty',function($model)
                            {
                                return $model->qty;
                            }
                        )
                        ->addColumn('actions',function($model)
                            {
                                return '
                                <div class="row">
                                    <div class="col-md-6">
                                        <button type="button" class="editPart btn btn-warning btn-sm pull-right" id="'.$model->id.'">Edit Part</button>
                                    </div>
                                    <div class="col-md-6">
                                        <a class="btn btn-danger btn-sm" href="'.asset('api/raw_products/delete').'?id='.$model->id.'">Remove</a>
                                    </div>
                                </div>
                                ';
                            }
                        )
                        ->make();
    }

    public function deleteRawMaterials()
    {
        ProductRawMaterials::where(['id'=>Input::get('id')])->delete();
        return redirect()->back();
    }

    public function editRawMaterials($id)
    {
        $productRawMaterial = ProductRawMaterials::where('id',$id)->first();
        return $productRawMaterial;
    }

    /**
     * @return \Illuminate\Contracts\View\View
     */
    public function create(ProductRequest $request)
    {
        $user = Auth::user();
        $account = Auth::user()->account;
        if (!empty(Input::get('product_raw_material_id'))) {
            $inputId=Input::get('product_raw_material_id');
            if(ProductRawMaterials::where(['user_id'=>$user->id,'account_id'=>$account->id,'raw_material_id'=>$inputId,'product_id'=>null])->count() == 0)
            {
                $rawMaterial = RawMaterial::scope($inputId)->first();
                $productRawMaterial=new ProductRawMaterials();
            
                $productRawMaterial->user_id = $user->id;
                $productRawMaterial->account_id = $account->id;
                $productRawMaterial->raw_material_id = $rawMaterial->id;
                $productRawMaterial->product_raw_material_key = $rawMaterial->raw_material_key;
                $productRawMaterial->notes = Input::get('raw_notes');
                $productRawMaterial->cost = $rawMaterial->cost;
                $productRawMaterial->total_cost = $rawMaterial->cost*Input::get('qty');
                $productRawMaterial->qty = Input::get('qty');
                $productRawMaterial->save();
            }

            if (Input::get('product_raw_id')!='' || Input::get('product_raw_id')!=null) {
                $productRawMaterial = ProductRawMaterials::where('id',Input::get('product_raw_id'))->first();
                $productRawMaterial->notes = Input::get('raw_notes');
                $productRawMaterial->total_cost = $productRawMaterial->cost*Input::get('qty');
                $productRawMaterial->qty = Input::get('qty');
                $productRawMaterial->save();
            }
            return redirect(url()->previous());
        }
        $bom_cost=ProductRawMaterials::where(['user_id'=>$user->id,'account_id'=>$account->id,'product_id'=>null])->sum('total_cost');
        $data = [
          'account' => $account,
          'taxRates' => $account->invoice_item_taxes ? TaxRate::scope()->whereIsInclusive(false)->get(['id', 'name', 'rate']) : null,
          'product' => null,
          'method' => 'POST',
          'products' => RawMaterial::scope()->orderBy('raw_material_key')->get(),
          'product_raw_material_id' => Input::get('product_raw_material_id'),
          'bom_cost'=>$bom_cost,
          'url' => 'products',
          'title' => trans('texts.create_product'),
        ];

        return View::make('accounts.product', $data);
    }

    /**
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(CreateProductRequest $request)
    {
        return $this->save();
    }

    /**
     * @param $publicId
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(UpdateProductRequest $request, $publicId)
    {
        return $this->save($publicId);
    }

    /**
     * @param bool $productPublicId
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    private function save($productPublicId = false)
    {
        if ($productPublicId) {
            $product = Product::scope($productPublicId)->withTrashed()->firstOrFail();
        } else {
            $product = Product::createNew();
        }

        $this->productRepo->save(Input::all(), $product);

        $message = $productPublicId ? trans('texts.updated_product') : trans('texts.created_product');
        Session::flash('message', $message);

        $action = request('action');
        if (in_array($action, ['archive', 'delete', 'restore', 'invoice'])) {
            return self::bulk();
        }
        if (ProductRawMaterials::where(['user_id'=>Auth::user()->id,'account_id'=>Auth::user()->account->id,'product_id'=>null])->count() != 0) {
            $list=ProductRawMaterials::where(['user_id'=>Auth::user()->id,'account_id'=>Auth::user()->account->id,'product_id'=>null])->get();
            $list->each(function(ProductRawMaterials $productRawMaterials) use($product){
                $productRawMaterials->product_id=$product->id;
                $productRawMaterials->save();
            });
        }
        if ($action == 'clone') {
            return redirect()->to(sprintf('products/%s/clone', $product->public_id));
        } else {
            return redirect()->to("products/{$product->public_id}/edit");
        }
    }

    /**
     * @return \Illuminate\Http\RedirectResponse
     */
    public function bulk()
    {
        $action = Input::get('action');
        $ids = Input::get('public_id') ? Input::get('public_id') : Input::get('ids');

        if ($action == 'invoice') {
            $products = Product::scope($ids)->get();
            $data = [];
            foreach ($products as $product) {
                $data[] = $product->product_key;
            }
            return redirect("invoices/create")->with('selectedProducts', $data);
        } else {
            $count = $this->productService->bulk($ids, $action);
        }

        $message = Utils::pluralize($action.'d_product', $count);
        Session::flash('message', $message);

        return $this->returnBulk(ENTITY_PRODUCT, $action, $ids);
    }
}
