@can('supplier.modify', $supplier)
    @if($supplier->remote_lastimport)
        <x-larastrap::suggestion>
            {{ __('texts.supplier.help.import_products_notice') }}
        </x-larastrap::suggestion>
    @endif

    <div class="row">
        <div class="col">
            @include('commons.addingbutton', [
                'template' => 'product.base-edit',
                'typename' => 'product',
                'target_update' => 'product-list-' . $supplier->id,
                'typename_readable' => __('texts.products.name'),
                'targeturl' => 'products',
                'extra' => [
                    'supplier_id' => $supplier->id
                ]
            ])

            @include('commons.importcsv', [
                'modal_id' => 'importCSV' . $supplier->id,
                'import_target' => 'products',
                'modal_extras' => [
                    'supplier_id' => $supplier->id
                ]
            ])

            <x-larastrap::ambutton tlabel="supplier.export_products" :href="route('suppliers.catalogue', ['id' => $supplier->id, 'format' => 'modal'])" />
        </div>
    </div>

    @if($supplier->active_orders->count() != 0)
        <br>
        <div class="alert alert-danger">
            {{ __('texts.supplier.help.handling_products') }}
        </div>
    @endif

    <hr>

    <x-larastrap::tabs>
        <x-larastrap::remotetabpane tlabel="generic.details" active="true" :button_attributes="['data-tab-url' => url('suppliers/' . $supplier->id . '/products')]" icon="bi-zoom-in">
            @include('supplier.products_details', ['supplier' => $supplier])
        </x-larastrap::remotetabpane>

        <x-larastrap::remotetabpane tlabel="generic.fast_modify" :button_attributes="['data-tab-url' => url('suppliers/' . $supplier->id . '/products_grid')]" icon="bi-lightning">
        </x-larastrap::remotetabpane>
    </x-larastrap::tabs>
@else
    @include('supplier.products_details', ['supplier' => $supplier])
@endcan
