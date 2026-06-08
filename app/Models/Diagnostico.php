<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Diagnostico extends Model
{
    use HasFactory;

    protected $table = 'diagnostico';
    protected $primaryKey = 'id_diagnostico';
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'costo',
        'observacion',
        'id_ingreso',
        'id_usuario',
        'id_precio_r',
        'id_pieza',
        'costo_reparacion', //aqui esta variable no la usamos por ahora 
        'fecha_expiracion', //parece que no la estamos usando aqui 
        'gravedad',
        'causa_detectada',
        'solucion',
        'estado',
         'fecha_inicio_revision',
         'fecha_fin_revision',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'fecha_expiracion' => 'datetime',
        'costo' => 'decimal:2',
        'costo_reparacion' => 'decimal:2',
       
        'gravedad' => 'string',
        'estado' => 'string',
          'fecha_inicio_revision' => 'datetime',
            'fecha_fin_revision' => 'datetime',
    ];

    /**
     * Valores permitidos para estado
     */
    public const ESTADOS_DIAGNOSTICO = [
        'ESPERANDO_DIAGNOSTICO',
        'PENDIENTE',
        'ESPERANDO_APROBACION',
        'NO_REPARADO',
        'EN_REPARACION',
        'EN_ESPERA_DE_PIEZAS',
        'LISTO_PARA_RETIRAR'
    ];

    /**
     * Valores permitidos para gravedad
     */
    public const NIVELES_GRAVEDAD = [
        'URGENTE', 
        'MODERADO',
        'LEVE'
    ];

    /**
     * Get the ingreso associated with the diagnostico.
     */
    public function ingreso(): BelongsTo
    {
        return $this->belongsTo(Ingreso_d::class, 'id_ingreso', 'id_ingreso');
    }
    public function pieza()
    {
    return $this->belongsTo(Pieza::class, 'id_pieza', 'id_pieza');
    }
      public function diagnosticosPiezas(): HasMany
    {
        return $this->hasMany(DiagnosticoPieza::class, 'id_diagnostico', 'id_diagnostico');
    }


    /**
     * Get the usuario (tecnico) associated with the diagnostico.
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }

    /**
     * Get the precioReparacion associated with the diagnostico.
     */
    public function precioReparacion(): BelongsTo
    {
        return $this->belongsTo(PrecioReparacion::class, 'id_precio_r', 'id_precio_reparacion');
    }

    /**
     * Get the reparacion associated with the diagnostico.
     */
    public function reparacion(): HasOne
    {
        return $this->hasOne(Reparacion::class, 'id_diagnostico', 'id_diagnostico');
    }
      /**
     * RELACIÓN: Obtener todas las piezas a través de la tabla pivote
     */
     public function piezas(): BelongsToMany
    {
        return $this->belongsToMany(
            Pieza::class,                          // Modelo destino
            'diagnostico_pieza',                   // Tabla pivote
            'id_diagnostico',                      // FK local en pivote
            'id_pieza'                             // FK destino en pivote
        )->withPivot(
            'id_diagnostico_pieza',                // ← IMPORTANTE: ID único de la relación
            'estado', 
            'costo', 
            'comentario',
            'fecha_aprobacion',
            'fecha_rechazo'
        );
    }
      public function diagnosticoPiezas(): HasMany
    {
        return $this->hasMany(DiagnosticoPieza::class, 'id_diagnostico', 'id_diagnostico');
    }
    public function getPiezasAprobadasAttribute()
    {
        return $this->piezas->where('pivot.estado', 'APROBADO');
    }
    
    /**
     * Obtener solo las piezas pendientes
     */
    public function getPiezasPendientesAttribute()
    {
        return $this->piezas->where('pivot.estado', 'PENDIENTE');
    }
    
    /**
     * Obtener solo las piezas rechazadas
     */
    public function getPiezasRechazadasAttribute()
    {
        return $this->piezas->where('pivot.estado', 'RECHAZADO');
    }
    
    /**
     * Calcular costo total de piezas aprobadas
     */
    public function getCostoTotalPiezasAttribute()
    {
        return $this->piezas
            ->where('pivot.estado', 'APROBADO')
            ->sum('pivot.costo');
    }
    
    /**
     * Verificar si todas las piezas están aprobadas
     */
    public function todasPiezasAprobadas(): bool
    {
        if ($this->piezas->count() === 0) return false;
        return $this->piezas->every(fn($p) => $p->pivot->estado === 'APROBADO');
    }

    
    /**
     * RELACIÓN: Solo las piezas aprobadas
     */
    public function piezasAprobadas()
    {
        return $this->belongsToMany(Pieza::class, 'diagnostico_pieza', 'id_diagnostico', 'id_pieza')
                    ->wherePivot('estado', 'aprobado')
                    ->withPivot('costo', 'comentario');
    }
    
    /**
     * RELACIÓN: Solo las piezas rechazadas
     */
    public function piezasRechazadas()
    {
        return $this->belongsToMany(Pieza::class, 'diagnostico_pieza', 'id_diagnostico', 'id_pieza')
                    ->wherePivot('estado', 'rechazado')
                    ->withPivot('costo', 'comentario');
    }
    
    /**
     * RELACIÓN: Calcular costo total del diagnóstico
     */
    public function getCostoTotalAttribute()
    {
        return $this->diagnosticosPiezas()
                    ->where('estado', 'aprobado')
                    ->sum('costo');
    }


    /**
     * Scope para diagnosticos por estado.
     */
    public function scopePorEstado($query, $estado)
    {
        return $query->where('estado', $estado);
    }

    /**
     * Scope para diagnosticos por gravedad.
     */
    public function scopePorGravedad($query, $gravedad)
    {
        return $query->where('gravedad', $gravedad);
    }

    /**
     * Scope para diagnosticos pendientes.
     */
    public function scopePendientes($query)
    {
        return $query->where('estado', 'ESPERANDO_DIAGNOSTICO');
    }

    /**
     * Scope para diagnosticos completados.
     */
    public function scopeCompletados($query)
    {
        return $query->where('estado', 'LISTO_PARA_RETIRAR');
    }

    /**
     * Scope para diagnosticos por usuario (tecnico).
     */
    public function scopePorUsuario($query, $usuarioId)
    {
        return $query->where('id_usuario', $usuarioId);
    }

    /**
     * Scope para diagnosticos por ingreso.
     */
    public function scopePorIngreso($query, $ingresoId)
    {
        return $query->where('id_ingreso', $ingresoId);
    }
    public function getHorasRevisionAttribute()
{
    if (!$this->fecha_inicio_revision || !$this->fecha_fin_revision) {
        return null;
    }
    
    return round($this->fecha_fin_revision->diffInHours($this->fecha_inicio_revision), 1);
}

// Accesor para calcular días de revisión
public function getDiasRevisionAttribute()
{
    if (!$this->fecha_inicio_revision || !$this->fecha_fin_revision) {
        return null;
    }
    
    return round($this->fecha_fin_revision->diffInDays($this->fecha_inicio_revision), 1);
}
}