<template>
    <!-- Modal -->
    <form @submit.prevent="formSubmit" class="">
        <div class="modal fade" :id="CUSTOMER_MODAL_ID" tabindex="-1" :aria-labelledby="CUSTOMER_MODAL_LABEL_ID" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h1 class="modal-title fs-5" :id="CUSTOMER_MODAL_LABEL_ID">{{ $t('messages.create') }} {{ $t('messages.customer')}}</h1>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row" id="form-offcanvas">
                            <div class="form-group col-md-6">
                                <label for="first_name">{{ $t('customer.lbl_first_name') }} <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" :placeholder="$t('employee.first_name')" v-model="first_name" />
                                <small v-if="errors.first_name" class="text-danger">{{ errors.first_name }}</small>
                                <small v-else-if="errorMessages.first_name?.[0]" class="text-danger">{{ errorMessages.first_name[0] }}</small>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="last_name">{{ $t('customer.lbl_last_name') }}</label>
                                <input type="text" class="form-control" :placeholder="$t('employee.last_name')" v-model="last_name" />
                                <small v-if="errors.last_name" class="text-danger">{{ errors.last_name }}</small>
                                <small v-else-if="errorMessages.last_name?.[0]" class="text-danger">{{ errorMessages.last_name[0] }}</small>
                            </div>
                            <div class="form-group col-md-12">
                                <label for="e-mail">{{ $t('customer.lbl_Email') }}</label>
                                <input type="text" class="form-control" :placeholder="$t('customer.email_address')" v-model="email" />
                                <small v-if="errors.email" class="text-danger">{{ errors.email }}</small>
                                <small v-else-if="errorMessages.email?.[0]" class="text-danger">{{ errorMessages.email[0] }}</small>
                            </div>
                            <div class="form-group col-md-12">
                                <label for="mobile">{{ $t('customer.lbl_phone_number') }} <span class="text-danger">*</span></label>
                                <input type="text" :placeholder="$t('messages.placeholder_phone')" class="form-control" v-model="mobile" />
                                <small v-if="errors.mobile" class="text-danger">{{ errors.mobile }}</small>
                                <small v-else-if="errorMessages.mobile?.[0]" class="text-danger">{{ errorMessages.mobile[0] }}</small>
                            </div>
                            <div class="form-group col-md-12">
                              <small class="text-muted">{{ $t('messages.female') }}</small>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                      <button type="submit" class="btn btn-primary">{{ $t('messages.save_changes') }}</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ $t('messages.close') }}</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</template>
<script setup>
import { ref, watch, onMounted } from 'vue'

import { useRequest } from '@/helpers/hooks/useCrudOpration'

import { useField, useForm } from 'vee-validate'
import * as yup from 'yup'

import { CUSTOMER_STORE } from '@/vue/constants/users'
const emit = defineEmits(['submit'])
const props = defineProps({
  data: {
    type: Object,
    default: () => ({
      first_name: '',
      last_name: ''
    })
  }
})
const CUSTOMER_MODAL_ID = 'create-customer-modal'
const CUSTOMER_MODAL_LABEL_ID = 'create-customer-modal-label'

const { storeRequest } = useRequest()


/*
 * Form Data & Validation & Handeling
 */
// Default FORM DATA
const defaultData = () => {
  errorMessages.value = {}
  return {
    first_name: '',
    last_name: '',
    email: '',
    mobile: '',
    gender: 'female',

  }
}

//  Reset Form
const setFormData = (data) => {
  resetForm({
    values: {
      first_name: data?.first_name ?? '',
      last_name: data?.last_name ?? '',
      email: data?.email ?? '',
      mobile: data?.mobile ?? '',
      gender: data?.gender ?? 'female',
    }
  })
}

// Validations
const validationSchema = yup.object({
    first_name: yup.string().required('يرجى إدخال الاسم الأول'),
    last_name: yup.string().nullable(),
    email: yup.string().nullable().email('يرجى إدخال بريد إلكتروني صحيح'),
    mobile: yup.string().required('يرجى إدخال رقم الجوال'),
})

const { handleSubmit, errors, resetForm } = useForm({
  validationSchema
})

const { value: first_name } = useField('first_name')
const { value: last_name } = useField('last_name')
const { value: email } = useField('email')
const { value: gender } = useField('gender')
const { value: mobile } = useField('mobile')

const errorMessages = ref({})

onMounted(() => {
  setFormData(defaultData())

  const modalElement = document.getElementById(CUSTOMER_MODAL_ID)
  if (modalElement) {
    modalElement.addEventListener('hidden.bs.modal', () => {
      setFormData(defaultData())
    })
  }
})

watch(() => props.data, (value) => {
  setFormData({
    ...defaultData(),
    first_name: value?.first_name ?? '',
    last_name: value?.last_name ?? ''
  })
}, { deep: true })

const formSubmit = handleSubmit((value) => {
  errorMessages.value = {}
  value.last_name = value.last_name?.trim() || ''
  value.email = value.email?.trim() || null
  value.gender = 'female'
  storeRequest({ url: CUSTOMER_STORE, body: value }).then((res) => {
    if(res.status) {
      emit('submit', {type: 'create_customer', value: res.data.id})
      setFormData(defaultData())
      bootstrap.Modal.getOrCreateInstance(document.getElementById(CUSTOMER_MODAL_ID)).hide()
      successSnackbar(res.message)
      return
    }
    errorMessages.value = res.errors || res.all_message || {}
    errorSnackbar(res.message || 'Unable to create customer')
  })
})

</script>
