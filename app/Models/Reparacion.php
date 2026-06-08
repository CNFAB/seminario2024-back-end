<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;  // 👈 AGREGAR ESTO
use Illuminate\Database\Eloquent\Relations\HasMany;    // 👈 AGREGAR ESTO

class Reparacion extends Model
{
    use HasFactory;
    
    protected $table = 'reparacion';
    protected $primaryKey = 'id_reparacion';
    public $timestamps = true;
const ESTADO_PAGO_PENDIENTE    = 'PENDIENTE';
const ESTADO_PAGO_PAGADO       = 'PAGADO';
const ESTADO_PAGO_PAGADO_LOCAL = 'PAGADO_LOCAL';

    protected $fillable = [
        'id_diagnostico',
        'id_usuario',
        'id_ingreso',
        'comentario',
        'estado',
        'created_at',  
        'updated_at',
        'estado_pago',
        'es_garantia' 
    ];
    protected $casts = [
    'estado_pago' => 'string',
    'es_garantia' => 'boolean',
];
    
    protected $appends = ['estado_general'];

    // ==================== RELACIONES ====================
    
    public function diagnostico(): BelongsTo
    {
        return $this->belongsTo(Diagnostico::class, 'id_diagnostico', 'id_diagnostico');
    }
    
    public function tecnico(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }
    
    public function ingreso(): BelongsTo
    {
        return $this->belongsTo(Ingreso_d::class, 'id_ingreso', 'id_ingreso');
    }
    
    
    public function reparacionesMultiples(): HasMany
    {
        return $this->hasMany(ReparacionMultiple::class, 'id_reparacion', 'id_reparacion');
    }
    public function testimonio()
    {
    return $this->hasOne(Testimonio::class, 'id_reparacion', 'id_reparacion');
    }

    public function getDispositivoAttribute()
    {
        // Verificar que exista diagnóstico e ingreso
        if ($this->diagnostico && $this->diagnostico->ingreso) {
            return $this->diagnostico->ingreso->dispositivo;
        }
        return null;
    }

    public function dispositivo()
    {
        // Para reparaciones CON diagnóstico
        if ($this->id_diagnostico) {
            return $this->diagnostico->ingreso()->dispositivo();
        }
        
        // Para reparaciones DIRECTAS (sin diagnóstico)
        if ($this->id_ingreso) {
            return $this->ingreso()->dispositivo();
        }
        
        return null;
    }

    // ==================== ACCESORES ====================
    
    public function getEstadoGeneralAttribute(): string
    {
        // Si tiene estado real en BD, usarlo
        if (!empty($this->attributes['estado'])) {
            return $this->attributes['estado'];
        }

        // Fallback: calcular desde las piezas
        if (!$this->relationLoaded('reparacionesMultiples')) {
            return 'SIN_REPARACIONES';
        }

        $multiples = $this->reparacionesMultiples;

        if ($multiples->isEmpty()) {
            return 'SIN_REPARACIONES';
        }

        $total      = $multiples->count();
        $terminadas = $multiples->where('estado', 'TERMINADO')->count();

        if ($terminadas === $total)                                          return 'TERMINADO';
        if ($multiples->where('estado', 'ESPERANDO_PIEZA')->count() > 0)    return 'EN_ESPERA_DE_PIEZAS';
        if ($multiples->where('estado', 'EN_REPARACION')->count() > 0)      return 'EN_REPARACION';
        if ($multiples->where('estado', 'PENDIENTE')->count() === $total)   return 'PENDIENTE';

        return 'EN_PROCESO';
    }

    // ==================== HELPERS ====================
    
    public function tieneDiagnostico(): bool
    {
        return !is_null($this->id_diagnostico);
    }

    public function tieneIngreso(): bool
    {
        return !is_null($this->id_ingreso);
    }

    /**
     * Verificar si la reparación tiene garantía activa
     */
    public function tieneGarantiaActiva(): bool
    {
        return $this->garantia()->where('estado', 'ACTIVA')
            ->where('fecha_fin', '>=', now())
            ->exists();
    }

    /**
     * Obtener garantía activa
     */
    public function garantiaActiva()
    {
        return $this->garantia()
            ->where('estado', 'ACTIVA')
            ->where('fecha_fin', '>=', now())
            ->first();
    }
    
    public function scopeComercial($query)
        {
            return $query->where('es_garantia', false);
        }

// Scope para reparaciones de garantía
    public function scopeGarantia($query)
        {
            return $query->where('es_garantia', true);
        }
    }