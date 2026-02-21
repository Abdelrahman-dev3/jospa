<template>
  <form @submit="formSubmit" class="bussiness-hour">
    <div class="col-md-12 d-flex justify-content-between align-items-center mb-3">
      <h5 class="mb-0">Configure Working Hours</h5>
      <button type="button" class="btn btn-outline-primary btn-sm" @click="handleCopy">
        <i class="fas fa-copy copy-icon me-1"></i>
        Copy Saturday to All Days
      </button>
    </div>

    <ul class="data-scrollbar list-group list-group-flush">
      <li v-for="(day, index) in weekdays" class="form-group col-md-12 list-group-item" :key="++index">
        <div class="form-group col-md-12 gap-1">
          <!-- Day name and holiday checkbox -->
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="mb-0 text-capitalize">{{ day.day }}</h6>
            <div class="form-check">
              <input
                class="form-check-input"
                type="checkbox"
                v-model="day.is_holiday"
                :id="'holiday-' + day.day"
              >
              <label class="form-check-label" :for="'holiday-' + day.day">
                Holiday
              </label>
            </div>
          </div>

          <!-- Start and End time -->
          <div class="col-md-12 row row-cols-3 mb-2">
            <div class="col">
              <label class="form-label small">Start Time</label>
              <flat-pickr
                id="start_time"
                class="form-control"
                v-model="day.start_time"
                :config="config"
                :disabled="day.is_holiday ? true : false"
                :class="{ background_colour: day.is_holiday }"
              ></flat-pickr>
            </div>
            <div class="col">
              <label class="form-label small">End Time</label>
              <flat-pickr
                id="end_time"
                class="form-control"
                v-model="day.end_time"
                :config="config"
                :disabled="day.is_holiday ? true : false"
                :class="{ background_colour: day.is_holiday }"
              ></flat-pickr>
            </div>
            <div class="col">
              <label class="form-label small">&nbsp;</label>
              <div class="form-group">
                <!-- Placeholder for alignment -->
              </div>
            </div>
          </div>

          <!-- Breaks section -->
          <template v-if="!day.is_holiday">
            <div class="ms-3">
              <h6 class="small text-muted mb-2">Break Times</h6>
              <div v-for="(breakTime, breakIndex) in day.breaks" :key="breakIndex" class="form-group mb-2">
                <div class="col-md-12 row row-cols-3">
                  <div class="col">
                    <flat-pickr
                      id="start_break"
                      class="form-control form-control-sm"
                      v-model="breakTime.start_break"
                      :config="config"
                      placeholder="Break start"
                    ></flat-pickr>
                  </div>
                  <div class="col">
                    <flat-pickr
                      id="end_break"
                      class="form-control form-control-sm"
                      v-model="breakTime.end_break"
                      :config="config"
                      placeholder="Break end"
                    ></flat-pickr>
                  </div>
                  <div class="col">
                    <button
                      type="button"
                      class="btn btn-outline-danger btn-sm"
                      @click="deleteInputField(day, breakIndex)"
                    >
                      <i class="fas fa-trash"></i> Remove
                    </button>
                  </div>
                </div>
              </div>
              <div>
                <a @click="addInputField(day)" class="clickable-text text-primary small">
                  <i class="fas fa-plus me-1"></i>
                  Add Break Time
                </a>
              </div>
            </div>
          </template>
        </div>
      </li>
    </ul>
    <!-- <SubmitButton :IS_SUBMITED="IS_SUBMITED"></SubmitButton> -->
    <FormFooter :IS_SUBMITED="IS_SUBMITED"></FormFooter>
  </form>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRequest } from '@/helpers/hooks/useCrudOpration'
import { useSelect } from '@/helpers/hooks/useSelect'
import FlatPickr from 'vue-flatpickr-component'
import { useForm, useField } from 'vee-validate'
import moment from 'moment'
import * as yup from 'yup'
import FormFooter from '@/vue/components/form-elements/FormFooter.vue'

// Import all constants from employee.js
import {
  BRANCH_LIST,
  SHIFT_LIST,
  BUSINESS_HOURS_LIST,
  BUSINESS_HOURS_STORE
} from '../constant/employee'

const props = defineProps({
  employeeId: {
    type: [Number, String],
    default: null
  },
  branches: {
    type: Array,
    default: () => []
  },
  shifts: {
    type: Array,
    default: () => []
  },
  existingHours: {
    type: Array,
    default: () => []
  }
})

const singleSelectOption = ref({
  closeOnSelect: true,
  searchable: true,
  clearable: false
})

const IS_SUBMITED = ref(false)

const config = ref({
  dateFormat: 'H:i:S',
  altInput: true,
  altFormat: 'h:i K',
  enableTime: true,
  noCalendar: true,
  defaultHour: '09',
  defaultMinute: '00',
  defaultSeconds: '00',
  static: true
})

const { storeRequest, getRequest } = useRequest()

const validationSchema = yup.object({})


const { handleSubmit, errors } = useForm({ validationSchema })

const branch = ref({ options: [], list: [] })
const shift = ref({ options: [], list: [] })


// message
const display_submit_message = (res) => {
  IS_SUBMITED.value = false
  if (res.status) {
    window.successSnackbar(res.message)
  } else {
    window.errorSnackbar(res.message)
  }
}

onMounted(() => {
  loadBusinessHours();
});


const defaultData = () => {
  return [
    { day: 'saturday', start_time: moment().set({ hour: 9, minute: 0, second: 0 }).format('HH:mm'), end_time: moment().set({ hour: 18, minute: 0, second: 0 }).format('HH:mm'), is_holiday: false, breaks: [] },
    { day: 'sunday', start_time: moment().set({ hour: 9, minute: 0, second: 0 }).format('HH:mm'), end_time: moment().set({ hour: 18, minute: 0, second: 0 }).format('HH:mm'), is_holiday: false, breaks: [] },
    { day: 'monday', start_time: moment().set({ hour: 9, minute: 0, second: 0 }).format('HH:mm'), end_time: moment().set({ hour: 18, minute: 0, second: 0 }).format('HH:mm'), is_holiday: false, breaks: [] },
    { day: 'tuesday', start_time: moment().set({ hour: 9, minute: 0, second: 0 }).format('HH:mm'), end_time: moment().set({ hour: 18, minute: 0, second: 0 }).format('HH:mm'), is_holiday: false, breaks: [] },
    { day: 'wednesday', start_time: moment().set({ hour: 9, minute: 0, second: 0 }).format('HH:mm'), end_time: moment().set({ hour: 18, minute: 0, second: 0 }).format('HH:mm'), is_holiday: false, breaks: [] },
    { day: 'thursday', start_time: moment().set({ hour: 9, minute: 0, second: 0 }).format('HH:mm'), end_time: moment().set({ hour: 18, minute: 0, second: 0 }).format('HH:mm'), is_holiday: false, breaks: [] },
    { day: 'friday', start_time: moment().set({ hour: 9, minute: 0, second: 0 }).format('HH:mm'), end_time: moment().set({ hour: 18, minute: 0, second: 0 }).format('HH:mm'), is_holiday: false, breaks: [] },
  ]
}
const weekdays = ref(defaultData())

const handleCopy = () => {
  weekdays.value.forEach((day, index) => {
    if (index !== 0) {
      day.start_time = weekdays.value[0].start_time
      day.end_time = weekdays.value[0].end_time
      day.is_holiday = weekdays.value[0].is_holiday
      day.breaks = [...weekdays.value[0].breaks]
    }
  })
}

const loadBusinessHours = async () => {
  try {
    const res = await axios.post(route('employee.working_hours.business_hours.list'), {
      employee_id: props.employeeId
    });

    if (res.data && res.data.length > 0) {
      businessHours.value = res.data;
    } else {
      businessHours.value = [];
    }
  } catch (error) {
    console.error("Error loading business hours:", error);
  }
};

const addInputField = (day) => {
  day.breaks.push({
    start_break: moment().set({ hour: 12, minute: 0, second: 0 }).format('HH:mm'),
    end_break: moment().set({ hour: 13, minute: 0, second: 0 }).format('HH:mm')
  })
}

const deleteInputField = (day, index) => {
  day.breaks.splice(index, 1)
}

//Form Submit
const formSubmit = handleSubmit((values) => {
  IS_SUBMITED.value = true
  values.weekdays = weekdays.value

  if (props.employeeId) {
    values.employee_id = props.employeeId
  }

  storeRequest({ url: BUSINESS_HOURS_STORE(), body: values }).then((res) => {
    if (res.status) {
      weekdays.value = res.data
      display_submit_message(res)
    } else {
      display_submit_message(res)
    }
  })
})
</script>

<style scoped>
.multiselect-clear {
  display: none !important;
}
.clickable-text {
  display: inline-block;
  cursor: pointer;
}
.bussiness-hour .data-scrollbar {
  height: 700px;
  overflow-y: auto;
}
.background_colour {
  background-color: #50494917 !important;
  cursor: not-allowed;
}
.copy-icon {
  color: gray;
}
</style>
