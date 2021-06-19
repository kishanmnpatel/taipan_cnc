<?php

namespace App\Services;

use App\Ninja\Datatables\RawMaterialDatatable;
use App\Ninja\Repositories\RawMaterialRepository;
use Auth;
use Utils;

class RawMaterialService extends BaseService
{
    /**
     * @var DatatableService
     */
    protected $datatableService;

    /**
     * @var RawMaterialRepository
     */
    protected $rawMaterialRepository;

    /**
     * RawMaterialService constructor.
     *
     * @param DatatableService  $datatableService
     * @param RawMaterialRepository $rawMaterialRepository
     */
    public function __construct(DatatableService $datatableService, RawMaterialRepository $rawMaterialRepository)
    {
        $this->datatableService = $datatableService;
        $this->rawMaterialRepository = $rawMaterialRepository;
    }

    /**
     * @return RawMaterialRepository
     */
    protected function getRepo()
    {
        return $this->rawMaterialRepository;
    }

    /**
     * @param $accountId
     * @param mixed $search
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDatatable($accountId, $search)
    {
        $datatable = new RawMaterialDatatable(true);
        $query = $this->rawMaterialRepository->find($accountId, $search);

        // if (! Utils::hasPermission('view_product')) {
            $query->where('raw_materials.user_id', '=', Auth::user()->id);
        // }

        return $this->datatableService->createDatatable($datatable, $query);
    }
}
