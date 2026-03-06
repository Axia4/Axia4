<?php

namespace Axia4\Models;

/**
 * Aulario model (Eloquent ORM)
 *
 * Represents a classroom resource hub (aulario) belonging to a Centro.
 * Table: axia4_aularios
 */
class Aulario extends \ADIOS\Core\Model
{
    protected $table = 'axia4_aularios';

    protected $fillable = [
        'centro_id',
        'slug',
        'name',
        'config',
    ];

    protected $casts = [
        'config' => 'array',
    ];

    public function columns(): array
    {
        return [
            'centro_id' => [
                'type'  => 'lookup',
                'title' => 'Centro',
                'model' => Centro::class,
            ],
            'slug' => [
                'type'     => 'varchar',
                'title'    => 'Identificador (slug)',
                'required' => true,
            ],
            'name' => [
                'type'     => 'varchar',
                'title'    => 'Nombre del aulario',
                'required' => true,
            ],
            'config' => [
                'type'  => 'json',
                'title' => 'Configuración',
            ],
        ];
    }

    public function centro()
    {
        return $this->belongsTo(Centro::class, 'centro_id');
    }

    public function alumnos()
    {
        return $this->hasMany(Alumno::class, 'aulario_id');
    }
}
