<div class="booking-activity-log-container p-2" dir="rtl">
    <!-- Header Summary Card -->
    <div class="card border shadow-sm mb-3 bg-light-subtle">
        <div class="card-body p-3">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="avatar avatar-50 rounded-circle bg-primary text-white d-flex align-items-center justify-content-center shadow-sm">
                        <i class="fa-solid fa-calendar-check fa-lg"></i>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2">
                            <h5 class="mb-0 fw-bold">حجز رقم #{{ $summary['booking_id'] }}</h5>
                            <span class="badge bg-{{ $summary['status_color'] }} px-2 py-1">
                                <i class="{{ $summary['status_icon'] }} me-1"></i> {{ $summary['status_label'] }}
                            </span>
                            @if($summary['is_deleted'])
                                <span class="badge bg-danger px-2 py-1">
                                    <i class="fa-solid fa-trash me-1"></i> محذوف نهائياً
                                </span>
                            @endif
                        </div>
                        <div class="text-muted small mt-1">
                            <span><i class="fa-solid fa-user me-1 text-primary"></i> <strong>العميل:</strong> {{ $summary['customer_name'] }} ({{ $summary['customer_phone'] }})</span>
                            <span class="mx-2">•</span>
                            <span><i class="fa-solid fa-store me-1 text-primary"></i> <strong>الفرع:</strong> {{ $summary['branch_name'] }}</span>
                        </div>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="refreshActivityLog({{ $summary['booking_id'] }})" title="تحديث السجل">
                        <i class="fa-solid fa-rotate me-1"></i> تحديث
                    </button>
                    <span class="badge bg-dark rounded-pill px-3 py-2">
                        إجمالي الحركات: {{ $summary['total_activities_count'] }}
                    </span>
                </div>
            </div>

            <!-- Key Audit Stats Row -->
            <div class="row g-2 mt-2 pt-2 border-top">
                <!-- Creator Info -->
                <div class="col-md-4 col-sm-6">
                    <div class="p-2 border rounded bg-white h-100 d-flex align-items-center gap-2">
                        <img src="{{ $summary['created_by']['avatar'] }}" alt="avatar" class="avatar avatar-35 rounded-circle border flex-shrink-0" onerror="this.src='/images/user.png'">
                        <div class="overflow-hidden">
                            <div class="d-flex align-items-center gap-1">
                                <span class="small text-muted">من أنشأ الحجز:</span>
                                <span class="{{ $summary['created_by']['role_badge_class'] }} py-0 px-1" style="font-size: 0.7rem;">{{ $summary['created_by']['role'] }}</span>
                            </div>
                            <div class="fw-bold text-truncate small" title="{{ $summary['created_by']['name'] }}">{{ $summary['created_by']['name'] }}</div>
                            <div class="text-muted" style="font-size: 0.75rem;">{{ $summary['created_at'] }} ({{ $summary['created_time_ago'] }})</div>
                        </div>
                    </div>
                </div>

                <!-- Last Updated Info -->
                <div class="col-md-4 col-sm-6">
                    <div class="p-2 border rounded bg-white h-100 d-flex align-items-center gap-2">
                        <img src="{{ $summary['updated_by']['avatar'] }}" alt="avatar" class="avatar avatar-35 rounded-circle border flex-shrink-0" onerror="this.src='/images/user.png'">
                        <div class="overflow-hidden">
                            <div class="d-flex align-items-center gap-1">
                                <span class="small text-muted">آخر تعديل بواسطة:</span>
                                <span class="{{ $summary['updated_by']['role_badge_class'] }} py-0 px-1" style="font-size: 0.7rem;">{{ $summary['updated_by']['role'] }}</span>
                            </div>
                            <div class="fw-bold text-truncate small" title="{{ $summary['updated_by']['name'] }}">{{ $summary['updated_by']['name'] }}</div>
                            <div class="text-muted" style="font-size: 0.75rem;">{{ $summary['updated_at'] }} ({{ $summary['updated_time_ago'] }})</div>
                        </div>
                    </div>
                </div>

                <!-- Deletion Info or Payment Info -->
                <div class="col-md-4 col-sm-12">
                    @if($summary['is_deleted'])
                        <div class="p-2 border border-danger-subtle rounded bg-danger-subtle h-100 d-flex align-items-center gap-2">
                            <img src="{{ $summary['deleted_by']['avatar'] ?? default_user_avatar() }}" alt="avatar" class="avatar avatar-35 rounded-circle border flex-shrink-0" onerror="this.src='/images/user.png'">
                            <div class="overflow-hidden">
                                <div class="d-flex align-items-center gap-1">
                                    <span class="small text-danger fw-bold">حذفه المستخدم:</span>
                                    <span class="badge bg-danger py-0 px-1" style="font-size: 0.7rem;">{{ $summary['deleted_by']['role'] ?? 'مسؤول' }}</span>
                                </div>
                                <div class="fw-bold text-truncate small text-danger" title="{{ $summary['deleted_by']['name'] ?? 'النظام' }}">{{ $summary['deleted_by']['name'] ?? 'النظام' }}</div>
                                <div class="text-muted" style="font-size: 0.75rem;">{{ $summary['deleted_at'] ?? '-' }}</div>
                            </div>
                        </div>
                    @else
                        <div class="p-2 border rounded bg-white h-100 d-flex align-items-center justify-content-between">
                            <div>
                                <span class="small text-muted d-block">حالة الدفع الحالية:</span>
                                <strong>
                                    @if($booking->payment_status == 1)
                                        <span class="text-success"><i class="fa-solid fa-check-circle me-1"></i> مدفوع بالكامل</span>
                                    @else
                                        <span class="text-warning"><i class="fa-solid fa-clock me-1"></i> غير مدفوع</span>
                                    @endif
                                </strong>
                            </div>
                            <div class="text-end">
                                <span class="small text-muted d-block">المبلغ:</span>
                                <span class="badge bg-soft-primary fs-6">{{ Currency::format($booking->services->sum('service_price')) }}</span>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Buttons Bar -->
    <div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
        <span class="small fw-bold text-muted me-1"><i class="fa-solid fa-filter me-1"></i> تصفية السجل:</span>
        <button type="button" class="btn btn-sm btn-outline-dark active filter-btn" data-filter="all">الكل ({{ count($activities) }})</button>
        <button type="button" class="btn btn-sm btn-outline-success filter-btn" data-filter="created">إنشاء الحجز</button>
        <button type="button" class="btn btn-sm btn-outline-info filter-btn" data-filter="status_changed">تغيير الحالة</button>
        <button type="button" class="btn btn-sm btn-outline-warning filter-btn" data-filter="updated">التعديلات</button>
        <button type="button" class="btn btn-sm btn-outline-danger filter-btn" data-filter="deleted">الحذف والاسترجاع</button>
        <button type="button" class="btn btn-sm btn-outline-primary filter-btn" data-filter="payment_updated">المدفوعات</button>
    </div>

    <!-- Timeline Wrapper -->
    <div class="activity-timeline-wrapper position-relative px-2 py-1" style="max-height: 520px; overflow-y: auto;">
        @if(empty($activities))
            <div class="alert alert-info text-center py-4">
                <i class="fa-solid fa-circle-info fa-2x mb-2 text-info"></i>
                <h6 class="fw-bold">لا توجد سجلات نشاط مسجلة لهذا الحجز بعد.</h6>
                <p class="small text-muted mb-0">تم إنشاء هذا الحجز في {{ $summary['created_at'] }} بواسطة {{ $summary['created_by']['name'] }}.</p>
            </div>
        @else
            <div class="timeline position-relative">
                @foreach($activities as $activity)
                    @php
                        $filterCategory = match($activity['event']) {
                            'created' => 'created',
                            'status_changed' => 'status_changed',
                            'updated' => 'updated',
                            'deleted', 'restored' => 'deleted',
                            'payment_updated' => 'payment_updated',
                            default => 'other'
                        };
                    @endphp
                    <div class="timeline-item mb-3 activity-card" data-category="{{ $filterCategory }}">
                        <div class="card border border-{{ $activity['badge_color'] }}-subtle shadow-sm hover-shadow transition">
                            <div class="card-header bg-{{ $activity['badge_color'] }}-subtle py-2 px-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-{{ $activity['badge_color'] }} d-flex align-items-center gap-1 px-2 py-1">
                                        <i class="{{ $activity['icon'] }}"></i> {{ $activity['event_badge'] }}
                                    </span>
                                    <span class="fw-bold text-dark small">{{ $activity['description'] }}</span>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="text-muted small" title="{{ $activity['created_at'] }}">
                                        <i class="fa-regular fa-clock me-1"></i> {{ $activity['formatted_date'] }}
                                        <span class="badge bg-light text-secondary ms-1">({{ $activity['time_ago'] }})</span>
                                    </span>
                                </div>
                            </div>
                            <div class="card-body p-3">
                                <!-- Causer Info Bar -->
                                <div class="d-flex align-items-center justify-content-between pb-2 mb-2 border-bottom flex-wrap gap-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <img src="{{ $activity['causer']['avatar'] }}" alt="avatar" class="avatar avatar-30 rounded-circle border" onerror="this.src='/images/user.png'">
                                        <div>
                                            <span class="fw-bold small">{{ $activity['causer']['name'] }}</span>
                                            <span class="{{ $activity['causer']['role_badge_class'] }} ms-1" style="font-size: 0.72rem;">{{ $activity['causer']['role'] }}</span>
                                            @if(!empty($activity['causer']['mobile']) && $activity['causer']['mobile'] !== '-')
                                                <small class="text-muted ms-2"><i class="fa-solid fa-phone me-1"></i>{{ $activity['causer']['mobile'] }}</small>
                                            @endif
                                        </div>
                                    </div>
                                    @if(!empty($activity['properties']['ip_address']))
                                        <div class="text-muted small" style="font-size: 0.75rem;">
                                            <i class="fa-solid fa-network-wired me-1"></i> IP: {{ $activity['properties']['ip_address'] }}
                                        </div>
                                    @endif
                                </div>

                                <!-- Event Details Body -->
                                @if($activity['event'] === 'status_changed')
                                    <div class="p-2 rounded bg-light border d-flex align-items-center justify-content-center gap-3">
                                        <div class="text-center">
                                            <small class="text-muted d-block">الحالة السابقة</small>
                                            <span class="badge bg-secondary px-3 py-2 fs-7">{{ $activity['properties']['old_status_label'] ?? $activity['properties']['old_status'] ?? '-' }}</span>
                                        </div>
                                        <div class="text-primary fs-4">
                                            <i class="fa-solid fa-arrow-left"></i>
                                        </div>
                                        <div class="text-center">
                                            <small class="text-muted d-block">الحالة الجديدة</small>
                                            <span class="badge bg-success px-3 py-2 fs-7">{{ $activity['properties']['new_status_label'] ?? $activity['properties']['new_status'] ?? '-' }}</span>
                                        </div>
                                    </div>
                                    @if(!empty($activity['properties']['reason']))
                                        <div class="mt-2 small text-muted">
                                            <strong>سبب التغيير / ملاحظة:</strong> {{ $activity['properties']['reason'] }}
                                        </div>
                                    @endif

                                @elseif($activity['event'] === 'created')
                                    <div class="row g-2 small">
                                        <div class="col-md-6">
                                            <div class="p-2 border rounded bg-light-subtle">
                                                <strong><i class="fa-solid fa-clock me-1 text-primary"></i> الموعد:</strong>
                                                {{ $activity['properties']['start_date_time'] ?? '-' }}
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="p-2 border rounded bg-light-subtle">
                                                <strong><i class="fa-solid fa-store me-1 text-primary"></i> الفرع:</strong>
                                                {{ $activity['properties']['branch_name'] ?? '-' }}
                                            </div>
                                        </div>
                                        @if(!empty($activity['properties']['services']))
                                            <div class="col-12">
                                                <div class="p-2 border rounded bg-light-subtle">
                                                    <strong><i class="fa-solid fa-spa me-1 text-primary"></i> الخدمات المحجوزة:</strong>
                                                    <div class="mt-1 d-flex flex-wrap gap-1">
                                                        @foreach($activity['properties']['services'] as $srv)
                                                            <span class="badge bg-soft-primary border">
                                                                {{ $srv['name'] ?? 'خدمة' }} 
                                                                <small class="text-muted">({{ $srv['employee'] ?? 'بدون موظف' }})</small>
                                                            </span>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            </div>
                                        @elseif(!empty($activity['properties']['services_summary']))
                                            <div class="col-12">
                                                <div class="p-2 border rounded bg-light-subtle">
                                                    <strong><i class="fa-solid fa-spa me-1 text-primary"></i> الخدمات المحجوزة:</strong>
                                                    <span>{{ $activity['properties']['services_summary'] }}</span>
                                                </div>
                                            </div>
                                        @endif
                                        @if(!empty($activity['properties']['note']))
                                            <div class="col-12">
                                                <div class="p-2 border rounded bg-light-subtle">
                                                    <strong><i class="fa-solid fa-note-sticky me-1 text-primary"></i> الملاحظات الأولية:</strong>
                                                    <span>{{ $activity['properties']['note'] }}</span>
                                                </div>
                                            </div>
                                        @endif
                                    </div>

                                @elseif($activity['event'] === 'updated' && !empty($activity['changes']))
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered mb-0 align-middle text-center small">
                                            <thead class="table-light">
                                                <tr>
                                                    <th style="width: 28%;" class="text-start">العنصر المعدل</th>
                                                    <th style="width: 36%;" class="text-danger">القيمة السابقة (قبل التعديل)</th>
                                                    <th style="width: 36%;" class="text-success">القيمة الجديدة (بعد التعديل)</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($activity['changes'] as $change)
                                                    <tr>
                                                        <td class="text-start fw-bold text-dark">
                                                            <i class="fa-solid fa-circle-dot me-1 text-warning" style="font-size: 0.6rem;"></i>
                                                            {{ $change['label'] ?? $change['key'] }}
                                                        </td>
                                                        <td class="bg-danger-subtle text-danger text-wrap">
                                                            <del>{{ $change['old'] ?? '-' }}</del>
                                                        </td>
                                                        <td class="bg-success-subtle text-success text-wrap fw-bold">
                                                            {{ $change['new'] ?? '-' }}
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>

                                @elseif($activity['event'] === 'payment_updated')
                                    <div class="p-2 rounded bg-light border d-flex align-items-center justify-content-between flex-wrap gap-2 small">
                                        <div>
                                            <strong>حالة الدفع:</strong>
                                            <span class="badge bg-{{ ($activity['properties']['new_status'] ?? 0) == 1 ? 'success' : 'warning' }} ms-1">
                                                {{ $activity['properties']['new_status_label'] ?? '-' }}
                                            </span>
                                        </div>
                                        <div>
                                            <strong>طريقة الدفع:</strong>
                                            <span class="badge bg-soft-info ms-1">{{ strtoupper($activity['properties']['transaction_type'] ?? 'cash') }}</span>
                                        </div>
                                    </div>

                                @elseif($activity['event'] === 'deleted')
                                    <div class="alert alert-danger mb-0 py-2 small d-flex align-items-center gap-2">
                                        <i class="fa-solid fa-triangle-exclamation fa-lg"></i>
                                        <div>
                                            <strong>تم حذف هذا الحجز نهائياً.</strong>
                                            @if(!empty($activity['properties']['reason']))
                                                <div class="mt-1">السبب: {{ $activity['properties']['reason'] }}</div>
                                            @endif
                                        </div>
                                    </div>

                                @elseif($activity['event'] === 'restored')
                                    <div class="alert alert-success mb-0 py-2 small d-flex align-items-center gap-2">
                                        <i class="fa-solid fa-circle-check fa-lg"></i>
                                        <div>
                                            <strong>تم استرجاع الحجز بنجاح وإعادته إلى قائمة الحجوزات النشطة.</strong>
                                        </div>
                                    </div>

                                @else
                                    <div class="text-muted small">
                                        {{ $activity['description'] }}
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <!-- Modal Footer -->
    <div class="d-flex justify-content-between align-items-center pt-3 mt-2 border-top">
        <span class="small text-muted">
            <i class="fa-solid fa-shield-halved me-1 text-primary"></i> نظام تدقيق وسجل الحجوزات الآمن (JOSPA Audit Log)
        </span>
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">إغلاق</button>
    </div>
</div>

<script>
    // Filtering logic inside modal
    (function() {
        const filterBtns = document.querySelectorAll('.filter-btn');
        const activityCards = document.querySelectorAll('.activity-card');

        filterBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                filterBtns.forEach(b => b.classList.remove('active'));
                this.classList.add('active');

                const filter = this.getAttribute('data-filter');

                activityCards.forEach(card => {
                    const category = card.getAttribute('data-category');
                    if (filter === 'all' || category === filter) {
                        card.style.display = 'block';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });
        });
    })();

    // Refresh activity log function
    function refreshActivityLog(bookingId) {
        const container = document.querySelector('.booking-activity-log-container');
        if (!container) return;

        const originalHtml = container.innerHTML;
        container.innerHTML = '<div class="text-center py-5"><span class="spinner-border text-primary" role="status"></span><p class="mt-2 text-muted small">جاري تحديث السجل...</p></div>';

        fetch('/app/bookings/' + bookingId + '/activity-log?html=1', {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.text())
        .then(html => {
            const modalBody = container.closest('.modal-body') || container.parentElement;
            modalBody.innerHTML = html;
        })
        .catch(err => {
            container.innerHTML = originalHtml;
            alert('حدث خطأ أثناء تحديث السجل.');
        });
    }
</script>
