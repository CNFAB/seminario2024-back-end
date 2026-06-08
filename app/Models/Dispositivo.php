<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Ingreso_d;
class Dispositivo extends Model
{
    protected $table = 'dispositivo';
    protected $primaryKey = 'id_dispositivo';
    public $timestamps = false;

    protected $fillable = [
        'id_cliente',
        'id_modelo',
        'imei',
        'codigo_interno'
    ];

    // Relación con Cliente
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'id_cliente');
    }

    // Relación con Modelo
    public function modelo(): BelongsTo
    {
        return $this->belongsTo(Modelo::class, 'id_modelo');
    }
    public function ingresos(): HasMany
{
    return $this->hasMany(Ingreso_d::class, 'id_dispositivo', 'id_dispositivo');
}

    // generacion de codigo interno
    public static function generarCodigoInterno($idModelo)
    {
        $modelo = Modelo::with('marca')->find($idModelo);
        
        if (!$modelo) {
            throw new \Exception('Modelo no encontrado para generar código');
        }

        // Obtener prefijo de la marca (ej: SAMSUNG → SAM)
        $prefijo = strtoupper(substr($modelo->marca->marca, 0, 3));
        
        // Buscar el último código con este prefijo
        $ultimoCodigo = self::where('codigo_interno', 'like', $prefijo . '-%')
            ->orderBy('id_dispositivo', 'desc')
            ->first();

        // Generar número consecutivo
        $consecutivo = $ultimoCodigo ? 
            (int) substr($ultimoCodigo->codigo_interno, -3) + 1 : 1;

        // Formato: MAR-001, MAR-002, etc.
        return $prefijo . '-' . str_pad($consecutivo, 3, '0', STR_PAD_LEFT);
    }

    // Scope para dispositivos con IMEI
    public function scopeConImei($query)
    {
        return $query->whereNotNull('imei');
    }

    // Scope para dispositivos sin IMEI  
    public function scopeSinImei($query)
    {
        return $query->whereNull('imei');
    }

    // Scope para buscar por código interno
    public function scopePorCodigoInterno($query, $codigo)
    {
        return $query->where('codigo_interno', $codigo);
    }
}