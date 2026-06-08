<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pieza extends Model
{
    use HasFactory;
    protected $table = 'pieza';
    protected $primaryKey = 'id_pieza';
    public $timestamps = false;
    // Campos asignables
    protected $fillable = [
        'nombre_pieza',
        'stock',
        'precio',
        'id_categoria'
    ];

    protected $casts = [
        'stock' => 'integer',
        'precio' => 'decimal:2',
        'id_categoria' => 'integer'
    ];

    /**
     * RELACIÓN: Una pieza pertenece a una categoría
     */
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'id_categoria', 'id_categoria');
    }

    /**
     * RELACIÓN: Una pieza puede estar en muchas reparaciones
     */
     public function diagnosticosPiezas(): HasMany
    {
        return $this->hasMany(DiagnosticoPieza::class, 'id_pieza', 'id_pieza');
    }
    public function reparaciones(): HasMany
    {
        return $this->hasMany(Reparacion::class, 'id_pieza', 'id_pieza');
    }
    public function modelos() {
    return $this->belongsToMany(Modelo::class, 'compatibilidad', 'id_pieza', 'id_modelo')
                ->withPivot('descripcion')
                ->withTimestamps();
    }
   public function compatibilidades() {
    return $this->hasMany(Compatibilidad::class, 'id_pieza', 'id_pieza');
    }
    /**
     * SCOPE: Piezas con stock disponible
     */
    public function scopeConStock($query)
    {
        return $query->where('stock', '>', 0);
    }

    /**
     * SCOPE: Piezas sin stock
     */
    public function scopeSinStock($query)
    {
        return $query->where('stock', '<=', 0);
    }

    /**
     * SCOPE: Piezas por categoría
     */
    public function scopeDeCategoria($query, $categoriaId)
    {
        return $query->where('id_categoria', $categoriaId);
    }

    /**
     * SCOPE: Piezas con precio mayor a
     */
    public function scopePrecioMayorA($query, $precio)
    {
        return $query->where('precio', '>', $precio);
    }

    /**
     * SCOPE: Piezas con precio menor a
     */
    public function scopePrecioMenorA($query, $precio)
    {
        return $query->where('precio', '<', $precio);
    }

    /**
     * SCOPE: Buscar por nombre
     */
    public function scopeBuscarPorNombre($query, $nombre)
    {
        return $query->where('nombre_pieza', 'ILIKE', "%{$nombre}%");
    }

    /**
     * MÉTODO: Verificar si tiene stock
     */
    public function tieneStock(): bool
    {
        return $this->stock > 0;
    }

    /**
     * MÉTODO: Disminuir stock
     */
    public function disminuirStock($cantidad = 1): bool
    {
        if ($this->stock >= $cantidad) {
            $this->stock -= $cantidad;
            return $this->save();
        }
        return false;
    }

    /**
     * MÉTODO: Aumentar stock
     */
    public function aumentarStock($cantidad = 1): bool
    {
        $this->stock += $cantidad;
        return $this->save();
    }

    /**
     * MÉTODO: Obtener el nombre de la categoría (acceso rápido)
     */
    public function getNombreCategoriaAttribute()
    {
        return $this->categoria ? $this->categoria->nombre_categoria : 'Sin categoría';
    }

    /**
     * MÉTODO: Calcular valor total del inventario de esta pieza
     */
    public function getValorInventarioAttribute()
    {
        return $this->stock * $this->precio;
    }

    /**
     * MÉTODO: Verificar si es una pieza cara (más de $100)
     */
    public function getEsCaraAttribute()
    {
        return $this->precio > 100;
    }

    /**
     * MÉTODO: Formatear precio para mostrar
     */
    public function getPrecioFormateadoAttribute()
    {
        return '$' . number_format($this->precio, 2);
    }
     public function diagnosticos(): BelongsToMany
    {
        return $this->belongsToMany(
            Diagnostico::class, 
            'diagnostico_pieza', 
            'id_pieza', 
            'id_diagnostico'
        )->withPivot('estado', 'costo', 'fecha_aprobacion', 'fecha_rechazo', 'comentario');
    }
    
    /**
     * RELACIÓN: Solo diagnósticos donde esta pieza fue aprobada
     */
    public function diagnosticosDondeAprobada()
    {
        return $this->belongsToMany(Diagnostico::class, 'diagnostico_pieza', 'id_pieza', 'id_diagnostico')
                    ->wherePivot('estado', 'aprobado')
                    ->withPivot('costo', 'comentario');
    }
    
    /**
     * MÉTODO: Verificar si esta pieza fue usada en un diagnóstico específico
     */
    public function fueUsadaEnDiagnostico($id_diagnostico)
    {
        return $this->diagnosticosPiezas()
                    ->where('id_diagnostico', $id_diagnostico)
                    ->exists();
    }


    /**
     * MÉTODO: Obtener información completa de la pieza
     */
    public function informacionCompleta()
    {
        return [
            'id' => $this->id_pieza,
            'nombre' => $this->nombre_pieza,
            'stock' => $this->stock,
            'precio' => $this->precio,
            'precio_formateado' => $this->precio_formateado,
            'categoria' => $this->nombre_categoria,
            'tiene_stock' => $this->tieneStock(),
            'valor_inventario' => $this->valor_inventario,
            'es_cara' => $this->es_cara
        ];
    }
}