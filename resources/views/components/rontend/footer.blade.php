<footer class="pt-5" style="background: url('{{ asset('images/frontend/footer.webp') }}') center center/cover no-repeat; margin-top: 100px;">
    <div class="container text-center py-5">
        <!-- Logo -->
        <img src="{{ asset('images/frontend/LOGO-WHITE.webp') }}" alt="Jo Spa Logo" style="height: 70px; margin-bottom: 2rem;">
        
        <!-- Description -->
        <div class="text-white mb-4" style="font-size: 1.2rem; max-width: 700px; margin: 0 auto;">
            {{ __('frontend.footer_description') }}
        </div>
        
        <!-- Social Icons -->
        <div class="d-flex justify-content-center gap-4 mb-4">
            <!-- (روابط الأيقونات مثل ما هي، ما تحتاج تعديل) -->
            {{-- مثال: --}}
            <a href="#"><svg>...</svg></a>
            <!-- كرر لباقي الأيقونات -->
        </div>
    </div>

    <!-- Second Footer -->
    <div class="w-100 py-3" style="background: #111;">
        <div class="container text-center text-white" style="font-size: 1.1rem;">
            {{ __('frontend.footer_rights', ['domain' => 'jospa-sa.com', 'year1' => 2024, 'year2' => 2025]) }}
        </div>
    </div>
</footer>
