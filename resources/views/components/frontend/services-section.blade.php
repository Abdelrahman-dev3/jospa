<link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('pages-css/services-section.css') }}">
<div id="wifi-loader" style="display:none;">
    <svg class="circle-outer" viewBox="0 0 86 86">
        <circle class="back" cx="43" cy="43" r="40"></circle>
        <circle class="front" cx="43" cy="43" r="40"></circle>
        <circle class="new" cx="43" cy="43" r="40"></circle>
    </svg>
    <svg class="circle-middle" viewBox="0 0 60 60">
        <circle class="back" cx="30" cy="30" r="27"></circle>
        <circle class="front" cx="30" cy="30" r="27"></circle>
    </svg>
    <svg class="circle-inner" viewBox="0 0 34 34">
        <circle class="back" cx="17" cy="17" r="14"></circle>
        <circle class="front" cx="17" cy="17" r="14"></circle>
    </svg>
    <div class="text" data-text="Loading"></div>
</div>

<section class="py-5" style="background: #bf945612">
    <img src="Vector.png" style="position: absolute;left: 0;z-index: 999;height: 280px;" alt="">

    <div id="bookNaw" >
        <h2 class="mb-5 mt-3 text-center" style="font-size: 50px;background: #BF9456;-webkit-background-clip: text;-webkit-text-fill-color: transparent;font-weight: bold;">
            {{ __('messagess.our_service_categories') }}
        </h2>
        @if(isset($categories) && $categories->count() > 0)
            <div class="row s-s-mt-100">
                @foreach($categories as $index => $category)
                    <div class="col-12 col-md-6 col-lg-4" style="height: fit-content;" data-aos="fade-up" data-aos-delay="{{ $index * 100 }}">
                        @include('components.frontend.category-card', [
                            'image' => $category->feature_image,
                            'name' => $category->name,
                            'description' => $category->description,
                            'price_range' => $category->services && $category->services->count() > 0 && $category->services->whereNotNull('default_price')->count() > 0 ?
                                'SR ' . number_format($category->services->whereNotNull('default_price')->min('default_price'), 2) . ' - SR ' . number_format($category->services->whereNotNull('default_price')->max('default_price'), 2) :
                                __('messagess.contact_for_pricing'),
                            'category_id' => $category->id
                        ])
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-center py-5">
                <p class="text-muted">{{ __('messagess.no_service_categories') }}</p>
            </div>
        @endif
    </div>

</section>

<div id="branchContainer" style="display:none !important;"></div>
<br>

<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
    AOS.init({ once: true });
    const currentLang = '{{ app()->getLocale() }}';
    let selectedMainServiceId = null;

    function showLoader() {
        document.getElementById("wifi-loader").style.display = "flex";
    }

    function hideLoader() {
        document.getElementById("wifi-loader").style.display = "none";
    }

    function selectMainService(mainServiceId) {
        selectedMainServiceId = mainServiceId;
          
        showBranchesForMainService(mainServiceId);
    }

    function showBranchesForMainService(mainServiceId) {
        showLoader()
        fetch(`/api/base-branches/?category_id=${mainServiceId}`)
          .then(res => res.json())
          .then(data => {
        
            const container = document.getElementById('branchContainer');
            container.style.position = "fixed";
            container.innerHTML = '';
        
            const closeBtn = document.createElement('button');
            closeBtn.className = 'close-btn';
            closeBtn.innerText = '✖';
            closeBtn.addEventListener('click', () => {
                container.style.setProperty('display', 'none', 'important');
            });
            container.appendChild(closeBtn);
        
            data.branches.forEach(branch => {
        
                const card = document.createElement('div');
                card.className = 'branch-card';
        
                card.innerHTML = `
                    <img src="${branch.feature_image}" 
                         alt="${branch.name[currentLang] || branch.name.en}" 
                         style="width:100%; height:200px; object-fit:cover;">
                         
                    <h5>${branch.name[currentLang] || branch.name.en}</h5>
                    <p>${branch.description}</p>
                `;
        
                card.addEventListener('click', () => {
                    window.location.href = `/salonService?branch_id=${branch.id}&mainService_id=${selectedMainServiceId}`;
                });
        
                container.appendChild(card);
            });
        
            if (data.is_visible == 1) {
        
                const card_H = document.createElement('div');
                card_H.className = 'branch-card';
        
                card_H.innerHTML = `
                    <img src="/images/frontend/jospahomeservises.png"
                         alt="${currentLang == 'ar' ? 'الخدمات المنزلية' : 'Home Service'}"
                         style="width:100%; height:200px; object-fit:cover;">
                         
                    <h5>${currentLang == 'ar' ? 'الخدمات المنزلية' : 'Home Service'}</h5>
                `;
        
                card_H.addEventListener('click', () => {
                    window.location.href = `/HomeService?branch_id=0`;
                });
        
                container.appendChild(card_H);
            }
        
            container.style.display = 'block';
            hideLoader();
          })
          .catch(err => console.error(err));
    }
</script>