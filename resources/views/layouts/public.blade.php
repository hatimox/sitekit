<!DOCTYPE html>
<html lang="en" class="">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Documentation') - {{ config('app.name', 'SiteKit') }}</title>
    <meta name="description" content="@yield('description', 'SiteKit documentation - learn how to deploy, manage, and monitor your servers.')">

    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {}
            }
        }
    </script>

    <!-- Alpine.js -->
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <!-- Custom styles -->
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');

        body {
            font-family: 'Inter', sans-serif;
        }

        /* Prose styles for markdown content */
        .prose h1 { font-size: 2rem; font-weight: 700; margin-bottom: 1rem; margin-top: 2rem; }
        .prose h2 { font-size: 1.5rem; font-weight: 600; margin-bottom: 0.75rem; margin-top: 1.5rem; border-bottom: 1px solid #e5e7eb; padding-bottom: 0.5rem; }
        .prose h3 { font-size: 1.25rem; font-weight: 600; margin-bottom: 0.5rem; margin-top: 1.25rem; }
        .prose p { margin-bottom: 1rem; line-height: 1.75; }
        .prose ul { list-style-type: disc; padding-left: 1.5rem; margin-bottom: 1rem; }
        .prose ol { list-style-type: decimal; padding-left: 1.5rem; margin-bottom: 1rem; }
        .prose li { margin-bottom: 0.25rem; }
        .prose strong { font-weight: 600; }
        .prose code { background-color: #f3f4f6; padding: 0.125rem 0.375rem; border-radius: 0.25rem; font-size: 0.875rem; }
        .prose pre { background-color: #1f2937; color: #f9fafb; padding: 1rem; border-radius: 0.5rem; overflow-x: auto; margin-bottom: 1rem; }
        .prose pre code { background-color: transparent; padding: 0; color: inherit; }
        .prose a { color: #d97706; text-decoration: underline; }
        .prose a:hover { color: #b45309; }
        .prose table { width: 100%; border-collapse: collapse; margin-bottom: 1rem; }
        .prose th { background-color: #f3f4f6; padding: 0.5rem 1rem; text-align: left; font-weight: 600; border: 1px solid #e5e7eb; }
        .prose td { padding: 0.5rem 1rem; border: 1px solid #e5e7eb; }
        .prose hr { border-color: #e5e7eb; margin: 2rem 0; }
        .prose blockquote { border-left: 4px solid #d97706; padding-left: 1rem; font-style: italic; color: #6b7280; }

        /* Dark mode prose */
        .dark .prose h2 { border-bottom-color: #374151; }
        .dark .prose code { background-color: #374151; color: #f9fafb; }
        .dark .prose pre { background-color: #111827; }
        .dark .prose a { color: #fbbf24; }
        .dark .prose a:hover { color: #f59e0b; }
        .dark .prose th { background-color: #374151; border-color: #4b5563; }
        .dark .prose td { border-color: #4b5563; }
        .dark .prose hr { border-color: #374151; }
        .dark .prose blockquote { color: #9ca3af; }

        /* Sidebar active state */
        .sidebar-link.active {
            background-color: #fef3c7;
            color: #92400e;
            font-weight: 500;
        }
        .dark .sidebar-link.active {
            background-color: #78350f;
            color: #fde68a;
        }
    </style>

    <!-- Dark mode detection -->
    <script>
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            document.documentElement.classList.add('dark');
        }
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => {
            if (e.matches) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        });
    </script>
</head>
<body class="antialiased text-gray-800 bg-white dark:text-gray-200 dark:bg-gray-900 transition-colors duration-200">

<!-- Navigation -->
<header class="bg-white dark:bg-gray-900 shadow-sm dark:shadow-gray-800 sticky top-0 z-50" x-data="{ mobileMenuOpen: false }">
    <div class="container mx-auto px-4">
        <div class="flex justify-between items-center py-4">
            <!-- Logo -->
            <div class="flex items-center">
                <a href="/" class="flex items-center space-x-2">
                    <svg class="w-8 h-8 text-amber-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="2" y="3" width="20" height="14" rx="2" />
                        <line x1="8" y1="21" x2="16" y2="21" />
                        <line x1="12" y1="17" x2="12" y2="21" />
                        <circle cx="12" cy="10" r="2" />
                    </svg>
                    <span class="text-xl font-bold text-gray-900 dark:text-white">{{ config('app.name', 'SiteKit') }}</span>
                </a>
            </div>

            <!-- Desktop Navigation -->
            <nav class="hidden md:flex items-center space-x-8">
                <a href="/docs" class="text-amber-600 dark:text-amber-400 font-medium transition">Docs</a>
                <a href="https://github.com/avansaber/sitekit" target="_blank" class="text-gray-600 dark:text-gray-300 hover:text-amber-600 dark:hover:text-amber-400 font-medium transition">GitHub</a>
                <a href="https://avansaber.com/about" target="_blank" class="text-gray-600 dark:text-gray-300 hover:text-amber-600 dark:hover:text-amber-400 font-medium transition">About</a>
            </nav>

            <!-- CTA Button -->
            <div class="hidden md:flex items-center space-x-4">
                <a href="/app/login" class="bg-amber-600 hover:bg-amber-700 text-white font-medium py-2 px-4 rounded-lg transition">
                    Sign In
                </a>
            </div>

            <!-- Mobile menu button -->
            <button @click="mobileMenuOpen = !mobileMenuOpen" class="md:hidden text-gray-500 dark:text-gray-400 hover:text-amber-600 focus:outline-none" aria-label="Toggle menu">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path x-show="!mobileMenuOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    <path x-show="mobileMenuOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
    </div>

    <!-- Mobile Menu -->
    <div x-show="mobileMenuOpen" class="md:hidden bg-white dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700">
        <div class="container mx-auto px-4 py-3 space-y-3">
            <a href="/docs" class="block text-amber-600 font-medium py-2">Docs</a>
            <a href="https://github.com/avansaber/sitekit" target="_blank" class="block text-gray-600 dark:text-gray-300 hover:text-amber-600 font-medium py-2">GitHub</a>
            <a href="https://avansaber.com/about" target="_blank" class="block text-gray-600 dark:text-gray-300 hover:text-amber-600 font-medium py-2">About</a>
            <a href="/app/login" class="block bg-amber-600 hover:bg-amber-700 text-white font-medium py-2 px-4 rounded-lg transition text-center">
                Sign In
            </a>
        </div>
    </div>
</header>

<!-- Main Content -->
@yield('content')

<!-- Footer -->
<footer class="bg-gray-50 dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 py-8 mt-12">
    <div class="container mx-auto px-4">
        <div class="flex flex-col md:flex-row justify-between items-center">
            <div class="flex items-center space-x-2 mb-4 md:mb-0">
                <svg class="w-6 h-6 text-amber-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="2" y="3" width="20" height="14" rx="2" />
                    <line x1="8" y1="21" x2="16" y2="21" />
                    <line x1="12" y1="17" x2="12" y2="21" />
                    <circle cx="12" cy="10" r="2" />
                </svg>
                <span class="font-semibold text-gray-900 dark:text-white">{{ config('app.name', 'SiteKit') }}</span>
            </div>
            <div class="text-sm text-gray-600 dark:text-gray-400">
                &copy; {{ date('Y') }} <a href="https://avansaber.com" target="_blank" class="hover:text-amber-600">AvanSaber</a>. All rights reserved.
            </div>
        </div>
    </div>
</footer>

</body>
</html>
