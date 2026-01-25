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
        │   └── Aularios/
        │       ├── aulario_abc123.json
        │       └── aulario_xyz456.json
        └── centro2/
            └── Aularios/
                └── aulario_def789.json
```

## File Examples

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
