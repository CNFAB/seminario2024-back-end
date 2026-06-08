<?php
namespace App\Http\Requests\Testimonio;
use Illuminate\Foundation\Http\FormRequest;

class StoreTestimonioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('cliente')->check();
    }

    public function rules(): array
    {
        return [
            'id_reparacion' => [
                'required',
                'integer',
                'exists:reparacion,id_reparacion',
                function ($attribute, $value, $fail) {
                    // ✅ FIX: usar guard 'cliente' igual que en authorize()
                    $clienteId = auth('cliente')->id() 
                        ?? \Tymon\JWTAuth\Facades\JWTAuth::parseToken()->getPayload()->get('sub');

                    $reparacion = \App\Models\Reparacion::where('id_reparacion', $value)
                        ->where(function($q) use ($clienteId) {
                            $q->whereHas('ingreso.dispositivo', fn($q) => $q->where('id_cliente', $clienteId))
                              ->orWhereHas('diagnostico.ingreso.dispositivo', fn($q) => $q->where('id_cliente', $clienteId));
                        })
                        ->first();

                    if (!$reparacion) {
                        $fail('Esta reparación no te pertenece.');
                        return;
                    }

                    if ($reparacion->estado !== 'TERMINADO') {
                        $fail('Solo puedes calificar reparaciones terminadas.');
                        return;
                    }

                    $ingreso = $reparacion->id_ingreso
                        ? $reparacion->ingreso
                        : $reparacion->diagnostico?->ingreso;

                    if ($ingreso && $ingreso->estado !== \App\Models\Ingreso_d::ESTADO_RETIRADO) {
                        $fail('Solo puedes calificar después de retirar tu dispositivo.');
                    }
                },
            ],
            'calificacion_estrella' => 'required|integer|min:1|max:5',
            'comentario' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'id_reparacion.required' => 'Debes seleccionar una reparación.',
            'id_reparacion.exists' => 'La reparación no existe.',
            'calificacion_estrella.required' => 'Por favor, selecciona una calificación de 1 a 5 estrellas.',
            'calificacion_estrella.min' => 'La calificación mínima es 1 estrella.',
            'calificacion_estrella.max' => 'La calificación máxima es 5 estrellas.',
            'comentario.max' => 'El comentario no puede superar los 500 caracteres.',
        ];
    }
}