# Example Data Structure for Axia4

This directory contains example data files that demonstrate the structure needed for the Axia4 application.

## Directory Structure

```
DATA/
├── Usuarios.json                          # Main application users
└── entreaulas/
    ├── Usuarios/                          # EntreAulas user files
    │   ├── user1.json
    │   └── user2.json
    └── Centros/                           # Centro data
        ├── centro1/
        │   ├── Aularios/
        │   │   ├── aulario_abc123.json
        │   │   └── aulario_xyz456.json
        │   └── SuperCafe/                 # SuperCafe data (per centro)
        │       ├── Menu.json              # Menu items / categories (optional)
        │       └── Comandas/              # One JSON file per order
        │           └── sc<id>.json
        └── centro2/
            └── Aularios/
                └── aulario_def789.json
```

## File Examples

### SuperCafe (persons come from the existing Alumnos system)

Persons are loaded automatically from the aulario Alumnos directories — no separate configuration file is needed.
See the **Aulario Student Names** section for the alumnos data format.

### SuperCafe Menu (DATA/entreaulas/Centros/{centro_id}/SuperCafe/Menu.json)

```json
{
  "Bebidas": {
    "Café": 1,
    "Zumo": 1.5
  },
  "Comida": {
    "Bocadillo": 2.5,
    "Ensalada": 3
  }
}
```

### SuperCafe Order (DATA/entreaulas/Centros/{centro_id}/SuperCafe/Comandas/{id}.json)

```json
{
  "Fecha": "2024-01-15",
  "Persona": "aulario_abc123:Juan",
  "Comanda": "1x Café, 1x Bocadillo",
  "Notas": "Sin azúcar",
  "Estado": "Pedido"
}
```

`Persona` is stored as `{aulario_id}:{alumno_name}` and resolved to a display name at render time.
Order statuses: `Pedido`, `En preparación`, `Listo`, `Entregado`, `Deuda`.
A person with 3 or more orders in `Deuda` status cannot place new orders.

### Main Users (DATA/Usuarios.json)

```json
{
  "username1": {
    "password_hash": "$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi"
  },
  "username2": {
    "password_hash": "$2y$10$example_hash_here"
  }
}
```

### EntreAulas User (DATA/entreaulas/Usuarios/username.json)

```json
{
  "password_hash": "$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi",
  "display_name": "John Doe",
  "centro": "centro1",
  "aulas": [
    "aulario_abc123",
    "aulario_xyz456"
  ]
}
```

### Aulario Configuration (DATA/entreaulas/Centros/{centro_id}/Aularios/{aulario_id}.json)

```json
{
  "name": "Aulario Principal",
  "icon": "/static/logo-entreaulas.png"
}
```

### Aulario Student Names (DATA/entreaulas/Centros/{centro_id}/Aularios/{aulario_id}/Alumnos/)

The Alumnos directory contains subdirectories for each student, where each student has:
- A unique folder name (student identifier)
- A `photo.jpg` file with the student's photo/pictogram

Example structure:
```
DATA/entreaulas/Centros/centro1/Aularios/aulario_abc123/Alumnos/
├── Juan/
│   └── photo.jpg
├── Maria/
│   └── photo.jpg
└── Pedro/
    └── photo.jpg
```

This structure is used by the "¿Quién soy?" (Who am I?) feature in Panel Diario, where students can identify themselves by selecting their photo.

## Generating Password Hashes

To create password hashes for your users, use one of these methods:

### Using Docker:

```bash
docker exec -it axia4-app php -r "echo password_hash('your_password', PASSWORD_DEFAULT);"
```

### Using PHP CLI directly:

```bash
php -r "echo password_hash('your_password', PASSWORD_DEFAULT);"
```

### Using a PHP script:

```php
<?php
echo password_hash('your_password', PASSWORD_DEFAULT);
?>
```

## Security Notes

- **NEVER** commit the actual DATA directory with real user credentials to version control
- The DATA directory should only exist on your production/development servers
- Use strong, unique passwords for all accounts
- Regularly backup the DATA directory
- Set appropriate file permissions (755 for directories, 644 for files)
