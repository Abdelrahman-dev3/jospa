<template>
  <div class="d-flex align-items-center justify-content-between w-100 border-top p-3">
    <!-- Current Status Display -->
    <div class="d-flex align-items-center gap-3">
      <span class="p-2 border border-light rounded-circle"
        :style="{ 'background-color': filterStatus(status)?.color_hex }">
        <span class="visually-hidden">Status</span>
      </span>
      <h5 class="mb-0">{{ filterStatus(status)?.title }}</h5>
    </div>
    <!-- Dropdown Select for Status Change -->
    <select v-if="props.booking_id > 0 && props.employee_id > 0"  class="form-select w-auto" v-model="selectedStatus" @change="changeBookingStatus(selectedStatus)">
    <option v-for="(item, key) in filteredStatusList" :key="key" :value="key"  v-if="key !== 'completed'">
        {{ item.title }}
      </option>
    </select>
    <template v-for="(singleStatus, key) in filteredStatusList" :key="key" >
      <button type="button"
          v-if="canShowButton(key, singleStatus?.next_status)" class="btn btn-outline-primary rounded-pill" @click="changeBookingStatus(singleStatus?.next_status)">
        {{ filterStatus(singleStatus?.next_status).title === 'Confirmed' ? 'Confirm' : filterStatus(singleStatus?.next_status).title }}
      </button>

    </template>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import { UPDATE_STATUS } from '../../constant/booking'
import { useRequest } from '@/helpers/hooks/useCrudOpration'

import { computed } from 'vue'

const filteredStatusList = computed(() => {
  const excluded = ['completed']
  return Object.fromEntries(
    Object.entries(props.statusList).filter(([key]) => !excluded.includes(key))
  )
})


// Props
const props = defineProps({
  status: String,
  statusList: { type: Object, default: () => [] },
  employee_id: Number | String,
  booking_id: Number | String
})

// Emit
const emit = defineEmits(['statusUpdate'])

// State
const selectedStatus = ref(props.status)
// Request
const { updateRequest } = useRequest()
// Watch for prop changes and sync
watch(() => props.status, (newStatus) => {
  selectedStatus.value = newStatus
})

// Utils
const filterStatus = (value) => {
  return props.statusList[value]
}

// Check if we should show the button
const canShowButton = (statusKey, nextStatus) => {
  return props.booking_id > 0 &&
    props.employee_id > 0 &&
    filterStatus(nextStatus)?.title &&
    props.status === statusKey
}



// Change Status
const changeBookingStatus = (status) => {
  const data = {
    status: status
  }
  updateRequest({ url: UPDATE_STATUS, id: props.booking_id, body: data }).then((res) => {
    if (res.status) {
      emit('statusUpdate', res.data)
      window.successSnackbar(res.message)
    }
  })
}
</script>
