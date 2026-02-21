<section class="py-5" style="margin-top: 100px;">
    <div class="container" style="padding:0 5rem">
        <div class="row align-items-center g-5">
            <!-- Left: Text -->
            <div class="col-12 col-lg-6" data-aos="fade-right" data-aos-delay="100">
                <h2 class="fw-bold mb-4" style="font-size: 3rem;">
                   <span style="color: var(--primary-color);">{{ __('messages.learn_about') }}</span> {{ __('messages.jo_spa') }}
                </h2>
                <p class="mb-4" style="font-size: 1.2rem; line-height: 1.8; color: #222;">
                    {{ __('messages.learn_paragraph') }}
                </p>
                @include('components.frontend.gift-button', [
                    'text' => __('messages.read_more'),
                    'href' => '#',
                    'class' => 'fs-4 px-5 py-3 border-0',
                ])
            </div>
            <!-- Right: Image -->
            <div class="col-12 col-lg-6 text-center" data-aos="fade-left" data-aos-delay="200">
                <img src="{{ asset('images/frontend/learn.webp') }}" alt="{{ __('messages.learn_image_alt') }}" class="img-fluid rounded-4 shadow" style="max-width: 90%; object-fit: cover;">
            </div>
        </div>
    </div>
</section>

<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
  AOS.init({ once: true });
</script>
