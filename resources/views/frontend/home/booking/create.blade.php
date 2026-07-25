@extends('layouts.booking', ['showTopNotifications' => true, 'showBottomNotifications' => false])

@section('head')
<meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ __('messagess.booking_system') }}</title>
        <link rel="stylesheet" href="{{ mix('css/backend.css') }}">
        @if (language_direction() == 'rtl')
            <link rel="stylesheet" href="{{ asset('css/rtl.css') }}">
        @endif
        
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Lemonada:wght@300;400;500;600;700&display=swap" rel="stylesheet"> 

        <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />

        <!-- Icons -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" rel="stylesheet"/>
        
        <link rel="stylesheet" href="{{ asset('pages-css/h-book-page.css') }}">
@endsection

@section('content')
    <!-- Main Container -->
    <div class="container">
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
        <!-- Content -->
        <div class="content">
            <div id="stepHelpPanel" class="step-help-panel">
                <div class="help-header">
                    <h4 id="helpTitle"></h4>
                    <button onclick="closeHelp()">×</button>
                </div>
            
                <video id="helpVideo" controls loop style="display: none; width: 100%; max-height: 300px;">
                    Sorry, your browser doesn't support embedded videos.
                </video>
            
                <p id="helpDesc" class="help-desc"></p>
            
                <ul id="helpTips" class="help-tips"></ul>
            </div>

            <!-- Progress Bar -->
            <div class="progress-bar">
                <div class="progress-step active" data-progress-step="1">
                    <span class="iconify" data-icon="mdi-light:home" style="font-size: 29px;"></span>
                </div>
                <div class="line"></div>
                <div class="progress-step" data-progress-step="2">
                    <i class="bi bi-list-ul"></i>
                </div>
                <div class="line"></div>
                <div class="progress-step" data-progress-step="3">
                    <i class="bi bi-person"></i>
                </div>
                <div class="line"></div>
                <div class="progress-step" data-progress-step="4">
                    <i class="bi bi-calendar"></i>
                </div>
                <div class="line"></div>
                <div class="progress-step" data-progress-step="5">
                    <i class="bi bi-credit-card"></i>
                </div>
            </div>
            <!-- Step 1: Location -->
            <div id="step1" class="step-content">
                <div class="location-form">
                    <div class="form-group">
                        <label class="top-label"> 
                            {{ __('messagess.location_data') }} 
                        </label>
                        <div class="location-form">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>{{ __('messagess.service_for_name') }}</label>
                                    <input type="text" id="customerName" placeholder="{{ __('messagess.name') }}">
                                </div>
                                <div class="form-group">
                                    <label>{{ __('messagess.mobile_no') }}</label>
                                    <input type="tel" id="mobileNo" placeholder="05*********">
                                    <small class="form-text text-muted">{{ __('messagess.wsb') }}</small>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label>{{ __('messagess.neighbor') }}</label>
                                    <select id="neighborhood">
                                        <option value="">{{ __('messagess.please_select') }}</option>
                                        @foreach($cities as $city)
                                            <option value=" {{ json_decode($city->name, true)[app()->getLocale() ?? 'en'] }}"> {{ json_decode($city->name, true)[app()->getLocale() ?? 'en'] }}</option>
                                        @endforeach  
                                    </select>
                                </div>
                            </div>
            
            
                            <div class="form-group">
                                <label>{{ __('messagess.select_location') }}</label>
                                <button type="button" id="myLocationBtn">{{ __('messagess.my_location') }}</button>
                                <input type="text" id="locationInput" placeholder="{{ __('messagess.location_placeholder') }}" readonly>
                            </div>
                            <div id="map" style="width:100%; height:400px; margin-top:10px;"></div>
            
                        </div>

                    </div>
                </div>
            </div>
            <!-- Step 2: Service & massage -->
            <div id="step2" class="step-content hidden">
                <label class="top-label" style="width: 58%;margin: auto;">
                    {{ __('messagess.select_service_to_book') }}
                    <span class="step-help-btn" data-step="step2" data-help='@json(trans("steps.step2"))'>
                    <i class="bi bi-question-circle"></i>
                    </span>
                </label>
                <br>
                <div class="service-grid">

                </div>
                <div class="massage-cards">

                </div>
                <!-- أيقونة السلة -->
                <div id="cartIcon" class="cart-icon">
                    <span class="iconify" data-icon="mdi-light:cart"></span>
                </div>

                <!-- Side Cart -->
                <div class="sammary-steps cart" id="sideCart">

                </div>

            </div>
            <!-- Step 3: Staff Selection -->
            <div id="step3" class="step-content hidden">
                <label class="top-label" style="width: 58%;margin: auto;"> 
                    {{ __('messagess.select_service_provider') }} 
                    <span class="step-help-btn" data-step="step3" data-help='@json(trans("steps.step3"))'>
                        <i class="bi bi-question-circle"></i>
                    </span>
                </label>
                <br>
                <div class="sammary-steps"></div>
                <div id="staffGrid" class="staff-grid">

                </div>
            </div>
            <!-- Step 4: Date & Time Selection -->
            <div id="step4" class="step-content hidden">
                <label class="top-label" style="width: 58%;margin: auto;">
                    {{ __('messagess.select_time_and_date_for_services') }} 
                    <span class="step-help-btn" data-step="step4" data-help='@json(trans("steps.step4"))'>
                        <i class="bi bi-question-circle"></i>
                    </span>    
                </label>
                <div class="sammary-steps" style="margin: 40px 0;"></div>
                <div class="Date-Time-Mob" style="display: flex;justify-content: space-between;">
                    <div class="calen">
                        <label class="sub-label"> {{ __('messagess.select_preferred_day') }} </label>
                        <div class="calendar">
                            <div class="calendar-header">
                                <button class="calendar-nav" id="prevMonth">‹</button>
                                <div class="calendar-title" id="calendarTitle">{{ __('messagess.month_title') }}</div>
                                <button class="calendar-nav" id="nextMonth">›</button>
                            </div>
                            <div style="overflow: auto;">
                                <div class="calendar-days" id="calendarDays">
                                    <!-- Calendar days will be generated by JavaScript -->
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="times">
                        <label class="sub-label"> {{ __('messagess.select_preferred_time') }} </label>
                        <div class="time-slots">
                            <div class="time-period">
                                <div class="time-toggle">
                                  <button id="morningBtn" class="active">{{ __('messagess.morning') }}</button>
                                  <button id="eveningBtn">{{ __('messagess.afternoon') }}</button>
                                </div>
                                {{-- قبل الظهر --}}
                                <div class="time-section" id="morning-section">
                                    <div class="time-grid" id="morning-grid"></div>
                                </div>
    
                                {{-- بعد الظهر --}}
                                <div class="time-section mt-4" id="afternoon-section">
                                    <div class="time-grid" id="afternoon-grid"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Step 5:  Payment Selection -->
            <div id="step5" class="step-content hidden">
                <div class="booking-payment">
                    
                </div>
            </div>
            
            <div class="step-content hidden2 booking-summary-host" id="summaryCard">

            </div>
            <!-- Navigation -->
            <div class="navigation">
                <button class="btn btn-secondary" id="prevBtn" disabled>{{ __('messagess.previous') }}</button>
                <button class="btn btn-primary" id="nextBtn">{{ __('messagess.next') }}</button>
            </div>
        </div>
    </div>
    <!-- Footer -->
@endsection

@section('scripts')
<script>
        const translations = { next: "{{ __('messagess.next') }}", complete: "{{ __('messagess.complete') }}"};
        
        // Application State
        let currentStep = 1;
        let maxSteps = 5; 
        
        let selectedData = {
            customerName:null,
            mobileNo:null,
            neighborhood:null,
            location: null,
            branch: 0,
            branchName: 'home services',
            services: []  
        };
        
        const summaryCard = document.getElementById('summaryCard');

        // DOM Elements
        const steps = document.querySelectorAll('.step');
        const stepContents = document.querySelectorAll('.step-content');
        const progressSteps = document.querySelectorAll('.progress-step');
        const prevBtn = document.getElementById('prevBtn');
        const nextBtn = document.getElementById('nextBtn');
        const currentLang = "{{ app()->getLocale() }}";
        let activeSubId = null;
        let activeStaffId = null;

        // Initialize Calendar
        let currentDate = new Date();
        
        const prevBn = document.getElementById('prevMonth');
        const nextBn = document.getElementById('nextMonth');
        
        prevBn.addEventListener('click', () => {
            currentDate.setMonth(currentDate.getMonth() - 1);
            generateCalendar(activeSubId, activeStaffId);
        });
        
        nextBn.addEventListener('click', () => {
            currentDate.setMonth(currentDate.getMonth() + 1);
            generateCalendar(activeSubId, activeStaffId);
        });
        
        
        let selectedDate = null;
        
        const cartIcon = document.getElementById('cartIcon');
        const sideCart = document.getElementById('sideCart');
        
        cartIcon.addEventListener('click', () => {
            sideCart.classList.toggle('open');
        });
        
        function initializeApp() {
            updateUI();
            setupEventListeners();
            setupAutoNavigation();
        }

        function updateUI() {
            // Update progress bar
            progressSteps.forEach((step, index) => {
                const stepNumber = parseInt(step.dataset.progressStep);
                step.classList.remove('active', 'completed');

                if (stepNumber < currentStep) {
                    step.classList.add('completed');
                } else if (stepNumber === currentStep) {
                    step.classList.add('active');
                }
            });

            // Update step content
            stepContents.forEach((content, index) => {
                content.classList.toggle('hidden', index + 1 !== currentStep);
            });

            // Update navigation buttons
            prevBtn.disabled = currentStep === 1;
            nextBtn.textContent = currentStep === maxSteps ? translations.complete : translations.next;
        }

        // Add this to your setupEventListeners() function
        document.querySelectorAll('.progress-step').forEach((step, index) => {
            step.addEventListener('click', () => {
                if (index + 1 <= currentStep || index === 0) {
                    currentStep = index + 1;
                    updateUI();
                }
            });
        });

        function setupEventListeners() {
            // Navigation buttons
            prevBtn.addEventListener('click', () => {
                if (currentStep > 1) {
                    currentStep--;
                    updateUI();
                }
            });

            nextBtn.addEventListener('click', () => {
                if (validateCurrentStep()) {
                    if (currentStep < maxSteps) {
                        currentStep++;
                        updateUI();
                    } else {
                        // completeBooking();
                    }
                }
            });
        }

        const morningBtn = document.getElementById('morningBtn');
        const eveningBtn = document.getElementById('eveningBtn');
        const morningTimes = document.getElementById('morning-grid');
        const eveningTimes = document.getElementById('afternoon-grid');
        
        morningBtn.addEventListener('click', () => {
          morningBtn.classList.add('active');
          eveningBtn.classList.remove('active');
          morningTimes.style.display = 'flex';
          eveningTimes.style.display = 'none';
        });
        
        eveningBtn.addEventListener('click', () => {
          eveningBtn.classList.add('active');
          morningBtn.classList.remove('active');
          eveningTimes.style.display = 'flex';
          morningTimes.style.display = 'none';
        });

        function showLoader() {
            document.getElementById("wifi-loader").style.display = "flex";
        }
        
        function hideLoader() {
            document.getElementById("wifi-loader").style.display = "none";
        }
 
        function fetchServiceGroups() {
            showLoader();
            fetch(`/api/service-groups?is_home=1`)
                .then(response => response.json())
                .then(data => {
                    const serviceGrid = document.querySelector('.service-grid');
                    serviceGrid.innerHTML = '';
                    const lang = typeof currentLang !== 'undefined' ? currentLang : 'en';
                    const urlParams = new URLSearchParams(window.location.search);
                    const mainServiceId = urlParams.get('mainService_id');
                    data.forEach(service => {
                        let serviceName = '';
                        try {
                            const parsedName = JSON.parse(service.name);
                            serviceName = parsedName[lang] || parsedName['en'];
                        } catch (e) {
                            serviceName = service.name;
                        }

                        const card = document.createElement('div');
                        const serch = document.createElement('div');
                        card.className = 'service-card';
                        card.dataset.service = service.id;
                        card.innerHTML = `
                            <div class="service-card__media">
                                <img src="${service.image}" alt="${serviceName}">
                            </div>
                            <div class="service-card__body">
                                <span class="service-card__label">${lang === 'ar' ? 'القسم' : 'Category'}</span>
                                <h4 class="service-card__title">${serviceName}</h4>
                            </div>`;
                            
                        card.addEventListener('click', () => {
                        document.querySelectorAll('.service-card').forEach(c => c.classList.remove('selected'));
                        card.classList.add('selected');
                        const exists = selectedData.services.some(s => s.id === service.id);
                        if (!exists) {
                            selectedData.services.push({
                                id: service.id,
                                name: serviceName,
                                image: service.image
                            });
                        }
                        fetchServicesByGroup(service.id);
                    });
                        serviceGrid.appendChild(card);
                        if(mainServiceId && parseInt(mainServiceId) == service.id){
                            card.click();
                        }
                    });
                    hideLoader();
                })
                .catch(error => {
                    console.error('Error fetching services:', error);
                    hideLoader();
                });
        }

        function fetchServicesByGroup(serviceGroupId) {
            showLoader();
            fetch(`/api/services/${serviceGroupId}/${selectedData.branch}/bookings`)
                .then(response => response.json())
                .then(data => {
                    const massageContainer = document.querySelector('.massage-cards');
                    massageContainer.innerHTML = '';
                    const lang = typeof currentLang !== 'undefined' ? currentLang : 'en';

                    if (!document.querySelector('#serviceSearch')) {
                        const searchWrapper = document.createElement('div');
                        searchWrapper.className = 'search-box';
        
                        const searchInput = document.createElement('input');
                        searchInput.type = 'text';
                        searchInput.id = 'serviceSearch';
                        searchInput.placeholder = lang === 'ar' ? 'ابحث عن الخدمة...' : 'Search service...';
        
                        searchWrapper.appendChild(searchInput);
                        massageContainer.parentNode.insertBefore(searchWrapper, massageContainer);
        
                        searchInput.addEventListener('input', function () {
                            const query = this.value.toLowerCase();
                            document.querySelectorAll('.massage-card').forEach(card => {
                                const name = card.querySelector('.massage-name').textContent.toLowerCase();
                                card.style.display = name.includes(query) ? 'block' : 'none';
                            });
                        });
                    }
                    const urlParams = new URLSearchParams(window.location.search);
                    const subServiceId = urlParams.get('subService_id');
                    data.forEach(service => {
                        let serviceName = '';
                        let serviceLocation = '';
                        
                        if (typeof service.name === 'object') {
                            serviceName = service.name[lang] || service.name['en'];
                        } else {
                            try {
                                const parsedName = JSON.parse(service.name);
                                serviceName = parsedName[lang] || parsedName['en'];
                            } catch (e) {
                                serviceName = service.name;
                            }
                        }

                        try {
                            const parsedLocation = JSON.parse(service.location);
                            serviceLocation = parsedLocation[lang] || parsedLocation['en'];
                        } catch (e) {
                            serviceLocation = service.location;
                        }

                        const card = document.createElement('div');
                        card.className = 'massage-card';
                        card.dataset.massage = service.id;
                        card.dataset.main = serviceGroupId;

                        card.innerHTML = `
                            ${service.mostWanted ? `<div class="most-wanted">MOST WANTED</div>` : ''}
                            <div class="massage-name">${serviceName}</div>
                            ${service.description[lang] ? `
                                <div class="massage-location">
                                    <label style="font-size:16px;color: gray;line-height: 1.5;font-style: normal;" class="tooltip-label">
                                        ${lang === 'ar' ? 'الوصف' : 'Description'} 
                                        <i class="fas fa-question-circle"></i>
                                        <span class="tooltip-text">${service.description[lang]}</span>
                                    </label>
                                </div>` : ""}
                            <div class="massage-duration"> <span style="font-weight: 200;"> ${lang === 'ar' ? 'مدة الجلسة :' : 'Session Duration:'} </span> ${service.duration_min} ${lang === 'ar' ? 'دقائق' : 'Minutes'}</div>
                            <div class="massage-price"> <span style="font-weight: 200;"> ${lang === 'ar' ? 'السعر :' : 'price :'} </span>${parseInt(service.default_price)} ${lang === 'ar' ? 'ريال' : 'SAR'}</div>
                        `;
                        card.addEventListener('click', (e) => {
                            if (e.target.classList.contains('massage-book-btn')) return;
                            card.classList.toggle('selected');
                            let parentGroup = selectedData.services.find(s => s.id == serviceGroupId);
                            if (parentGroup) {
                                if (!parentGroup.subServices) {
                                    parentGroup.subServices = [];
                                }
                                const exists = parentGroup.subServices.find(sub => sub.id == service.id);
                                if (exists) {
                                    parentGroup.subServices = parentGroup.subServices.filter(sub => sub.id != service.id);
                                    updateSummarySteps();
                                } else {
                                    parentGroup.subServices.push({
                                        id: service.id,
                                        name: serviceName,
                                        duration: service.duration_min,
                                        price: parseInt(service.default_price)
                                    });
                                    updateSummarySteps();
                                    const minHomeBookingAmount = Number("{{ setting('min_home_booking_amount', 0) }}");
                                    const { total } = getBookingSummaryStats();
                                    if (minHomeBookingAmount <= 0 || total >= minHomeBookingAmount) {
                                        setTimeout(() => {
                                            currentStep++;
                                            updateUI();
                                            updateSummarySteps();
                                        
                                            setTimeout(() => {
                                                const firstSummaryCard = document.querySelector('.sammary-steps .summary-card');
                                                if (firstSummaryCard) {
                                                    firstSummaryCard.click();
                                                }
                                            }, 300);
                                        }, 500);
                                    }

                                }
                            }
                        });
                        massageContainer.appendChild(card);
                        if(subServiceId && parseInt(subServiceId) == service.id){
                            card.click();
                        }
                    });
                    hideLoader();
                })
                .catch(error => {
                    console.error('Error fetching services:', error);
                    hideLoader();
                });
        }

        function fetchStaffMembers(branchId , subserve) {
            showLoader();
            fetch(`/api/staff?branch_id=${branchId}&service_id=${subserve}`)
                .then(response => response.json())
                .then(data => {
                    const staffGrid = document.getElementById('staffGrid');
                    if (!staffGrid) {
                        console.error('ما في عنصر بالـ id = "staffGrid"');
                        return;
                    }
                    staffGrid.innerHTML = '';

                    if (data.length === 0) {
                        const noStaffMessage = document.createElement('div');
                        noStaffMessage.className = 'no-staff-message';
                        noStaffMessage.innerText = 'لا يوجد موظفين متاحين لهذه الخدمة';
                        staffGrid.appendChild(noStaffMessage);
                        hideLoader();
                        return;
                    }


                    data.forEach(staff => {
                        const card = document.createElement('div');
                        card.className = 'staff-card';
                        card.dataset.staff = staff.id;
                        card.dataset.subserve = subserve;
                        const fullName = staff.full_name || `${staff.first_name || ''} ${staff.last_name || ''}`;
                        const initials = fullName.trim().split(' ').map(word => word[0]).join('').substring(0, 2).toUpperCase();

                        card.innerHTML = `
                            <div class="staff-avatar" style="background: linear-gradient(45deg, ${staff.color1 || '#4a90e2'}, ${staff.color2 || '#7b68ee'}); display: flex; align-items: center; justify-content: center; color: white; font-size: 24px;">
                                ${initials}
                            </div>
                            <div class="staff-name">${fullName}</div>
                        `;

                        card.addEventListener('click', () => {
                            document.querySelectorAll('.staff-card').forEach(c => c.classList.remove('selected'));
                            card.classList.add('selected');
                        
                            let parentService = selectedData.services.find(s =>
                                s.subServices.some(subSrv => subSrv.id === subserve)
                            );
                        
                            if (parentService) {
                                let currentSub = parentService.subServices.find(subSrv => subSrv.id === subserve);
                                if (currentSub) {
                                    currentSub.staffId = staff.id;
                                    currentSub.staffName = fullName;
                                    updateSummarySteps();
                                }
                            }
                        
                            let count = 0;
                            selectedData.services.forEach(s => count += s.subServices.length);
                        
                            if (count === 1) {
                                currentStep = 4;
                                updateUI();
                        
                                const firstSummaryCard = document.querySelector('.summary-card');
                                if (firstSummaryCard) {
                                    firstSummaryCard.classList.add('selected-card');
                                    generateCalendar(subserve, staff.id);
                                }
                            }
                        });

                        staffGrid.appendChild(card);
                    });
                    hideLoader();
                })
                .catch(error => {
                    console.error('Error fetching staff:', error);
                    hideLoader();
                });
        }

        function generateCalendar(subserve = null , staffId = null) {
            const year = currentDate.getFullYear();
            const month = currentDate.getMonth();
        
            // Update calendar title
            const months = [
                '{{ __("messagess.january") }}',
                '{{ __("messagess.february") }}',
                '{{ __("messagess.march") }}',
                '{{ __("messagess.april") }}',
                '{{ __("messagess.may") }}',
                '{{ __("messagess.june") }}',
                '{{ __("messagess.july") }}',
                '{{ __("messagess.august") }}',
                '{{ __("messagess.september") }}',
                '{{ __("messagess.october") }}',
                '{{ __("messagess.november") }}',
                '{{ __("messagess.december") }}'
            ];
            document.getElementById('calendarTitle').textContent = `${months[month]} ${year}`;
        
            // Days of week names
            const weekDays = [
                '{{ __("messagess.sunday") }}',
                '{{ __("messagess.monday") }}',
                '{{ __("messagess.tuesday") }}',
                '{{ __("messagess.wednesday") }}',
                '{{ __("messagess.thursday") }}',
                '{{ __("messagess.friday") }}',
                '{{ __("messagess.saturday") }}'
            ];
        
            // Generate calendar days
            const daysContainer = document.getElementById('calendarDays');
            daysContainer.innerHTML = '';
        
            const firstDay = new Date(year, month, 1);          
            const lastDay = new Date(year, month + 1, 0);   
        
            for (let d = firstDay.getDate(); d <= lastDay.getDate(); d++) { 
                const date = new Date(year, month, d); 
        
                const dayElement = document.createElement('div');
                dayElement.className = 'calendar-day';
        
                // اسم اليوم
                const dayName = document.createElement('div');
                dayName.className = 'calendar-day-name';
                dayName.textContent = weekDays[date.getDay()];
                dayElement.appendChild(dayName);
        
                // رقم اليوم
                const dayNumber = document.createElement('div');
                dayNumber.className = 'calendar-day-number';
                dayNumber.textContent = d;
                dayElement.appendChild(dayNumber);
        
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                if (date < today) {
                    dayElement.classList.add('disabled');
                } else {
                    dayElement.addEventListener('click', () => {
                        document.querySelectorAll('.calendar-day').forEach(d => d.classList.remove('selected'));
                        dayElement.classList.add('selected');
                    
                        const selectedDateFormatted = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2,'0')}-${String(date.getDate()).padStart(2,'0')}`;
                        selectedDate = new Date(date);

                        if (selectedData.services) {
                            selectedData.services.forEach(service => {
                                service.subServices.forEach(sub => {
                                    if (sub.id == subserve) {
                                        sub.date = selectedDateFormatted;
                                        updateSummarySteps()
                                        fetchAvailableTimes(selectedDateFormatted, staffId , subserve);
                                    }
                                });
                            });
                        }
                    
                        console.log(selectedData);
                        console.log("date selected:", selectedDateFormatted);
                    });

                }
        
                if (selectedDate && date.toDateString() === selectedDate.toDateString()) {
                    dayElement.classList.add('selected');
                }
        
                daysContainer.appendChild(dayElement);
            }
        }
        
        function fetchAvailableTimes(date , staffId , subserve) {
            showLoader();
            let increasing = 30;
            if (selectedData.services) {
                selectedData.services.forEach(service => {
                    if (!service.subServices) return;
                    service.subServices.forEach(sub => {
                        if (sub.id == subserve && sub.duration) {
                            const parsed = parseInt(sub.duration, 10);
                            if (!Number.isNaN(parsed) && parsed > 0) {
                                increasing = parsed;
                            }
                        }
                    });
                });
            }
            fetch(`/api/available/${date}/${staffId}?Increasing=${encodeURIComponent(increasing)}`)
                .then(response => response.json())
                .then(data => {

                    const morningGrid = document.querySelector('#morning-grid');
                    const afternoonGrid = document.querySelector('#afternoon-grid');
                    let hasMorning = false;
                    let hasAfternoon = false;
                    
                    if (!morningGrid || !afternoonGrid) {
                        console.error('❌ عناصر الوقت غير موجودة في الصفحة.');
                        return;
                    }

                    morningGrid.innerHTML = '';
                    afternoonGrid.innerHTML = '';

                    if (data.length === 0) {
                        morningGrid.innerHTML = '<p>لا توجد مواعيد متاحة لهذا اليوم.</p>';
                        afternoonGrid.innerHTML = '<p>لا توجد مواعيد متاحة لهذا اليوم.</p>';
                        hideLoader();
                        return;
                    }

                    data.forEach(time => {
                        const hour = parseInt(time.split(':')[0], 10);
                        const slot = document.createElement('div');
                        slot.className = 'time-slot';
                        slot.textContent = time;
                        slot.dataset.time = time;
            
                        slot.addEventListener('click', () => {
                            document.querySelectorAll('.time-slot').forEach(s => s.classList.remove('selected'));
                            slot.classList.add('selected');
                                selectedData.services.forEach(service => {
                                    service.subServices.forEach(sub => {
                                        if (sub.id == subserve) {
                                            sub.time = time;
                                            updateSummarySteps()
                                        }
                                    });
                                });

                        });
            
                        if (hour < 12) {
                            hasMorning = true;
                            morningGrid.appendChild(slot);
                        } else {
                            hasAfternoon = true;
                            afternoonGrid.appendChild(slot);
                        }
                    });
                    if (!hasMorning) {
                        morningGrid.innerHTML = '<p>لا توجد مواعيد صباحية متاحة.</p>';
                    }
        
                    if (!hasAfternoon) {
                        afternoonGrid.innerHTML = '<p>لا توجد مواعيد مسائية متاحة.</p>';
                    }
                    morningBtn.click()
                    hideLoader();
                })
                .catch(err => {
                    console.error('❌ خطأ أثناء جلب المواعيد:', err);
                    hideLoader();
                });
}

        function updateSummarySteps() {
            const isSummaryStage = summaryCard.classList.contains('show');
            const summaryContainers = document.querySelectorAll('.sammary-steps');
            summaryContainers.forEach(summaryContainer => {
                summaryContainer.innerHTML = '';
            
                summaryContainer.style.display = "grid";
                summaryContainer.style.gridTemplateColumns = "repeat(3, minmax(250px, 1fr))";
                summaryContainer.style.gap = "15px";
        
                if (!selectedData.services || selectedData.services.length === 0) {
                    const noServicesText = currentLang === 'ar' 
                        ? 'لم يتم اختيار أي خدمات بعد' 
                        : 'No services selected yet';
                    summaryContainer.innerHTML = `<p style="color: gray;">${noServicesText}</p>`;
                    return;
                }
        
                selectedData.services.forEach(service => {
                    if (service.subServices && service.subServices.length > 0) {
                        service.subServices.forEach(sub => {
                            const card = document.createElement('div');
                            card.className = 'summary-card';
                            card.style.cssText = `border-radius:10px;padding:20px;background:white;cursor:pointer;transition:0.2s ease-in-out;border: 2px solid #FFFFFF;
                            `;
                            
                            card.onmouseenter = () => card.style.boxShadow = "0 4px 10px rgba(0,0,0,0.1)";
                            card.onmouseleave = () => card.style.boxShadow = "none";
                            
                            const header = `
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                                    <div>
                                        <strong style="font-size:16px; color:#979797;">${service.name}</strong>
                                        <p style="margin:5px 0 0; font-size:14px; color:#979797;">${sub.name}</p>
                                    </div>
                                    <img src="${service.image || 'https://via.placeholder.com/60'}" 
                                         alt="${service.name}" 
                                         style="width:60px; height:60px; border-radius:8px; object-fit:cover;">
                                </div>
                            `;
        
                             const details = `
                            <div style="margin-top:10px;">
                                <label style="font-size:13px; color:#979797;">${currentLang === 'ar' ? 'الموظف:' : 'Staff:'}</label>
                                <input class="OI" type="text" value="${sub.staffName || ''}" disabled
                                       style="width:100%; padding:8px; margin-bottom:10px; border:1px solid #D9D9D9; border-radius:6px; box-sizing:border-box;">
                            
                                <div class="row g-2 justify-content-between">
                                
                                    <div class="col-6 position-relative">
                                        <input type="date"
                                               value="${sub.date || ''}"
                                               disabled
                                               class="form-control pe-5 text-end border rounded-2">
                                
                                        <span class="iconify position-absolute top-50 end-0 translate-middle-y me-2 "
                                              data-icon="uit-calendar"></span>
                                    </div>
                                
                                    <div class="col-6 position-relative">
                                        <input type="time"
                                               value="${sub.time || ''}"
                                               disabled
                                               class="form-control pe-5 text-end  border rounded-2">
                                
                                        <span class="iconify position-absolute top-50 end-0 translate-middle-y me-2 "
                                              data-icon="weui-time-outlined"></span>
                                    </div>
                                
                                </div>

                            
                                <div style="text-align:right; font-weight:bold; color:#CF9233; margin-top:8px;">
                                    ${currentLang === 'ar' ? 'السعر:' : 'Price:'} ${sub.price || 0} ${currentLang === 'ar' ? 'ريال' : 'SAR'}
                                </div>
                            </div>
                            `;
                            let deleteBtn = null;
                            if (!isSummaryStage) {
                                deleteBtn = document.createElement('button');
                                deleteBtn.textContent = currentLang === 'ar' ? '\u062D\u0630\u0641 \u0627\u0644\u062E\u062F\u0645\u0629' : 'Delete Service';
                                deleteBtn.style.cssText = `
                                    margin-top:10px;
                                    background:#e74c3c;
                                    color:white;
                                    border:none;
                                    padding:5px 10px;
                                    border-radius:5px;
                                    cursor:pointer;
                                `;
                                deleteBtn.addEventListener('click', (e) => {
                                    e.stopPropagation();
                                    const serviceCard = document.querySelector(`.massage-card[data-massage="${sub.id}"][data-main="${service.id}"]`);
                                    if (serviceCard) {
                                        serviceCard.classList.remove('selected');
                                    }
                                    service.subServices = service.subServices.filter(subSrv => subSrv.id !== sub.id);
                                    
                                    if (service.subServices.length === 0) {
                                        selectedData.services = selectedData.services.filter(s => s !== service);
                                    }
                                    updateSummarySteps();
                                    updateUI();

                                    if (!selectedData.services || selectedData.services.length === 0) {
                                        currentStep = 2; 
                                        updateUI();
                                    }
                                });
                            }

                            card.innerHTML = header + details;
                            if (deleteBtn) {
                                card.appendChild(deleteBtn);
                            }
        
                            card.addEventListener('click', () => {
                                if (currentStep === 3) {
                                    document.querySelectorAll('.summary-card').forEach(c => c.classList.remove('selected-card'));
                                    card.classList.add('selected-card');
                                    fetchStaffMembers(selectedData.branch, sub.id);
                                } else if(currentStep === 4) {
                                    document.querySelectorAll('.summary-card').forEach(c => c.classList.remove('selected-card'));
                                    card.classList.add('selected-card');
                                    activeSubId = sub.id;
                                    activeStaffId = sub.staffId;
                                    generateCalendar(sub.id , sub.staffId);
                                }
                            });
        
                            summaryContainer.appendChild(card);
                        });
                    }
                });
                if (!isSummaryStage) {
                    const addServiceBtn = document.createElement('button');
                    addServiceBtn.textContent = currentLang === 'ar' ? '\u0627\u0636\u0641 \u062E\u062F\u0645\u0629 \u062C\u062F\u064A\u062F\u0629' : 'Add New Service';
                    addServiceBtn.style.cssText = `
                        margin-top: 15px;
                        background: #4a90e2;
                        color: white;
                        border: none;
                        padding: 8px 15px;
                        border-radius: 6px;
                        cursor: pointer;
                        font-weight: bold;
                    `;
                    addServiceBtn.addEventListener('click', () => {
                        currentStep = 2;
                        updateUI();
                    });
                    
                    summaryContainer.appendChild(addServiceBtn);
                }
            });
        }
        
        function setupAutoNavigation() {
        document.querySelectorAll('.progress-step').forEach(step => {
                step.addEventListener('click', () => {
                    const targetStep = parseInt(step.dataset.progressStep);
                    if (targetStep <= currentStep || targetStep === 1) {
                        currentStep = targetStep;
                        updateUI();
                    }
                });
            }); 
        }

        function validateCurrentStep() {
            switch (currentStep) {
                case 1:
                    const customerName = document.getElementById('customerName').value;
                    const mobileNo = document.getElementById('mobileNo').value;
                    const neighborhood = document.getElementById('neighborhood').value;
                    const locationInput = document.getElementById('locationInput').value;
                    if (!customerName || !mobileNo || !neighborhood || !locationInput) {
                        alert('{{ __("messagess.please_fill_data") }}');
                        return false;
                    }
                    selectedData.customerName = customerName;
                    selectedData.mobileNo = mobileNo;
                    selectedData.neighborhood = neighborhood;
                    selectedData.locationInput = locationInput;
                
                    console.log(selectedData);
                    fetchServiceGroups();
    
                    break;
                case 2:
                    if (!selectedData.services || selectedData.services.length === 0) {
                        const lang = typeof currentLang !== 'undefined' ? currentLang : 'en';
                        const msg = lang === 'ar' ? 'يرجى اختيار خدمة واحدة على الأقل' : 'Please select at least one service';
                        if (typeof createNotify === 'function') {
                            createNotify({ title: lang === 'ar' ? 'تنبيه' : 'Alert', desc: msg });
                        } else {
                            alert(msg);
                        }
                        return false;
                    }
                    const minHomeBookingAmount = Number("{{ setting('min_home_booking_amount', 0) }}");
                    const { total } = getBookingSummaryStats();
                    if (minHomeBookingAmount > 0 && total < minHomeBookingAmount) {
                        const lang = typeof currentLang !== 'undefined' ? currentLang : 'en';
                        const msg = lang === 'ar' 
                            ? `عفواً، الحد الأدنى لحجز الخدمات المنزلية هو ${minHomeBookingAmount} ريال (المجموع الحالي: ${total} ريال)` 
                            : `Sorry, the minimum amount for booking home services is ${minHomeBookingAmount} SAR (Current total: ${total} SAR)`;
                        if (typeof createNotify === 'function') {
                            createNotify({ title: lang === 'ar' ? 'تنبيه' : 'Alert', desc: msg });
                        } else {
                            alert(msg);
                        }
                        return false;
                    }
                    break;
                case 3:
                    let allHaveStaff = selectedData.services.every(service => 
                        (service.subServices || []).every(sub => sub.staffId)
                    );
                    
                    if (!allHaveStaff) {
                      alert("من فضلك اختر موظف لكل خدمة فرعية");
                      return false;
                    }
                    console.log(selectedData)
                    break;
                case 4:
                let allHavetimes = selectedData.services.every(service =>
                  service.subServices.every(sub => sub.time)
                );
                    if (!allHavetimes) {
                        alert('يرجى اختيار وقت');
                        return false;
                    }else{
                        currentStep = 4;
                        showBookingSummary();
                    }
                    nextBtn.textContent = translations.complete;
                    break;
            }
            return true;
        }

        function showBookingSummary() {
            const lang = typeof currentLang !== 'undefined' ? currentLang : 'en';
            summaryCard.classList.remove('hidden');
            summaryCard.classList.add('show');
            summaryCard.innerHTML = `
                <div id="nad-sun" style="z-index: 999;width: 100%;min-height: 1642px;background: #0000008a;position: absolute;top: 84%;left: 50%;transform: translate(-50%, -50%);border-radius: 0 0 401px 401px / 0 0 80px 80px;">
                    <div class="w-100-mob" style="border-radius: 15px;width: 71%;min-height: 900px;background: white;position: absolute;top: 56%;left: 50%;transform: translate(-50%, -50%);padding: 45px;">
                        <div style="text-align: center;margin: 0 0 30px 0px;">
                            <label style="color: black;font-size: 20px;font-weight: bold;">
                                ${lang === 'ar' ? 'ملخص حجز الخدمات من جوسبا ' : 'Summary of service booking from  jospa'} 
                            </label>
                        </div>
                        <div class="sammary-steps"></div>
                            <div class="ti">
                                <label style="text-align: center;font-size: 18px;font-weight: 600;color: #979797;">{{ __('messagess.products_you_may_like') }}</label>
                            </div>
                            @if(isset($suggest) && $suggest->count() > 0)
                            <div class="row g-4">
                                @foreach($suggest as $index => $product)
                                    <div class="col-12 col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="{{ $index * 100 }}">
                                        @include('components.frontend.products-card', [
                                            'image' => $product->feature_image,
                                            'name' => $product->name,
                                            'des' => $product->short_description,
                                            'product_id' => $product->id,
                                            'categories' => $product->categories,
                                            'min_price' => $product->min_price,
                                            'max_price' => $product->max_price,
                                        ])
                                    </div>
                                @endforeach
                            </div>
                            @endif
                            <div class="two-btn">
                            
                                <button class=" dis-btn btn-e btn-filled" onclick="completeBooking('payment')">
                                     ${lang === 'ar' ? 'ادفع الآن' : 'Pay now :'}      
                                     <img src="{{asset('images/icons/vesa.png')}}">
                                </button>
                                <button class=" dis-btn btn-e btn-outline" onclick="completeBooking('cart')">
                                    <img class="mdi-lightcart" src="{{ asset('images/icons/mdi-light-cart.svg') }}" alt="mdi-light:cart">
                                     ${lang === 'ar' ? 'اضافة للسلة' : 'Add to cart'}      
                                </button>
                                
                            </div>
                    </div>
                </div>
                `;
                updateSummarySteps()
        }
                
        function updateSummarySteps() {
            const isSummaryStage = summaryCard.classList.contains('show');
            const summaryContainers = document.querySelectorAll('.sammary-steps');

            summaryContainers.forEach(summaryContainer => {
                summaryContainer.innerHTML = '';
                summaryContainer.classList.add('summary-grid');

                if (!selectedData.services || selectedData.services.length === 0) {
                    const noServicesText = currentLang === 'ar'
                        ? 'لم يتم اختيار أي خدمات بعد'
                        : 'No services selected yet';
                    summaryContainer.innerHTML = `<p class="summary-empty">${noServicesText}</p>`;
                    return;
                }

                selectedData.services.forEach(service => {
                    (service.subServices || []).forEach(sub => {
                        const card = document.createElement('div');
                        card.className = 'summary-card';

                        card.innerHTML = `
                            <div class="summary-card__header">
                                <div>
                                    <h4 class="summary-card__service">${service.name}</h4>
                                    <p class="summary-card__subservice">${sub.name}</p>
                                </div>
                                <img src="${service.image || 'https://via.placeholder.com/60'}" alt="${service.name}" class="summary-card__image">
                            </div>
                            <div class="summary-card__body">
                                <div>
                                    <span class="summary-card__label">${currentLang === 'ar' ? 'الموظفة' : 'Staff'}</span>
                                    <div class="summary-card__field">${sub.staffName || (currentLang === 'ar' ? 'لم يتم الاختيار بعد' : 'Not selected yet')}</div>
                                </div>
                                <div class="summary-card__meta">
                                    <div class="summary-card__meta-item">
                                        <span class="summary-card__meta-title">${currentLang === 'ar' ? 'التاريخ' : 'Date'}</span>
                                        <span class="summary-card__meta-value">${sub.date || '--'}</span>
                                    </div>
                                    <div class="summary-card__meta-item">
                                        <span class="summary-card__meta-title">${currentLang === 'ar' ? 'الوقت' : 'Time'}</span>
                                        <span class="summary-card__meta-value">${sub.time || '--'}</span>
                                    </div>
                                </div>
                                <div class="summary-card__footer">
                                    <div class="summary-card__price">${currentLang === 'ar' ? 'السعر' : 'Price'}: ${sub.price || 0} ${currentLang === 'ar' ? 'ريال' : 'SAR'}</div>
                                </div>
                            </div>
                        `;

                        if (!isSummaryStage) {
                            const deleteBtn = document.createElement('button');
                            deleteBtn.textContent = currentLang === 'ar' ? 'حذف الخدمة' : 'Delete Service';
                            deleteBtn.className = 'summary-card__remove';
                            deleteBtn.addEventListener('click', (e) => {
                                e.stopPropagation();
                                const serviceCard = document.querySelector(`.massage-card[data-massage="${sub.id}"][data-main="${service.id}"]`);
                                if (serviceCard) {
                                    serviceCard.classList.remove('selected');
                                }

                                service.subServices = (service.subServices || []).filter(subSrv => subSrv.id !== sub.id);
                                if ((service.subServices || []).length === 0) {
                                    selectedData.services = selectedData.services.filter(s => s !== service);
                                }

                                updateSummarySteps();
                                updateUI();

                                if (!selectedData.services || selectedData.services.length === 0) {
                                    currentStep = 2;
                                    updateUI();
                                }
                            });
                            card.appendChild(deleteBtn);
                        }

                        card.addEventListener('click', () => {
                            if (currentStep === 3) {
                                document.querySelectorAll('.summary-card').forEach(c => c.classList.remove('selected-card'));
                                card.classList.add('selected-card');
                                fetchStaffMembers(selectedData.branch, sub.id);
                            } else if (currentStep === 4) {
                                document.querySelectorAll('.summary-card').forEach(c => c.classList.remove('selected-card'));
                                card.classList.add('selected-card');
                                activeSubId = sub.id;
                                activeStaffId = sub.staffId;
                                generateCalendar(sub.id, sub.staffId);
                            }
                        });

                        summaryContainer.appendChild(card);
                    });
                });

                if (!isSummaryStage) {
                    const addServiceBtn = document.createElement('button');
                    addServiceBtn.textContent = currentLang === 'ar' ? 'اضف خدمة جديدة' : 'Add New Service';
                    addServiceBtn.className = 'summary-add-service';
                    addServiceBtn.addEventListener('click', () => {
                        currentStep = 2;
                        updateUI();
                    });
                    summaryContainer.appendChild(addServiceBtn);
                }
            });
        }

        function getBookingSummaryStats() {
            let total = 0;
            let servicesCount = 0;

            (selectedData.services || []).forEach(service => {
                (service.subServices || []).forEach(sub => {
                    total += Number(sub.price || 0);
                    servicesCount += 1;
                });
            });

            return { total, servicesCount };
        }

        function getBookingSummaryLocation() {
            return selectedData.locationInput || selectedData.neighborhood || selectedData.branchName || '';
        }

        function closeBookingSummary() {
            summaryCard.classList.remove('show');
            summaryCard.classList.add('hidden2');
            summaryCard.innerHTML = '';
        }

        function showBookingSummary() {
            const lang = typeof currentLang !== 'undefined' ? currentLang : 'en';
            const { total, servicesCount } = getBookingSummaryStats();
            const locationLabel = getBookingSummaryLocation();

            summaryCard.classList.remove('hidden');
            summaryCard.classList.add('show');
            summaryCard.innerHTML = `
                <div class="booking-summary-overlay">
                    <div class="booking-summary-sheet">
                        <div class="booking-summary-header">
                            <div>
                                <span class="booking-summary-kicker">${lang === 'ar' ? 'مراجعة الحجز' : 'Booking review'}</span>
                                <h2 class="booking-summary-title">${lang === 'ar' ? 'تفاصيل الفاتورة والحجز' : 'Booking and invoice summary'}</h2>
                                <p class="booking-summary-subtitle">
                                    ${lang === 'ar'
                                        ? 'راجعي الخدمات المختارة وموقع الزيارة قبل إتمام العملية أو إضافتها إلى السلة.'
                                        : 'Review the selected services and visit location before paying or adding them to the cart.'}
                                </p>
                            </div>
                            <button type="button" class="booking-summary-close" onclick="closeBookingSummary()">&times;</button>
                        </div>
                        <div class="booking-summary-body">
                            <div class="booking-summary-top">
                                <div class="sammary-steps"></div>
                                <div class="booking-summary-total">
                                    <span class="booking-summary-total-label">${lang === 'ar' ? 'إجمالي الفاتورة' : 'Invoice total'}</span>
                                    <strong class="booking-summary-total-amount">${total.toFixed(0)} ${lang === 'ar' ? 'ريال' : 'SAR'}</strong>
                                    <div class="booking-summary-total-meta">
                                        <span class="booking-summary-total-pill">${servicesCount} ${lang === 'ar' ? 'خدمة' : 'service(s)'}</span>
                                        ${locationLabel ? `<span class="booking-summary-total-pill">${locationLabel}</span>` : ''}
                                    </div>
                                </div>
                            </div>
                            <div class="booking-summary-products">
                                <div class="booking-summary-products-title">{{ __('messagess.products_you_may_like') }}</div>
                            @if(isset($suggest) && $suggest->count() > 0)
                            <div class="row g-4">
                                @foreach($suggest as $index => $product)
                                    <div class="col-12 col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="{{ $index * 100 }}">
                                        @include('components.frontend.products-card', [
                                            'image' => $product->feature_image,
                                            'name' => $product->name,
                                            'des' => $product->short_description,
                                            'product_id' => $product->id,
                                            'categories' => $product->categories,
                                            'min_price' => $product->min_price,
                                            'max_price' => $product->max_price,
                                        ])
                                    </div>
                                @endforeach
                            </div>
                            @endif
                            </div>
                            <div class="booking-summary-actions">
                                <button class="dis-btn btn-e btn-outline" onclick="closeBookingSummary()">
                                    ${lang === 'ar' ? 'تعديل الحجز' : 'Edit booking'}
                                </button>
                                <button class="dis-btn btn-e btn-outline" onclick="completeBooking('cart')">
                                    <img class="mdi-lightcart" src="{{ asset('images/icons/mdi-light-cart.svg') }}" alt="mdi-light:cart">
                                    ${lang === 'ar' ? 'اضافة للسلة' : 'Add to cart'}
                                </button>
                                <button class="dis-btn btn-e btn-filled" onclick="completeBooking('payment')">
                                    ${lang === 'ar' ? 'ادفع الآن' : 'Pay now'}
                                    <img src="{{asset('images/icons/vesa.png')}}">
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            updateSummarySteps()
        }

        function completeBooking(btn) {
            const minHomeBookingAmount = Number("{{ setting('min_home_booking_amount', 0) }}");
            const { total } = getBookingSummaryStats();
            if (minHomeBookingAmount > 0 && total < minHomeBookingAmount) {
                const lang = typeof currentLang !== 'undefined' ? currentLang : 'en';
                const msg = lang === 'ar' 
                    ? `عفواً، الحد الأدنى لحجز الخدمات المنزلية هو ${minHomeBookingAmount} ريال` 
                    : `Sorry, the minimum amount for booking home services is ${minHomeBookingAmount} SAR`;
                createNotify({ title: lang === 'ar' ? 'تنبيه' : 'Alert', desc: msg });
                return;
            }

            const payload = {
                ...selectedData, 
                btn_value: btn
            };
            fetch(`/cart`, {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json', 
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content 
              },
              body: JSON.stringify(payload)
            })
            .then(response => response.json())
            .then(data => {
                document.querySelectorAll('.dis-btn').forEach(btn => {
                    btn.disabled = true;
                    btn.style.opacity = '0.6';
                    btn.style.cursor = 'not-allowed';
                });

                if (data.need_login == true) {
                    createNotify({ title: 'تم تحويل الحجز إلى حجز مؤقت', desc: 'برجاء تسجيل الدخول لتحويل الحجز إلى حجز دائم'
                    });
                    setTimeout(() => {
                        window.location.href = '/signin'; 
                    }, 3000);
                }

                if(btn == 'cart'){
                    createNotify({ title: 'تمت العملية بنجاح', desc: data.message  });
                      setTimeout(() => {
                        window.location.href = '/cart'; 
                      }, 3000);
                }
                if(btn == 'payment' && !data.need_login){
                      setTimeout(() => {
                        window.location.href = '/payment?ids=1'; 
                      }, 1500);
                }
            
            })
            .catch(error => {
              console.error('❌ خطأ أثناء الإرسال:', error);
            });
        }
        // Initialize the application
        document.addEventListener('DOMContentLoaded', initializeApp);
</script>
        
    <script>
        const panel = document.getElementById('stepHelpPanel');
        
        document.querySelectorAll('.step-help-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const data = JSON.parse(btn.dataset.help);
        
                document.getElementById('helpTitle').innerText = data.title;
                document.getElementById('helpDesc').innerText  = data.desc;
        
                const video = document.getElementById('helpVideo');
        
                if (data.video) {
                    video.src = '/' + data.video; 
                    video.style.display = 'block';
                    video.load();  
                    video.play().catch(() => {
                        console.log('Autoplay prevented by browser');
                    });
                } else {
                    video.pause();
                    video.style.display = 'none';
                    video.src = '';
                }
        
                const tipsList = document.getElementById('helpTips');
                tipsList.innerHTML = '';
                data.tips.forEach(tip => {
                    tipsList.innerHTML += `<li>${tip}</li>`;
                });
        
                panel.classList.add('show');
            });
        });
        
        function closeHelp() {
            const video = document.getElementById('helpVideo');
            video.pause(); 
            panel.classList.remove('show');
        }
    </script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    <script>
    const map = L.map('map').setView([24.7136, 46.6753], 8);
    
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);
    
    let marker;
    
    map.on('click', function(e) {
        const lat = e.latlng.lat.toFixed(6);
        const lng = e.latlng.lng.toFixed(6);
    
        if(marker) marker.setLatLng([lat, lng]);
        else marker = L.marker([lat, lng]).addTo(map);
    
        document.getElementById('locationInput').value = lat + ',' + lng;
    });
    
    document.getElementById('myLocationBtn').addEventListener('click', () => {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition((position) => {
                const lat = position.coords.latitude.toFixed(6);
                const lng = position.coords.longitude.toFixed(6);
    
                if(marker) marker.setLatLng([lat, lng]);
                else marker = L.marker([lat, lng]).addTo(map);
    
                map.setView([lat, lng], 15);
                document.getElementById('locationInput').value = lat + ',' + lng;
            }, (err) => {
                alert('تعذر الحصول على موقعك: ' + err.message);
            });
        } else {
            alert('المتصفح لا يدعم تحديد الموقع');
        }
    });
    
    </script>
@endsection
