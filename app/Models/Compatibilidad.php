<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Compatibilidad extends Model
{
    protected $table = 'compatibilidad';
    protected $primaryKey = 'id_compatible';
    public $timestamps = false;

    protected $fillable = [
        'id_pieza',
        'id_modelo',
        'descripcion'
    ];

    // Relaciones
    public function pieza()
    {
        return $this->belongsTo(Pieza::class, 'id_pieza', 'id_pieza');
    }

    public function modelo()
    {
        return $this->belongsTo(Modelo::class, 'id_modelo', 'id_modelo');
    }
}