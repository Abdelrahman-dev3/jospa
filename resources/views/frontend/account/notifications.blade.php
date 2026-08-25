@extends('layouts.frontend-page', ['showProgress' => true, 'showTopNotifications' => true, 'topSpacerHeight' => '71.4px', 'showFooter' => true])

@section('head')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ __('notifications.notifications') }} | {{ app_name() }}</title>

    <link rel="stylesheet" href="{{ mix('css/libs.min.css') }}">
    <link rel="stylesheet" href="{{ mix('css/backend.css') }}">
    @if (language_direction() == 'rtl')<link rel="stylesheet" href="{{ asset('css/rtl.css') }}">@endif
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('custom-css/frontend.css') }}">
    <link rel="stylesheet" href="{{ asset('pages-css/notifications-dropdown.css') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Zain:ital,wght@0,200;0,300;0,400;0,700;0,800;0,900;1,300;1,400&display=swap" rel="stylesheet">
@endsection

@section('content')
<div class="notifications-page">
    <div class="np-header">
        <h4>{{ __('notifications.notifications') }}</h4>
        <button class="np-mark-all" id="npMarkAll">{{ __('notifications.mark_all_read') }}</button>
    </div>

    <div class="np-tabs">
        <button class="np-tab active" data-filter="all">{{ __('notifications.all') }}</button>
        <button class="np-tab" data-filter="unread">{{ __('notifications.unread') }}</button>
    </div>

    <div class="np-list" id="npList">
        <!-- Loaded via JS -->
    </div>

    <button class="np-load-more" id="npLoadMore" style="display:none">{{ __('notifications.view_all') }}</button>
</div>

@endsection

@section('scripts')
<script src="{{ mix('js/backend.js') }}"></script>
<script>
(function() {
    const ICON_MAP = {
        booking: '<i class="fa-solid fa-calendar-check"></i>',
        wallet: '<i class="fa-solid fa-wallet"></i>',
        loyalty: '<i class="fa-solid fa-star"></i>',
        gift: '<i class="fa-solid fa-gift"></i>',
        package: '<i class="fa-solid fa-box-open"></i>',
        coupon: '<i class="fa-solid fa-ticket"></i>',
        welcome: '<i class="fa-solid fa-user-check"></i>',
        default: '<i class="fa-solid fa-bell"></i>'
    };

    function getNotificationIconHtml(icon) {
        if (!icon) return ICON_MAP.default;
        if (typeof icon === 'string' && (icon.includes('fa-') || icon.includes('bi-') || icon.includes('mdi-'))) {
            return `<i class="${icon}"></i>`;
        }
        return ICON_MAP[icon] || ICON_MAP.default;
    }

    let currentPage = 1;
    let lastPage = 1;
    let currentFilter = 'all';
    let allItems = [];

    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        const div = document.createElement('div');
        div.textContent = String(str);
        return div.innerHTML;
    }

    function renderItem(n) {
        return `
            <a href="${n.url || '#'}" class="np-item ${!n.read_at ? 'unread' : ''}">
                <div class="np-icon nd-icon-${n.icon || 'default'}">${getNotificationIconHtml(n.icon)}</div>
                <div class="np-content">
                    <div class="np-title">${escapeHtml(n.title)}</div>
                    <div class="np-message">${escapeHtml(n.message)}</div>
                    <div class="np-time">${escapeHtml(n.time_ago)}</div>
                </div>
            </a>
        `;
    }

    function renderList() {
        const container = document.getElementById('npList');
        let items = allItems;

        if (currentFilter === 'unread') {
            items = allItems.filter(n => !n.read_at);
        }

        if (!items || items.length === 0) {
            container.innerHTML = `
                <div class="np-empty">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                    </svg>
                    <p>{{ __('notifications.no_notifications') }}</p>
                </div>`;
            return;
        }

        container.innerHTML = items.map(renderItem).join('');
    }

    function fetchNotifications(page = 1, append = false) {
        fetch(`{{ route('notifications.index') }}?per_page=20&page=${page}`, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(data => {
            if (data.status) {
                var items = data.data;
                if (items && !Array.isArray(items)) {
                    items = Object.values(items);
                }
                if (append) {
                    allItems = allItems.concat(items);
                } else {
                    allItems = items;
                }
                currentPage = data.pagination.current_page;
                lastPage = data.pagination.last_page;
                renderList();

                const loadMore = document.getElementById('npLoadMore');
                if (loadMore) {
                    loadMore.style.display = currentPage < lastPage ? 'block' : 'none';
                }
            }
        })
        .catch(() => {});
    }

    // Mark all as read
    document.getElementById('npMarkAll').addEventListener('click', function() {
        fetch('{{ route('notifications.mark-all-read') }}', {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(r => r.json())
        .then(() => {
            allItems.forEach(n => n.read_at = new Date().toISOString());
            renderList();
        })
        .catch(() => {});
    });

    // Load more
    document.getElementById('npLoadMore').addEventListener('click', function() {
        fetchNotifications(currentPage + 1, true);
    });

    // Tabs
    document.querySelectorAll('.np-tab').forEach(tab => {
        tab.addEventListener('click', function() {
            document.querySelectorAll('.np-tab').forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            currentFilter = this.dataset.filter;
            renderList();
        });
    });

    // Initial load
    fetchNotifications();
})();
</script>
@endsection
