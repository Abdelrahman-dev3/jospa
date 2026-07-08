<template>
  <form @submit="formSubmit">
    <div class="offcanvas offcanvas-end" tabindex="-1" id="form-offcanvas" aria-labelledby="form-offcanvasLabel">
      <FormHeader :currentId="currentId" :editTitle="editTitle" :createTitle="createTitle"></FormHeader>
      <div class="offcanvas-body">
        <div class="row">
          <div class="col-md-12 row">
            <div class="col-md-12 text-center">
              <img :src="ImageViewer || defaultImage" class="img-fluid avatar avatar-120 avatar-rounded mb-2" alt="profile-image" />
              <div v-if="validationMessage" class="text-danger mb-2">{{ validationMessage }}</div>
              <div class="d-flex align-items-center justify-content-center gap-2">
                <input type="file" ref="profileInputRef" class="form-control d-none" id="logo" name="profile_image" accept=".jpeg, .jpg, .png, .gif" @change="fileUpload" />
                <label class="btn btn-info mb-3" for="logo">{{ $t('messages.upload') }}</label>
                <input type="button" class="btn btn-danger mb-3" name="remove" :value="$t('messages.remove')" @click="removeLogo()" v-if="ImageViewer" />
              </div>
              <span class="text-danger">{{ errors.profile_image }}</span>
            </div>

            <InputField :is-required="true" :label="$t('customer.lbl_first_name')" :placeholder="$t('customer.first_name')" v-model="first_name" :error-message="errors.first_name" :error-messages="errorMessages['first_name']"></InputField>
            <InputField :is-required="false" :label="$t('customer.lbl_last_name')" :placeholder="$t('customer.last_name')" v-model="last_name" :error-message="errors['last_name']" :error-messages="errorMessages['last_name']"></InputField>

            <InputField :is-required="false" :label="$t('customer.lbl_Email')" :placeholder="$t('customer.email_address')" v-model="email" :error-message="errors['email']" :error-messages="errorMessages['email']"></InputField>
            <div class="form-group">
              <label class="form-label">{{ $t('customer.lbl_phone_number') }}<span class="text-danger">*</span> </label>
              <vue-tel-input :value="safeMobile" @input="handleInput" v-bind="{ mode: 'international', maxLen: 15 }"></vue-tel-input>
              <span class="text-danger">{{ errors['mobile'] }}</span>
            </div>

            <div v-for="field in customefield" :key="field.id">
              <FormElement v-model="custom_fields_data" :name="field.name" :label="field.label" :type="field.type" :required="field.required" :options="field.value" :field_id="field.id"></FormElement>
            </div>
          </div>
        </div>
      </div>
    <FormFooter :IS_SUBMITED="IS_SUBMITED"></FormFooter>
    </div>
  </form>
</template>
<script setup>
import { ref, onMounted, computed } from 'vue'
import { EDIT_URL, STORE_URL, UPDATE_URL,EMAIL_UNIQUE_CHECK } from '../constant/customer'
import { useField, useForm } from 'vee-validate'

import { VueTelInput } from 'vue3-tel-input'

import { useModuleId, useRequest, useOnOffcanvasHide } from '@/helpers/hooks/useCrudOpration'
import * as yup from 'yup'

import { readFile } from '@/helpers/utilities'
import FormHeader from '@/vue/components/form-elements/FormHeader.vue'
import FormFooter from '@/vue/components/form-elements/FormFooter.vue'
import InputField from '@/vue/components/form-elements/InputField.vue'
import FormElement from '@/helpers/custom-field/FormElement.vue'
const ISREADONLY = ref(false)
// props
defineProps({
  createTitle: { type: String, default: '' },
  editTitle: { type: String, default: '' },
  defaultImage: { type: String, default: 'https://dummyimage.com/600x300/cfcfcf/000000.png' },
  customefield: { type: Array, default: () => [] }
})

const { getRequest, storeRequest, updateRequest, listingRequest } = useRequest()

/*
 * Form Data & Validation & Handeling
 */
const currentId = useModuleId(() => {
  if (currentId.value > 0) {
    getRequest({ url: EDIT_URL, id: currentId.value }).then((res) => res.status && setFormData(res.data))
  } else {
    setFormData(defaultData())
  }
})

// File Upload Function
const validationMessage = ref('')
const ImageViewer = ref(null)
const profileInputRef = ref(null)
const fileUpload = async (e) => {
  let file = e.target.files[0];
  const maxSizeInMB = 2;
  const maxSizeInBytes = maxSizeInMB * 1024 * 1024;

  if (file) {
    if (file.size > maxSizeInBytes) {
      validationMessage.value = `File size exceeds ${maxSizeInMB} MB. Please upload a smaller file.`;
      profileInputRef.value.value = '';
      return;
    }

    await readFile(file, (fileB64) => {
      ImageViewer.value = fileB64;
      profileInputRef.value.value = '';
      validationMessage.value = ''; 
    });
    feature_image.value = file;
  } else {
    validationMessage.value = '';
  }
};
// Function to delete Images
const removeImage = ({ imageViewerBS64, changeFile }) => {
  imageViewerBS64.value = null
  changeFile.value = null
}

const removeLogo = () => removeImage({ imageViewerBS64: ImageViewer, changeFile: profile_image })

/*
 * Form Data & Validation & Handeling
 */
// Default FORM DATA
const defaultData = () => {
  ISREADONLY.value = false
  errorMessages.value = {}
  return {
    id: null,
    first_name: '',
    last_name: '',
    email: '',
    mobile: '',
    gender: 'female',
    profile_image: '',
    custom_fields_data: {}
  }
}

const normalizePhoneValue = (value) => {
  if (typeof value === 'string') {
    return value
  }

  if (value === null || value === undefined) {
    return ''
  }

  return String(value)
}

//  Reset Form
const setFormData = (data) => {
  ImageViewer.value = data.profile_image
  ISREADONLY.value = true
  resetForm({
    values: {
      id: data.id,
      first_name: data.first_name,
      last_name: data.last_name,
      email: data.email,
      mobile: normalizePhoneValue(data.mobile),
      gender: data.gender || 'female',
      profile_image: data.profile_image,
      custom_fields_data: data.custom_field_data
    }
  })
}

const reset_datatable_close_offcanvas = (res) => {
   IS_SUBMITED.value = false
  if (res.status) {
    window.successSnackbar(res.message)
    renderedDataTable.ajax.reload(null, false)
    bootstrap.Offcanvas.getInstance('#form-offcanvas').hide()
    setFormData(defaultData())
  } else {
    window.errorSnackbar(res.message)
    errorMessages.value = res.all_message
  }
}

  const numberRegex = /^\d+$/;
  let EMAIL_REGX = /^[a-zA-Z0-9_.+-]+@[a-zA-Z0-9-]+\.[a-zA-Z0-9-.]+$/;

  // Validations
  const validationSchema = yup.object({
    first_name: yup.string()
    .required('First Name is a required field')
    .test('is-string', 'Only strings are allowed', (value) => {
      // Regular expressions to disallow special characters and numbers
      const specialCharsRegex = /[!@#$%^&*(),.?":{}|<>\-_;'\/+=\[\]\\]/
      return !specialCharsRegex.test(value) && !numberRegex.test(value)
    }),

    last_name: yup.string()
    .nullable()
    .test('is-string', 'اسم العائلة يجب أن يحتوي على حروف فقط', (value) => {
      if (!value) {
        return true
      }
      // Regular expressions to disallow special characters and numbers
      const specialCharsRegex = /[!@#$%^&*(),.?":{}|<>\-_;'\/+=\[\]\\]/
      return !specialCharsRegex.test(value) && !numberRegex.test(value)
    }),
    email: yup.string()
    .nullable()
    .test('valid-email', 'يرجى إدخال بريد إلكتروني صحيح', (value) => {
      if (!value) {
        return true
      }
      return EMAIL_REGX.test(value)
    })
    .test('unique', 'Email must be unique', async function(value) {
      if (!value || !EMAIL_REGX.test(value)) {
        return true
      }
      const userId  = id.value;
          const isUnique = await storeRequest({ url: EMAIL_UNIQUE_CHECK, body: { email: value, user_id: userId }, type: 'file' });
          if (!isUnique.isUnique) {
              return this.createError({ path: 'email', message: 'هذا البريد الإلكتروني مستخدم بالفعل' });
              }
          return true;
        }),
        mobile: yup.string()
        .required('يرجى إدخال رقم الجوال')
        .matches(/^(\+?\d+)?(\s?\d+)*$/, 'رقم الجوال يجب أن يحتوي على أرقام فقط'),
  })


const { handleSubmit, errors, resetForm } = useForm({
  validationSchema,
})
const { value: id } = useField('id')
const { value: first_name } = useField('first_name')
const { value: last_name } = useField('last_name')
const { value: email } = useField('email')
const { value: gender } = useField('gender')
const { value: mobile } = useField('mobile')
const { value: profile_image } = useField('profile_image')
const { value: custom_fields_data } = useField('custom_fields_data')
const errorMessages = ref({})
const safeMobile = computed(() => normalizePhoneValue(mobile.value))

// phone number
const handleInput = (phone, phoneObject) => {
  if (phone instanceof Event) {
    return
  }
  mobile.value = normalizePhoneValue(phoneObject?.formatted ?? phone)
}

// Form Submit
const IS_SUBMITED = ref(false)
const formSubmit = handleSubmit((values) => {
  if(IS_SUBMITED.value) return false
  IS_SUBMITED.value = true
  values.last_name = values.last_name?.trim() || ''
  values.email = values.email?.trim() || null
  values.gender = values.gender || 'female'
  values.custom_fields_data = JSON.stringify(values.custom_fields_data)

  if (currentId.value > 0) {
    updateRequest({ url: UPDATE_URL, id: currentId.value, body: values, type: 'file' }).then((res) => reset_datatable_close_offcanvas(res))
  } else {
    storeRequest({ url: STORE_URL, body: values, type: 'file' }).then((res) => reset_datatable_close_offcanvas(res))
  }
})

useOnOffcanvasHide('form-offcanvas', () => setFormData(defaultData()))
</script>
