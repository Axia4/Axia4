# Axia4

Axia4 is a unified platform for EuskadiTech and Sketaria, providing various services including EntreAulas (connected classroom management system).

> **Axia4 is now built on the [ADIOS framework](https://github.com/wai-blue/adios)** – a lightweight PHP 8 framework combining React, TailwindCSS, Twig, Eloquent ORM and PrimeReact.

## Quick Start with Docker

The easiest way to run Axia4 is using Docker:

```bash
# 1. Clone the repository
git clone https://github.com/Axia4/Axia4.git
cd Axia4

# 2. Install PHP dependencies
composer install

# 3. Create the data directory structure (legacy file store)
mkdir -p DATA/Usuarios DATA/entreaulas/Usuarios DATA/entreaulas/Centros

# 4. Copy and edit the environment configuration
cp env.php env.php   # already present – edit DB_* values as needed

# 5. Start the application with Docker Compose
docker compose up -d

# 6. Open the application
# http://localhost:882/
```

## Development Setup

### Prerequisites
- PHP 8.2+
- Composer 2+
- Node.js 20+ and npm
- Docker & Docker Compose (optional but recommended)

### Install dependencies

```bash
# PHP (ADIOS framework + Twig)
composer install

# JavaScript (React, TailwindCSS, webpack, etc.)
npm install
```

### Build frontend assets

```bash
# One-time build
npm run build

# Development build (watch mode)
npm run watch
```

### Environment configuration

Copy `env.php` and adapt the database connection and other settings:

```php
$config = [
    'url'  => 'http://localhost',
    'db'   => [
        'host'     => 'localhost',
        'database' => 'axia4',
        'username' => 'axia4',
        'password' => 'axia4',
    ],
    // ...
];
```

## Architecture (ADIOS)

| Layer           | Technology                      |
|-----------------|---------------------------------|
| Backend         | PHP 8.2 + ADIOS framework       |
| Templating      | Twig 3                          |
| Database ORM    | Eloquent (Laravel)              |
| Frontend UI     | React + PrimeReact              |
| CSS             | TailwindCSS 4                   |
| Build           | Webpack 5                       |
| Web server      | FrankenPHP (Caddy + PHP)        |
| Container       | Docker / Docker Compose         |

### Project structure

```
Axia4/
├── src/
│   ├── App.php                   # ADIOS application loader
│   ├── App.tsx                   # React entry point
│   ├── App.twcss                 # TailwindCSS entry point
│   ├── Auth/
│   │   └── Axia4Auth.php         # Custom authentication handler
│   ├── Core/
│   │   └── Router.php            # URL routing
│   ├── Controllers/
│   │   ├── Home.php
│   │   ├── Login.php
│   │   ├── Logout.php
│   │   ├── Account.php
│   │   ├── EntreAulas.php
│   │   ├── Club.php
│   │   ├── SysAdmin.php
│   │   ├── PrivacyPolicy.php
│   │   ├── Login/
│   │   │   ├── Google.php        # Start Google OAuth flow
│   │   │   └── GoogleCallback.php
│   │   └── Account/
│   │       └── Register.php
│   ├── Models/
│   │   ├── User.php              # Eloquent model
│   │   ├── Centro.php
│   │   ├── Aulario.php
│   │   └── Alumno.php
│   └── Views/                    # Twig templates
│       ├── Layout.twig
│       ├── Home.twig
│       ├── Login.twig
│       ├── Account.twig
│       ├── EntreAulas.twig
│       ├── Club.twig
│       ├── SysAdmin.twig
│       ├── Error.twig
│       ├── PrivacyPolicy.twig
│       └── Account/
│           └── Register.twig
├── public_html/                  # Legacy PHP code (kept for reference)
├── index.php                     # ADIOS entry point
├── env.php                       # Environment configuration
├── composer.json                 # PHP dependencies
├── package.json                  # JS dependencies
├── webpack.config.js
├── tailwind.config.js
├── babel.config.js
├── tsconfig.json
├── Dockerfile                    # Multi-stage production build
├── Dockerfile.dev                # Development image
└── docker-compose.yml
```

## Features

- **Home**: Responsive app grid with links to all Axia4 services
- **EntreAulas**: Classroom management (aulario, alumnos, comedor/SuperCafe, panel diario)
- **Club**: Public-facing club website
- **Mi Cuenta**: User profile and QR code
- **SysAdmin**: Admin panel for platform configuration
- **Authentication**: Local password login + Google OAuth 2.0

## Google OAuth Setup

1. Create a project in [Google Cloud Console](https://console.cloud.google.com/).
2. Enable the **Google Identity** API.
3. Create OAuth 2.0 credentials (Web application).
4. Set the redirect URI to: `https://your-domain.com/?route=login/google/callback`
5. Set `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET` in environment or `env.php`.

## Documentation

- **[ADIOS framework](https://github.com/wai-blue/adios)** – upstream framework documentation
- **[Data Structure](DATA_STRUCTURE.md)** – legacy JSON file storage schema
- **[TODO](TODO.md)** – development roadmap

## License

See LICENSE file for details.


