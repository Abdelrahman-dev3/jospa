@include('components.frontend.notifications')

<section class="py-5">
    <div class="container" id"rtl" style="padding: 0 3rem 0 3rem;">
        <div class="row g-5 align-items-start">
            <!-- Right: Contact Form -->
            <div class="col-12 col-lg-12">
                <form action="{{ route('contact.store') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <input name="name" type="text" class="form-control form-control-lg" id="name" placeholder="{{ __('messagess.enter_your_name') }}" style="border-radius: 4px;width:100%;position:relative;z-index: 99;    margin: 0 0 29px 0;">
                    </div>
                    <div class="mb-3">
                        <input name="phone" type="tel" class="form-control form-control-lg" id="phone" placeholder="{{ __('messagess.enter_your_phone') }}" style="border-radius: 4px;width:100%;position:relative;z-index: 99;    margin: 0 0 29px 0;">
                    </div>
                    <div class="mb-3">
                        <input name="email" type="email" class="form-control form-control-lg" id="email" placeholder="{{ __('messagess.enter_your_email') }}" style="border-radius: 4px;width:100%;position:relative;z-index: 99;    margin: 0 0 29px 0;">
                    </div>
                    <div class="mb-3">
                        <textarea name="message" class="form-control form-control-lg" id="message" rows="4" placeholder="{{ __('messagess.enter_your_message') }}" style="border-radius: 4px;width:100%;position:relative;z-index: 99;    margin: 0 0 29px 0;"></textarea>
                    </div>
                    <div style="width:100%;display:flex;justify-content: end;">
                    <button disabled type="submit" class="btn btn-primary btn-lg px-5 py-3 fw-bold" id="submitBtn"style="background-color: gray; border: none; border-radius: 35px;">
                        {{ __('messagess.send_message') }}
                    </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>



<script>
const phoneInput = document.getElementById('phone');
const submitBtn = document.getElementById('submitBtn');

phoneInput.addEventListener('input', function () {
    const phone = this.value.trim();
    const saudiRegex = /^(?:\+9665|9665|05|5)\d{8}$/;

    if (saudiRegex.test(phone)) {
        submitBtn.disabled = false;
        submitBtn.style.backgroundColor = 'var(--primary-color)';
        phoneInput.style.borderColor = 'green'; // حقل صحيح
    } else {
        submitBtn.disabled = true;
        submitBtn.style.backgroundColor = 'gray';
        phoneInput.style.borderColor = 'red'; // حقل غير صحيح
    }
});
</script>
      <script>
      document.addEventListener("DOMContentLoaded", function () {
          @if(session('success'))
                createNotify({ title: '', desc: "{{ session('success') }}" });
          @endif
  
          @if(session('error'))
                createNotify({ title: '', desc: "{{ session('error') }}" });
          @endif
  
          @if($errors->any())
              @foreach($errors->all() as $error)
                    createNotify({ title: '', desc: "{{ $error }}" });
              @endforeach
          @endif
    const dir = document.documentElement.getAttribute("dir");
    const box = document.querySelector(".style-box");

    if (dir === "ltr") {
      box.style.right = "-25%";
      box.style.left = "auto"; // مهم تشيل left
      box.style.rotate = "42deg";
    }
      });
  </script>