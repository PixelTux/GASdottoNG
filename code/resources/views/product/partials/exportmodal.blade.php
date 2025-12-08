<x-larastrap::modal classes="close-on-submit">
    <x-larastrap::form method="GET" :action="$route" :buttons="[['tlabel' => 'generic.download', 'type' => 'submit']]">
        <p>{!! __('texts.export.help_csv_libreoffice') !!}</p>

        <hr/>

        <x-larastrap::structchecks name="fields" tlabel="export.data.columns" :options="App\Formatters\Product::formattableColumns()" />

        <x-larastrap::radios name="format" tlabel="export.data.format" :options="[
            'pdf' => __('texts.export.data.formats.pdf'),
            'csv' => __('texts.export.data.formats.csv'),
            'gdxp' => __('texts.export.data.formats.gdxp'),
        ]" value="pdf" />
    </x-larastrap::form>
</x-larastrap::modal>
