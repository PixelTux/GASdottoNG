<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Services\ModifierTypesService;
use App\Exceptions\AuthException;

use App\ModifierType;

class ModifierTypesController extends BackedController
{
    public function __construct(ModifierTypesService $service)
    {
        $this->middleware('auth');

        $this->commonInit([
            'reference_class' => 'App\\ModifierType',
            'endpoint' => 'modtypes',
            'service' => $service
        ]);
    }

    public function show(Request $request, $id)
    {
        try {
            $mt = $this->service->show($id);
            return view('modifiertype.edit', ['modtype' => $mt]);
        }
        catch (AuthException $e) {
            abort($e->status());
        }
    }

    public function search(Request $request)
    {
        $modtype = ModifierType::find($request->input('modifiertype'));
        $startdate = decodeDate($request->input('startdate'));
        $enddate = decodeDate($request->input('enddate'));

        return view('modifiertype.valuestable', [
            'startdate' => $startdate,
            'enddate' => $enddate,
            'modifiers' => $modtype->modifiers->pluck('id')
        ]);
    }
}
