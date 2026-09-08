<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Note extends Model
{
    // Comme ca on peut tout remplir d'un coup, c'est plus pratique.
    protected $guarded = [];
}
