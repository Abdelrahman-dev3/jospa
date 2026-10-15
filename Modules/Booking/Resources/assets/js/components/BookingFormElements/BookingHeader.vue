<template>
  <div class="d-flex gap-2 align-items-center">
    <h4 class="offcanvas-title" id="form-offcanvasLabel">
      <template v-if="booking_id > 0">
        {{ $t('booking.lbl_appointment') }}
      </template>
      <template v-else>{{ $t('booking.lbl_new_appointment') }} </template>
    </h4>
    <small class="badge bg-success" v-if="is_paid">{{ $t('booking.lbl_is_paid') }}</small>
  </div>
  <div class="d-flex align-items-center gap-2">
    <div v-if="booking_id > 0">
      <button data-bs-toggle="tooltip" title="سجل الحجز (Activity Log)" type="button" @click="openActivityLogModal" class="btn btn-soft-info btn-sm">
        <i class="fa-solid fa-clock-rotate-left me-1"></i> سجل الحجز
      </button>
    </div>
    <div v-if="booking_id > 0 && status !== 'cancelled' && status !== 'check_in'">
      <strong>
        <button data-bs-toggle="tooltip" title="Cancel Booking" type="button" @click="changeBookingStatus('cancelled', 'Are You Sure You want to Cancel?')"  class="btn btn-soft-danger btn-sm" >
          <i class="fa-solid fa-ban"></i>
        </button>
      </strong>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>
</template>
<script setup>
import { UPDATE_STATUS } from '../../constant/booking'
import { useRequest } from '@/helpers/hooks/useCrudOpration'
import { confirmcancleSwal } from '@/helpers/utilities'
const props = defineProps({
  booking_id: { type: [Number, String], default: 0 },
  status: { type: String, default: 'pending' },
  is_paid: { type: Number, default: 0 }
})
const emit = defineEmits(['statusUpdate'])

const { updateRequest } = useRequest()

// Update Status
const changeBookingStatus = (status, message) => {
  confirmcancleSwal({ title: message }).then((result) => {
    if (!result.isConfirmed) return

    const data = {
      status: status
    }
    updateRequest({ url: UPDATE_STATUS, id: props.booking_id, body: data }).then((res) => {
      if (res.status) {
        Swal.fire({
          title: 'Appointment Cancelled !',
          //text: res.message,
          icon: 'success',
          showClass: {
            popup: 'animate__animated animate__zoomIn'
          },
          hideClass: {
            popup: 'animate__animated animate__zoomOut'
          }
        })
      }
      res.status && emit('statusUpdate', res.data)
    })
  })
}

const openActivityLogModal = () => {
  if (typeof window.openModal === 'function') {
    window.openModal({
      title: 'سجل حركات ونشاطات الحجز #' + props.booking_id,
      size: 'xl',
      url: '/app/bookings/' + props.booking_id + '/activity-log',
      render: 'ajax'
    })
  } else {
    const modalElem = document.getElementById('renderModal')
    if (modalElem) {
      const modalTitle = modalElem.querySelector('.modal-title')
      const modalBody = modalElem.querySelector('.modal-body')
      if (modalTitle) modalTitle.innerText = 'سجل حركات ونشاطات الحجز #' + props.booking_id
      if (modalBody) modalBody.innerHTML = '<div class="text-center py-5"><span class="spinner-border text-primary" role="status"></span><p class="mt-2 text-muted small">جاري تحميل سجل الحجز...</p></div>'
      
      if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        const bsModal = bootstrap.Modal.getOrCreateInstance(modalElem)
        bsModal.show()
      }

      fetch('/app/bookings/' + props.booking_id + '/activity-log?html=1', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      })
      .then(r => r.text())
      .then(html => {
        if (modalBody) modalBody.innerHTML = html
      })
      .catch(() => {
        if (modalBody) modalBody.innerHTML = '<div class="alert alert-danger p-3 text-center">حدث خطأ أثناء تحميل السجل.</div>'
      })
    }
  }
}
</script>
