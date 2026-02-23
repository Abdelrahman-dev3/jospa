<!-- backend -->
@php
    use App\Models\Vartext;

    $gift = Vartext::where('type', 'gift')->first();
    $locale = app()->getLocale();

    $giftTitle = $gift->title[$locale] ?? $gift->title['en'] ?? '';
    $giftDescription = $gift->description[$locale] ?? $gift->description['en'] ?? '';
@endphp

<!-- style -->
<style>
    .gift-section {
        position: relative;
        height: 540px;
    }

    .gift-bg {
        object-fit: cover;
        min-height: 500px;
    }

    .gift-overlay {
        background: #BF945680;
    }

    .main-gift {
        font-weight: bold;
        min-height: 500px;
        z-index: 3;
    }

    .gift-title {
        font-size: 3rem;
        color: #fff;
    }

    .gift-desc {
        font-size: 22px;
        font-weight: 400;
        font-family: 'Almarai', sans-serif;
        color: #fff;
    }

    .b_h {
        transition: all 0.5s ease;
    }

    .b_h:hover {
        background-color: rgba(255,255,255,0.2) !important;
        color: #000 !important;
    }

    /* Responsive */
    @media (max-width: 767.98px) {
        .gift-title {
            font-size: 1.6rem !important;
        }

        .gift-desc {
            font-size: 1rem !important;
            max-width: 95% !important;
        }

        .gift-btn {
            font-size: 0.95rem !important;
            max-width: 320px;
            margin: auto;
        }
    }

    @media (max-width: 576px) {
        .main-gift {
            justify-content: center;
            gap: 14px;
        }

        .gift-desc {
            margin: 0 !important;
        }
    }
    .gift-btn {
        position: relative;
        z-index: 9;
        border-radius: 42px !important;
        border: 1px solid #c69b6d;
        background: #fff;
        max-width: 340px;
        width: 100%;
        height: 58px;
        color: #c69b6d;
        transition: all 0.3s ease;
    }

    .gift-btn span {
        font-size: 22px;
        white-space: nowrap;
        color: inherit;
    }

    .gift-btn:hover {
        background-color: #c69b6d;
        color: #fff;
    }

    .gift-btn:hover .iconify {
        color: #fff !important;
    }
</style>

<!-- html -->
<section class="gift-section">

    <img src="{{ asset('images/pages/gift-bg.png') }}"
         alt="{{ __('messagess.background_alt') }}"
         class="w-100 h-100 position-absolute top-0 start-0 gift-bg">

    <div class="position-absolute top-0 start-0 w-100 h-100 gift-overlay"></div>

    <div class="main-gift position-relative d-flex flex-column align-items-center text-center px-2 px-md-0">

        <span class="iconify"
              data-icon="simple-line-icons:present"
              data-width="60"
              data-height="100"
              style="color:#FFFFFF;margin:65px 0 12px;">
        </span>

        <h2 class="fw-bold mb-3 gift-title">
            {{ $giftTitle }}
        </h2>

        <div class="d-flex justify-content-center align-items-center flex-column gap-4" style="width:48%;height:182px;">
            <div class="gift-desc text-center">
                {{ $giftDescription }}
            </div>
        </div>
    </div>

    <div style="margin-top: -60px;">
        <a href="{{ route('gift.page') }}"
           class="btn gift-btn d-flex align-items-center justify-content-center gap-2 mx-auto px-4">
        
            <span class="iconify"
                  data-icon="basil:present-outline"
                  data-width="40"
                  data-height="37">
            </span>
        
            <span>
                {{ __('messagess.gift_line_4') }}
            </span>
        </a>
    </div>

</section>
