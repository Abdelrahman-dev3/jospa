<template>
    <!-- Modal -->
    <form @submit="formSubmit" class="">
        <div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h1 class="modal-title fs-5" id="exampleModalLabel">{{ $t('messages.create') }} {{ $t('messages.customer')}}</h1>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row" id="form-offcanvas">
                            <div class="form-group col-md-6">
                                <label for="first_name">{{ $t('customer.lbl_first_name') }} <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" :placeholder="$t('employee.first_name')" v-model="first_name" />
                                <small v-if="errors.first_name" class="text-danger">{{ errors.first_name }}</small>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="last_name">{{ $t('customer.lbl_last_name') }} <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" :placeholder="$t('employee.last_name')" v-model="last_name" />
                                <small v-if="errors.last_name" class="text-danger">{{ errors.last_name }}</small>
                            </div>
                            <div class="form-group col-md-12">
                                <label for="e-mail">{{ $t('customer.lbl_Email') }}</label>
                                <input type="text" class="form-control" :placeholder="$t('customer.email_address')" v-model="email" />
                                <small v-if="errors.email" class="text-danger">{{ errors.email }}</small>
                            </div>
                            <div class="form-group col-md-12">
                                <label for="mobile">{{ $t('customer.lbl_phone_number') }} <span class="text-danger">*</span></label>
                                <input type="text" :placeholder="$t('messages.placeholder_phone')" class="form-control" v-model="mobile" />
                                <small v-if="errors.mobile" class="text-danger">{{ errors.mobile }}</small>
                            </div>
                            <div class="form-group col-md-12">
                              <label for="" class="w-100">{{ $t('customer.lbl_gender') }}</label>
                                <div class="form-check form-check-inline">
                                  <input class="form-check-input" type="radio" name="gender" v-model="gender" id="male" value="male">
                                  <label class="form-check-label" for="male">
                                    {{ $t('messages.male') }}
                                  </label>
                                </div>
                                <div class="form-check form-check-inline">
                                  <input class="form-check-input" type="radio" name="gender" v-model="gender" id="female" value="female">
                                  <label class="form-check-label" for="female">
                                    {{ $t('messages.female') }}
                                  </label>
                                </div>

                                <!-- <div class="form-check form-check-inline">
                                  <input class="form-check-input" type="radio" name="gender" v-model="gender" id="other" value="other">
                                  <label class="form-check-label" for="other">
                                    Intersex
                                  </label>
                                </div> -->
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
    first_name: '',
    last_name: ''
  }
})
watch(() => props.data, (value) => {
  first_name.value = value.first_name,
  last_name.value = value.last_name
} ,{deep: true})
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
    gender: 'male',

  }
}

//  Reset Form
const setFormData = (data) => {
  resetForm({
    values: {
      first_name: data.first_name,
      last_name: data.last_name,
      email: data.email,
      mobile: data.mobile,
      gender: data.gender,
    }
  })
}

// Validations
const validationSchema = yup.object({
    first_name: yup.string().required(),
    last_name: yup.string().required(),
    email: yup.string().nullable().email(),
    mobile: yup.string().required(),
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
})

const formSubmit = handleSubmit((value) => {
  console.log(value)
  storeRequest({ url: CUSTOMER_STORE, body: value }).then((res) => {
    if(res.status) {
      emit('submit', {type: 'create_customer', value: res.data.id})
      setFormData(defaultData())
      bootstrap.Modal.getInstance(document.getElementById("exampleModal")).hide()
    }
  })
})

</script>
