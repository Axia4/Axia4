# Axia4 Docker Setup

This document explains how to run the Axia4 PHP application using Docker.

## Prerequisites

- Docker Engine 20.10+
- Docker Compose V2

## Quick Start

1. **Prepare the data directory**
   ```bash
   mkdir -p DATA/entreaulas/Usuarios
   mkdir -p DATA/entreaulas/Centros
   ```

2. **Build and start the application**
   ```bash
   docker-compose up -d
   ```

3. **Access the application**
   
   Open your browser and navigate to: `http://localhost:8080`

## Configuration

### Data Directory Structure

The application stores all data in the `/DATA` directory which is mounted from `./DATA` on the host:

```
DATA/
├── Usuarios.json                          # Main user accounts
└── entreaulas/
    ├── Usuarios/                          # EntreAulas user files
    │   └── {username}.json
    └── Centros/                           # Centro data
        └── {centro_id}/
            └── Aularios/                  # Aulario configurations
                └── {aulario_id}.json
```

### Creating Initial Users

**Main Users** (`DATA/Usuarios.json`):
```json
{
  "username": {
    "password_hash": "hashed_password_here"
  }
}
```

**EntreAulas Users** (`DATA/entreaulas/Usuarios/{username}.json`):
```json
{
  "password_hash": "hashed_password_here",
  "display_name": "Full Name",
  "centro": "centro_id",
  "aulas": ["aulario_id_1", "aulario_id_2"]
}
```

To generate a password hash, you can use PHP:
```bash
docker exec -it axia4-app php -r "echo password_hash('your_password', PASSWORD_DEFAULT);"
```

### Port Configuration

By default, the application runs on port 8080. To change this, edit `docker-compose.yml`:

```yaml
ports:
  - "YOUR_PORT:80"
```

## Docker Commands

### Start the application
```bash
docker-compose up -d
```

### Stop the application
```bash
docker-compose down
```

### View logs
```bash
docker-compose logs -f
```

### Rebuild after changes
```bash
docker-compose up -d --build
```

### Access the container shell
```bash
docker exec -it axia4-app bash
```

## Development Mode

To enable live code updates without rebuilding, uncomment the volume mount in `docker-compose.yml`:

```yaml
volumes:
  - ./DATA:/DATA
  - ./public_html:/var/www/html  # Uncomment this line
```

## Troubleshooting

### Permission Issues

If you encounter permission errors with the DATA directory:

```bash
sudo chown -R 33:33 DATA
sudo chmod -R 755 DATA
```

(User ID 33 is typically the www-data user in the container)

### Check Application Logs

```bash
docker-compose logs axia4-web
```

### Inspect Container

```bash
docker exec -it axia4-app bash
# Then inside the container:
ls -la /DATA
cat /var/log/apache2/error.log
```

## Security Notes

- Change default passwords immediately in production
- Ensure the DATA directory has appropriate permissions
- Consider using environment variables for sensitive configuration
- Use HTTPS in production (add a reverse proxy like Nginx or Traefik)

## Backup

To backup your data:

```bash
tar -czf axia4-data-backup-$(date +%Y%m%d).tar.gz DATA/
```

## Restore

To restore from backup:

```bash
tar -xzf axia4-data-backup-YYYYMMDD.tar.gz
```
