@extends('frontend::layouts.master')

{{--@section('head')--}}
{{--    <meta charset="UTF-8">--}}
{{--    <meta name="viewport" content="width=device-width, initial-scale=1.0">--}}
{{--    <title>{{ __('messagess.privacy_page_title') }} &mdash; Jospa</title>--}}
{{--    <meta name="description" content="{{ __('messagess.privacy_meta_description') }}">--}}
{{--    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">--}}
{{--    <link rel="stylesheet" href="{{ asset('pages-css/privacy-policy.css') }}">--}}
{{--@endsection--}}

@section('content')

    {{-- Hero Section --}}
    <section class="pp-hero" id="privacy-hero">
        <div class="pp-hero-content">
            <div class="pp-hero-icon">
                <i class="bi bi-shield-lock-fill"></i>
            </div>
            <h1>{{ __('messagess.privacy_page_title') }}</h1>
            <p class="pp-hero-subtitle">{{ __('messagess.privacy_hero_subtitle') }}</p>
            <div class="pp-hero-date">
                <i class="bi bi-calendar3"></i>
                {{ __('messagess.privacy_last_updated') }}: 3 {{ __('messagess.privacy_june') }} 2026
            </div>
        </div>
    </section>

    {{-- Wave Divider --}}
    <div class="pp-wave-divider">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 60" preserveAspectRatio="none">
            <path d="M0,0 C360,60 1080,60 1440,0 L1440,0 L0,0 Z" fill="#4D4541"/>
        </svg>
    </div>

    {{-- Main Content --}}
    <main class="pp-main">
        <div class="pp-container">

            {{-- Section 1: Information We Collect --}}
            <div class="pp-section" id="pp-section-1">
                <div class="pp-section-header">
                    <div class="pp-section-num">1</div>
                    <h2 class="pp-section-title">{{ __('messagess.privacy_s1_title') }}</h2>
                </div>
                <p class="pp-text">{{ __('messagess.privacy_s1_intro') }}</p>

                <h3 class="pp-sub-title"><i class="bi bi-person-fill"></i> {{ __('messagess.privacy_s1a_title') }}</h3>
                <ul class="pp-list">
                    <li>{{ __('messagess.privacy_s1a_item1') }}</li>
                    <li>{{ __('messagess.privacy_s1a_item2') }}</li>
                    <li>{{ __('messagess.privacy_s1a_item3') }}</li>
                </ul>

                <h3 class="pp-sub-title"><i class="bi bi-key-fill"></i> {{ __('messagess.privacy_s1b_title') }}</h3>
                <ul class="pp-list">
                    <li>{{ __('messagess.privacy_s1b_item1') }}</li>
                    <li>{{ __('messagess.privacy_s1b_item2') }}</li>
                    <li>{{ __('messagess.privacy_s1b_item3') }}</li>
                </ul>

                <h3 class="pp-sub-title"><i class="bi bi-cpu-fill"></i> {{ __('messagess.privacy_s1c_title') }}</h3>
                <ul class="pp-list">
                    <li>{{ __('messagess.privacy_s1c_item1') }}</li>
                    <li>{{ __('messagess.privacy_s1c_item2') }}</li>
                    <li>{{ __('messagess.privacy_s1c_item3') }}</li>
                    <li>{{ __('messagess.privacy_s1c_item4') }}</li>
                </ul>
            </div>

            {{-- Section 2: How We Use Your Information --}}
            <div class="pp-section" id="pp-section-2">
                <div class="pp-section-header">
                    <div class="pp-section-num">2</div>
                    <h2 class="pp-section-title">{{ __('messagess.privacy_s2_title') }}</h2>
                </div>
                <p class="pp-text">{{ __('messagess.privacy_s2_intro') }}</p>

                <div class="pp-table-wrapper">
                    <table class="pp-table">
                        <thead>
                            <tr>
                                <th>{{ __('messagess.privacy_s2_th_purpose') }}</th>
                                <th>{{ __('messagess.privacy_s2_th_explanation') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>{{ __('messagess.privacy_s2_r1_purpose') }}</td>
                                <td>{{ __('messagess.privacy_s2_r1_explanation') }}</td>
                            </tr>
                            <tr>
                                <td>{{ __('messagess.privacy_s2_r2_purpose') }}</td>
                                <td>{{ __('messagess.privacy_s2_r2_explanation') }}</td>
                            </tr>
                            <tr>
                                <td>{{ __('messagess.privacy_s2_r3_purpose') }}</td>
                                <td>{{ __('messagess.privacy_s2_r3_explanation') }}</td>
                            </tr>
                            <tr>
                                <td>{{ __('messagess.privacy_s2_r4_purpose') }}</td>
                                <td>{{ __('messagess.privacy_s2_r4_explanation') }}</td>
                            </tr>
                            <tr>
                                <td>{{ __('messagess.privacy_s2_r5_purpose') }}</td>
                                <td>{{ __('messagess.privacy_s2_r5_explanation') }}</td>
                            </tr>
                            <tr>
                                <td>{{ __('messagess.privacy_s2_r6_purpose') }}</td>
                                <td>{{ __('messagess.privacy_s2_r6_explanation') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Section 3: Cookies --}}
            <div class="pp-section" id="pp-section-3">
                <div class="pp-section-header">
                    <div class="pp-section-num">3</div>
                    <h2 class="pp-section-title">{{ __('messagess.privacy_s3_title') }}</h2>
                </div>
                <p class="pp-text">{{ __('messagess.privacy_s3_intro') }}</p>
                <ul class="pp-list">
                    <li>{{ __('messagess.privacy_s3_item1') }}</li>
                    <li>{{ __('messagess.privacy_s3_item2') }}</li>
                    <li>{{ __('messagess.privacy_s3_item3') }}</li>
                </ul>
                <p class="pp-text">{{ __('messagess.privacy_s3_note') }}</p>
            </div>

            {{-- Section 4: Information Sharing --}}
            <div class="pp-section" id="pp-section-4">
                <div class="pp-section-header">
                    <div class="pp-section-num">4</div>
                    <h2 class="pp-section-title">{{ __('messagess.privacy_s4_title') }}</h2>
                </div>
                <p class="pp-text">{{ __('messagess.privacy_s4_intro') }}</p>
                <ul class="pp-list">
                    <li>{{ __('messagess.privacy_s4_item1') }}</li>
                    <li>{{ __('messagess.privacy_s4_item2') }}</li>
                    <li>{{ __('messagess.privacy_s4_item3') }}</li>
                </ul>
                <p class="pp-text">{{ __('messagess.privacy_s4_note') }}</p>
            </div>

            {{-- Section 5: Data Protection --}}
            <div class="pp-section" id="pp-section-5">
                <div class="pp-section-header">
                    <div class="pp-section-num">5</div>
                    <h2 class="pp-section-title">{{ __('messagess.privacy_s5_title') }}</h2>
                </div>
                <p class="pp-text">{{ __('messagess.privacy_s5_intro') }}</p>
                <ul class="pp-list">
                    <li>{{ __('messagess.privacy_s5_threat1') }}</li>
                    <li>{{ __('messagess.privacy_s5_threat2') }}</li>
                    <li>{{ __('messagess.privacy_s5_threat3') }}</li>
                </ul>
                <p class="pp-text">{{ __('messagess.privacy_s5_measures_intro') }}</p>
                <ul class="pp-list">
                    <li>{{ __('messagess.privacy_s5_measure1') }}</li>
                    <li>{{ __('messagess.privacy_s5_measure2') }}</li>
                    <li>{{ __('messagess.privacy_s5_measure3') }}</li>
                </ul>
            </div>

            {{-- Section 6: Data Retention --}}
            <div class="pp-section" id="pp-section-6">
                <div class="pp-section-header">
                    <div class="pp-section-num">6</div>
                    <h2 class="pp-section-title">{{ __('messagess.privacy_s6_title') }}</h2>
                </div>
                <p class="pp-text">{{ __('messagess.privacy_s6_intro') }}</p>
                <ul class="pp-list">
                    <li>{{ __('messagess.privacy_s6_item1') }}</li>
                    <li>{{ __('messagess.privacy_s6_item2') }}</li>
                    <li>{{ __('messagess.privacy_s6_item3') }}</li>
                </ul>
            </div>

            {{-- Section 7: Your Rights --}}
            <div class="pp-section" id="pp-section-7">
                <div class="pp-section-header">
                    <div class="pp-section-num">7</div>
                    <h2 class="pp-section-title">{{ __('messagess.privacy_s7_title') }}</h2>
                </div>
                <p class="pp-text">{{ __('messagess.privacy_s7_intro') }}</p>
                <ul class="pp-list">
                    <li>{{ __('messagess.privacy_s7_right1') }}</li>
                    <li>{{ __('messagess.privacy_s7_right2') }}</li>
                    <li>{{ __('messagess.privacy_s7_right3') }}</li>
                    <li>{{ __('messagess.privacy_s7_right4') }}</li>
                    <li>{{ __('messagess.privacy_s7_right5') }}</li>
                    <li>{{ __('messagess.privacy_s7_right6') }}</li>
                </ul>
            </div>

            {{-- Section 8: External Links --}}
            <div class="pp-section" id="pp-section-8">
                <div class="pp-section-header">
                    <div class="pp-section-num">8</div>
                    <h2 class="pp-section-title">{{ __('messagess.privacy_s8_title') }}</h2>
                </div>
                <p class="pp-text">{{ __('messagess.privacy_s8_text') }}</p>
            </div>

            {{-- Section 9: Changes to Policy --}}
            <div class="pp-section" id="pp-section-9">
                <div class="pp-section-header">
                    <div class="pp-section-num">9</div>
                    <h2 class="pp-section-title">{{ __('messagess.privacy_s9_title') }}</h2>
                </div>
                <p class="pp-text">{{ __('messagess.privacy_s9_text') }}</p>
            </div>

            {{-- Consent Notice --}}
            <div class="pp-consent">
                <div class="pp-consent-content">
                    <h3><i class="bi bi-check-circle-fill"></i> {{ __('messagess.privacy_consent_title') }}</h3>
                    <p>{{ __('messagess.privacy_consent_text') }}</p>
                </div>
            </div>

            {{-- Back to Top --}}
            <div class="pp-back-top">
                <a href="#privacy-hero">
                    <i class="bi bi-arrow-up-circle"></i>
                    {{ __('messagess.privacy_back_to_top') }}
                </a>
            </div>

        </div>
    </main>

@endsection

@section('scripts')
<script>
    // Smooth scroll for anchor links
    document.querySelectorAll('.pp-back-top a').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({ behavior: 'smooth' });
            }
        });
    });
</script>
@endsection
