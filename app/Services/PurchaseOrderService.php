<?php

namespace App\Services;

use App\Events\QuoteInvitationWasApproved;
use App\Models\Client;
use App\Models\Invitation;
use App\Models\Invoice;
use App\Ninja\Datatables\PurchaseOrderDatatable;
use App\Ninja\Repositories\VendorRepository;
use App\Ninja\Repositories\PurchaseOrderRepository;
use App\Jobs\DownloadInvoices;
use Auth;
use Utils;

class PurchaseOrderService extends BaseService
{
    /**
     * @var VendorRepository
     */
    protected $clientRepo;

    /**
     * @var PurchaseOrderRepository
     */
    protected $purchaseOrderRepository;

    /**
     * @var DatatableService
     */
    protected $datatableService;

    /**
     * InvoiceService constructor.
     *
     * @param VendorRepository  $clientRepo
     * @param PurchaseOrderRepository $purchaseOrderRepository
     * @param DatatableService  $datatableService
     */
    public function __construct(
        VendorRepository $clientRepo,
        PurchaseOrderRepository $purchaseOrderRepository,
        DatatableService $datatableService
    ) {
        $this->clientRepo = $clientRepo;
        $this->purchaseOrderRepository = $purchaseOrderRepository;
        $this->datatableService = $datatableService;
    }

    /**
     * @return PurchaseOrderRepository
     */
    protected function getRepo()
    {
        return $this->purchaseOrderRepository;
    }

    /**
     * @param $ids
     * @param $action
     *
     * @return int
     */
    public function bulk($ids, $action)
    {
        if ($action == 'download') {
            $invoices = $this->getRepo()->findByPublicIdsWithTrashed($ids);
            dispatch(new DownloadInvoices(Auth::user(), $invoices));
            return count($invoices);
        } else {
            return parent::bulk($ids, $action);
        }
    }

    /**
     * @param array        $data
     * @param Invoice|null $invoice
     *
     * @return \App\Models\Invoice|Invoice|mixed
     */
    public function save(array $data, Invoice $invoice = null)
    {
        if (isset($data['client'])) {
            $canSaveClient = false;
            $canViewClient = false;
            $clientPublicId = array_get($data, 'client.public_id') ?: array_get($data, 'client.id');
            if (empty($clientPublicId) || intval($clientPublicId) < 0) {
                $canSaveClient = Auth::user()->can('create', ENTITY_CLIENT);
            } else {
                $client = Client::scope($clientPublicId)->first();
                $canSaveClient = Auth::user()->can('edit', $client);
                $canViewClient = Auth::user()->can('view', $client);
            }
            if ($canSaveClient) {
                $client = $this->clientRepo->save($data['client']);
            }
            if ($canSaveClient || $canViewClient) {
                $data['client_id'] = $client->id;
            }
        }

        return $this->purchaseOrderRepository->save($data, $invoice);
    }

    /**
     * @param $quote
     * @param Invitation|null $invitation
     *
     * @return mixed
     */
    public function convertQuote($quote)
    {
        $account = $quote->account;
        $invoice = $this->purchaseOrderRepository->cloneInvoice($quote, $quote->id);

        if ($account->auto_archive_quote) {
            $this->purchaseOrderRepository->archive($quote);
        }

        return $invoice;
    }

    /**
     * @param $quote
     * @param Invitation|null $invitation
     *
     * @return mixed|null
     */
    public function approveQuote($quote, Invitation $invitation = null)
    {
        $account = $quote->account;

        if (! $account->hasFeature(FEATURE_QUOTES) || ! $quote->isType(INVOICE_TYPE_QUOTE) || $quote->quote_invoice_id) {
            return null;
        }

        event(new QuoteInvitationWasApproved($quote, $invitation));

        if ($account->auto_convert_quote) {
            $invoice = $this->convertQuote($quote);

            foreach ($invoice->invitations as $invoiceInvitation) {
                if ($invitation->contact_id == $invoiceInvitation->contact_id) {
                    $invitation = $invoiceInvitation;
                }
            }
        } else {
            $quote->markApproved();
        }

        return $invitation->invitation_key;
    }

    public function getDatatable($accountId, $clientPublicId, $entityType, $search)
    {
        $datatable = new PurchaseOrderDatatable(true, $clientPublicId);
        $datatable->entityType = $entityType;

        $query = $this->purchaseOrderRepository->getInvoices($accountId, $clientPublicId, $entityType, $search);
                    // ->where('purchase_orders.invoice_type_id', '=', $entityType == ENTITY_QUOTE ? INVOICE_TYPE_QUOTE : INVOICE_TYPE_STANDARD);

        // if (! Utils::hasPermission('view_' . $entityType)) {
            $query->where('purchase_orders.user_id', '=', Auth::user()->id);
        // }
        // dd($query->wheres);
        return $this->datatableService->createDatatable($datatable, $query);
    }
}
