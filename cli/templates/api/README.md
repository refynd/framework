# {{APP_NAME}}

A powerful API built with the Refynd PHP framework.

## Getting Started

### Installation

1. Install dependencies:
```bash
composer install
```

2. Set up environment:
```bash
cp .env.example .env
```

3. Create storage directories:
```bash
mkdir -p storage/cache storage/logs
touch storage/database.sqlite
```

### Development

Start the development server:
```bash
refynd serve
```

Your API will be available at: http://localhost:8000

### API Endpoints

- `GET /health` - Health check
- `GET /api/v1/users` - List users
- `POST /api/v1/users` - Create user
- `GET /api/v1/users/{id}` - Get user
- `PUT /api/v1/users/{id}` - Update user
- `DELETE /api/v1/users/{id}` - Delete user

### Testing

Run tests:
```bash
refynd test
```

### Code Analysis

Run static analysis:
```bash
composer analyse
```

## Project Structure

```
{{APP_NAME}}/
├── app/
│   ├── Bootstrap/          # Application bootstrap
│   ├── Http/
│   │   ├── Controllers/    # API controllers
│   │   └── Middleware/     # Custom middleware
│   └── Models/             # Data models
├── public/                 # Web server document root
├── routes/                 # Route definitions
├── storage/                # Logs, cache, database
├── tests/                  # Test files
└── vendor/                 # Composer dependencies
```

## License

This project is open-sourced software licensed under the [MIT license](LICENSE).
