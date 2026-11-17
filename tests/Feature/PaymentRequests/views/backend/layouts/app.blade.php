{{-- Test-only stand-in for the dashboard layout (the real one needs menus, branches and settings tables). --}}
<!DOCTYPE html>
<html><head><title>@yield('title')</title>@stack('after-styles')</head>
<body>@yield('content')@stack('after-scripts')</body></html>
