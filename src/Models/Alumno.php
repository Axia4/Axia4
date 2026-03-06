<?php

namespace Axia4\Models;

/**
 * Alumno model (Eloquent ORM)
 *
 * Represents a student (alumno) in an aulario.
 * Table: axia4_alumnos
 */
class Alumno extends \ADIOS\Core\Model
{
    protected $table = 'axia4_alumnos';

    protected $fillable = [
        'aulario_id',
        'username',
        'display_name',
        'photo_path',
        'data',
    ];

    protected $casts = [
        'data' => 'array',
    ];

    public function columns(): array
    {
        return [
            'aulario_id' => [
                'type'  => 'lookup',
                'title' => 'Aulario',
                'model' => Aulario::class,
            ],
            'username' => [
                'type'  => 'varchar',
                'title' => 'Nombre de usuario',
            ],
            'display_name' => [
                'type'     => 'varchar',
                'title'    => 'Nombre para mostrar',
                'required' => true,
            ],
            'photo_path' => [
                'type'  => 'varchar',
                'title' => 'Ruta de la foto',
            ],
            'data' => [
                'type'  => 'json',
                'title' => 'Datos adicionales',
            ],
        ];
    }

    public function aulario()
    {
        return $this->belongsTo(Aulario::class, 'aulario_id');
    }
}
