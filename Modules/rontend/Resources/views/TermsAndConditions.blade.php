<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ language_direction() }}" class="theme-fs-sm">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">

  <title>@yield('title') | {{ app_name() }}</title>

  <link rel="stylesheet" href="{{ mix('css/libs.min.css') }}">
  <link rel="stylesheet" href="{{ mix('css/backend.css') }}">
  @if (language_direction() == 'rtl')
  <link rel="stylesheet" href="{{ asset('css/rtl.css') }}">
  @endif

  @stack('after-styles')
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

  <style>
    body {
      background: white !important;
    }

    .card {
      font-size: 15px;
      line-height: 1.6;
      color: black;
    }

    .card-body {
      background: #F9F6F0;
    }

    .card-title {
      font-size: 23px;
      margin-bottom: 27px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      cursor: pointer;
    }

    .toggle-content {
      transition: max-height 0.4s ease;
    }
    .card-title i {
      transition: transform 0.6s ease;
    }

    .card-title.active i {
      transform: rotate(180deg);
    }
    .toggle-content li {
        list-style-type: disc;
        list-style-position: inside;
    }
    .hidden{
        max-height: 65px;
        overflow: hidden;
    }
  </style>
</head>

<body>
  @include('components.frontend.progress-bar')

  <div class="position-relative" style="height: 17vh;">
    @include('components.frontend.navbar')
  </div>

  <main>
    <div class="container mt-4">
      <div class="row">
        @foreach($terms as $index => $term)
        <div class="col-md-6 mb-4">
          <div class="card shadow-sm">
            <div class="card-body hidden">
              <h5 class="card-title toggle-title">
                {{$index + 1}} - {{ $term->title[app()->getLocale()] }}
                <i class="fas fa-chevron-down"></i>
              </h5>
              <ul class="list-unstyled mt-3 toggle-content">
              @if ($term->points)
                @foreach($term->points[app()->getLocale()] as $point)
                <li>{{ $point }}</li>
                @endforeach
              @endif
              </ul>
            </div>
          </div>
        </div>
        @endforeach
      </div>
    </div>
  </main>

  <div class="position-relative" style="height: 17vh;"></div>
  @include('components.frontend.footer')

  <script>
    document.querySelectorAll('.toggle-title').forEach(title => {
      title.addEventListener('click', function () {
        const content = this.parentElement;
        this.classList.toggle('active');
        content.classList.toggle('hidden');
      });
    });
  </script>
</body>
</html>
