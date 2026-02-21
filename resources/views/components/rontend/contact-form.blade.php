<section class="py-5" style="margin-top: 100px;">
    <div class="container" style="padding: 0 5rem;">
        <div class="row g-5 align-items-start">
            <!-- Left: Address and Image -->
            <div class="col-12 col-lg-6">
                <div class="d-flex align-items-center mb-4" style="border-bottom: 3px solid var(--primary-color); padding-bottom: 0.5rem; width: 180px">
                    <span class="me-3" style="font-size: 2rem; color: var(--primary-color);">
                        <svg data-v-1143b0ed="" width="32" height="32"  aria-hidden="true" focusable="false" data-prefix="fas" data-icon="address-book" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path class="" fill="currentColor" d="M96 0C60.7 0 32 28.7 32 64l0 384c0 35.3 28.7 64 64 64l288 0c35.3 0 64-28.7 64-64l0-384c0-35.3-28.7-64-64-64L96 0zM208 288l64 0c44.2 0 80 35.8 80 80c0 8.8-7.2 16-16 16l-192 0c-8.8 0-16-7.2-16-16c0-44.2 35.8-80 80-80zm-32-96a64 64 0 1 1 128 0 64 64 0 1 1 -128 0zM512 80c0-8.8-7.2-16-16-16s-16 7.2-16 16l0 64c0 8.8 7.2 16 16 16s16-7.2 16-16l0-64zM496 192c-8.8 0-16 7.2-16 16l0 64c0 8.8 7.2 16 16 16s16-7.2 16-16l0-64c0-8.8-7.2-16-16-16zm16 144c0-8.8-7.2-16-16-16s-16 7.2-16 16l0 64c0 8.8 7.2 16 16 16s16-7.2 16-16l0-64z"></path></svg>
                    </span>
                    <h3 class="mb-0 fw-bold" style="font-size: 2rem; color: var(--primary-color)">
                        Address
                    </h3>
                </div>
                <p class="mb-4" style="font-size: 1.3rem; color: #333;">
                    Riyadh, Saudi Arabia
                </p>
                <img src="{{ asset('images/frontend/about.webp') }}" alt="Contact Location" class="img-fluid rounded-4 shadow" style="max-width: 100%; height: 350px; object-fit: cover;">
            </div>

            <!-- Right: Contact Form -->
            <div class="col-12 col-lg-6">
                <form>
                    <div class="mb-3">
                        <label for="name" class="form-label fw-bold" style="color: #333;">Your Name</label>
                        <input type="text" class="form-control form-control-lg" id="name" placeholder="Enter your name" style="border-radius: 8px; border: 2px solid #e9ecef;">
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label fw-bold" style="color: #333;">Your Email</label>
                        <input type="email" class="form-control form-control-lg" id="email" placeholder="Enter your email" style="border-radius: 8px; border: 2px solid #e9ecef;">
                    </div>
                    <div class="mb-3">
                        <label for="phone" class="form-label fw-bold" style="color: #333;">Your Phone</label>
                        <input type="tel" class="form-control form-control-lg" id="phone" placeholder="Enter your phone number" style="border-radius: 8px; border: 2px solid #e9ecef;">
                    </div>
                    <div class="mb-3">
                        <label for="message" class="form-label fw-bold" style="color: #333;">Your Message</label>
                        <textarea class="form-control form-control-lg" id="message" rows="4" placeholder="Enter your message" style="border-radius: 8px; border: 2px solid #e9ecef;"></textarea>
                    </div>
                    <div class="mb-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="privacy" style="border: 2px solid var(--primary-color);">
                            <label class="form-check-label" for="privacy" style="color: #333;">
                                I accept the <a href="#" style="color: var(--primary-color);">Privacy Policy</a> and <a href="#" style="color: var(--primary-color);">Terms & Conditions</a>.
                            </label>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg px-5 py-3 fw-bold" style="background-color: var(--primary-color); border: none; border-radius: 0;">
                        Send Message
                    </button>
                </form>
            </div>
        </div>
    </div>
</section>
