<?php

namespace App\Services\Concerns;

use Barryvdh\DomPDF\Facade\Pdf;

use App\Formatters\Product as ProductFormatter;

trait ExportsCatalogue
{
    private function realCatalogue($subject, $format, array $request)
    {
        $this->ensureAuth();

        $format = $format ?: $request['format'];

        if ($format == 'gdxp') {
            return redirect($subject->exportableURL());
        }
        else if ($format == 'modal') {
            return view('product.partials.exportmodal', [
                'route' => $subject->catalogueExportURL(),
            ]);
        }

        /*
            Questi sono i dati di default, da usare quando si fa il download del
            listino come allegato al fornitore (e dunque non si passa per il
            pannello di selezione dei campi).
            Cfr. Supplier::defaultAttachments()
        */
        $fields = $request['fields'] ?? ['name', 'measure', 'price', 'active'];
        $headers = ProductFormatter::getHeaders($fields);
        $filename = sanitizeFilename(__('texts.export.products_list_filename', [
            'supplier' => $subject->printableName(),
            'format' => $format,
        ]));

        $products = $subject->products()->sorted()->get();
        $data = ProductFormatter::formatArray($products, $fields);

        if ($format == 'pdf') {
            $pdf = Pdf::loadView('documents.cataloguepdf', [
                'subject' => $subject,
                'headers' => $headers,
                'data' => $data,
            ]);

            return $pdf->download($filename);
        }
        elseif ($format == 'csv') {
            return output_csv($filename, $headers, $data, null);
        }
    }

    public function catalogue($id, $format, array $request)
    {
        $subject = $this->show($id);
        return $this->realCatalogue($subject, $format, $request);
    }
}
