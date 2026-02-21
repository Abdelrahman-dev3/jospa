<section class="py-5" style="margin-top: 100px;">
    <div class="container" style="padding:0 5rem">
        <div class="row g-5 justify-content-center">
            <!-- Our Goal Card -->
            <div class="col-12 col-md-6">
                <div class="card h-100 shadow goal-vision-card rounded-4" style="padding: 3.8rem">
                    <div class="d-flex align-items-center mb-3">
                        <span class="me-3" style="font-size: 2.2rem; color: var(--primary-color);">
                            <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" fill="currentColor" class="bi bi-bullseye" viewBox="0 0 16 16"><path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm0-1A7 7 0 1 1 8 1a7 7 0 0 1 0 14z"/><path d="M8 13A5 5 0 1 0 8 3a5 5 0 0 0 0 10zm0-1A4 4 0 1 1 8 4a4 4 0 0 1 0 8z"/><path d="M8 11A3 3 0 1 0 8 5a3 3 0 0 0 0 6zm0-1A2 2 0 1 1 8 6a2 2 0 0 1 0 4z"/></svg>
                        </span>
                        <h3 class="mb-0 fw-bold" style="font-size: 2.4rem; color: var(--primary-color);">
                            {{ __('messages.our_goal_title') }}
                        </h3>
                    </div>
                    <div class="mt-4" style="font-size: 1.4rem; line-height: 1.8; color: #333;">
                        {{ __('messages.our_goal_text') }}
                    </div>
                </div>
            </div>
            <!-- Our Vision Card -->
            <div class="col-12 col-md-6">
                <div class="card h-100 shadow goal-vision-card rounded-4"  style="padding: 3.8rem">
                    <div class="d-flex align-items-center mb-3">
                        <span class="me-3" style="font-size: 2.2rem; color: var(--primary-color);">
                            <svg width="36" height="36" aria-hidden="true" focusable="false" data-prefix="fas" data-icon="binoculars" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path fill="currentColor" d="M128 32l32 0c17.7 0 32 14.3 32 32l0 32L96 96l0-32c0-17.7 14.3-32 32-32zm64 96l0 320c0 17.7-14.3 32-32 32L32 480c-17.7 0-32-14.3-32-32l0-59.1c0-34.6 9.4-68.6 27.2-98.3C40.9 267.8 49.7 242.4 53 216L60.5 156c2-16 15.6-28 31.8-28l99.8 0zm227.8 0c16.1 0 29.8 12 31.8 28L459 216c3.3 26.4 12.1 51.8 25.8 74.6c17.8 29.7 27.2 63.7 27.2 98.3l0 59.1c0 17.7-14.3 32-32 32l-128 0c-17.7 0-32-14.3-32-32l0-320 99.8 0zM320 64c0-17.7 14.3-32 32-32l32 0c17.7 0 32 14.3 32 32l0 32-96 0 0-32zm-32 64l0 160-64 0 0-160 64 0z"></path></svg>
                        </span>
                        <h3 class="mb-0 fw-bold" style="font-size: 2.4rem; color: var(--primary-color);">
                            {{ __('messages.our_vision_title') }}
                        </h3>
                    </div>
                    <div class="mt-4" style="font-size: 1.4rem; line-height: 1.8; color: #333;">
                        {{ __('messages.our_vision_text') }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
.goal-vision-card {
    transition: box-shadow 0.3s;
}
.goal-vision-card:hover {
    box-shadow: 0 0.5rem 2rem rgba(0,0,0,0.1) !important;
}
</style>
