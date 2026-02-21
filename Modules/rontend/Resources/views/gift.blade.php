<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gift Cards - JOSPA</title>
    <link rel="stylesheet" href="jospa-gift-card.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Cairo', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background-color: #f5f5f5;
    color: #333;
    line-height: 1.6;
    direction: rtl;
    text-align: right;
}

.main-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
    background-color: #ffffff;
    min-height: 100vh;
}

.gift-card-container {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 40px;
    margin-bottom: 30px;
}

/* Product Gallery Styles */
.product-gallery {
    padding: 20px;
}

.main-image-container {
    position: relative;
    margin-bottom: 20px;
}

.main-image-container img {
    width: 100%;
    height: 400px;
    object-fit: cover;
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
}

.close-gallery-btn {
    position: absolute;
    top: 15px;
    left: 15px;
    background: rgba(255, 255, 255, 0.9);
    border: none;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    font-size: 18px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
}

.close-gallery-btn:hover {
    background: white;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}

.thumbnail-container {
    display: flex;
    gap: 10px;
}

.thumbnail-img {
    width: 70px;
    height: 55px;
    object-fit: cover;
    border-radius: 4px;
    cursor: pointer;
    border: 2px solid transparent;
    transition: all 0.3s ease;
    opacity: 0.7;
}

.thumbnail-img:hover,
.thumbnail-img.active {
    border-color: #d4a574;
    opacity: 1;
}

/* Form Container Styles */
.form-container {
    padding: 0 20px;
}

.page-title {
    font-size: 2rem;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 30px;
    text-align: center;
}

.section {
    margin-bottom: 25px;
}

.section-title {
    font-size: 1rem;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 15px;
}

/* Radio Button Styles */
.radio-group {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.radio-item {
    display: flex;
    align-items: center;
    cursor: pointer;
    font-size: 0.9rem;
}

.radio-item input[type="radio"] {
    display: none;
}

.radio-indicator {
    width: 16px;
    height: 16px;
    border: 2px solid #ddd;
    border-radius: 50%;
    margin-left: 10px;
    position: relative;
    transition: all 0.3s ease;
}

.radio-item input[type="radio"]:checked + .radio-indicator {
    border-color: #d4a574;
}

.radio-item input[type="radio"]:checked + .radio-indicator::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 6px;
    height: 6px;
    background: #d4a574;
    border-radius: 50%;
}

.radio-text {
    color: #555;
}

/* Form Input Styles */
.input-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    margin-bottom: 15px;
}

.input-group {
    display: flex;
    flex-direction: column;
}

.input-group.full-width {
    grid-column: 1 / -1;
}

.input-label {
    font-size: 0.85rem;
    font-weight: 500;
    color: #666;
    margin-bottom: 6px;
}

.form-input,
.form-textarea {
    padding: 10px 12px;
    border: 1px solid #e0e0e0;
    border-radius: 4px;
    font-size: 0.9rem;
    font-family: inherit;
    transition: all 0.3s ease;
    background-color: #fff;
}

.form-input:focus,
.form-textarea:focus {
    outline: none;
    border-color: #d4a574;
    box-shadow: 0 0 0 2px rgba(212, 165, 116, 0.1);
}

.form-textarea {
    resize: vertical;
    min-height: 70px;
}

/* Services Container */
.services-container {
    margin-top: 20px;
    margin-bottom: 25px;
}

.service-dropdown {
    border: 1px solid #e0e0e0;
    border-radius: 4px;
    margin-bottom: 2px;
    overflow: hidden;
}

.dropdown-header {
    width: 100%;
    padding: 12px 15px;
    background: #fff;
    border: none;
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
    font-size: 0.85rem;
    font-family: inherit;
    color: #555;
    transition: all 0.3s ease;
}

.dropdown-header:hover {
    background-color: #f8f9fa;
}

.dropdown-header.active {
    background-color: #f1f8ff;
}

.dropdown-arrow {
    transition: transform 0.3s ease;
    font-size: 0.8rem;
    color: #888;
}

.dropdown-header.active .dropdown-arrow {
    transform: rotate(180deg);
}

.dropdown-menu {
    display: none;
    background: #fafbfc;
    border-top: 1px solid #e0e0e0;
}

.dropdown-menu.show {
    display: block;
}

.dropdown-item {
    display: block;
    padding: 10px 15px;
    text-decoration: none;
    color: #666;
    font-size: 0.8rem;
    border-bottom: 1px solid #f0f0f0;
    transition: all 0.3s ease;
}

.dropdown-item:last-child {
    border-bottom: none;
}

.dropdown-item:hover {
    background-color: #e8f4f8;
    color: #2c3e50;
}

/* Service Notes */
.service-notes {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 6px;
    margin: 20px 0;
    border-right: 3px solid #d4a574;
}

.notes-title {
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 8px;
    font-size: 0.9rem;
}

.notes-list {
    list-style: none;
    padding-right: 10px;
}

.notes-list li {
    font-size: 0.8rem;
    color: #666;
    margin-bottom: 6px;
    padding-right: 12px;
    position: relative;
    line-height: 1.4;
}

.notes-list li::before {
    content: '•';
    color: #d4a574;
    position: absolute;
    right: 0;
    top: 0;
}

/* Pricing Summary */
.pricing-summary {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 6px;
    margin: 15px 0;
}

.price-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
    font-size: 0.85rem;
}

.price-row:last-child {
    margin-bottom: 0;
}

.price-label {
    color: #666;
}

.price-value {
    font-weight: 600;
    color: #2c3e50;
}

/* Add to Cart Button */
.add-to-cart-button {
    width: 100%;
    padding: 12px 20px;
    background: linear-gradient(135deg, #d4a574, #c19456);
    color: white;
    border: none;
    border-radius: 6px;
    font-size: 0.95rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    margin-bottom: 15px;
    font-family: inherit;
}

.add-to-cart-button:hover {
    background: linear-gradient(135deg, #c19456, #b08745);
    transform: translateY(-1px);
    box-shadow: 0 6px 20px rgba(196, 148, 86, 0.3);
}

/* View Counter */
.view-counter {
    text-align: center;
    font-size: 0.8rem;
    color: #888;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.view-dots {
    letter-spacing: 2px;
}

/* Footer */
.page-footer {
    text-align: center;
    padding: 15px;
    border-top: 1px solid #eee;
    margin-top: 20px;
}

.footer-link {
    color: #888;
    text-decoration: none;
    font-size: 0.85rem;
}

.footer-link:hover {
    color: #d4a574;
}

/* Responsive Design */
@media (max-width: 1024px) {
    .gift-card-container {
        grid-template-columns: 1fr;
        gap: 30px;
    }

    .main-container {
        padding: 15px;
    }
}

@media (max-width: 768px) {
    .main-container {
        padding: 10px;
    }

    .input-row {
        grid-template-columns: 1fr;
        gap: 10px;
    }

    .page-title {
        font-size: 1.6rem;
    }

    .main-image-container img {
        height: 280px;
    }

    .thumbnail-img {
        width: 60px;
        height: 45px;
    }
}

@media (max-width: 480px) {
    .page-title {
        font-size: 1.4rem;
    }

    .dropdown-header {
        padding: 10px 12px;
        font-size: 0.8rem;
    }

    .form-input,
    .form-textarea {
        padding: 8px 10px;
        font-size: 0.85rem;
    }

    .service-notes {
        padding: 12px;
    }

    .notes-list li {
        font-size: 0.75rem;
    }
}

@media (max-width: 991.98px) {
  .main-container {
    max-width: 98vw;
    padding: 8px 2vw;
  }
  .gift-card-container {
    grid-template-columns: 1fr;
    gap: 18px;
  }
  .product-gallery .main-image-container img {
    height: 220px;
  }
  .form-container {
    padding: 0 4px;
  }
  .input-row {
    grid-template-columns: 1fr;
    gap: 8px;
  }
}
@media (max-width: 600px) {
  .main-container {
    padding: 2px 1vw;
  }
  .gift-card-container {
    gap: 8px;
  }
  .product-gallery .main-image-container img {
    height: 120px;
  }
  .form-container {
    padding: 0 1px;
  }
  .input-row {
    gap: 4px;
  }
}
</style>

</head>
<body>
    <div class="main-container">
        <div class="gift-card-container">
            <!-- Left side - Product Gallery -->
            <div class="product-gallery">
                <div class="main-image-container">
                    <img src="https://images.unsplash.com/photo-1549298916-b41d501d3772?w=600&h=400&fit=crop" alt="JOSPA Gift Card" id="mainProductImage">
                    <button class="close-gallery-btn">&times;</button>
                </div>
                <div class="thumbnail-container">
                    <img src="https://images.unsplash.com/photo-1549298916-b41d501d3772?w=100&h=80&fit=crop" alt="Gift Card View 1" class="thumbnail-img active" onclick="switchMainImage(this)">
                    <img src="https://images.unsplash.com/photo-1513475382585-d06e58bcb0e0?w=100&h=80&fit=crop" alt="Gift Card View 2" class="thumbnail-img" onclick="switchMainImage(this)">
                </div>
            </div>

            <!-- Right side - Form -->
            <div class="form-container">
                <h1 class="page-title">Gift Cards</h1>

                <!-- Delivery Method Section -->
                <div class="section delivery-section">
                    <h3 class="section-title">طريقة الاستلام *</h3>
                    <div class="radio-group">
                        <label class="radio-item">
                            <input type="radio" name="delivery_method" value="traditional" checked>
                            <span class="radio-indicator"></span>
                            <span class="radio-text">بطاقة الهدايا التقليدية</span>
                        </label>
                        <label class="radio-item">
                            <input type="radio" name="delivery_method" value="email">
                            <span class="radio-indicator"></span>
                            <span class="radio-text">استلام عبر البريد</span>
                        </label>
                    </div>
                </div>

                <!-- Card Information Section -->
                <div class="section card-info-section">
                    <h3 class="section-title">معلومات البطاقة</h3>

                    <div class="input-row">
                        <div class="input-group">
                            <label class="input-label">اسم المهدي *</label>
                            <input type="text" class="form-input" required>
                        </div>
                        <div class="input-group">
                            <label class="input-label">اسم المهدى إليه *</label>
                            <input type="text" class="form-input" required>
                        </div>
                    </div>

                    <div class="input-row">
                        <div class="input-group">
                            <label class="input-label">رقم الهاتف المهدي</label>
                            <input type="tel" class="form-input">
                        </div>
                        <div class="input-group">
                            <label class="input-label">رقم الهاتف المهدى إليه</label>
                            <input type="tel" class="form-input">
                        </div>
                    </div>

                    <div class="input-group full-width">
                        <label class="input-label">الخدمات المطلوب تطبيقها (اختياري لا يتجاوز 100 حرف)</label>
                        <textarea class="form-textarea" maxlength="100" placeholder="اكتب رسالة اختيارية..."></textarea>
                    </div>
                </div>

                <!-- Services Selection -->
                <div class="services-container">
                    <h3 class="section-title">اختيار الخدمة</h3>

                    <div class="service-dropdown">
                        <button class="dropdown-header" onclick="toggleServiceDropdown('payments')">
                            <span>الدفعات</span>
                            <span class="dropdown-arrow">▼</span>
                        </button>
                        <div class="dropdown-menu" id="payments">
                            <a href="#" class="dropdown-item">خدمة الدفع الأساسية</a>
                            <a href="#" class="dropdown-item">خدمة الدفع المتقدمة</a>
                        </div>
                    </div>

                    <div class="service-dropdown">
                        <button class="dropdown-header" onclick="toggleServiceDropdown('delivery_services')">
                            <span>خدمات التسليم</span>
                            <span class="dropdown-arrow">▼</span>
                        </button>
                        <div class="dropdown-menu" id="delivery_services">
                            <a href="#" class="dropdown-item">تسليم فوري</a>
                            <a href="#" class="dropdown-item">تسليم مجدول</a>
                        </div>
                    </div>

                    <div class="service-dropdown">
                        <button class="dropdown-header" onclick="toggleServiceDropdown('skincare')">
                            <span>العناية بالبشرة</span>
                            <span class="dropdown-arrow">▼</span>
                        </button>
                        <div class="dropdown-menu" id="skincare">
                            <a href="#" class="dropdown-item">تنظيف البشرة</a>
                            <a href="#" class="dropdown-item">ترطيب البشرة</a>
                            <a href="#" class="dropdown-item">علاج البشرة</a>
                        </div>
                    </div>

                    <div class="service-dropdown">
                        <button class="dropdown-header" onclick="toggleServiceDropdown('facial_care')">
                            <span>العناية بالوجه</span>
                            <span class="dropdown-arrow">▼</span>
                        </button>
                        <div class="dropdown-menu" id="facial_care">
                            <a href="#" class="dropdown-item">ماسك الوجه</a>
                            <a href="#" class="dropdown-item">تقشير الوجه</a>
                        </div>
                    </div>

                    <div class="service-dropdown">
                        <button class="dropdown-header" onclick="toggleServiceDropdown('bleaching')">
                            <span>Bleaching services - خدمات التبييض</span>
                            <span class="dropdown-arrow">▼</span>
                        </button>
                        <div class="dropdown-menu" id="bleaching">
                            <a href="#" class="dropdown-item">تبييض الأسنان</a>
                            <a href="#" class="dropdown-item">تبييض البشرة</a>
                        </div>
                    </div>

                    <div class="service-dropdown">
                        <button class="dropdown-header" onclick="toggleServiceDropdown('wax_services')">
                            <span>خدمات Wax</span>
                            <span class="dropdown-arrow">▼</span>
                        </button>
                        <div class="dropdown-menu" id="wax_services">
                            <a href="#" class="dropdown-item">إزالة الشعر بالشمع</a>
                            <a href="#" class="dropdown-item">تشكيل الحواجب</a>
                        </div>
                    </div>

                    <div class="service-dropdown">
                        <button class="dropdown-header" onclick="toggleServiceDropdown('hair_services')">
                            <span>خدمات الشعر</span>
                            <span class="dropdown-arrow">▼</span>
                        </button>
                        <div class="dropdown-menu" id="hair_services">
                            <a href="#" class="dropdown-item">قص الشعر</a>
                            <a href="#" class="dropdown-item">صبغ الشعر</a>
                            <a href="#" class="dropdown-item">تصفيف الشعر</a>
                        </div>
                    </div>

                    <div class="service-dropdown">
                        <button class="dropdown-header" onclick="toggleServiceDropdown('bath_services')">
                            <span>خدمات الحمام</span>
                            <span class="dropdown-arrow">▼</span>
                        </button>
                        <div class="dropdown-menu" id="bath_services">
                            <a href="#" class="dropdown-item">حمام مغربي</a>
                            <a href="#" class="dropdown-item">حمام تركي</a>
                        </div>
                    </div>

                    <div class="service-dropdown">
                        <button class="dropdown-header" onclick="toggleServiceDropdown('cosmetics')">
                            <span>كوزميتك</span>
                            <span class="dropdown-arrow">▼</span>
                        </button>
                        <div class="dropdown-menu" id="cosmetics">
                            <a href="#" class="dropdown-item">مكياج يومي</a>
                            <a href="#" class="dropdown-item">مكياج سهرة</a>
                        </div>
                    </div>

                    <div class="service-dropdown">
                        <button class="dropdown-header" onclick="toggleServiceDropdown('makeup')">
                            <span>ميك أب Make up</span>
                            <span class="dropdown-arrow">▼</span>
                        </button>
                        <div class="dropdown-menu" id="makeup">
                            <a href="#" class="dropdown-item">مكياج طبيعي</a>
                            <a href="#" class="dropdown-item">مكياج احترافي</a>
                        </div>
                    </div>
                </div>

                <!-- Service Notes -->
                <div class="service-notes">
                    <p class="notes-title">ملاحظة سوفت:</p>
                    <ul class="notes-list">
                        <li>يؤكد الإشعار بقيمة 400 ريال مادياً للخدمة الواحدة وذلك يتم حجز متاح لمدة 35 يوماً 35 ريال</li>
                        <li>إرسال البطاقة الإلكترونية يكون خلال 24 ساعة من إتمام الطلب خلال أوقات العمل من الساعة 11:00صباحاً وحتى الساعة 10:00مساءً</li>
                        <li>في حال طلب الاستلام من المركز نرجو التواصل لتأكيد حجز حضورنا ويكون الاستلام بين الساعة 8:00 صباحاً إلى الساعة 8:00 مساءً</li>
                        <li>للاستفادة من بطاقة الإهداء يجب إبرازها لموظفة الاستقبال ولا يمكن الاستفادة من الخدمة</li>
                    </ul>
                </div>

                <!-- Pricing Section -->
                <div class="pricing-summary">
                    <div class="price-row">
                        <span class="price-label">Options amount:</span>
                        <span class="price-value">0.00 ريال</span>
                    </div>
                    <div class="price-row">
                        <span class="price-label">المجموع الفرعي:</span>
                        <span class="price-value">0.00 ريال</span>
                    </div>
                </div>

                <!-- Add to Cart Button -->
                <button class="add-to-cart-button" onclick="addToCart()">إضافة إلى السلة</button>

                <!-- View Counter -->
                <div class="view-counter">
                    <span class="view-dots">⚪ ⚪ ⚪ ⚪ ⚪</span>
                    <span class="view-text">مشاهدة</span>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="page-footer">
            <div class="footer-content">
                <a href="#" class="footer-link">Gift Cards</a>
            </div>
        </div>
    </div>

    <script>

    // JOSPA Gift Card Interface JavaScript

// Image gallery functionality
function switchMainImage(thumbnailElement) {
    const mainImage = document.getElementById('mainProductImage');
    const allThumbnails = document.querySelectorAll('.thumbnail-img');

    // Update main image source
    const newImageSrc = thumbnailElement.src.replace('w=100&h=80', 'w=600&h=400');
    mainImage.src = newImageSrc;

    // Update active thumbnail
    allThumbnails.forEach(thumb => thumb.classList.remove('active'));
    thumbnailElement.classList.add('active');
}

// Service dropdown functionality
function toggleServiceDropdown(dropdownId) {
    const dropdownMenu = document.getElementById(dropdownId);
    const dropdownHeader = dropdownMenu.previousElementSibling;
    const allDropdowns = document.querySelectorAll('.dropdown-menu');
    const allHeaders = document.querySelectorAll('.dropdown-header');

    // Close all other dropdowns
    allDropdowns.forEach(menu => {
        if (menu.id !== dropdownId) {
            menu.classList.remove('show');
        }
    });

    // Remove active class from all headers
    allHeaders.forEach(header => {
        if (header !== dropdownHeader) {
            header.classList.remove('active');
        }
    });

    // Toggle current dropdown
    dropdownMenu.classList.toggle('show');
    dropdownHeader.classList.toggle('active');
}

// Form validation function
function validateFormData() {
    const requiredInputs = document.querySelectorAll('input[required]');
    let isFormValid = true;

    requiredInputs.forEach(input => {
        if (!input.value.trim()) {
            input.style.borderColor = '#e74c3c';
            input.style.boxShadow = '0 0 0 2px rgba(231, 76, 60, 0.1)';
            isFormValid = false;
        } else {
            input.style.borderColor = '#e0e0e0';
            input.style.boxShadow = 'none';
        }
    });

    return isFormValid;
}

// Add to cart functionality
function addToCart() {
    if (!validateFormData()) {
        showNotificationMessage('يرجى ملء جميع الحقول المطلوبة', 'error');
        return;
    }

    // Collect form data
    const formData = collectFormData();

    // Simulate cart addition
    console.log('تم إضافة بطاقة الهدايا:', formData);

    // Show success message
    showNotificationMessage('تم إضافة بطاقة الهدايا إلى السلة بنجاح!', 'success');

    // Update pricing if needed
    updatePricingDisplay();
}

// Collect all form data
function collectFormData() {
    const deliveryMethod = document.querySelector('input[name="delivery_method"]:checked')?.value;
    const senderName = document.querySelectorAll('.form-input')[0]?.value;
    const recipientName = document.querySelectorAll('.form-input')[1]?.value;
    const senderPhone = document.querySelectorAll('.form-input')[2]?.value;
    const recipientPhone = document.querySelectorAll('.form-input')[3]?.value;
    const serviceMessage = document.querySelector('.form-textarea')?.value;

    const selectedServices = Array.from(document.querySelectorAll('.dropdown-item.selected'))
        .map(item => item.textContent.trim());

    return {
        deliveryMethod,
        senderName,
        recipientName,
        senderPhone,
        recipientPhone,
        serviceMessage,
        selectedServices,
        timestamp: new Date().toISOString()
    };
}

// Show notification messages
function showNotificationMessage(message, type = 'info') {
    // Remove any existing notifications
    const existingNotification = document.querySelector('.custom-notification');
    if (existingNotification) {
        existingNotification.remove();
    }

    // Create notification element
    const notification = document.createElement('div');
    notification.className = `custom-notification ${type}`;

    const colors = {
        success: '#27ae60',
        error: '#e74c3c',
        info: '#3498db',
        warning: '#f39c12'
    };

    notification.innerHTML = `
        <div class="notification-content">
            <span class="notification-text">${message}</span>
            <button class="notification-close" onclick="closeNotification(this)">&times;</button>
        </div>
    `;

    // Style the notification
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${colors[type] || colors.info};
        color: white;
        padding: 15px 20px;
        border-radius: 6px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
        z-index: 1000;
        max-width: 350px;
        font-family: 'Cairo', sans-serif;
        animation: slideInRight 0.3s ease;
        direction: rtl;
    `;

    // Add notification styles if not already present
    if (!document.querySelector('#notification-styles')) {
        const style = document.createElement('style');
        style.id = 'notification-styles';
        style.textContent = `
            @keyframes slideInRight {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            @keyframes slideOutRight {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(100%); opacity: 0; }
            }
            .notification-content {
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 15px;
            }
            .notification-close {
                background: none;
                border: none;
                color: white;
                font-size: 20px;
                cursor: pointer;
                padding: 0;
                width: 25px;
                height: 25px;
                display: flex;
                align-items: center;
                justify-content: center;
                border-radius: 50%;
                transition: background-color 0.3s ease;
            }
            .notification-close:hover {
                background-color: rgba(255, 255, 255, 0.2);
            }
        `;
        document.head.appendChild(style);
    }

    document.body.appendChild(notification);

    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            closeNotification(notification.querySelector('.notification-close'));
        }
    }, 5000);
}

// Close notification
function closeNotification(button) {
    const notification = button.closest('.custom-notification');
    notification.style.animation = 'slideOutRight 0.3s ease';
    setTimeout(() => {
        if (notification.parentNode) {
            notification.remove();
        }
    }, 300);
}

// Update pricing display
function updatePricingDisplay() {
    const selectedServices = document.querySelectorAll('.dropdown-item.selected');
    const basePrice = 400; // Base gift card price in SAR
    let optionsAmount = selectedServices.length * 50; // 50 SAR per additional service

    const optionsAmountElement = document.querySelector('.price-row:first-child .price-value');
    const subtotalElement = document.querySelector('.price-row:last-child .price-value');

    if (optionsAmountElement && subtotalElement) {
        optionsAmountElement.textContent = `${optionsAmount.toFixed(2)} ريال`;
        subtotalElement.textContent = `${(basePrice + optionsAmount).toFixed(2)} ريال`;
    }
}

// Service item selection
function selectServiceItem(serviceElement) {
    serviceElement.classList.toggle('selected');
    serviceElement.style.backgroundColor = serviceElement.classList.contains('selected')
        ? '#e8f4f8' : '';
    updatePricingDisplay();
}

// Phone number formatting for Saudi numbers
function formatSaudiPhoneNumber(input) {
    let value = input.value.replace(/\D/g, '');

    if (value.length > 0) {
        if (value.startsWith('966')) {
            value = '+' + value;
        } else if (value.startsWith('05') && value.length === 10) {
            value = '+966' + value.substring(1);
        } else if (value.length === 9 && value.startsWith('5')) {
            value = '+966' + value;
        }
    }

    input.value = value;
}

// Character counter for textarea
function updateCharacterCount(textarea) {
    const maxLength = parseInt(textarea.getAttribute('maxlength'));
    const currentLength = textarea.value.length;

    let counter = textarea.parentNode.querySelector('.char-counter');
    if (!counter) {
        counter = document.createElement('div');
        counter.className = 'char-counter';
        counter.style.cssText = `
            font-size: 0.75rem;
            color: #888;
            text-align: left;
            margin-top: 4px;
            direction: ltr;
        `;
        textarea.parentNode.appendChild(counter);
    }

    counter.textContent = `${currentLength}/${maxLength}`;
    counter.style.color = currentLength > maxLength * 0.9 ? '#e74c3c' : '#888';
}

// Initialize event listeners
document.addEventListener('DOMContentLoaded', function() {
    // Phone number formatting
    const phoneInputs = document.querySelectorAll('input[type="tel"]');
    phoneInputs.forEach(input => {
        input.addEventListener('input', function() {
            formatSaudiPhoneNumber(this);
        });

        input.addEventListener('blur', function() {
            if (this.value && !this.value.match(/^\+966[5-9]\d{8}$/)) {
                this.style.borderColor = '#f39c12';
                this.title = 'يرجى إدخال رقم هاتف سعودي صحيح';
            } else {
                this.style.borderColor = '#e0e0e0';
                this.title = '';
            }
        });
    });

    // Character counter for textarea
    const textarea = document.querySelector('.form-textarea');
    if (textarea) {
        textarea.addEventListener('input', function() {
            updateCharacterCount(this);
        });
        updateCharacterCount(textarea); // Initialize counter
    }

    // Service item selection
    const serviceItems = document.querySelectorAll('.dropdown-item');
    serviceItems.forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            selectServiceItem(this);
        });
    });

    // Form validation on blur
    const requiredInputs = document.querySelectorAll('input[required]');
    requiredInputs.forEach(input => {
        input.addEventListener('blur', function() {
            if (!this.value.trim()) {
                this.style.borderColor = '#e74c3c';
            } else {
                this.style.borderColor = '#e0e0e0';
            }
        });
    });

    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.service-dropdown')) {
            const allDropdowns = document.querySelectorAll('.dropdown-menu');
            const allHeaders = document.querySelectorAll('.dropdown-header');

            allDropdowns.forEach(menu => menu.classList.remove('show'));
            allHeaders.forEach(header => header.classList.remove('active'));
        }
    });

    // Initialize pricing display
    updatePricingDisplay();

    // Handle form submission with Enter key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA') {
            e.preventDefault();

            // Find next input or submit if last field
            const inputs = Array.from(document.querySelectorAll('input, textarea'));
            const currentIndex = inputs.indexOf(e.target);

            if (currentIndex < inputs.length - 1) {
                inputs[currentIndex + 1].focus();
            } else {
                addToCart();
            }
        }

        // Close dropdowns with Escape key
        if (e.key === 'Escape') {
            const allDropdowns = document.querySelectorAll('.dropdown-menu');
            const allHeaders = document.querySelectorAll('.dropdown-header');

            allDropdowns.forEach(menu => menu.classList.remove('show'));
            allHeaders.forEach(header => header.classList.remove('active'));
        }
    });
});

// Export/Import functionality (for future use)
function exportGiftCardData() {
    const data = collectFormData();
    const dataStr = JSON.stringify(data, null, 2);
    const dataBlob = new Blob([dataStr], {type: 'application/json'});

    const link = document.createElement('a');
    link.href = URL.createObjectURL(dataBlob);
    link.download = `jospa-gift-card-${Date.now()}.json`;
    link.click();
}

// Print gift card (for future use)
function printGiftCard() {
    const printContent = document.querySelector('.gift-card-container').innerHTML;
    const printWindow = window.open('', '_blank');

    printWindow.document.write(`
        <!DOCTYPE html>
        <html dir="rtl">
        <head>
            <title>JOSPA Gift Card</title>
            <style>
                body { font-family: 'Cairo', sans-serif; direction: rtl; }
                .no-print { display: none; }
                @media print {
                    .dropdown-menu { display: none !important; }
                    .add-to-cart-button { display: none; }
                }
            </style>
        </head>
        <body>
            ${printContent}
        </body>
        </html>
    `);

    printWindow.document.close();
    printWindow.print();
}
    </script>
</body>
</html>
