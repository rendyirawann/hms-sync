<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', $brand['name'] . ' - Pesan Hotel')</title>
    
    <link rel="icon" href="{{ $brand['favicon_url'] }}" sizes="any" />
    <link rel="icon" type="image/png" sizes="32x32" href="{{ $brand['favicon_png_url'] }}" />
    <link rel="apple-touch-icon" sizes="180x180" href="{{ $brand['apple_icon_url'] }}" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    @vite('resources/css/app.css')
    <link rel="stylesheet" href="{{ asset('assets/css/hms-motion.css') }}">

    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .text-gradient {
            background: linear-gradient(90deg, #3b82f6 0%, #8b5cf6 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="bg-white text-slate-900 antialiased">
