<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pieza extends Model
{
    protected $table = 'Pieza';
    protected $primaryKey = 'id_pieza';
    public $timestamps = false;
}
