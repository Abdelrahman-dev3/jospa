<template>
  <form @submit="formSubmit">
    <div class="offcanvas offcanvas-end" tabindex="-1" id="form-offcanvas" aria-labelledby="form-offcanvasLabel">
      <FormHeader :currentId="currentId" :editTitle="editTitle" :createTitle="createTitle"></FormHeader>
      <div class="offcanvas-body">
        <fieldset>
          <legend>{{ $t('product.product_information') }}</legend>
          <div class="row">
            <div class="form-group col-md-4">
              <div class="text-center">
                <img :src="ImageViewer || defaultImage" alt="package-image" class="img-fluid mb-2 product-image-thumbnail" />
                <div v-if="validationMessage" class="text-danger mb-2">{{ validationMessage }}</div>
                <div class="d-flex align-items-center justify-content-center gap-2">
                  <input type="file" ref="packageInputRef" class="form-control d-none" id="package_image" name="package_image" @change="fileUpload" accept=".jpeg, .jpg, .png, .gif" />
                  <label class="btn btn-info" for="package_image">{{ $t('messages.upload') }}</label>
                  <input type="button" class="btn btn-danger" name="remove" :value="$t('messages.remove')" @click="removePackageImage()" v-if="ImageViewer" />
                </div>
              </div>
            </div>

            <div class="col-md-8">
              <!-- <InputField class="" type="text" :is-required="true" :label="$t('package.lbl_name')+' '+ $t('settings.translate.ar')" placeholder="Enter Package Name" v-model="name['ar']" :error-message="errorMessages['name'] && errorMessages['name']['ar']"></InputField>
              <InputField class="" type="text" :is-required="true" :label="$t('package.lbl_name')+' '+ $t('settings.translate.en')" placeholder="Enter Package Name" v-model="name['en']" :error-message="errorMessages['name'] && errorMessages['name']['en']"></InputField> -->
              <InputField :is-required="true" :label="$t('package.lbl_name') + ' ' + $t('settings.translate.ar')" :placeholder="$t('package.placeholder_name')" v-model="nameAr" :error-message="nameArError" :error-messages="errorMessages['name'] && errorMessages['name']['ar']" />

              <InputField :is-required="true" :label="$t('package.lbl_name') + ' ' + $t('settings.translate.en')" :placeholder="$t('package.placeholder_name')" v-model="nameEn" :error-message="nameEnError" :error-messages="errorMessages['name'] && errorMessages['name']['en']" />
              <InputField class="" type="textarea" :textareaRows="5" :label="$t('package.lbl_description')" placeholder="Enter Description" v-model="description"></InputField>
            </div>
          </div>

          <div class="row">
            <div class="form-group col-md-4">
              <label class="form-label" for="branch">{{ $t('employee.lbl_select_branch') }}</label
              ><span class="text-danger">*</span>
              <Multiselect id="branch_id" v-model="branch_id" :value="branch_id" placeholder="Select Branch" v-bind="singleSelectOption" :options="branch.options" @select="onBranchChange" class="form-group"> </Multiselect>
              <span class="text-danger">{{ errors.branch_id }}</span>
            </div>
            <div class="form-group col-md-8">
              <label class="form-label" for="package_category_ids">{{ $t('service.lbl_category') }}</label>
              <Multiselect
                id="package_category_ids"
                v-model="category_ids"
                :value="category_ids"
                placeholder="Select categories first to filter services"
                v-bind="multiSelectOption"
                :options="category.options"
                :disabled="!branch_id"
                class="form-group"
              >
              </Multiselect>
              <small class="text-muted">Selected categories will filter the services available in this package.</small>
            </div>
          </div>

          <div class="row">
            <div class="col-md-4">
              <InputField
                type="number"
                min="0"
                step="0.01"
                :is-required="true"
                :label="$t('package.lbl_package_price')"
                placeholder="0.00"
                v-model="package_price"
                :error-message="errors.package_price"
              ></InputField>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label class="form-label" for="start_date">{{ $t('package.lbl_start_at') }}</label>
                <div class="w-100">
                  <flat-pickr id="start_date" class="form-control" :config="config" v-model="start_date" :value="start_date" :placeholder="$t('package.lbl_start_at')" :disabled="isUnlimited"></flat-pickr>
                  <span class="text-danger">{{ errors.start_date }}</span>
                </div>
              </div>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="end_date">{{ $t('package.lbl_end_at') }}</label>
              <div class="w-100">
                <flat-pickr id="end_date" class="form-control" :config="config" v-model="end_date" :value="end_date" :placeholder="$t('package.lbl_end_at')" :disabled="isUnlimited"></flat-pickr>
                <span class="text-danger">{{ errors.end_date }}</span>
              </div>
            </div>
            <div class="col-md-4 d-flex align-items-end">
              <div class="form-check form-switch">
                <input class="form-check-input" id="no_expiry" type="checkbox" v-model="isUnlimited" />
                <label class="form-label mb-0 ms-2" for="no_expiry">{{ $t('employee.No_expiry') }}</label>
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-12 px-5">
              <div class="form-group">
                <div class="d-flex justify-content-between align-items-center">
                  <div class="d-flex align-items-center gap-3">
                    <label class="form-label mb-0" for="category-status">{{ $t('employee.lbl_status') }}</label>
                    <div class="form-check form-switch">
                      <input class="form-check-input" :value="1" name="status" id="category-status" type="checkbox" v-model="status" />
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </fieldset>
        <fieldset>
          <legend>{{ $t('package.lbl_services') }}</legend>
          <div class="row">
            <div class="table-responsive">
              <table class="table table-striped border table-responsive dataTable no-footer">
                <thead>
                  <tr>
                    <th>Service</th>
                    <th>Unit Price</th>
                    <th>Quantity</th>
                    <th>Total Price</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tr v-if="selectedServices.length === 0">
                  <td colspan="5" class="text-center">{{ $t('package.no_services') }}</td>
                </tr>
                <tr v-for="(service, index) in selectedServices" :key="index">
                  <td class="w-50">
                    <div><Multiselect v-model="service.service_id" :value="service.service_id" v-bind="singleSelectOption" @select="selectService(index)" :options="availableServiceOptions(service.service_id)" placeholder="Select Service" id="type" autocomplete="off"></Multiselect></div>
                  </td>
                  <td>
                    <div><input class="form-control" type="number" min="0" placeholder="0" :value="service.service_price" disabled /></div>
                  </td>
                  <td>
                    <QtyButton v-model="service.qty" :value="service.qty" @click="changeQty(index)"></QtyButton>
                  </td>
                  <td>
                    <div><input class="form-control" type="number" placeholder="0" :value="serviceLineTotal(service)" disabled /></div>
                  </td>
                  <td>
                    <div>
                      <button class="btn btn-soft-danger btn-sm" @click.prevent="removeService(index)"><i class="fa-solid fa-trash"></i></button>
                    </div>
                  </td>
                </tr>
              </table>

              <button @click="addMore" type="button" class="btn btn-info">Add More</button>
              <div class="text-danger">{{ errors.services || servicesError }}</div>
            </div>
          </div>
        </fieldset>
      </div>
      <div class="form-group m-0 p-3 d-flex justify-content-end border-top gap-3">
        <label for=""
          ><strong>Services total value</strong>
        </label>
        <span>{{ formatCurrencyVue(SUB_TOTAL_SERVICE_AMOUNT) }}</span>
      </div>
      <FormFooter :IS_SUBMITED="IS_SUBMITED"></FormFooter>
    </div>
  </form>
</template>

<script setup>
import { ref, onMounted, computed, watch, watchEffect } from 'vue'
import { EDIT_URL, STORE_URL, UPDATE_URL, BRANCH_LIST, CATEGORY_LIST, SERVICE_LIST, EMPLOYEE_LIST } from '../constant'
import { useField, useForm } from 'vee-validate'
import InputField from '@/vue/components/form-elements/InputField.vue'
import { useSelect } from '@/helpers/hooks/useSelect'

import { useModuleId, useRequest, useOnOffcanvasHide } from '@/helpers/hooks/useCrudOpration'
import * as yup from 'yup'
import { readFile } from '@/helpers/utilities'
import FormHeader from '@/vue/components/form-elements/FormHeader.vue'
import FormFooter from '@/vue/components/form-elements/FormFooter.vue'
import FormElement from '@/helpers/custom-field/FormElement.vue'
import FlatPickr from 'vue-flatpickr-component'
import QtyButton from '@/vue/components/form-elements/QtyButton.vue'

const selectedServices = ref([])

const formatCurrencyVue = (value) => {
  if (window.currencyFormat !== undefined) {
    return window.currencyFormat(value)
  }
  return value
}

const normalizeId = (value) => {
  if (value === null || value === undefined || value === '') {
    return null
  }

  const numericValue = Number(value)

  return Number.isNaN(numericValue) ? value : numericValue
}

const normalizedIdsSignature = (ids = []) =>
  ids
    .map((id) => normalizeId(id))
    .filter((id) => id !== null)
    .sort((left, right) => String(left).localeCompare(String(right)))
    .join(',')

const defaultService = () => {
  return { service_name: '', service_id: '', service_price: 0, qty: 1, discounted_price: 0, totalPrice: 0 }
}
const syncSelectedServiceFields = () => {
  service_id.value = selectedServices.value.map((service) => service.service_id).filter((id) => id !== '' && id !== null && id !== undefined)
  service_name.value = selectedServices.value.map((service) => service.service_name).filter((name) => !!name)
}

const selectedServiceIds = computed(() => selectedServices.value.map((service) => normalizeId(service.service_id)).filter((id) => id !== null))

const availableServiceOptions = (currentServiceId = null) => {
  const normalizedCurrentId = normalizeId(currentServiceId)
  const blockedIds = selectedServiceIds.value.filter((id) => id !== normalizedCurrentId)

  return serviceList.value.options.filter((option) => {
    const normalizedOptionId = normalizeId(option.value)

    return normalizedOptionId === normalizedCurrentId || !blockedIds.includes(normalizedOptionId)
  })
}

const removeService = (index) => {
  selectedServices.value.splice(index, 1)
  syncSelectedServiceFields()
}

const serviceLineTotal = (service) => Number(service.service_price || 0) * Number(service.qty || 0)

const selectService = (index) => {
  const serviceId = normalizeId(selectedServices.value[index].service_id)
  selectedServices.value[index].service_id = serviceId
  const selectedSingleService = serviceList.value.list.find((s) => normalizeId(s.service_id) === serviceId)
  if (selectedSingleService !== undefined) {
    selectedServices.value[index].service_price = selectedSingleService.service_price
    selectedServices.value[index].service_name = selectedSingleService.service_name
    selectedServices.value[index].qty = 1
    selectedServices.value[index].totalPrice = serviceLineTotal(selectedServices.value[index])
    selectedServices.value[index].discounted_price = selectedServices.value[index].service_price
  }
  syncSelectedServiceFields()
}

function changeQty(index) {
  if (!selectedServices.value[index]) {
    return
  }

  selectedServices.value[index].qty = Math.max(1, Number(selectedServices.value[index].qty || 1))
  selectedServices.value[index].totalPrice = serviceLineTotal(selectedServices.value[index])
  selectedServices.value[index].discounted_price = selectedServices.value[index].service_price
}

const addMore = () => {
  selectedServices.value.push(defaultService())
}
// props
const props = defineProps({
  createTitle: { type: String, default: '' },
  editTitle: { type: String, default: '' },
  customefield: { type: Array, default: () => [] },
  defaultImage: { type: String, default: 'https://dummyimage.com/600x300/cfcfcf/000000.png' }
})

// Select Options
const singleSelectOption = ref({
  closeOnSelect: true,
  searchable: true
})
const multiSelectOption = ref({
  mode: 'tags',
  closeOnSelect: true,
  searchable: true
})

const { getRequest, storeRequest, updateRequest, listingRequest } = useRequest()

// flatpicker
const config = ref({
  dateFormat: 'Y-m-d',
  static: true,
  minDate: new Date()
})

// Edit Form Or Create Form
const currentId = useModuleId(() => {
  useSelect({ url: BRANCH_LIST }, { value: 'id', label: 'name' }).then((data) => {
    branch.value = data
    if (props.selectedSessionBranchId !== null) {
      // branch_id.value = props.selectedSessionBranchId
    } else if (data.options.length === 1) {
      // branch_id.value = data.options[0].value
      branchSelect()
    }
  })

  if (currentId.value > 0) {
    getRequest({ url: EDIT_URL, id: currentId.value }).then((res) => {
      if (res.status) {
        setFormData(res.data)
        branchSelect({ syncCategoriesFromServices: true })
      }
    })
  } else {
    setFormData(defaultData())
  }
})
const branch = ref({ options: [], list: [] })
const category = ref({ options: [], list: [] })
const employee = ref({ options: [], list: [] })
const serviceList = ref({ options: [], list: [] })
useOnOffcanvasHide('form-offcanvas', () => setFormData(defaultData()))

// Image Upload
const ImageViewer = ref(null)
const packageInputRef = ref(null)
const validationMessage = ref('')

const fileUpload = async (e) => {
  let file = e.target.files[0]
  const maxSizeInMB = 2
  const maxSizeInBytes = maxSizeInMB * 1024 * 1024

  if (file) {
    if (file.size > maxSizeInBytes) {
      validationMessage.value = `File size exceeds ${maxSizeInMB} MB. Please upload a smaller file.`
      packageInputRef.value.value = '' // Clear the input
      return
    }

    await readFile(file, (fileB64) => {
      ImageViewer.value = fileB64 // Preview image
      packageInputRef.value.value = '' // Reset file input
      validationMessage.value = '' // Clear validation message
    })
    package_image.value = file // Assign file for upload
  } else {
    validationMessage.value = ''
  }
}

// Function to delete Images
const removeImage = ({ imageViewerBS64, changeFile }) => {
  imageViewerBS64.value = null
  changeFile.value = null
}

const removePackageImage = () => removeImage({ imageViewerBS64: ImageViewer, changeFile: package_image })

/*
 * Form Data & Validation & Handeling
 */
// Default FORM DATA
const defaultData = () => {
  errorMessages.value = {}
  return {
    name: {
      ar: '',
      en: ''
    },
    // end_date: new Date(new Date().setDate(new Date().getDate() + 1)).toJSON().slice(0, 10),
    // start_date: new Date().toJSON().slice(0, 10),
    end_date: null,
    start_date: null,
    branch_id: '',
    package_price: 0,
    status: 1,
    is_featured: 0,
    package_validity: 1,
    // employee_id: null,
    service_id: [],
    service_name: [],
    service: [],
    qty: 1,
    description: null,
    package_image: null
  }
}

//  Reset Form
const setFormData = (data) => {
  ImageViewer.value = data.package_image
  category_ids.value = []
  selectedServices.value = Array.isArray(data.service) ? data.service.map((service) => ({ ...service })) : []
  let parsedName = { ar: '', en: '' }

  try {
    if (typeof data.name === 'string') {
      parsedName = JSON.parse(data.name)
    } else if (typeof data.name === 'object' && data.name !== null) {
      parsedName = {
        ar: data.name.ar ?? '',
        en: data.name.en ?? ''
      }
    }
  } catch (e) {
    console.warn('Invalid JSON name field:', data.name)
  }
  isUnlimited.value = !data.start_date && !data.end_date
  resetForm({
    values: {
      name: parsedName,
      description: data.description,
      start_date: data.start_date,
      end_date: data.end_date,
      status: data.status ? true : false,
      package_price: data.package_price ?? 0,
      is_featured: data.is_featured || 0,
      service_id: data.service_id || [],
      service_name: data.service_name || [],
      qty: data.qty || 1,
      branch_id: data.branch_id,
      package_validity: data.package_validity,
      package_image: data.package_image
    }
  })
  selectedServices.value.forEach((service, index) => {
    service.totalPrice = serviceLineTotal(service)
  })
  syncSelectedServiceFields()
}
// Reload Datatable, SnackBar Message, Alert, Offcanvas Close
const reset_datatable_close_offcanvas = (res) => {
  IS_SUBMITED.value = false
  // image required bug solved
  if (!ImageViewer.value || ImageViewer.value === props.defaultImage) {
    validationMessage.value = 'Package image is required.'
    return // Stop form submission
  }
  if (res.status) {
    window.successSnackbar(res.message)
    renderedDataTable.ajax.reload(null, false)
    bootstrap.Offcanvas.getInstance('#form-offcanvas').hide()
    setFormData(defaultData())
    removePackageImage()
  } else {
    window.errorSnackbar(res.message)
    errorMessages.value = res.all_message
  }
}

// Validations
const validationSchema = yup.object({
  name: yup.object({
    ar: yup.string().required('Arabic name is required'),
    en: yup.string().required('English name is required')
  }),
  branch_id: yup.string().required('Branch is a required field'),
  package_price: yup.number().typeError('Package price must be a number').min(0, 'Package price must be greater than or equal to 0').required('Package price is required'),
  start_date: yup
    .string()
    .nullable()
    .transform((value) => (value === '' ? null : value))
    .test('required-when-not-unlimited', 'Start Date is required when package is time-limited', function (value) {
      if (isUnlimited.value) return true
      return !!value
    }),
  end_date: yup
    .string()
    .nullable()
    .transform((value) => (value === '' ? null : value))
    .test('required-when-not-unlimited', 'End Date is required when package is time-limited', function (value) {
      if (isUnlimited.value) return true
      return !!value
    })
    .test('is-greater', 'End Date must be greater than Start Date', function (value) {
      if (isUnlimited.value || !value) return true
      const { start_date } = this.parent
      if (!start_date) return true
      return new Date(value) > new Date(start_date)
    }),
  services: yup
    .array()
    .of(
      yup.object().shape({
        service_id: yup.number().required('Service selection is required'),
        qty: yup.number().required('Quantity is required'),
        discounted_price: yup
          .number()
          .required('Service price is required')
          .test('is-valid-discounted-price', 'Discounted price must be less than or equal to service price', function (value) {
            return value <= this.parent.service_price
          }),
        totalPrice: yup.number().required('Service total is required').typeError('Total price must be a number').min(1, 'Total price must be greater than or equal to 1')
      })
    )
    .test('unique-service-id', 'Please do not select the same service more than once', (services) => {
      const ids = services.map((service) => service.service_id)
      return new Set(ids).size === ids.length
    })
    .min(1, 'Please select at least one service')
    .required('Services are required')
})

const { handleSubmit, errors, resetForm } = useForm({
  validationSchema,
  initialValues: {
    name: {
      ar: '',
      en: ''
    },
    package_price: 0
    // services: selectedServices.value // Initial value for services from selectedServices
  }
})
// const { value: name } = useField('name')
const { value: nameAr, errorMessage: nameArError } = useField('name.ar')
const { value: nameEn, errorMessage: nameEnError } = useField('name.en')
const { value: branch_id } = useField('branch_id')
const { value: package_price } = useField('package_price')
const { value: status } = useField('status')
const { value: is_featured } = useField('is_featured')

const { value: start_date } = useField('start_date')
const { value: end_date } = useField('end_date')
const isUnlimited = ref(false)
const { value: service_id } = useField('service_id')
const { value: service_name } = useField('service_name')
const category_ids = ref([])
const { value: description } = useField('description')
const { value: package_validity } = useField('package_validity')
const { value: package_image } = useField('package_image')

service_id.value = []
service_name.value = []
const errorMessages = ref({})

const servicesComputed = computed(() => {
  return selectedServices.value.map((service) => ({
    service_id: service.service_id,
    qty: service.qty,
    discounted_price: service.discounted_price,
    totalPrice: serviceLineTotal(service),
    service_price: service.service_price,
    service_name: service.service_name
  }))
})
const isFirstUpdate = ref(true)
onMounted(() => {
  isFirstUpdate.value = true
  setFormData(defaultData())
})

const syncCategoriesFromSelectedServices = () => {
  if (selectedServices.value.length === 0 || serviceList.value.list.length === 0) {
    return
  }

  const inferredCategoryIds = [
    ...new Set(
      selectedServices.value
        .map((service) => serviceList.value.list.find((item) => normalizeId(item.service_id) === normalizeId(service.service_id))?.category_id)
        .map((categoryId) => normalizeId(categoryId))
        .filter((categoryId) => categoryId !== null)
    )
  ]

  if (normalizedIdsSignature(category_ids.value) !== normalizedIdsSignature(inferredCategoryIds)) {
    category_ids.value = inferredCategoryIds
  }
}

const pruneSelectedServicesToAvailable = () => {
  if (serviceList.value.list.length === 0) {
    selectedServices.value = selectedServices.value.filter((service) => !service.service_id)
    syncSelectedServiceFields()
    return
  }

  const availableIds = serviceList.value.list.map((service) => normalizeId(service.service_id))
  selectedServices.value = selectedServices.value.filter((service) => {
    const normalizedServiceId = normalizeId(service.service_id)
    return normalizedServiceId === null || availableIds.includes(normalizedServiceId)
  })
  syncSelectedServiceFields()
}

const branchSelect = ({ syncCategoriesFromServices = false } = {}) => {
  if (!branch_id.value) {
    category.value = { options: [], list: [] }
    serviceList.value = { options: [], list: [] }
    employee.value = { options: [], list: [] }
    return Promise.resolve()
  }

  return Promise.all([
    useSelect({ url: EMPLOYEE_LIST, data: { branch_id: branch_id.value } }, { value: 'id', label: 'name' }),
    useSelect({ url: CATEGORY_LIST, data: { branch_id: branch_id.value } }, { value: 'id', label: 'name' }),
    useSelect({ url: SERVICE_LIST, data: { branch_id: branch_id.value, category_ids: category_ids.value } }, { value: 'service_id', label: 'service_name' })
  ]).then(([employeeData, categoryData, serviceData]) => {
    employee.value = employeeData
    category.value = categoryData
    serviceList.value = serviceData

    if (syncCategoriesFromServices) {
      syncCategoriesFromSelectedServices()
      return
    }

    pruneSelectedServicesToAvailable()
  })
}

const onBranchChange = () => {
  category_ids.value = []
  selectedServices.value = []
  syncSelectedServiceFields()
  branchSelect()
}

// Form Submit
const IS_SUBMITED = ref(false)
const SUB_TOTAL_SERVICE_AMOUNT = computed(() => selectedServices.value.reduce((total, service) => total + serviceLineTotal(service), 0))

const formSubmit = handleSubmit((values) => {
  if (IS_SUBMITED.value) return false
  if (!ImageViewer.value || ImageViewer.value === props.defaultImage) {
    validationMessage.value = 'Package image is required.'
    return // Stop form submission
  }

  values.start_date = isUnlimited.value ? null : values.start_date
  values.end_date = isUnlimited.value ? null : values.end_date

  IS_SUBMITED.value = true
  values.name = JSON.stringify(values.name)
  values.services = JSON.stringify(services.value)
  // values.services = JSON.stringify(selectedServices.value)
  if (currentId.value > 0) {
    updateRequest({ url: UPDATE_URL, id: currentId.value, body: values, type: 'file' }).then((res) => reset_datatable_close_offcanvas(res))
  } else {
    storeRequest({ url: STORE_URL, body: values, type: 'file' }).then((res) => reset_datatable_close_offcanvas(res))
  }
})

const servicesError = computed(() => {
  if (errors.services) {
    return errors.services[0] // Display the first error message for services
  } else {
    return ''
  }
})

// useField for services
const { value: services } = useField('services')

// Watch for changes in servicesComputed and update the form field value
watchEffect(() => {
  if (isFirstUpdate.value) {
    if (servicesComputed.value && servicesComputed.value.length > 0) {
      services.value = servicesComputed.value
      isFirstUpdate.value = false
    }
  } else {
    services.value = servicesComputed.value
  }
})

watch(
  category_ids,
  (value, oldValue) => {
    if (!branch_id.value) {
      return
    }

    if (normalizedIdsSignature(value) === normalizedIdsSignature(oldValue)) {
      return
    }

    branchSelect()
  },
  { deep: true }
)

watch(branch_id, (value) => {
  if (value) {
    return
  }

  category_ids.value = []
  selectedServices.value = []
  syncSelectedServiceFields()
  branchSelect()
})

watch(isUnlimited, (value) => {
  if (value) {
    start_date.value = null
    end_date.value = null
  }
})
</script>

<style scoped>
.table {
  overflow: visible;
}
.product-image-thumbnail {
  width: 100%;
  object-fit: cover;
  height: 200px;
  max-height: 200px;
  border-radius: 1rem;
  padding: 0.75rem;
  border: 1px solid var(--bs-border-color);
}
@media only screen and (min-width: 768px) {
  .offcanvas {
    width: 80%;
  }
}

@media only screen and (min-width: 1280px) {
  .offcanvas {
    width: 60%;
  }
}
.editor-container {
  height: 200px;
}
</style>
