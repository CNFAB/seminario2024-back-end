<?php
namespace App\Http\Controllers;

use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\Client\Payment\PaymentClient;
use Illuminate\Http\Request;
use App\Models\Pago;
use App\Models\ReparacionMultiple;
use App\Models\Reparacion;

class PagoController extends Controller
{
    public function crearPreferencia(Request $request)
    {
        MercadoPagoConfig::setAccessToken(env('MP_ACCESS_TOKEN'));

        $client     = new PreferenceClient();
        $preference = $client->create([
            "items" => [[
                "title"      => $request->input('descripcion', 'Reparación de dispositivo'),
                "quantity"   => 1,
                "unit_price" => (float) $request->input('monto', 0),
            ]],
            "back_urls" => [
                "success" => env('FRONTEND_URL') . "/cliente/dashboard?pago=success",
                "failure" => env('FRONTEND_URL') . "/cliente/dashboard?pago=failure",
                "pending" => env('FRONTEND_URL') . "/cliente/dashboard?pago=pending",
            ],
            "auto_return"        => "approved",
            "external_reference" => (string) $request->input('id_reparacion'),
        ]);

        Pago::create([
            'id_reparacion'    => $request->input('id_reparacion'),
            'mp_preference_id' => $preference->id,
            'estado'           => 'PENDIENTE',
            'monto'            => $request->input('monto'),
            'descripcion'      => $request->input('descripcion'),
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        return response()->json([
            "id"         => $preference->id,
            "init_point" => $preference->sandbox_init_point,
        ]);
    }

    public function confirmarPago(Request $request)
    {
        $preferenceId = $request->input('preference_id');
        $pago = Pago::where('mp_preference_id', $preferenceId)->first();

        if (!$pago) {
            return response()->json(['error' => 'Pago no encontrado'], 404);
        }

        $pago->update([
            'estado'     => 'EN_PROCESO',
            'fecha_pago' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['status' => 'ok', 'pago' => $pago]);
    }

    public function webhook(Request $request)
    {
        $type = $request->input('type') ?? $request->input('topic');

        if ($type !== 'payment') {
            return response()->json(['status' => 'ignored']);
        }

        MercadoPagoConfig::setAccessToken(env('MP_ACCESS_TOKEN'));

        $paymentId     = $request->input('data.id') ?? $request->input('id');
        $paymentClient = new PaymentClient();
        $payment       = $paymentClient->get($paymentId);

        $estado = match($payment->status) {
            'approved' => 'APROBADO',
            'rejected' => 'RECHAZADO',
            default    => 'EN_PROCESO',
        };

        $pago = Pago::where('mp_preference_id', $payment->preference_id)->first();

        if ($pago) {
            $pago->update([
                'mp_payment_id' => $paymentId,
                'estado'        => $estado,
                'fecha_pago'    => now(),
                'updated_at'    => now(),
            ]);

            if ($estado === 'APROBADO') {
                $this->actualizarEstadosReparacion($pago);
            }
        }

        return response()->json(['status' => 'ok']);
    }

    public function verificarPorPreferencia(Request $request)
    {
        $preferenceId = $request->input('preference_id');
        $paymentId    = $request->input('payment_id');

        if (!$preferenceId) {
            return response()->json(['error' => 'preference_id requerido'], 400);
        }

        $pago = Pago::where('mp_preference_id', $preferenceId)->first();
        if (!$pago) {
            return response()->json(['error' => 'Pago no encontrado'], 404);
        }

        if ($pago->estado === 'APROBADO') {
            return response()->json(['estado' => 'APROBADO', 'pago' => $pago]);
        }

        if ($paymentId) {
            MercadoPagoConfig::setAccessToken(env('MP_ACCESS_TOKEN'));
            $paymentClient = new PaymentClient();
            $payment       = $paymentClient->get($paymentId);

            if ($payment->status === 'approved') {
                $pago->update([
                    'estado'        => 'APROBADO',
                    'mp_payment_id' => $paymentId,
                    'fecha_pago'    => now(),
                    'updated_at'    => now(),
                ]);

                $this->actualizarEstadosReparacion($pago);
            }
        }

        return response()->json([
            'estado' => $pago->fresh()->estado,
            'pago'   => $pago->fresh()
        ]);
    }

    // ==================== MÉTODO PRIVADO ====================

   private function actualizarEstadosReparacion(Pago $pago): void
{
    $idReparacion = $pago->id_reparacion;

    if (!$idReparacion) return;

    // 1. Todas las piezas → estado_pago = PAGADO
    ReparacionMultiple::where('id_reparacion', $idReparacion)
        ->update([
            'estado_pago' => 'PAGADO',
            'updated_at'  => now(),
        ]);

    // 2. Reparación padre → estado_pago = PAGADO (NO tocar estado)
    Reparacion::where('id_reparacion', $idReparacion)
        ->update([
            'estado_pago' => 'PAGADO',
            'updated_at'  => now(),
        ]);
}
}