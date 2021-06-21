@extends('header')

@section('content')
    @parent

    {!! Former::open($url)
            ->method($method)
            ->autocomplete('off')
            ->rules(['product_key' => 'required|max:255'])
            ->addClass('col-lg-10 col-lg-offset-1 main-form warn-on-exit') !!}

    @if ($product)
        {{ Former::populate($product) }}
        {{ Former::populateField('cost', Utils::roundSignificant($product->cost)) }}
    @endif

    <span style="display:none">
        {!! Former::text('public_id') !!}
        {!! Former::text('action') !!}
    </span>

    <div class="row">
        <div class="col-lg-10 col-lg-offset-1">

            <div class="panel panel-default">
                <div class="panel-body form-padding-right">

                    {!! Former::text('product_key')->label('texts.product') !!}
                    {!! Former::textarea('notes')->rows(6) !!}

                    @include('partials/custom_fields', ['entityType' => ENTITY_PRODUCT])

                    {!! Former::text('cost')->label('Sell Price') !!}

                    {!! Former::text('bom_cost')->value($bom_cost)->readonly()->label('Cost Box') !!}

                    @if ($account->invoice_item_taxes)
                        @include('partials.tax_rates')
                    @endif
                    @php
                        $queryString=request()->route()->getName() == 'products.edit' ? $product->id : '';
                    @endphp
                    Bill Of Materials
                    @if (request()->route()->getName() == 'products.edit')
                        {!! Datatable::table()
                            ->addColumn('part_name','cost','supplier','qty','actions')       // these are the column headings to be shown
                            ->setUrl(asset('/api/raw_products?getId='.$queryString))   // this is the route where data will be retrieved
                            ->render() !!}
                    @endif
                    <button type="button" class="addPart btn btn-primary btn-sm pull-right">Add Part</button>
                </div>
            </div>
        </div>
    </div>

    @foreach(Module::getOrdered() as $module)
        @if(View::exists($module->alias . '::products.edit'))
        <div class="row">
            <div class="col-lg-10 col-lg-offset-1">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title in-white">
                            <i class="fa fa-{{ $module->icon }}"></i>
                            {{ $module->name }}
                        </h3>
                    </div>
                    <div class="panel-body form-padding-right">
                        @includeIf($module->alias . '::products.edit')
                    </div>
                </div>
            </div>
        </div>
        @endif
    @endforeach

    @if (Auth::user()->canCreateOrEdit(ENTITY_PRODUCT, $product))
    <center class="buttons">
        {!! Button::normal(trans('texts.cancel'))->large()->asLinkTo(HTMLUtils::previousUrl('/products'))->appendIcon(Icon::create('remove-circle')) !!}
        {!! Button::success(trans('texts.save'))->submit()->large()->appendIcon(Icon::create('floppy-disk')) !!}
        @if ($product)
            {!! DropdownButton::normal(trans('texts.more_actions'))
                    ->withContents($product->present()->moreActions())
                    ->large()
                    ->dropup() !!}
        @endif
    </center>
    @endif
    {!! Former::close() !!}

    <div id="addPartModal" class="modal fade" role="dialog">
        <div class="modal-dialog">
         <div class="modal-content">
          <div class="modal-header">
            <h4 class="modal-title">Add Part</h4>
                 {{-- <button type="button" class="close" data-dismiss="modal">&times;</button> --}}
               </div>
               <div class="modal-body">
                <form id="addPartForm" method="GET" action="{{asset('products/create')}}" class="form-horizontal">
                 {{ csrf_field() }}
                 {!!Former::hidden('product_raw_id')->value(null)->addClass('product_raw_id') !!}
                 {{-- {!! Former::select('product_raw_material_id')
                    ->addOption('','')
                    ->fromQuery($products, 'raw_material_key', 'id')
                    ->addGroupClass('payment-type-select')->addClass('material_id') !!} --}}

                {!! Former::select('product_raw_material_id')
                ->addOption('', '')
                ->data_bind("dropdown: client, dropdownOptions: {highlighter: comboboxHighlighter}")
                ->addClass('client-input')
                ->addClass('material_id')
                ->addGroupClass('client_select closer-row') !!}

                {{-- {!! Former::text('raw_notes')->addClass('material_notes') !!} --}}
                {!! Former::text('qty')->addClass('material_qty') !!}

      <br>
                  <div class="form-group" align="center">
                   <input type="submit" id="addPartModalSubmitButton" class="btn btn-success" value="Submit" />
                  </div>
                </form>
               </div>
            </div>
           </div>
       </div>

    <script type="text/javascript">

        $(function() {
            $('#product_key').focus();
            $(document).on('click', '.addPart', function(){
                $('#addPartForm')[0].reset(); 
                $('#addPartModal').modal('show');
            });
            $(document).on('click', '.editPart', function(){
                $('#addPartForm')[0].reset(); 
                var id=$(this).attr('id');
                $.ajax({  
                     url:"{{asset('api/raw_products/edit')}}/"+id,  
                     method:"GET",
                     success:function(data){ 
                         console.log(data);
                         $('.product_raw_id').val(id);
                         for (var i=0; i<clients.length; i++) {
                            var client = clients[i];
                            var clientName = client.raw_material_key || '';
                            if (client.public_id === data.raw_material_id) {
                                $('.material_id').val(clientName);
                                $('input[name=product_raw_material_id]').val(client.public_id);
                            }
                        }
                         $('.material_notes').val(data.notes);
                         $('.material_qty').val(data.qty);
                     },
                     error:function(data){
                        console.log(data);
                     }
                }); 
                $('#addPartModal').modal('show');
            });
            var clients = {!! $products !!};
            var clientMap = {};
            var $clientSelect = $('select#product_raw_material_id');
    
            for (var i=0; i<clients.length; i++) {
                var client = clients[i];
                clientMap[client.public_id] = client;
                var clientName = client.raw_material_key || '';
                $clientSelect.append(new Option(clientName, client.public_id));
            }

            var $input = $('select#product_raw_material_id');
            $input.combobox().on('change', function(e) {
                var clientId = parseInt($('input[name=product_raw_material_id]').val(), 10) || 0;
                if (clientId > 0) {
                    var selected = clientMap[clientId];
                    for (var i=0; i<clients.length; i++) {
                        var client = clients[i];
                        var clientName = client.raw_material_key || '';
                        if (client.public_id === selected) {
                            $('.client-input').val(clientName);
                        }
                    }
                }
            });
        });

        function submitAction(action) {
            $('#action').val(action);
            $('.main-form').submit();
        }

        function onDeleteClick() {
            sweetConfirm(function() {
                submitAction('delete');
            });
        }

    </script>

@stop
