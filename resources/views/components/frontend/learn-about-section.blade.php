<link rel="stylesheet" href="{{ asset('pages-css/learn-about-section.css') }}">

<div class="main-learn">
  <div class="learn-logo">
    <img src="{{ asset('images/jospalogo.png') }}" alt="Learn About Us">
  </div>
  <div class="learn-content">
    <span>{{ __('messagess.main_title') }}</span>
    <h2>{{ __('messagess.spa_atmosphere') }}</h2>
    <p>{{ __('messagess.spa_experience') }}</p>
  </div>
  <div class="d-flex flex-column flex-md-row justify-content-center" style="justify-content: center;margin: 35px 0;">
    <div class="f-sec">
      <h3>{{ __('messagess.vision_title') }}</h3>
      <p>{{ __('messagess.vision_text') }}</p>
    </div>
    <div class="s-sec">
      <h3>{{ __('messagess.mission_title') }}</h3>
      <p>{{ __('messagess.mission_text') }}</p>
    </div>
  </div>
</div>