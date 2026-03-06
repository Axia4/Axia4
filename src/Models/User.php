<?php

namespace Axia4\Models;

/**
 * User model (Eloquent ORM)
 *
 * Represents a registered Axia4 platform user.
 * Table: axia4_users
 *
 * This model is used when the application is running with a SQL database.
 * For backward compatibility with the legacy JSON file store, see
 * Axia4\Auth\Axia4Auth::loadUser() / saveUser().
 */
class User extends \ADIOS\Core\Model
{
    protected $table = 'axia4_users';

    protected $fillable = [
        'username',
        'display_name',
        'email',
        'password_hash',
        'permissions',
        'google_auth',
    ];

    protected $hidden = [
        'password_hash',
    ];

    protected $casts = [
        'permissions' => 'array',
        'google_auth' => 'boolean',
    ];

    /**
     * ADIOS column definitions (used for automatic form/table generation).
     */
    public function columns(): array
    {
        return [
            'username' => [
                'type'     => 'varchar',
                'title'    => 'Nombre de usuario',
                'required' => true,
                'unique'   => true,
            ],
            'display_name' => [
                'type'  => 'varchar',
                'title' => 'Nombre para mostrar',
            ],
            'email' => [
                'type'     => 'varchar',
                'title'    => 'Correo electrónico',
                'required' => true,
            ],
            'password_hash' => [
                'type'  => 'varchar',
                'title' => 'Hash de contraseña',
            ],
            'permissions' => [
                'type'  => 'json',
                'title' => 'Permisos',
            ],
            'google_auth' => [
                'type'  => 'boolean',
                'title' => 'Autenticación Google',
            ],
        ];
    }
}
