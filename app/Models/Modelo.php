<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Modelo extends Model
{
    protected $table = 'modelo';
    protected $primaryKey = 'id_modelo';
    public $timestamps = false;

    protected $fillable =[
        'id_marca',
        'nombre_modelo',
        'ram',
        'almacenamiento',
        'procesador',
        'pantalla',
        'bateria',
    ];
     protected $casts = [
        'ram' => 'integer',
        'almacenamiento' => 'integer',
        'bateria' => 'integer',
      ];

    public function marca(){
        return $this->belongsTo(Marca:: class,'id_marca');
         
    }   
    public function dispositivos(){
        return $this->hasMany(Dispositivo::class,'id_modelo','id_modelo');
    }
    public function ultimoIngreso()
    {
    return $this->hasOne(Ingreso::class, 'id_dispositivo')
                ->latest('fecha_ingreso');
    }
    public function ingresos()
    {
    return $this->hasMany(Ingreso::class, 'id_dispositivo')
                ->orderBy('fecha_ingreso', 'desc');
    }
    public function piezas() {
    return $this->belongsToMany(Pieza::class, 'compatibilidad', 'id_modelo', 'id_pieza')
                ->withPivot('descripcion');
    }
    public function compatibilidades() {
    return $this->hasMany(Compatibilidad::class, 'id_modelo', 'id_modelo');
    }
    public function getEspecificacionesCompletasAttribute()
    {
        $specs = [];
        
        if ($this->ram) {
            $specs[] = "{$this->ram}GB RAM";
        }
        if ($this->almacenamiento) {
            $specs[] = "{$this->almacenamiento}GB";
        }
        if ($this->procesador) {
            $specs[] = $this->procesador;
        }
        if ($this->pantalla) {
            $specs[] = $this->pantalla;
        }
        if ($this->bateria) {
            $specs[] = "{$this->bateria}mAh";
        }
        
        return implode(' • ', $specs);
    }

    public function getNombreCompletoAttribute()
    {
        $nombre = $this->nombre_modelo;
        if ($this->modelo_especifico) {
            $nombre .= " ({$this->modelo_especifico})";
        }
        if ($this->ram && $this->almacenamiento) {
            $nombre .= " - {$this->ram}/{$this->almacenamiento}";
        }
        return $nombre;
    }

    public function scopeConEspecificaciones($query)
    {
        return $query->select('id_modelo', 'nombre_modelo', 'modelo_especifico', 'ram', 'almacenamiento', 'procesador', 'pantalla', 'bateria');
    }

}
