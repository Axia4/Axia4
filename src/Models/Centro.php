<?php

namespace Axia4\Models;

/**
 * Centro model (Eloquent ORM)
 *
 * Represents an educational centre (centro educativo) in EntreAulas.
 * Table: axia4_centros
 */
class Centro extends \ADIOS\Core\Model
{
    protected $table = 'axia4_centros';

    protected $fillable = [
        'slug',
        'name',
        'description',
        'config',
    ];

    protected $casts = [
        'config' => 'array',
    ];

    public function columns(): array
    {
        return [
            'slug' => [
                'type'     => 'varchar',
                'title'    => 'Identificador (slug)',
                'required' => true,
                'unique'   => true,
            ],
            'name' => [
                'type'     => 'varchar',
                'title'    => 'Nombre del centro',
                'required' => true,
            ],
            'description' => [
                'type'  => 'text',
                'title' => 'Descripción',
            ],
            'config' => [
                'type'  => 'json',
                'title' => 'Configuración',
            ],
        ];
    }

    /**
     * A centre has many aularios.
     */
    public function aularios()
    {
        return $this->hasMany(Aulario::class, 'centro_id');
    }
}
