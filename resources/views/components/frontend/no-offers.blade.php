
<style>
body {background: #fefefe !important;}
.top-curve-svg path {fill: #fefefe !important;}
.no-offers {min-height: 500px;display: flex;align-items: center;justify-content: center;text-align: center;padding: 20px;}
.no-offers .content {max-width: 500px;animation: fadeIn 1s ease-in-out;}
.no-offers-image {width: 406px;margin-top: 65px;}
@keyframes fadeIn {
    from {opacity: 0;transform: translateY(25px);}
    to {opacity: 1;transform: translateY(0);}
}

</style>
<section class="no-offers">
    <div class="content">
        <img src="{{asset('images/icons/no-offers (2).png')}}" alt="لا توجد عروض" class="no-offers-image">
    </div>
</section>