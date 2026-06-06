<template>
            <div class="pagination-controls">
                <nav aria-label="Pagination">
                <ul class="pagination justify-content-end">
                <div class="dropdown ms-2"> <!-- إضافة مسافة بسيطة من زر الريفرش -->
                  <button
                    class="btn btn-secondary dropdown-toggle"
                    type="button"
                    id="employeeDropdown"
                    data-bs-toggle="dropdown"
                    aria-expanded="false"
                  >
                    {{ selectedEmployeeName || 'All Employees' }}
                  </button>
                  <ul class="dropdown-menu" aria-labelledby="employeeDropdown">
                    <li @click="filterByEmployee(null)">
                      <a class="dropdown-item" href="#">All Employees</a>
                    </li>
                    <li v-for="employee in EMPLOYEE_LIST" :key="employee.id" @click="filterByEmployee(employee)">
                      <a class="dropdown-item" href="#">{{ employee.title }}</a>
                    </li>
                  </ul>
                </div>

                  <li class="px-2"> 
                    <button
                  id="refresh"
                  class="btn bg-primary rounded"
                  data-bs-toggle="tooltip"
                  title="refresh"
                  @click="refreshPage"
                >
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M21.4799 12.2424C21.7557 12.2326 21.9886 12.4482 21.9852 12.7241C21.9595 14.8075 21.2975 16.8392 20.0799 18.5506C18.7652 20.3986 16.8748 21.7718 14.6964 22.4612C12.518 23.1505 10.1711 23.1183 8.01299 22.3694C5.85488 21.6205 4.00382 20.196 2.74167 18.3126C1.47952 16.4293 0.875433 14.1905 1.02139 11.937C1.16734 9.68346 2.05534 7.53876 3.55018 5.82945C5.04501 4.12014 7.06478 2.93987 9.30193 2.46835C11.5391 1.99683 13.8711 2.2599 15.9428 3.2175L16.7558 1.91838C16.9822 1.55679 17.5282 1.62643 17.6565 2.03324L18.8635 5.85986C18.945 6.11851 18.8055 6.39505 18.549 6.48314L14.6564 7.82007C14.2314 7.96603 13.8445 7.52091 14.0483 7.12042L14.6828 5.87345C13.1977 5.18699 11.526 4.9984 9.92231 5.33642C8.31859 5.67443 6.8707 6.52052 5.79911 7.74586C4.72753 8.97119 4.09095 10.5086 3.98633 12.1241C3.8817 13.7395 4.31474 15.3445 5.21953 16.6945C6.12431 18.0446 7.45126 19.0658 8.99832 19.6027C10.5454 20.1395 12.2278 20.1626 13.7894 19.6684C15.351 19.1743 16.7062 18.1899 17.6486 16.8652C18.4937 15.6773 18.9654 14.2742 19.0113 12.8307C19.0201 12.5545 19.2341 12.3223 19.5103 12.3125L21.4799 12.2424Z" fill="#ffffff"></path>
                    <path d="M20.0941 18.5594C21.3117 16.848 21.9736 14.8163 21.9993 12.7329C22.0027 12.4569 21.7699 12.2413 21.4941 12.2512L19.5244 12.3213C19.2482 12.3311 19.0342 12.5633 19.0254 12.8395C18.9796 14.283 18.5078 15.6861 17.6628 16.8739C16.7203 18.1986 15.3651 19.183 13.8035 19.6772C12.2419 20.1714 10.5595 20.1483 9.01246 19.6114C7.4654 19.0746 6.13845 18.0534 5.23367 16.7033C4.66562 15.8557 4.28352 14.9076 4.10367 13.9196C4.00935 18.0934 6.49194 21.37 10.008 22.6416C10.697 22.8908 11.4336 22.9852 12.1652 22.9465C13.075 22.8983 13.8508 22.742 14.7105 22.4699C16.8889 21.7805 18.7794 20.4073 20.0941 18.5594Z" fill="#ffffff"></path>
                  </svg>
                </button>
              </li>
                  <!-- Page Number Before Current -->
                  <li v-if="currentPage > 1" class="page-item">
                    <button @click="goToPage(currentPage - 1)" class="page-link">{{ currentPage - 1 }}</button>
                  </li>
                  <!-- Page Number After Current -->
                  <li v-if="currentPage < totalPages" class="page-item">
                    <button @click="goToPage(currentPage + 1)" class="page-link">{{ currentPage + 1 }}</button>
                  </li>
                </ul>
              </nav>

            </div>
  <div ref="calenderRef" class="calendar-root"></div>
  <booking-form :booking-type="bookingType"
                :status-list="bookingStatus"
                @onSubmit="onSubmitEvent"
                :booking-data="bookingData"></booking-form>
</template>
<script setup>
import { reactive, ref, onMounted, onUnmounted, nextTick } from 'vue'
import { createRequest } from '@/helpers/utilities'

import Calendar from '@event-calendar/core'
import DayGrid from '@event-calendar/day-grid'
import List from '@event-calendar/list'
import TimeGrid from '@event-calendar/time-grid'
import ResourceTimeGrid from '@event-calendar/resource-time-grid'
import Interaction from '@event-calendar/interaction'

import BookingForm from './BookingForm.vue'
import { INDEX_URL } from '../constant/booking'
import * as moment from 'moment'
const totalEmployees = ref(0)
const props = defineProps({
  status: { type: String, required: true },
  slotDuration: { type: String },
  branchId: {type: [String , Number]},
  date: new Date()
})
let slotsDurations = '00:15'
if(props.slotDuration !== '') {
  slotsDurations = props.slotDuration
}
const bookingStatus = ref(JSON.parse(props.status))
const calenderRef = ref(null)
const calenderInit = ref(null)
const EMPLOYEE_LIST = ref([])   // دي هتتملي من API (employees)
const selectedEmployeeId = ref(null) // ID الموظف المختار
const selectedEmployeeName = ref(null) // اسم الموظف المختار

const employeeAvailability = ref({})
const bookingType = ref('')
const bookingData = reactive({
  id: 0,
  start_date_time: null,
  employee_id: null,
  employee_name: null,
  branch_id: props.branchId
})
const resourceWidths = ref([])
const resizeState = reactive({
  active: false,
  index: -1,
  startX: 0,
  startWidth: 0
})
const MIN_RESOURCE_WIDTH = 120
const MAX_RESOURCE_WIDTH = 420
const DEFAULT_RESOURCE_WIDTH = 180
const RESIZE_HANDLE_SIZE = 6
let resizeHandlersAttached = false
let detachResizeHandlers = null
let detachTopScroll = null
let topScrollState = null
let fixedTimeColumnFrame = null

const getHorizontalScrollTargets = () => {
  if (!calenderRef.value) return []
  return Array.from(calenderRef.value.querySelectorAll('.ec-header, .ec-all-day, .ec-body'))
}

const getFixedTimeColumns = () => {
  if (!calenderRef.value) return []
  return Array.from(calenderRef.value.querySelectorAll(
    '.ec-header > .ec-sidebar, .ec-all-day > .ec-sidebar, .ec-body > .ec-content > .ec-sidebar'
  ))
}

const updateFixedTimeColumns = () => {
  const root = calenderRef.value
  if (!root) return

  const rootRect = root.getBoundingClientRect()
  const direction = getComputedStyle(root).direction || document.dir || 'ltr'
  const isRtl = direction === 'rtl'

  getFixedTimeColumns().forEach((column) => {
    column.style.transform = 'none'
    const columnRect = column.getBoundingClientRect()
    const offset = isRtl ? rootRect.right - columnRect.right : rootRect.left - columnRect.left
    column.style.transform = `translateX(${offset}px)`
  })
}

const scheduleFixedTimeColumnsUpdate = () => {
  if (fixedTimeColumnFrame) {
    cancelAnimationFrame(fixedTimeColumnFrame)
  }

  fixedTimeColumnFrame = requestAnimationFrame(() => {
    fixedTimeColumnFrame = null
    updateFixedTimeColumns()
  })
}

const resetFixedTimeColumns = () => {
  if (fixedTimeColumnFrame) {
    cancelAnimationFrame(fixedTimeColumnFrame)
    fixedTimeColumnFrame = null
  }
  getFixedTimeColumns().forEach((column) => {
    column.style.transform = ''
  })
}

const refreshPage = () => {
  window.location.reload();
};

const setBooking = (info) => {
  const employeeId = getInfoEmployeeId(info)
  const employee = EMPLOYEE_LIST.value.find((item) => String(item.id) === String(employeeId))
  bookingData.id = info.id || 0
  bookingData.employee_id = employeeId
  bookingData.employee_name = employee?.title || null
  bookingData.start_date_time = getInfoStartDate(info)
  bookingData.branch_id = resolveBranchId(info)
}

const getInfoStartDate = (info) => {
  return info?.date || info?.start || info?.event?.start || null
}

const getInfoEmployeeId = (info) => {
  return info?.resource?.id || info?.resourceIds?.[0] || info?.event?.resourceIds?.[0] || null
}

const resolveBranchId = (info) => {
  const resourceBranchId = info?.resource?.branch_id || info?.resource?.extendedProps?.branch_id
  if (Number(resourceBranchId) > 0) {
    return resourceBranchId
  }

  const employeeId = getInfoEmployeeId(info)
  const employee = EMPLOYEE_LIST.value.find((item) => String(item.id) === String(employeeId))
  if (Number(employee?.branch_id) > 0) {
    return employee.branch_id
  }

  return props.branchId
}

const slotUnavailableMessage = () => {
  const message = 'اختر موعد يكون الموظف متاح من خلاله'
  if (window.errorSnackbar) {
    window.errorSnackbar(message)
    return
  }

  window.alert(message)
}

const isWithinRange = (date, range) => {
  const clicked = moment(date)
  const start = moment(range.start)
  const end = moment(range.end)

  return clicked.isSameOrAfter(start) && clicked.isBefore(end)
}

const isWithinBreak = (date, range) => {
  return (range.breaks || []).some((staffBreak) => isWithinRange(date, staffBreak))
}

const isAvailableSlot = (info) => {
  const employeeId = getInfoEmployeeId(info)
  const startDate = getInfoStartDate(info)
  if (!employeeId || !startDate) {
    return false
  }

  const ranges = employeeAvailability.value[employeeId] || employeeAvailability.value[String(employeeId)] || []

  return ranges.some((range) => isWithinRange(startDate, range) && !isWithinBreak(startDate, range))
}

const handleCalendarSlotClick = (info) => {
  if (!isAvailableSlot(info)) {
    slotUnavailableMessage()
    return
  }

  showBookingForm(info)
}

const showBookingForm = async (info, type = 'CALENDER_BOOKING') => {
  bookingType.value = type
  setBooking(info)
  await nextTick()
  const elem = document.getElementById('booking-form')
  if (!elem) return
  const form = bootstrap.Offcanvas.getOrCreateInstance(elem)
  form.show()
  document.querySelector('.offcanvas-backdrop')?.remove()
  updateBodyClass('show')
}

const hideBookingForm = () => {
  const elem = document.getElementById('booking-form')
  const form = bootstrap.Offcanvas.getOrCreateInstance(elem)
  form.hide()
  updateBodyClass('hide')
}

const updateBodyClass = (value = 'hide') => {
  if(value == 'show') {
    document.body.classList.add('calender-view')
  } else {
    document.body.classList.remove('calender-view')
  }
}

const createBooking = () => {
  showBookingForm({}, 'CREATE_BOOKING')
}

const filterByEmployee = (employee) => {
  if (employee === null) {
    selectedEmployeeId.value = null
    selectedEmployeeName.value = 'All Employees'
  } else {
    selectedEmployeeId.value = employee.id
    selectedEmployeeName.value = employee.title
  }

  // إعادة تحميل الـ events بعد الاختيار
  calenderInit.value.refetchEvents()
  refreshResourceSizing()
}

const syncTopHorizontalScroll = () => {
  const root = calenderRef.value
  if (!root) return

  const scrollTargets = getHorizontalScrollTargets()
  if (!scrollTargets.length) return

  if (topScrollState) {
    topScrollState.scrollTargets.forEach((target) => {
      target.removeEventListener('scroll', topScrollState.onTargetScroll)
    })
    topScrollState.scrollTargets = scrollTargets
    topScrollState.scrollTargets.forEach((target) => {
      target.addEventListener('scroll', topScrollState.onTargetScroll)
    })
    updateTopScrollbarWidth()
    scheduleFixedTimeColumnsUpdate()
    return
  }

  const scrollbar = document.createElement('div')
  const spacer = document.createElement('div')
  scrollbar.className = 'booking-calendar-scrollbar'
  spacer.className = 'booking-calendar-scrollbar-spacer'
  scrollbar.appendChild(spacer)
  root.insertBefore(scrollbar, root.firstChild)

  let syncing = false
  const updateSpacer = () => {
    const width = Math.max(...topScrollState.scrollTargets.map((target) => target.scrollWidth))
    spacer.style.width = `${width}px`
    scheduleFixedTimeColumnsUpdate()
  }
  const onTopScroll = () => {
    if (syncing) return
    syncing = true
    topScrollState.scrollTargets.forEach((target) => {
      target.scrollLeft = scrollbar.scrollLeft
    })
    scheduleFixedTimeColumnsUpdate()
    syncing = false
  }
  const onTargetScroll = (event) => {
    if (syncing) return
    syncing = true
    scrollbar.scrollLeft = event.target.scrollLeft
    topScrollState.scrollTargets.forEach((target) => {
      if (target !== event.target) {
        target.scrollLeft = event.target.scrollLeft
      }
    })
    scheduleFixedTimeColumnsUpdate()
    syncing = false
  }

  scrollbar.addEventListener('scroll', onTopScroll)
  scrollTargets.forEach((target) => {
    target.addEventListener('scroll', onTargetScroll)
  })
  window.addEventListener('resize', updateSpacer)

  topScrollState = { scrollbar, spacer, scrollTargets, updateSpacer, onTargetScroll }
  updateSpacer()

  detachTopScroll = () => {
    scrollbar.removeEventListener('scroll', onTopScroll)
    topScrollState.scrollTargets.forEach((target) => {
      target.removeEventListener('scroll', onTargetScroll)
    })
    window.removeEventListener('resize', updateSpacer)
    scrollbar.remove()
    topScrollState = null
    resetFixedTimeColumns()
  }
}

const updateTopScrollbarWidth = () => {
  if (!topScrollState) return
  topScrollState.updateSpacer()
}

const clampResourceWidth = (value) => {
  return Math.min(MAX_RESOURCE_WIDTH, Math.max(MIN_RESOURCE_WIDTH, value))
}

const loadResourceWidths = () => {
  try {
    const raw = localStorage.getItem('bookingResourceWidths')
    if (!raw) return []
    const parsed = JSON.parse(raw)
    if (!Array.isArray(parsed)) return []
    return parsed.map((width) => clampResourceWidth(parseInt(width, 10) || DEFAULT_RESOURCE_WIDTH))
  } catch (error) {
    return []
  }
}

const saveResourceWidths = () => {
  try {
    localStorage.setItem('bookingResourceWidths', JSON.stringify(resourceWidths.value))
  } catch (error) {
    // ignore
  }
}

const syncResourceWidths = (count) => {
  if (count <= 0) return

  if (resourceWidths.value.length === 0) {
    const saved = loadResourceWidths()
    if (saved.length === count) {
      resourceWidths.value = saved
      return
    }

    resourceWidths.value = Array.from({ length: count }, (_, index) =>
      saved[index] ?? DEFAULT_RESOURCE_WIDTH
    )
    return
  }

  if (resourceWidths.value.length !== count) {
    const next = Array.from({ length: count }, (_, index) =>
      resourceWidths.value[index] ?? DEFAULT_RESOURCE_WIDTH
    )
    resourceWidths.value = next
  }
}

const setElementWidth = (element, width) => {
  const safeWidth = clampResourceWidth(width)
  element.style.flex = `0 0 ${safeWidth}px`
  element.style.minWidth = `${safeWidth}px`
  element.style.maxWidth = `${safeWidth}px`
  element.style.width = `${safeWidth}px`
}

const applyResourceWidths = () => {
  const root = calenderRef.value
  if (!root) return

  const headerResources = Array.from(root.querySelectorAll('.ec-header .ec-resource'))
  const bodyResources = Array.from(root.querySelectorAll('.ec-body .ec-resource'))
  const allDayResources = Array.from(root.querySelectorAll('.ec-all-day .ec-resource'))
  const allDayDays = allDayResources.length
    ? []
    : Array.from(root.querySelectorAll('.ec-all-day .ec-days'))
  const count = Math.max(
    headerResources.length,
    bodyResources.length,
    allDayResources.length,
    allDayDays.length
  )

  if (count === 0) return
  syncResourceWidths(count)

  headerResources.forEach((element, index) => {
    setElementWidth(element, resourceWidths.value[index] ?? DEFAULT_RESOURCE_WIDTH)
  })

  bodyResources.forEach((element, index) => {
    setElementWidth(element, resourceWidths.value[index] ?? DEFAULT_RESOURCE_WIDTH)
  })

  allDayResources.forEach((element, index) => {
    setElementWidth(element, resourceWidths.value[index] ?? DEFAULT_RESOURCE_WIDTH)
  })

  allDayDays.forEach((element, index) => {
    setElementWidth(element, resourceWidths.value[index] ?? DEFAULT_RESOURCE_WIDTH)
  })
}

const refreshResourceSizing = () => {
  if (!calenderRef.value) return
  requestAnimationFrame(() => {
    applyResourceWidths()
    attachResizeHandlers()
    syncTopHorizontalScroll()
    updateTopScrollbarWidth()
  })
}

const attachResizeHandlers = () => {
  if (resizeHandlersAttached || !calenderRef.value) return

  const root = calenderRef.value
  resizeHandlersAttached = true

  const onMouseMove = (event) => {
    if (resizeState.active) return
    const resourceCell = event.target.closest('.ec-resource')
    if (!resourceCell || !root.contains(resourceCell)) {
      root.style.cursor = ''
      return
    }

    const rect = resourceCell.getBoundingClientRect()
    const nearEdge = rect.right - event.clientX <= RESIZE_HANDLE_SIZE
    root.style.cursor = nearEdge ? 'col-resize' : ''
  }

  const onMouseDown = (event) => {
    const resourceCell = event.target.closest('.ec-resource')
    if (!resourceCell || !root.contains(resourceCell)) return

    const rect = resourceCell.getBoundingClientRect()
    if (rect.right - event.clientX > RESIZE_HANDLE_SIZE) return

    const headerCells = Array.from(root.querySelectorAll('.ec-header .ec-resource'))
    const bodyCells = Array.from(root.querySelectorAll('.ec-body .ec-resource'))
    const totalCells = Math.max(headerCells.length, bodyCells.length)

    let index = headerCells.indexOf(resourceCell)
    if (index < 0) {
      index = bodyCells.indexOf(resourceCell)
    }
    if (index < 0) return

    syncResourceWidths(totalCells)
    resizeState.active = true
    resizeState.index = index
    resizeState.startX = event.clientX
    resizeState.startWidth = resourceWidths.value[index] ?? rect.width
    document.body.classList.add('ec-resizing')
    event.preventDefault()
  }

  const onDrag = (event) => {
    if (!resizeState.active) return
    const delta = event.clientX - resizeState.startX
    const nextWidth = clampResourceWidth(resizeState.startWidth + delta)
    if (resourceWidths.value[resizeState.index] !== nextWidth) {
      resourceWidths.value.splice(resizeState.index, 1, nextWidth)
      applyResourceWidths()
    }
  }

  const onMouseUp = () => {
    if (!resizeState.active) return
    resizeState.active = false
    resizeState.index = -1
    document.body.classList.remove('ec-resizing')
    saveResourceWidths()
  }

  root.addEventListener('mousemove', onMouseMove)
  root.addEventListener('mousedown', onMouseDown)
  document.addEventListener('mousemove', onDrag)
  document.addEventListener('mouseup', onMouseUp)

  detachResizeHandlers = () => {
    root.removeEventListener('mousemove', onMouseMove)
    root.removeEventListener('mousedown', onMouseDown)
    document.removeEventListener('mousemove', onDrag)
    document.removeEventListener('mouseup', onMouseUp)
  }
}


onUnmounted(() => {
  window.removeEventListener('booking:create', createBooking)
  const elem = document.getElementById('booking-form')
  if(elem !== null) {
    updateBodyClass('hide')
    elem.removeEventListener('hide.bs.offcanvas', function() {
      setBooking({})
      updateBodyClass('hide')
      bookingType.value = ''
    })
  }
  if (detachResizeHandlers) {
    detachResizeHandlers()
    detachResizeHandlers = null
    resizeHandlersAttached = false
  }
  if (detachTopScroll) {
    detachTopScroll()
    detachTopScroll = null
  }
  document.body.classList.remove('ec-resizing')
})
onMounted(() => {
  window.addEventListener('booking:create', createBooking)
  const elem = document.getElementById('booking-form')
  if(elem !== null) {
    elem.addEventListener('hide.bs.offcanvas', function() {
      setBooking({})
      updateBodyClass('hide')
      bookingType.value = ''
    })
    const bkid = new URL(location.href).searchParams.get('booking_id')
    if(bkid !== null && bkid !== undefined) {
      bookingType.value = 'CALENDER_BOOKING'
      showBookingForm({id: bkid})
    }
  }
  if (calenderRef !== null) {
    calenderInit.value = new Calendar({
      target: calenderRef.value,
      props: {
        plugins: [DayGrid, List, TimeGrid, ResourceTimeGrid, Interaction],
        options: {
          date: props.date,
          slotEventOverlap: false,
          dragScroll: false,
          view: 'resourceTimeGridDay',
          height: '800px',
          headerToolbar: {
            start: 'prev,next today',
            center: 'title',
            end: 'resourceTimeGridDay'
            // dayGridMonth,timeGridWeek,timeGridDay,listWeek
          },
          buttonText: function (texts) {
            texts.resourceTimeGridDay = 'Day'
            texts.resourceTimeGridWeek = 'Week'
            return texts
          },
          eventContent: function (data) {
          //   // console.log(data, data.event.titleHTML)
            if(data.event.titleHTML !== undefined) {
              return {html: data.event.titleHTML + data.timeText}
            }
            return data.timeText
          },
          slotLabelFormat: function (data) {
            // Convert the input string to a Date object
            const date = new Date(data);

            // Get the hour and minute from the Date object
            const minute = data.getMinutes();

            // Check if the hour and minute are both "00"
            if (minute === 0) {
              return moment(data).format('hh:mm A');
            } else {
              return '';
            }
          },
          resources: [],
          scrollTime: '09:00:00',
          events: [],
          views: {
            timeGridWeek: { pointer: true },
            resourceTimeGridWeek: { pointer: true },
            resourceTimeGridDay: { pointer: true }
          },
          eventSources: [
            {
              events: async function (fetchInfo) {
                const visibleDate = fetchInfo && fetchInfo.start ? fetchInfo.start : props.date
                const params = {
                    employee_id: selectedEmployeeId.value,
                    branch_id: props.branchId,
                    date: moment(visibleDate).format('YYYY-MM-DD')
                };
              const events = await createRequest(INDEX_URL(params)).then((res) => {
                  const { employees, data } = res
                  totalEmployees.value = res.total_count
                  EMPLOYEE_LIST.value = employees
                  employeeAvailability.value = res.availability || {}
                  calenderInit.value.setOption('resources', employees)
                  refreshResourceSizing()
                  return data
                })
                return events
              }
            }
          ],
          dateClick: function (info) {
            handleCalendarSlotClick(info)
          },
          select: function (info) {
            handleCalendarSlotClick(info)
          },
          eventClick: function (info) {
            if (info.event.display === 'background') {
              return
            }

            const resourceId = info.event.resourceIds[0]
            const employee = EMPLOYEE_LIST.value.find((item) => String(item.id) === String(resourceId))
            const updatedInfo = {
              id: info.event.extendedProps?.booking_id || info.event.id,
              resource: {
                id: resourceId,
                branch_id: info.event.extendedProps?.branch_id || employee?.branch_id
              },
              date: info.event.start
            }
            showBookingForm(updatedInfo)
          },
          eventStartEditable: false,
          slotDuration: slotsDurations,
          dayMaxEvents: true,
          nowIndicator: true,
          selectable: false
        }
      }
    })
    refreshResourceSizing()
  }
})

const onSubmitEvent = () => {
  calenderInit.value.refetchEvents()
  refreshResourceSizing()
}



</script>
<style >
@import '@event-calendar/core/index.css';
body {
  transition: width 400ms ease;
}
.calender-view {
  width: calc(100% - 382px);
  transition: width 400ms ease;
}
.ec-lines {
  width: unset;
  margin-left: 8px;
}
.ec-header .ec-day {
  overflow: inherit !important;
  height: inherit !important;
  line-height: inherit;
  min-height: inherit;
}
.ec-day.ec-today {
  background-color: var(--bs-body-bg);
}
.dark .ec-day.ec-today {
  background-color: #181818;
}
.ec-event{
  border-radius: 0;
  border-bottom: 2px solid var(--bs-border-color);
  cursor: pointer;
}
.calendar-root .ec-bg-event {
  opacity: 1;
  pointer-events: none;
}
.calendar-root .ec-body .ec-day {
  position: relative;
}
.calendar-root .ec-body .ec-day::after {
  content: "";
  position: absolute;
  inset: 0;
  z-index: 1;
  pointer-events: none;
  background-image: repeating-linear-gradient(
    to bottom,
    transparent 0,
    transparent 23px,
    rgba(17, 24, 39, 0.32) 23px,
    rgba(17, 24, 39, 0.32) 24px
  );
}
.calendar-root .ec-bg-events {
  position: relative;
  z-index: 0;
}
.calendar-root .ec-events {
  position: relative;
  z-index: 2;
}
.ec-body:not(.ec-compact) .ec-line:nth-child(even):after{
  border-bottom-style: solid;
}
.ec-line:not(:first-child):after {
  border-color: rgba(17, 24, 39, 0.28);
}
.ec-header,.ec-all-day,.ec-body,.ec-days,.ec-day{
  border-color: rgba(17, 24, 39, 0.32);
}
.calendar-root .ec-resource,
.calendar-root .ec-sidebar,
.calendar-root .ec-time,
.calendar-root .ec-line-time,
.calendar-root .ec-lines,
.calendar-root .ec-days,
.calendar-root .ec-day {
  border-color: rgba(17, 24, 39, 0.32) !important;
}
.calendar-root .ec-body:not(.ec-compact) .ec-line:after {
  border-bottom-color: rgba(17, 24, 39, 0.28) !important;
}
.ec-button, .ec-button:not(:disabled) {
  color: var(--bs-body-color);
  background-color: var(--bs-body-bg);
  border-color: var(--bs-border-color);
}
.dark .ec-button:not(:disabled):hover, .dark .ec-button.ec-active {
  border-color: var(--bs-border-color);
  background-color: var(--bs-body-bg);
}
.ec-icon.ec-prev:after, .ec-icon.ec-next:after {
  border-color: var(--bs-body-color);
}
.calendar-root {
  position: relative;
  overflow-x: hidden;
}
.calendar-root .ec-header,
.calendar-root .ec-all-day,
.calendar-root .ec-body {
    overflow-x: hidden !important;
    scrollbar-width: none;
}
.calendar-root .ec-header::-webkit-scrollbar,
.calendar-root .ec-all-day::-webkit-scrollbar,
.calendar-root .ec-body::-webkit-scrollbar {
    display: none;
}
.calendar-root .ec-hidden-scroll,
.calendar-root .ec-body > .ec-scroll {
    overflow-x: hidden !important;
    scrollbar-width: none;
}
.calendar-root .ec-hidden-scroll::-webkit-scrollbar,
.calendar-root .ec-body > .ec-scroll::-webkit-scrollbar {
    display: none;
}
body.ec-resizing {
  user-select: none;
  cursor: col-resize;
}
.booking-calendar-scrollbar {
  overflow-x: auto;
  overflow-y: hidden;
  height: 16px;
  margin-bottom: 8px;
}
.booking-calendar-scrollbar-spacer {
  height: 1px;
}
.calendar-root .ec-sidebar,
.calendar-root .ec-time,
.calendar-root .ec-line-time {
  position: sticky;
  inset-inline-start: 0;
  z-index: 5;
  background: var(--bs-body-bg);
}
.calendar-root .ec-sidebar {
  flex-shrink: 0;
  will-change: transform;
}
.calendar-root .ec-header .ec-sidebar,
.calendar-root .ec-header .ec-time {
  z-index: 8;
}
[dir='rtl'] .calendar-root .ec-sidebar,
[dir='rtl'] .calendar-root .ec-time,
[dir='rtl'] .calendar-root .ec-line-time {
  right: 0;
  left: auto;
}
</style>
