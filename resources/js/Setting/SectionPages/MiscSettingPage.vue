<template>
  <form @submit="formSubmit">
    <CardTitle :title="$t('setting_sidebar.lbl_misc_setting')" icon="fa-solid fa-screwdriver-wrench"></CardTitle>

    <div class="row">
      <div class="col-md-4">
        <InputField :label="$t('setting_analytics_page.lbl_name')" :placeholder="$t('setting_analytics_page.lbl_name')" v-model="google_analytics" :errorMessage="errors.google_analytics"></InputField>
      </div>
      <div class="col-md-4">
        <div class="form-group">
          <label class="form-label" for="slot_duration">{{ $t('setting_booking_page.lbl_name') }}</label>
          <flat-pickr placeholder="Slot Duration (Min)" id="slot_duration" class="form-control" v-model="slot_duration" :value="slot_duration" :config="config"></flat-pickr>
          <span class="text-danger">{{ errors.slot_duration }}</span>
        </div>
      </div>
      <div class="col-md-4">
        <label class="form-label">{{ $t('setting_language_page.lbl_language') }}</label>
        <Multiselect id="default_language" v-model="default_language" :value="default_language" v-bind="singleSelectOption" :options="language.options" class="form-group"></Multiselect>
        <span class="text-danger">{{ errors.default_language }}</span>
      </div>

      <div class="col-md-4">
        <label class="form-label"
          >{{ $t('setting_language_page.lbl_timezone') }} <span class="badge bg-danger">{{ $t('setting_language_page.soon') }}</span></label
        >
        <Multiselect id="default_time_zone" v-model="default_time_zone" :value="default_time_zone" v-bind="TimeZoneSelectOption" :options="timezone.options" class="form-group"></Multiselect>
        <span class="text-danger">{{ errors.default_time_zone }}</span>
      </div>

      <div class="col-md-4">
        <label class="form-label">{{ $t('setting_language_page.lbl_data_table_limit') }}</label>
        <Multiselect id="data_table_limit" v-model="data_table_limit" :value="data_table_limit" v-bind="data_table_limit_data" class="form-group"></Multiselect>
        <span class="text-danger">{{ errors.data_table_limit }}</span>
      </div>
      <div class="col-md-4">
        <div class="form-group">
          <label class="form-label" for="home_booking_calendar_color">لون الحجز المنزلي في الكالندر</label>
          <div class="d-flex align-items-center gap-3">
            <input id="home_booking_calendar_color" v-model="home_booking_calendar_color" type="color" class="form-control form-control-color" />
            <span class="form-control">{{ home_booking_calendar_color || '#0EA5E9' }}</span>
          </div>
          <span class="text-danger">{{ errors.home_booking_calendar_color }}</span>
        </div>
      </div>
      <div class="col-md-4">
        <InputField :label="'الحد الأدنى لحجز الخدمات المنزلية (ريال)'" :placeholder="'مثال: 150'" v-model="min_home_booking_amount" :errorMessage="errors.min_home_booking_amount" type="number"></InputField>
      </div>
      <div class="col-md-4">
  <div class="form-group">
    <label class="form-label" for="saudi_national_day_theme">
      ثيم اليوم الوطني للسعودية
    </label>

    <div class="form-check form-switch">
      <input
        id="saudi_national_day_theme"
        v-model="saudi_national_day_theme"
        class="form-check-input"
        type="checkbox"
        role="switch"
        :true-value="1"
        :false-value="0"
      />

      <label class="form-check-label" for="saudi_national_day_theme">
        تفعيل ألوان اليوم الوطني على الموقع
      </label>
    </div>

    <span class="text-danger">
      {{ errors.saudi_national_day_theme }}
    </span>
  </div>
</div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════ -->
    <!-- قسم التحكم في جدول الحجوزات -->
    <!-- ═══════════════════════════════════════════════════════════ -->
    <hr class="my-4" />
    <div class="booking-window-section mb-4">
      <h5 class="section-title mb-3">
        <i class="fa-solid fa-calendar-lock me-2 text-primary"></i>
        التحكم في جدول الحجوزات
      </h5>

      <div class="row g-3">
        <!-- Enable/Disable toggle -->
        <div class="col-12">
          <div class="card border-0 bg-soft-primary p-3 rounded-3">
            <div class="d-flex align-items-center justify-content-between">
              <div>
                <div class="fw-semibold">تفعيل قيود نافذة الحجز</div>
                <small class="text-muted">عند التفعيل، لن يتمكن العملاء من الحجز خارج النطاق الزمني المحدد</small>
              </div>
              <div class="form-check form-switch ms-3">
                <input
                  id="booking_window_restriction"
                  v-model="booking_window_restriction"
                  class="form-check-input"
                  type="checkbox"
                  role="switch"
                  :true-value="1"
                  :false-value="0"
                  style="width:3rem; height:1.5rem;"
                />
              </div>
            </div>
          </div>
        </div>

        <!-- Window type -->
        <div class="col-md-4" v-if="booking_window_restriction">
          <label class="form-label fw-semibold">نوع القيد</label>
          <select id="booking_window_type" v-model="booking_window_type" class="form-select">
            <option value="rolling">متحرك (شهور / أيام من اليوم)</option>
            <option value="date_range">نطاق تاريخ محدد</option>
            <option value="both">كلاهما</option>
          </select>
          <small class="text-muted">اختر طريقة تحديد نافذة الحجز</small>
        </div>

        <!-- Rolling: months -->
        <div class="col-md-4" v-if="booking_window_restriction && (booking_window_type === 'rolling' || booking_window_type === 'both')">
          <label class="form-label fw-semibold">عدد الشهور المسموح بالحجز مقدماً</label>
          <input
            id="booking_advance_months"
            type="number"
            min="0"
            max="24"
            class="form-control"
            v-model="booking_advance_months"
            placeholder="مثال: 2"
          />
          <small class="text-muted">0 = استخدم الأيام بدلاً من الشهور</small>
        </div>

        <!-- Rolling: days -->
        <div class="col-md-4" v-if="booking_window_restriction && (booking_window_type === 'rolling' || booking_window_type === 'both')">
          <label class="form-label fw-semibold">أو عدد الأيام المسموح بالحجز مقدماً</label>
          <input
            id="booking_advance_days"
            type="number"
            min="0"
            max="730"
            class="form-control"
            v-model="booking_advance_days"
            placeholder="مثال: 60"
          />
          <small class="text-muted">يُفعَّل فقط إذا كانت الشهور = 0</small>
        </div>

        <!-- Date range: start -->
        <div class="col-md-4" v-if="booking_window_restriction && (booking_window_type === 'date_range' || booking_window_type === 'both')">
          <label class="form-label fw-semibold">تاريخ بداية فتح الحجز</label>
          <input
            id="booking_start_date"
            type="date"
            class="form-control"
            v-model="booking_start_date"
          />
          <small class="text-muted">العملاء لا يستطيعون الحجز قبل هذا التاريخ</small>
        </div>

        <!-- Date range: end -->
        <div class="col-md-4" v-if="booking_window_restriction && (booking_window_type === 'date_range' || booking_window_type === 'both')">
          <label class="form-label fw-semibold">تاريخ إغلاق الحجز</label>
          <input
            id="booking_end_date"
            type="date"
            class="form-control"
            v-model="booking_end_date"
          />
          <small class="text-muted">العملاء لا يستطيعون الحجز بعد هذا التاريخ</small>
        </div>

        <!-- Info banner when restriction enabled -->
        <div class="col-12" v-if="booking_window_restriction">
          <div class="alert alert-info d-flex align-items-center gap-2 py-2 mb-0">
            <i class="fa-solid fa-circle-info"></i>
            <span>سيظهر للعملاء الذين يحاولون الحجز خارج النطاق <strong>صفحة إشعار احترافية</strong> تُبيّن موعد فتح الحجز.</span>
          </div>
        </div>
      </div>
    </div>

    <div class="row py-4">
      <SubmitButton :IS_SUBMITED="IS_SUBMITED"></SubmitButton>
    </div>
  </form>
</template>
<script setup>
import CardTitle from '@/Setting/Components/CardTitle.vue'
import InputField from '@/vue/components/form-elements/InputField.vue'
import { onMounted, ref } from 'vue'
import { useField, useForm } from 'vee-validate'
import { STORE_URL, GET_URL, TIME_ZONE_LIST, CURRENCY_LIST } from '@/vue/constants/setting'
import { useSelect } from '@/helpers/hooks/useSelect'
import { LANGUAGE_LIST, LISTING_URL } from '@/vue/constants/language'
import { createRequest, buildMultiSelectObject } from '@/helpers/utilities'
import { useRequest } from '@/helpers/hooks/useCrudOpration'
import FlatPickr from 'vue-flatpickr-component'
import SubmitButton from './Forms/SubmitButton.vue'
import { confirmSwal } from '@/helpers/utilities'

// flatepicker
const config = ref({
  dateFormat: 'H:i',
  time_24hr: true,
  enableTime: true,
  noCalendar: true,
  defaultHour: '00', // Update default hour to 9
  defaultMinute: '30'
})

const IS_SUBMITED = ref(false)
const { storeRequest, listingRequest } = useRequest()

// options
const TimeZoneSelectOption = ref({
  closeOnSelect: true,
  searchable: true,
  clearable: false
})

const currencyOption = ref({
  closeOnSelect: true,
  searchable: true,
  clearable: false
})

const singleSelectOption = ref({
  closeOnSelect: true,
  searchable: true,
  clearable: false
})
const language = ref([])
const timezone = ref([])
const currency = ref([])

const type = 'time_zone'

const getLanguageList = () => {
  useSelect({ url: LANGUAGE_LIST }, { value: 'id', label: 'name' }).then((data) => (language.value = data))
}
const getTimeZoneList = () => {
  listingRequest({ url: TIME_ZONE_LIST, data: { type: type } }).then((res) => {
    timezone.value.options = buildMultiSelectObject(res.results, {
      value: 'id',
      label: 'text'
    })
  })
}

const data_table_limit_data = ref({
  searchable: true,
  options: [
    { label: 5, value: 5 },
    { label: 10, value: 10 },
    { label: 15, value: 15 },
    { label: 20, value: 20 },
    { label: 25, value: 25 },
    { label: 50, value: 50 },
    { label: 100, value: 100 },
    { label: 'All', value: -1 }
  ],
  closeOnSelect: true,
  createOption: true
})

//  Reset Form
const setFormData = (data) => {
  resetForm({
    values: {
      google_analytics: data.google_analytics,
      slot_duration: data.slot_duration,
      home_booking_calendar_color: data.home_booking_calendar_color || '#0EA5E9',
      default_language: data.default_language,
      default_time_zone: data.default_time_zone,
      data_table_limit: data.data_table_limit,
      default_currency: data.default_currency,
      min_home_booking_amount: data.min_home_booking_amount || 0,
      saudi_national_day_theme: Number(data.saudi_national_day_theme) === 1 ? 1 : 0,
      // Booking window restriction
      booking_window_restriction: Number(data.booking_window_restriction) === 1 ? 1 : 0,
      booking_window_type: data.booking_window_type || 'rolling',
      booking_advance_months: data.booking_advance_months || 0,
      booking_advance_days: data.booking_advance_days || 0,
      booking_start_date: data.booking_start_date || '',
      booking_end_date: data.booking_end_date || '',
    }
  })
}
const { handleSubmit, errors, resetForm } = useForm()
const errorMessage = ref(null)
const { value: slot_duration } = useField('slot_duration')
const { value: google_analytics } = useField('google_analytics')
const { value: home_booking_calendar_color } = useField('home_booking_calendar_color')
const { value: default_language } = useField('default_language')
const { value: default_time_zone } = useField('default_time_zone')
const { value: data_table_limit } = useField('data_table_limit')
const { value: default_currency } = useField('default_currency')
const { value: min_home_booking_amount } = useField('min_home_booking_amount')
const { value: saudi_national_day_theme } = useField('saudi_national_day_theme')
// Booking window restriction fields
const { value: booking_window_restriction } = useField('booking_window_restriction')
const { value: booking_window_type } = useField('booking_window_type')
const { value: booking_advance_months } = useField('booking_advance_months')
const { value: booking_advance_days } = useField('booking_advance_days')
const { value: booking_start_date } = useField('booking_start_date')
const { value: booking_end_date } = useField('booking_end_date')

saudi_national_day_theme.value = 0
booking_window_restriction.value = 0
booking_window_type.value = 'rolling'
booking_advance_months.value = 0
booking_advance_days.value = 0
booking_start_date.value = ''
booking_end_date.value = ''
slot_duration.value = '00:30'
home_booking_calendar_color.value = '#0EA5E9'
min_home_booking_amount.value = 0
const data = 'slot_duration,google_analytics,home_booking_calendar_color,default_language,default_time_zone,data_table_limit,default_currency,min_home_booking_amount,saudi_national_day_theme,booking_window_restriction,booking_window_type,booking_advance_months,booking_advance_days,booking_start_date,booking_end_date'
//const data = 'slot_duration,google_analytics,home_booking_calendar_color,default_language,default_time_zone,data_table_limit,default_currency,min_home_booking_amount'
onMounted(() => {
  createRequest(GET_URL(data)).then((response) => {
    setFormData(response)
  })

  getLanguageList()
  getTimeZoneList()
})

// message
const display_submit_message = (res) => {
  IS_SUBMITED.value = false
  if (res.status) {
    window.successSnackbar(res.message)
  } else {
    window.errorSnackbar(res.message)
  }
}
//Form Submit
const formSubmit = handleSubmit((values) => {
  IS_SUBMITED.value = true
  const newValues = {}
  Object.keys(values).forEach((key) => {
    newValues[key] = values[key] || ''
  })

  storeRequest({ url: STORE_URL, body: values }).then((res) => display_submit_message(res))
})
</script>
