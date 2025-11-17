<x-ls::card header="generic.contacts">
    @include('commons.manyrows', [
        'contents' => $obj ? $obj->contacts : [],
        'extra_class' => 'contacts-selection',
        'columns' => [
            [
                'label' => __('texts.generic.id'),
                'field' => 'id',
                'type' => 'hidden',
                'extra' => [
                    'nprefix' => 'contact_'
                ]
            ],
            [
                'label' => __('texts.generic.type'),
                'field' => 'type',
                'type' => 'select',
                'extra' => [
                    'nprefix' => 'contact_',
                    'options' => App\Contact::types()
                ]
            ],
            [
                'label' => __('texts.generic.value'),
                'field' => 'value',
                'type' => 'text',
                'extra' => [
                    'nprefix' => 'contact_'
                ]
            ]
        ]
    ])
</x-ls::card>
