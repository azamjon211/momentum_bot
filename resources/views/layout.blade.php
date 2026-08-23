<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>@yield('title', 'Dashboard')</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            color: #222;
        }

        .navbar {
            background: #fff;
            border-bottom: 1px solid #ddd;
            padding: 0 40px;
            height: 64px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .logo {
            font-size: 20px;
            font-weight: 700;
            color: #111;
            text-decoration: none;
        }

        .nav-links {
            display: flex;
            gap: 10px;
            height: 100%;
            align-items: center;
        }

        .nav-link {
            display: flex;
            align-items: center;
            height: 40px;
            padding: 0 18px;
            border-radius: 8px;
            text-decoration: none;
            color: #555;
            font-size: 15px;
            transition: 0.2s;
        }

        .nav-link:hover {
            background: #f0f0f0;
            color: #111;
        }

        .nav-link.active {
            background: #222;
            color: #fff;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px;
        }

        .page-header {
            margin-bottom: 30px;
        }

        .page-header h1 {
            font-size: 30px;
            margin-bottom: 8px;
        }

        .page-header p {
            color: #777;
        }
    </style>

    @stack('styles')
</head>

<body>

<main class="container">
    <x-state label="Total income" amount="$4000"/>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @yield('content')
</main>

@stack('scripts')
</body>
</html>
