<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Categoria extends Model
{
    use HasFactory;

    protected $table = 'categoria';
    protected $primaryKey = 'id_categoria';
    public $timestamps = false;
    
    protected $fillable = [
        'categoria',
        'mano_obra',
        'garantia_dias',
    ];
    
    protected $casts = [
        'mano_obra' => 'decimal:2',
        'garantia_dias' => 'integer',
    ];

    // ==========================================
    // ACCESORS
    // ==========================================
    
    /**
     * Accesor para obtener la categoría en mayúsculas.
     */
    public function getCategoriaMayusculaAttribute()
    {
        return strtoupper($this->categoria);
    }

    /**
     * Accesor para obtener el tipo de categoría.
     */
    public function getTipoAttribute()
    {
        return $this->mano_obra ? 'Mano de Obra' : 'Material';
    }

    /**
     * Accesor: meses de garantía (para mostrar más amigable)
     */
    public function getMesesGarantiaAttribute()
    {
        return round($this->garantia_dias / 30, 1);
    }

    /**
     * Accesor: texto legible de garantía (ej: "6 meses")
     */
    public function getGarantiaTextoAttribute()
    {
        if ($this->garantia_dias >= 365) {
            $años = round($this->garantia_dias / 365, 1);
            return "{$años} año" . ($años != 1 ? 's' : '');
        }
        
        $meses = round($this->garantia_dias / 30, 1);
        return "{$meses} mes" . ($meses != 1 ? 'es' : '');
    }

    // ==========================================
    // RELACIONES
    // ==========================================

    /**
     * Relación con Piezas
     */
    public function piezas(): HasMany
    {
        return $this->hasMany(Pieza::class, 'id_categoria', 'id_categoria');
    }
    
    /**
     * Relación con PrecioReparacion
     */
    public function precioReparaciones()
    {
        return $this->hasMany(PrecioReparacion::class, 'id_categoria', 'id_categoria');
    }

    /**
     * Relación con GarantiaPieza (NUEVA)
     * Una categoría tiene muchas garantías por pieza
     */
    public function garantiaPiezas(): HasMany
    {
        return $this->hasMany(GarantiaPieza::class, 'id_categoria', 'id_categoria');
    }

    // ==========================================
    // SCOPES
    // ==========================================

    /**
     * Scope: categorías con garantía activa (días > 0)
     */
    public function scopeConGarantia($query)
    {
        return $query->where('garantia_dias', '>', 0);
    }

    /**
     * Scope: buscar por nombre
     */
    public function scopePorNombre($query, $nombre)
    {
        return $query->where('categoria', 'LIKE', "%{$nombre}%");
    }

    // ==========================================
    // MUTATORS
    // ==========================================

    /**
     * Mutator: asegurar que garantia_dias sea positivo
     */
    public function setGarantiaDiasAttribute($value)
    {
        $this->attributes['garantia_dias'] = max(0, (int) $value);
    }
}