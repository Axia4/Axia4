# Axia4

Axia4 is a unified platform for EuskadiTech and Sketaria, providing various services including EntreAulas (connected classroom management system).

## Quick Start with Docker

The easiest way to run Axia4 is using Docker:

```bash
# 1. Clone the repository
git clone https://github.com/Axia4/Axia4.git
cd Axia4

# 2. Create the data directory structure
mkdir -p DATA/entreaulas/Usuarios
mkdir -p DATA/entreaulas/Centros

# 3. Start the application
docker compose up -d

# 4. Access the application
# Open http://localhost:8080 in your browser
```

## Documentation

- **[Docker Setup Guide](DOCKER.md)** - Complete guide for running Axia4 with Docker
- **[Data Structure](DATA_STRUCTURE.md)** - Information about the data directory structure and how to set up users

## Features

- **EntreAulas**: Management system for connected classrooms
- **Aularios**: Centralized access to classroom resources
- Integration with multiple external services

## Requirements

### Docker (Recommended)
- Docker Engine 20.10+
- Docker Compose V2

### Manual Installation
- PHP 8.2+
- Apache 2.4+
- PHP GD extension

## Configuration

All application data is stored in the `/DATA` directory which is mounted from the host system. See [DATA_STRUCTURE.md](DATA_STRUCTURE.md) for details on how to set up your data files.

## Development

To enable live code updates during development, uncomment the volume mount in `docker-compose.yml`:

```yaml
volumes:
  - ./DATA:/DATA
  - ./public_html:/var/www/html  # Uncomment this line
```

## Google OAuth Redirect URLs
Format: `https://example.com/_login.php?google_callback=1`

## Support

For issues and questions, please open an issue on GitHub.

## License

See LICENSE file for details.

