<style>
    .maincard{
        border: 1px solid #BF945633;
        padding: 30px;
        text-align: center;
        height: 85%;
        margin: auto;
    }
    .cardimg{
        width: 70%;
        height: 250px;
        background: #BF94561A;
        display: flex;
        justify-content: center; 
        align-items: center;
        margin: auto; 
    }
    .cardimg img{
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
        display: block;
    }

    .Category_Name{
        font-size: 30px;
        font-weight: 300 !important;
        font-family: 'Almarai', sans-serif !important;
        color: #BF9456;
    }
    .Category_desc{
        font-size: 20px;
        font-weight: 200 !important;
        color: #000000 !important;
        font-family: 'Almarai', sans-serif !important;
    }
    .tooltip-wrapper {
    position: relative;
    display: inline-block;
    cursor: help;
}

    .tooltip-wrapper .tooltip-content {
        position: absolute;
        bottom: 120%; 
        left: 50%;
        transform: translateX(-50%);
        background: #1f1f1f;
        color: #fff;
        padding: 12px 14px;
        border-radius: 10px;
        width: max-content;
        max-width: 320px;
        font-size: 14px;
        line-height: 1.6;
        text-align: center;
        white-space: normal;
    
        opacity: 0;
        pointer-events: none;
        transition: 0.3s ease;
        z-index: 999;
        box-shadow: 0 10px 30px rgba(0,0,0,0.4);
    
        max-height: 200px; 
        overflow-y: auto;  
    }
    
    .tooltip-wrapper .tooltip-content::before {
        content: "";
        position: absolute;
        bottom: -6px;
        left: 50%;
        transform: translateX(-50%);
        border-width: 6px;
        border-style: solid;
        border-color: #1f1f1f transparent transparent transparent;
    }
    
    .tooltip-wrapper:hover .tooltip-content {
        opacity: 1;
        pointer-events: auto;
    }
        /* لجعل التمرير سلس */
        .tooltip-content {
            overflow-y: auto;
            scroll-behavior: smooth; /* smooth scroll */
        }
        
        /* لتخصيص شكل scrollbar في المتصفحات الحديثة */
        .tooltip-content::-webkit-scrollbar {
            width: 8px;
        }
        
        .tooltip-content::-webkit-scrollbar-track {
            background: #f0f0f0;
            border-radius: 8px;
        }
        
        .tooltip-content::-webkit-scrollbar-thumb {
            background-color: #BF9456;
            border-radius: 8px;
            border: 2px solid #f0f0f0;
        }
</style>

<div class="maincard">
    <div class="cardimg">
        <img src="{{ $image ?? asset('images/frontend/card 11.png') }}" alt="{{ $name ?? 'Category' }}">
    </div>
    <div class="cardcontent">
        <h4 class="mt-3 mb-3 Category_Name">{{ $name ?? 'Category Name' }}</h4>
        <div class="tooltip-wrapper">
            <p class="text-muted Category_desc">
                @if(isset($description))
                    {{ \Illuminate\Support\Str::limit($description[app()->getLocale()] ?? '', 90) }}
                @endif
            </p>
            <div class="tooltip-content">
                @if(isset($description))
                    {{ $description[app()->getLocale()] ?? '' }}
                @endif
            </div>
        </div>

    </div>
    <div class="cardbtns">
        @if(isset($category_id))
            <a onclick="selectMainService({{ $category_id ?? 0 }})" class="btn btn-outline-light" style="font-size: 15px;width: 100%;background:#BF9456;font-family: 'Almarai', sans-serif; color: white">{{ __('messagess.bookNow') }}</a>
        @else
            <a href="#" class="btn btn-primary" style="font-family: 'Almarai', sans-serif;width: 100%;background:#BF9456;">{{ __('messagess.details') }}</a>
        @endif
            <br>
        <a href="{{ route('frontend.category.details', $category_id) }}"   class="btn btn-outline-light" style="font-size: 15px;border: 1px solid #BF9456;width: 100%;margin-top:10px;color: #BF9456;font-family: 'Almarai', sans-serif">{{ __('messagess.details') }}</a>
    </div>
</div>
