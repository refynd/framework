<?php

namespace Refynd\Http;

use Refynd\Container\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * HttpKernel - Handles HTTP Requests
 * 
 * Processes incoming HTTP requests through the routing system,
 * middleware pipeline, and returns appropriate responses.
 */
class HttpKernel
{
    protected Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Handle an incoming HTTP request
     */
    public function handle(Request $request): Response
    {
        try {
            // Simple routing based on query parameter for now
            $page = $request->query->get('page', 'home');
            $name = $request->query->get('name', 'Developer');
            
            switch ($page) {
                case 'users':
                    return $this->handleUsersPage($request);
                case 'about':
                    return $this->handleAboutPage($request);
                default:
                    return $this->handleWelcomePage($request, $name);
            }
            
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Handle welcome page
     */
    protected function handleWelcomePage(Request $request, string $name): Response
    {
        try {
            $viewFactory = $this->container->make('view');
            $view = $viewFactory('welcome', [
                'title' => 'Welcome to Refynd',
                'name' => $name,
                'features' => [
                    'Dependency Injection Container',
                    'Ledger ORM with Active Record',
                    'Prism Template Engine',
                    'Smith CLI Tool',
                    'Module System',
                    'Environment Configuration'
                ],
                'showAdvanced' => $request->query->has('advanced')
            ]);

            return new Response($view->render());
        } catch (\Throwable $e) {
            // Fallback to simple HTML if Prism fails
            return new Response($this->generateSimpleWelcome($name));
        }
    }

    /**
     * Handle users page
     */
    protected function handleUsersPage(Request $request): Response
    {
        try {
            // Example users data (would come from Ledger ORM)
            $users = [
                ['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com', 'created_at' => '2025-08-01 10:00:00'],
                ['id' => 2, 'name' => 'Jane Smith', 'email' => 'jane@example.com', 'created_at' => '2025-08-01 11:30:00'],
                ['id' => 3, 'name' => 'Bob Johnson', 'email' => 'bob@example.com', 'created_at' => '2025-08-01 12:15:00'],
            ];

            $viewFactory = $this->container->make('view');
            $view = $viewFactory('users', [
                'title' => 'User Management - Refynd',
                'users' => $users,
                'activeCount' => count($users)
            ]);

            return new Response($view->render());
        } catch (\Throwable $e) {
            return new Response('Users page error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Handle about page
     */
    protected function handleAboutPage(Request $request): Response
    {
        try {
            $features = [
                'Engine-Driven Architecture',
                'Module System',
                'Dependency Injection',
                'Ledger ORM',
                'Prism Templates',
                'Smith CLI Tool'
            ];

            $viewFactory = $this->container->make('view');
            $view = $viewFactory('about', [
                'title' => 'About Refynd Framework',
                'features' => $features
            ]);

            return new Response($view->render());
        } catch (\Throwable $e) {
            return new Response('About page error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Generate simple welcome content (fallback)
     */
    protected function generateSimpleWelcome(string $name): string
    {
        return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to Refynd</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container {
            text-align: center;
            color: white;
            max-width: 600px;
            padding: 2rem;
        }
        .logo {
            font-size: 4rem;
            font-weight: 700;
            margin-bottom: 1rem;
            text-shadow: 0 4px 8px rgba(0,0,0,0.3);
        }
        .tagline {
            font-size: 1.5rem;
            margin-bottom: 2rem;
            opacity: 0.9;
        }
        .description {
            font-size: 1.1rem;
            line-height: 1.6;
            opacity: 0.8;
            margin-bottom: 2rem;
        }
        .version {
            font-size: 0.9rem;
            opacity: 0.7;
            margin-top: 2rem;
        }
        .forge-icon {
            font-size: 2rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="forge-icon">🔨</div>
        <h1 class="logo">Refynd</h1>
        <p class="tagline">Craft Exceptional Applications</p>
        <p class="description">
            Welcome to Refynd - a powerful, elegant, and expressive PHP framework 
            designed for crafting exceptional web applications. With its modular 
            architecture and intuitive design, Refynd empowers developers to build 
            robust applications with confidence and joy.
        </p>
        <p class="version">Refynd Framework v1.0.0-alpha</p>
    </div>
</body>
</html>';
    }

    /**
     * Handle exceptions during request processing
     */
    protected function handleException(\Throwable $e): Response
    {
        // For now, return a simple error response
        // This will be expanded to include proper error handling, logging, etc.
        
        $content = sprintf(
            '<h1>Application Error</h1><p>%s</p><pre>%s</pre>',
            htmlspecialchars($e->getMessage()),
            htmlspecialchars($e->getTraceAsString())
        );

        return new Response($content, 500, [
            'Content-Type' => 'text/html; charset=UTF-8'
        ]);
    }
}
