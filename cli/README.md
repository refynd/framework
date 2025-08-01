# Refynd CLI

The official command-line interface for the Refynd PHP framework.

## Installation

Install globally via Composer:

```bash
composer global require refynd/cli
```

Make sure your global Composer bin directory is in your PATH.

## Available Commands

### Project Creation
```bash
refynd new my-app              # Create new API application
refynd new my-app --template=webapp  # Create web application
```

### Code Generation
```bash
refynd make:controller UserController        # Basic controller
refynd make:controller UserController --resource  # Resource controller
refynd make:controller ApiController --api   # API controller

refynd make:model User                        # Model class
refynd make:middleware AuthMiddleware         # Middleware class
```

### Development
```bash
refynd serve                    # Start development server
refynd serve --port=8080        # Custom port
refynd serve --host=0.0.0.0     # Custom host

refynd test                     # Run tests
refynd test --coverage          # With coverage
refynd test --filter=UserTest   # Filter tests
```

## Templates

### API Template
- REST API structure
- Example controllers and routes
- Database configuration
- Testing setup

### Web App Template (Coming Soon)
- Full-stack web application
- View templates
- Asset compilation
- Authentication scaffolding

## Requirements

- PHP 8.2 or higher
- Composer
- Refynd Framework ^1.1

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

The Refynd CLI is open-sourced software licensed under the [MIT license](LICENSE).
