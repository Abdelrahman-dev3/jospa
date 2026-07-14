<template>
  <form @submit="formSubmit">
    <div class="offcanvas offcanvas-end" tabindex="-1" id="form-offcanvas" aria-labelledby="form-offcanvasLabel">
      <FormHeader :currentId="currentId" :editTitle="editTitle" :createTitle="createTitle"></FormHeader>

      <div class="offcanvas-body">
        <div v-if="isFormLoading" class="system-user-form-loader">
          <span class="spinner-border text-primary" role="status" aria-hidden="true"></span>
          <span>Loading user data...</span>
        </div>

        <fieldset :disabled="isFormLoading" class="system-user-form-fieldset">
          <div class="row" :class="{ 'system-user-form-loading': isFormLoading }">
            <InputField class="col-md-6" :is-required="true" :label="$t('employee.lbl_first_name')" :placeholder="$t('employee.first_name')"
              v-model="first_name" :error-message="errors['first_name']" :error-messages="errorMessages['first_name']"></InputField>

            <InputField class="col-md-6" :is-required="true" :label="$t('employee.lbl_last_name')" :placeholder="$t('employee.last_name')"
              v-model="last_name" :error-message="errors['last_name']" :error-messages="errorMessages['last_name']"></InputField>

            <InputField class="col-md-6" :is-required="true" :label="$t('employee.lbl_Email')" :placeholder="$t('employee.email_address')"
              v-model="email" :error-message="errors['email']" :error-messages="errorMessages['email']"></InputField>

            <div class="form-group col-md-6">
              <label class="form-label">{{ $t('employee.lbl_phone_number') }}<span class="text-danger">*</span></label>
              <vue-tel-input :value="safeMobile" @input="handleInput" v-bind="{ mode: 'international', maxLen: 20 }"></vue-tel-input>
              <span class="text-danger">{{ errors['mobile'] }}</span>
              <span v-if="errorMessages['mobile']" class="text-danger d-block mt-1">{{ errorMessages['mobile'][0] }}</span>
            </div>

            <InputField type="password" class="col-md-6" :is-required="currentId === 0" :label="$t('employee.lbl_password')"
              :placeholder="$t('employee.password')" v-model="password" :error-message="errors['password']" :error-messages="errorMessages['password']"></InputField>

            <InputField type="password" class="col-md-6" :is-required="currentId === 0" :label="$t('employee.lbl_confirm_password')"
              :placeholder="$t('employee.confirm_password')" v-model="confirm_password" :error-message="errors['confirm_password']" :error-messages="errorMessages['confirm_password']"></InputField>

            <div class="form-group col-md-12">
              <label class="form-label" for="roles">{{ $t('users.roles') }}</label>
              <Multiselect
                id="roles"
                v-model="roles"
                :value="roles"
                placeholder="Select roles"
                v-bind="accessSelectOption"
                :options="roleOptions"
                class="form-group"
              />
              <span class="text-danger">{{ errors.roles }}</span>
              <span v-if="errorMessages['roles']" class="text-danger d-block mt-1">{{ errorMessages['roles'][0] }}</span>
            </div>

            <div class="form-group col-md-12">
              <label class="form-label" for="permissions">{{ $t('users.permissions') }}</label>
              <Multiselect
                id="permissions"
                v-model="permissions"
                :value="permissions"
                placeholder="Select permissions"
                v-bind="accessSelectOption"
                :options="permissionOptions"
                class="form-group"
              />
              <span class="text-danger">{{ errors.permissions }}</span>
              <span v-if="errorMessages['permissions']" class="text-danger d-block mt-1">{{ errorMessages['permissions'][0] }}</span>
            </div>

            <div class="form-group col-md-12">
              <div class="d-flex justify-content-between align-items-center">
                <label class="form-label mb-0" for="system-user-status">{{ $t('employee.lbl_status') }}</label>
                <div class="form-check form-switch">
                  <input id="system-user-status" class="form-check-input" type="checkbox" v-model="status" />
                </div>
              </div>
            </div>
          </div>
        </fieldset>
      </div>

      <FormFooter :IS_SUBMITED="IS_SUBMITED || isFormLoading"></FormFooter>
    </div>
  </form>
</template>

<script setup>
import { computed, nextTick, onMounted, ref } from 'vue'
import { useField, useForm } from 'vee-validate'
import * as yup from 'yup'
import { VueTelInput } from 'vue3-tel-input'
import { EDIT_URL, STORE_URL, UPDATE_URL } from '../constant/system-user'
import { useModuleId, useOnOffcanvasHide, useRequest } from '@/helpers/hooks/useCrudOpration'
import FormHeader from '@/vue/components/form-elements/FormHeader.vue'
import FormFooter from '@/vue/components/form-elements/FormFooter.vue'
import InputField from '@/vue/components/form-elements/InputField.vue'

const props = defineProps({
  createTitle: { type: String, default: '' },
  editTitle: { type: String, default: '' },
  availableRoles: { type: Array, default: () => [] },
  availablePermissions: { type: Array, default: () => [] },
})

const accessSelectOption = ref({
  mode: 'tags',
  closeOnSelect: false,
  searchable: true,
  hideSelected: false,
})

const { getRequest, storeRequest, updateRequest } = useRequest()
const isFormLoading = ref(false)
const errorMessages = ref({})
const IS_SUBMITED = ref(false)

const normalizeAccessNames = (value) => {
  if (Array.isArray(value)) {
    return [...new Set(value.map((item) => String(item).trim().toLowerCase()).filter(Boolean))]
  }

  if (value === null || value === undefined || value === '') {
    return []
  }

  return [...new Set(String(value).split(',').map((item) => item.trim().toLowerCase()).filter(Boolean))]
}

const formatAccessLabel = (value) => {
  return String(value || '')
    .replace(/_/g, ' ')
    .replace(/\b\w/g, (char) => char.toUpperCase())
}

const roleOptions = computed(() => props.availableRoles.map((role) => ({
  value: role.name,
  label: formatAccessLabel(role.name),
})))

const permissionOptions = computed(() => props.availablePermissions.map((permission) => ({
  value: permission.name,
  label: formatAccessLabel(permission.name),
})))

const defaultData = () => ({
  id: 0,
  first_name: '',
  last_name: '',
  email: '',
  mobile: '',
  password: '',
  confirm_password: '',
  roles: [],
  permissions: [],
  status: true,
})

const validationSchema = yup.object({
  first_name: yup.string().required('First name is a required field'),
  last_name: yup.string().required('Last name is a required field'),
  email: yup.string().required('Email is a required field').email('Must be a valid email'),
  mobile: yup.string().required('Phone Number is a required field'),
  password: yup.string().test('password-required', 'Password is required', function (value) {
    if (currentId.value === 0 && !value) {
      return false
    }

    if (!value) {
      return true
    }

    return value.length >= 8
  }).test('password-length', 'Password must be at least 8 characters long', (value) => !value || value.length >= 8),
  confirm_password: yup.string().test('confirm-password-required', 'Confirm password is required', function (value) {
    if (currentId.value === 0 && !value) {
      return false
    }

    if (password.value && !value) {
      return false
    }

    return true
  }).oneOf([yup.ref('password'), null], 'Passwords must match'),
})

const { handleSubmit, errors, resetForm } = useForm({
  validationSchema,
})

const { value: id } = useField('id')
const { value: first_name } = useField('first_name')
const { value: last_name } = useField('last_name')
const { value: email } = useField('email')
const { value: mobile } = useField('mobile')
const { value: password } = useField('password')
const { value: confirm_password } = useField('confirm_password')
const { value: roles } = useField('roles')
const { value: permissions } = useField('permissions')
const { value: status } = useField('status')

const normalizePhoneValue = (value) => {
  if (typeof value === 'string') {
    return value
  }

  if (value === null || value === undefined) {
    return ''
  }

  return String(value)
}

const safeMobile = computed(() => normalizePhoneValue(mobile.value))

const setFormData = (data) => {
  errorMessages.value = {}

  resetForm({
    values: {
      id: data.id ?? 0,
      first_name: data.first_name ?? '',
      last_name: data.last_name ?? '',
      email: data.email ?? '',
      mobile: normalizePhoneValue(data.mobile ?? ''),
      password: '',
      confirm_password: '',
      roles: normalizeAccessNames(data.roles ?? []),
      permissions: normalizeAccessNames(data.permissions ?? []),
      status: data.status === undefined ? true : Boolean(data.status),
    },
  })
}

const currentId = useModuleId(async () => {
  await prepareForm(currentId.value)
})

const prepareForm = async (formId) => {
  isFormLoading.value = true

  try {
    if (formId > 0) {
      const res = await getRequest({ url: EDIT_URL, id: formId })
      if (res.status && res.data) {
        setFormData(res.data)
      }
    } else {
      setFormData(defaultData())
    }
  } finally {
    await nextTick()
    isFormLoading.value = false
  }
}

const mapServerErrors = (res) => {
  if (res?.errors && typeof res.errors === 'object') {
    errorMessages.value = res.errors
    return true
  }

  if (res?.all_message && typeof res.all_message === 'object') {
    errorMessages.value = res.all_message
    return true
  }

  errorMessages.value = {}
  return false
}

const resetDatatableCloseOffcanvas = (res) => {
  IS_SUBMITED.value = false

  if (res?.status) {
    errorMessages.value = {}
    window.successSnackbar(res.message)
    window.renderedDataTable.ajax.reload(null, false)
    const formOffcanvasElement = document.getElementById('form-offcanvas')
    bootstrap.Offcanvas.getInstance(formOffcanvasElement)?.hide()
    setFormData(defaultData())
    return
  }

  if (!mapServerErrors(res)) {
    window.errorSnackbar(res?.message || 'Unable to save user data.')
  }
}

const handleInput = (phone, phoneObject) => {
  if (phone instanceof Event) {
    return
  }

  mobile.value = normalizePhoneValue(phoneObject?.formatted ?? phone)
}

const formSubmit = handleSubmit(async (values) => {
  if (IS_SUBMITED.value) return false

  IS_SUBMITED.value = true
  errorMessages.value = {}

  const body = {
    ...values,
    status: values.status ? 1 : 0,
    roles: normalizeAccessNames(values.roles),
    permissions: normalizeAccessNames(values.permissions),
  }

  if (!body.password) {
    delete body.password
  }

  if (!body.confirm_password) {
    delete body.confirm_password
  }

  const res = currentId.value > 0
    ? await updateRequest({ url: UPDATE_URL, id: currentId.value, body })
    : await storeRequest({ url: STORE_URL, body })

  resetDatatableCloseOffcanvas(res)
})

onMounted(() => {
  setFormData(defaultData())
})

useOnOffcanvasHide('form-offcanvas', () => {
  isFormLoading.value = false
  IS_SUBMITED.value = false
  setFormData(defaultData())
})
</script>

<style scoped>
@media only screen and (min-width: 768px) {
  .offcanvas {
    width: 70%;
  }
}

@media only screen and (min-width: 1280px) {
  .offcanvas {
    width: 50%;
  }
}

.system-user-form-fieldset {
  border: 0;
  margin: 0;
  min-inline-size: 0;
  padding: 0;
}

.system-user-form-loader {
  align-items: center;
  background: rgba(255, 255, 255, 0.92);
  border-bottom: 1px solid #e9ecef;
  color: #495057;
  display: flex;
  gap: 0.75rem;
  inset-inline: 0;
  justify-content: center;
  padding: 1rem;
  position: sticky;
  top: 0;
  z-index: 5;
}

.system-user-form-loading {
  opacity: 0.55;
  pointer-events: none;
}
</style>
