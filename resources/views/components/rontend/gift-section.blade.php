<section class="position-relative my-5" style="height: calc(100vh - 200px);">
    <img src="{{ asset('images/frontend/gift.webp') }}" alt="{{ __('messages.gift_background_alt') }}"
         class="w-100 h-100 position-absolute top-0 start-0" style="object-fit: cover; z-index: 1; min-height: 500px;">
    <div class="position-absolute top-0 start-0 w-100 h-100" style="background: var(--primary-color); opacity: 0.8; z-index: 2;"></div>
    <div class="position-relative d-flex flex-column align-items-center justify-content-center text-center px-2 px-md-0"
         style="min-height: 500px; z-index: 3;">
        <img src="{{ asset('images/frontend/gift-card-1.svg') }}" alt="{{ __('messages.gift_card_alt') }}" class="gift-card-img-responsive"
             style="width: 80px; margin-bottom: 2rem; filter: brightness(0) invert(1) !important; object-fit: cover;">

        <h2 class="text-white fw-bold mb-3 gift-title-responsive" style="font-size: 3rem;">
            {{ __('messages.gift_section_title') }}
        </h2>

        <div class="text-black mb-4 mt-4 gift-desc-responsive" style="font-size: 1.6rem; max-width: 50%;">
            {{ __('messages.gift_section_description') }}
        </div>

        <div class="mt-5">
            @include('components.frontend.gift-button', [
                'text' => __('messages.choose_gift_now'),
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="30" fill="#ffffff"><path d="M19.5,6h-2.306c.212-.141,.427-.277,.62-.448,1.35-1.285,1.576-2.977,.576-4.31-1.028-1.37-2.979-1.648-4.347-.621-1.047,.785-1.664,1.908-2.031,2.897-.366-.989-.984-2.112-2.031-2.897-1.37-1.027-3.32-.749-4.347,.621-1,1.332-.773,3.024,.589,4.321,.188,.167,.398,.299,.605,.437h-2.328C2.019,6,0,8.019,0,10.5v9c0,2.481,2.019,4.5,4.5,4.5h15c2.481,0,4.5-2.019,4.5-4.5V10.5c0-2.481-2.019-4.5-4.5-4.5ZM14.643,1.422c.93-.697,2.251-.508,2.948,.42,.696,.929,.522,2.045-.453,2.974-.898,.797-2.084,1.185-3.628,1.185h-1.009l.017-.41c.086-.628,.506-2.953,2.126-4.168Zm-7.743,3.405c-.988-.94-1.162-2.057-.466-2.985,.696-.929,2.019-1.118,2.948-.42,1.574,1.179,2.015,3.403,2.118,4.106v.472h-1c-1.533,0-2.714-.388-3.6-1.173Zm-2.4,2.173h6.956c-.444,2.95-4.763,3-4.956,3-.276,0-.5,.225-.499,.501,0,.275,.224,.499,.5,.499,1.626,0,4.381-.584,5.5-2.548,1.119,1.964,3.874,2.548,5.5,2.548,.276,0,.5-.224,.5-.499,0-.276-.223-.5-.499-.501-.194,0-4.512-.05-4.956-3h6.956c1.93,0,3.5,1.57,3.5,3.5v7.5H1v-7.5c0-1.93,1.57-3.5,3.5-3.5Zm15,16H4.5c-1.93,0-3.5-1.57-3.5-3.5v-.5H23v.5c0,1.93-1.57,3.5-3.5,3.5Z"/></svg>',
                'href' => '#'
            ])
        </div>
    </div>
</section>

<style>
@media (max-width: 767.98px) {
    .gift-title-responsive {
        font-size: 1.6rem !important;
    }
    .gift-desc-responsive {
        font-size: 1rem !important;
        max-width: 95% !important;
    }
    .gift-card-img-responsive {
        width: 48px !important;
        margin-bottom: 1rem !important;
    }
}
</style>
