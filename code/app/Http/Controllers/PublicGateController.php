<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Printers\Order as OrderPrinter;
use App\Order;

class PublicGateController extends Controller
{
    public function show(Request $request, $token, $action)
    {
        try {
            $req = publicGateRetrieveLink($token);

            switch($req->t) {
                case 'odoc':
                    $order_id = $req->p->id;
                    $order = Order::find($order_id);

                    switch($action) {
                        case 'show':
                            $links = [];
                            $type = $order->supplier->notify_on_close_enabled;

                            if ($type == 'shipping_summary') {
                                $types = ['shipping', 'summary'];
                            }
                            else {
                                $types = [$type];
                            }

                            foreach($types as $t) {
                                $base_filename = __('texts.orders.files.order.' . $t);
                                $links[$base_filename . ' - CSV'] = publicGateGetLink('order_documents', $t . '_csv', ['id' => $order->id]);
                                $links[$base_filename . ' - PDF'] = publicGateGetLink('order_documents', $t . '_pdf', ['id' => $order->id]);
                            }

                            return view('public.order_documents', [
                                'order' => $order,
                                'links' => $links,
                            ]);

                            break;

                        default:
                            $printer = new OrderPrinter();
                            list($type, $format) = explode('_', $action);

                            return $printer->document($order, $type, [
                                'format' => $format,
                                'status' => 'pending',
                                'extra_modifiers' => 0,
                                'action' => 'download',
                            ]);

                            break;
                    }
                    break;

                default:
                    throw new \Exception('Richiesta ' . $req->t . ' non riconosciuta');
                    break;
            }
        }
        catch(\Exception $e) {
            \Log::error('Token non valido per accesso pubblico: ' . $token . ' - ' . $e->getMessage());
            abort(404);
        }
    }
}
