<template>
  <form @submit="formSubmit">
    <div>
      <CardTitle :title="$t('setting_sidebar.lbl_payment')" icon="fa-solid fa-coins"></CardTitle>
    </div>
    <div class="form-group">
      <div class="d-flex justify-content-between align-items-center">
        <label class="form-label" for="payment_method_tap">{{ $t('setting_payment_method.lbl_tap') }} </label>
        <div class="form-check form-switch">
          <input class="form-check-input" :true-value="1" :false-value="0" :value="tap_payment_method"
            :checked="tap_payment_method == 1 ? true : false" name="tap_payment_method" id="payment_method_tap"
            type="checkbox" v-model="tap_payment_method" />
        </div>
      </div>
    </div>
    <div class="form-group ms-3">
      <div class="d-flex justify-content-between align-items-center">
        <label class="form-label" for="payment_method_tap_card">{{ $t('setting_payment_method.lbl_visa_mastercard') }} </label>
        <div class="form-check form-switch">
          <input class="form-check-input" :true-value="1" :false-value="0" :value="tap_card_payment_method"
            :checked="tap_card_payment_method == 1 ? true : false" name="tap_card_payment_method" id="payment_method_tap_card"
            type="checkbox" v-model="tap_card_payment_method" :disabled="tap_payment_method != 1" />
        </div>
      </div>
    </div>
    <div class="form-group ms-3">
      <div class="d-flex justify-content-between align-items-center">
        <label class="form-label" for="payment_method_tap_apple_pay">{{ $t('setting_payment_method.lbl_apple_pay') }} </label>
        <div class="form-check form-switch">
          <input class="form-check-input" :true-value="1" :false-value="0" :value="tap_apple_pay_payment_method"
            :checked="tap_apple_pay_payment_method == 1 ? true : false" name="tap_apple_pay_payment_method" id="payment_method_tap_apple_pay"
            type="checkbox" v-model="tap_apple_pay_payment_method" :disabled="tap_payment_method != 1" />
        </div>
      </div>
    </div>
    <div class="form-group ms-3">
      <div class="d-flex justify-content-between align-items-center">
        <label class="form-label" for="payment_method_tap_mada">{{ $t('setting_payment_method.lbl_mada') }} </label>
        <div class="form-check form-switch">
          <input class="form-check-input" :true-value="1" :false-value="0" :value="tap_mada_payment_method"
            :checked="tap_mada_payment_method == 1 ? true : false" name="tap_mada_payment_method" id="payment_method_tap_mada"
            type="checkbox" v-model="tap_mada_payment_method" :disabled="tap_payment_method != 1" />
        </div>
      </div>
    </div>
    <div class="form-group">
      <div class="d-flex justify-content-between align-items-center">
        <label class="form-label" for="payment_method_tabby">{{ $t('setting_payment_method.lbl_tabby') }} </label>
        <div class="form-check form-switch">
          <input class="form-check-input" :true-value="1" :false-value="0" :value="tabby_payment_method"
            :checked="tabby_payment_method == 1 ? true : false" name="tabby_payment_method" id="payment_method_tabby"
            type="checkbox" v-model="tabby_payment_method" />
        </div>
      </div>
    </div>
    <div class="form-group">
      <div class="d-flex justify-content-between align-items-center">
        <label class="form-label" for="payment_method_tamara">{{ $t('setting_payment_method.lbl_tamara') }} </label>
        <div class="form-check form-switch">
          <input class="form-check-input" :true-value="1" :false-value="0" :value="tamara_payment_method"
            :checked="tamara_payment_method == 1 ? true : false" name="tamara_payment_method" id="payment_method_tamara"
            type="checkbox" v-model="tamara_payment_method" />
        </div>
      </div>
    </div>
    <SubmitButton :IS_SUBMITED="IS_SUBMITED"></SubmitButton>
  </form>
</template>

<script setup>
import { ref, watch } from 'vue'
import CardTitle from '@/Setting/Components/CardTitle.vue'
import { useField, useForm } from 'vee-validate'
import { STORE_URL, GET_URL } from '@/vue/constants/setting'
import InputField from '@/vue/components/form-elements/InputField.vue'
import * as yup from 'yup';
import { useRequest } from '@/helpers/hooks/useCrudOpration'
import { onMounted } from 'vue'
import { createRequest } from '@/helpers/utilities'
import SubmitButton from './Forms/SubmitButton.vue'
const { storeRequest } = useRequest()
const IS_SUBMITED = ref(false)
//  Reset Form
const setFormData = (data) => {
  resetForm({
    values: {
      tap_payment_method: data.tap_payment_method ?? 1,
      tap_card_payment_method: data.tap_card_payment_method ?? 1,
      tap_apple_pay_payment_method: data.tap_apple_pay_payment_method ?? 1,
      tap_mada_payment_method: data.tap_mada_payment_method ?? 1,
      tabby_payment_method: data.tabby_payment_method ?? 1,
      tamara_payment_method: data.tamara_payment_method ?? 1,
      razor_payment_method: data.razor_payment_method ?? 0,
      razorpay_secretkey: data.razorpay_secretkey ?? '',
      razorpay_publickey: data.razorpay_publickey ?? '',
      str_payment_method: data.str_payment_method ?? 0,
      stripe_secretkey: data.stripe_secretkey ?? '',
      stripe_publickey: data.stripe_publickey ?? '',
      paystack_payment_method: data.paystack_payment_method ?? 0,
      paystack_secretkey: data.paystack_secretkey ?? '',
      paystack_publickey: data.paystack_publickey ?? '',
      paypal_payment_method: data.paypal_payment_method ?? 0,
      paypal_secretkey: data.paypal_secretkey ?? '',
      paypal_clientid: data.paypal_clientid ?? '',
      flutterwave_payment_method: data.flutterwave_payment_method ?? 0,
      flutterwave_secretkey: data.flutterwave_secretkey ?? '',
      flutterwave_publickey: data.flutterwave_publickey ?? '',
      cinet_payment_method: data.cinet_payment_method ?? 0,
      cinet_clientid: data.cinet_clientid ?? '',
      cinet_apikey: data.cinet_apikey ?? '',
      cinet_secretkey: data.cinet_secretkey ?? '',
      sadad_payment_method: data.sadad_payment_method ?? 0,
      sadad_clientid: data.sadad_clientid ?? '',
      sadad_secretkey: data.sadad_secretkey ?? '',
      sadad_domain: data.sadad_domain ?? '',
      airtelmoney_payment_method: data.airtelmoney_payment_method ?? 0,
      airtelmoney_is_status: data.airtelmoney_is_status ?? 0,
      airtelmoney_clientid: data.airtelmoney_clientid ?? 0,
      airtelmoney_secretkey: data.airtelmoney_secretkey ?? 0,
      phonepay_payment_method: data.phonepay_payment_method ?? 0,
      phonepay_is_status: data.phonepay_is_status ?? 0,
      phonepay_appid: data.phonepay_appid ?? 0,
      phonepay_merchentid: data.phonepay_merchentid ?? 0,
      phonepay_saltid: data.phonepay_saltid ?? 0,
      phonepay_saltkey: data.phonepay_saltkey ?? 0,
      midtrans_payment_method: data.midtrans_payment_method ?? 0,
      midtrans_is_status: data.midtrans_is_status ?? 0,
      midtrans_clientid: data.midtrans_clientid ?? 0,
    }
  })
}
const validationSchema = yup.object({
  razorpay_secretkey: yup.string().test('razorpay_secretkey', 'Must be a valid RazorPay key', function (value) {
    if (this.parent.razor_payment_method == 1 && !value) {
      return false;
    }
    return true
  }),
  razorpay_publickey: yup.string().test('razorpay_publickey', 'Must be a valid RazorPay Publickey', function (value) {
    if (this.parent.razor_payment_method == 1 && !value) {

      return false;
    }
    return true
  }),
  stripe_secretkey: yup.string().test('stripe_secretkey', 'Must be a valid Stripe key', function (value) {
    if (this.parent.str_payment_method == 1 && !value) {
      return false;
    }
    return true
  }),
  stripe_publickey: yup.string().test('stripe_publickey', 'Must be a valid Stripe Publickey', function (value) {
    if (this.parent.str_payment_method == 1 && !value) {
      return false;
    }
    return true
  }),

  paystack_secretkey: yup.string().test('paystack_secretkey', 'Must be a valid Paystack key', function (value) {
    if (this.parent.paystack_payment_method == 1 && !value) {
      return false;
    }
    return true
  }),
  paystack_publickey: yup.string().test('paystack_publickey', 'Must be a valid Paystack Publickey', function (value) {
    if (this.parent.paystack_payment_method == 1 && !value) {
      return false;
    }
    return true
  }),

  paypal_secretkey: yup.string().test('paypal_secretkey', 'Must be a valid Paypal key', function (value) {
    if (this.parent.paypal_payment_method == 1 && !value) {
      return false;
    }
    return true
  }),
  paypal_clientid: yup.string().test('paypal_clientid', 'Must be a valid Paypal Publickey', function (value) {
    if (this.parent.paypal_payment_method == 1 && !value) {
      return false;
    }
    return true
  }),

  flutterwave_secretkey: yup.string().test('flutterwave_secretkey', 'Must be a valid Flutterwave key', function (value) {
    if (this.parent.flutterwave_payment_method == 1 && !value) {
      return false;
    }
    return true
  }),
  flutterwave_publickey: yup.string().test('flutterwave_publickey', 'Must be a valid Flutterwave Publickey', function (value) {
    if (this.parent.flutterwave_payment_method == 1 && !value) {
      return false;
    }
    return true
  }),

  cinet_clientid: yup.string().test('cinet_clientid', 'Must be a valid Cinet Clientid', function (value) {
    if (this.parent.cinet_payment_method == 1 && !value) {
      return false;
    }
    return true
  }),

  cinet_apikey: yup.string().test('cinet_apikey', 'Must be a valid Cinet Apikey', function (value) {
    if (this.parent.cinet_payment_method == 1 && !value) {
      return false;
    }
    return true
  }),
  cinet_secretkey: yup.string().test('cinet_secretkey', 'Must be a valid Cinet Secretkey', function (value) {
    if (this.parent.cinet_payment_method == 1 && !value) {
      return false;
    }
    return true
  }),

  sadad_clientid: yup.string().test('sadad_clientid', 'Must be a valid Sadad Clientid', function (value) {
    if (this.parent.sadad_payment_method == 1 && !value) {
      return false;
    }
    return true
  }),

  sadad_secretkey: yup.string().test('sadad_secretkey', 'Must be a valid Sadad Secretkey', function (value) {
    if (this.parent.sadad_payment_method == 1 && !value) {
      return false;
    }
    return true
  }),

  sadad_domain: yup.string().test('sadad_domain', 'Must be a valid Sadad Domain', function (value) {
    if (this.parent.sadad_payment_method == 1 && !value) {
      return false;
    }
    return true
  }),

  airtelmoney_clientid: yup.string().test('airtelmoney_clientid', 'Must be a valid airtelmoney Clientid', function (value) {
    if (this.parent.airtelmoney_payment_method == 1 && !value) {
      return false;
    }
    return true
  }),

  airtelmoney_secretkey: yup.string().test('airtelmoney_secretkey', 'Must be a valid airtelmoney key', function (value) {
    if (this.parent.airtelmoney_payment_method == 1 && !value) {
      return false;
    }
    return true
  }),

  phonepay_appid: yup.string().test('phonepay_appid', 'Must be a valid Phonepay Appid', function (value) {
    if (this.parent.phonepay_payment_method == 1 && !value) {
      return false;
    }
    return true
  }),

  phonepay_merchentid: yup.string().test('phonepay_merchentid', 'Must be a valid Phonepay Merchantid', function (value) {
    if (this.parent.phonepay_payment_method == 1 && !value) {
      return false;
    }
    return true
  }),

  phonepay_saltid: yup.string().test('phonepay_saltid', 'Must be a valid Phonepay Saltid', function (value) {
    if (this.parent.phonepay_payment_method == 1 && !value) {
      return false;
    }
    return true
  }),

  phonepay_saltkey: yup.string().test('phonepay_saltkey', 'Must be a valid Phonepay Saltkey', function (value) {
    if (this.parent.phonepay_payment_method == 1 && !value) {
      return false;
    }
    return true
  }),

  midtrans_clientid: yup.string().test('midtrans_clientid', 'Must be a valid Midtrans Clientid', function (value) {
    if (this.parent.midtrans_payment_method == 1 && !value) {
      return false;
    }
    return true
  }),
})
const { handleSubmit, errors, resetForm } = useForm({ validationSchema })
const errorMessages = ref({})
const { value: tap_payment_method } = useField('tap_payment_method')
const { value: tap_card_payment_method } = useField('tap_card_payment_method')
const { value: tap_apple_pay_payment_method } = useField('tap_apple_pay_payment_method')
const { value: tap_mada_payment_method } = useField('tap_mada_payment_method')
const { value: tabby_payment_method } = useField('tabby_payment_method')
const { value: tamara_payment_method } = useField('tamara_payment_method')
const { value: razor_payment_method } = useField('razor_payment_method')
const { value: razorpay_secretkey } = useField('razorpay_secretkey')
const { value: razorpay_publickey } = useField('razorpay_publickey')
const { value: str_payment_method } = useField('str_payment_method')
const { value: stripe_secretkey } = useField('stripe_secretkey')
const { value: stripe_publickey } = useField('stripe_publickey')
const { value: paystack_payment_method } = useField('paystack_payment_method')
const { value: paystack_secretkey } = useField('paystack_secretkey')
const { value: paystack_publickey } = useField('paystack_publickey')
const { value: paypal_payment_method } = useField('paypal_payment_method')
const { value: paypal_secretkey } = useField('paypal_secretkey')
const { value: paypal_clientid } = useField('paypal_clientid')
const { value: flutterwave_payment_method } = useField('flutterwave_payment_method')
const { value: flutterwave_secretkey } = useField('flutterwave_secretkey')
const { value: flutterwave_publickey } = useField('flutterwave_publickey')
const { value: cinet_payment_method } = useField('cinet_payment_method')
const { value: cinet_clientid } = useField('cinet_clientid')
const { value: cinet_apikey } = useField('cinet_apikey')
const { value: cinet_secretkey } = useField('cinet_secretkey')
const { value: sadad_payment_method } = useField('sadad_payment_method')
const { value: sadad_clientid } = useField('sadad_clientid')
const { value: sadad_secretkey } = useField('sadad_secretkey')
const { value: sadad_domain } = useField('sadad_domain')
const { value: airtelmoney_payment_method } = useField('airtelmoney_payment_method')
const { value: airtelmoney_is_status } = useField('airtelmoney_is_status')
const { value: airtelmoney_clientid } = useField('airtelmoney_clientid')
const { value: airtelmoney_secretkey } = useField('airtelmoney_secretkey')
const { value: phonepay_payment_method } = useField('phonepay_payment_method')
const { value: phonepay_is_status } = useField('phonepay_is_status')
const { value: phonepay_appid } = useField('phonepay_appid')
const { value: phonepay_merchentid } = useField('phonepay_merchentid')
const { value: phonepay_saltid } = useField('phonepay_saltid')
const { value: phonepay_saltkey } = useField('phonepay_saltkey')
const { value: midtrans_payment_method } = useField('midtrans_payment_method')
const { value: midtrans_is_status } = useField('midtrans_is_status')
const { value: midtrans_clientid } = useField('midtrans_clientid')

watch(() => razor_payment_method.value, (value) => {
  if (value == '0') {
    razorpay_secretkey.value = ''
    razorpay_publickey.value = ''
  }
}, { deep: true })
watch(() => str_payment_method.value, (value) => {
  if (value == '0') {
    stripe_secretkey.value = ''
    stripe_publickey.value = ''
  }
}, { deep: true })
watch(() => paystack_payment_method.value, (value) => {
  if (value == '0') {
    paystack_secretkey.value = ''
    paystack_publickey.value = ''
  }
}, { deep: true })
watch(() => paypal_payment_method.value, (value) => {
  if (value == '0') {
    paypal_secretkey.value = ''
    paypal_clientid.value = ''
  }
}, { deep: true })
watch(() => flutterwave_payment_method.value, (value) => {
  if (value == '0') {
    flutterwave_secretkey.value = ''
    flutterwave_publickey.value = ''
  }
}, { deep: true })

watch(() => cinet_payment_method.value, (value) => {
  if (value == '0') {
    cinet_clientid.value = ''
    cinet_apikey.value = ''
    cinet_secretkey.value = ''
  }
}, { deep: true })

watch(() => sadad_payment_method.value, (value) => {
  if (value == '0') {
    sadad_clientid.value = ''
    sadad_secretkey.value = ''
    sadad_domain.value = ''
  }
}, { deep: true })

watch(() => airtelmoney_payment_method.value, (value) => {
  if (value == '0') {
    airtelmoney_clientid.value = ''
    airtelmoney_secretkey.value = ''
  }
}, { deep: true })

watch(() => phonepay_payment_method.value, (value) => {
  if (value == '0') {
    phonepay_appid.value = ''
    phonepay_merchentid.value = ''
    phonepay_saltid.value = ''
    phonepay_saltkey.value = ''
  }
}, { deep: true })

watch(() => midtrans_payment_method.value, (value) => {
  if (value == '0') {
    midtrans_clientid.value = ''
  }
}, { deep: true })
// message
const display_submit_message = (res) => {
  IS_SUBMITED.value = false
  if (res.status) {
    window.successSnackbar(res.message)
  } else {
    window.errorSnackbar(res.message)
    errorMessages.value = res.errors
  }
}

//fetch data
const data = 'tap_payment_method,tap_card_payment_method,tap_apple_pay_payment_method,tap_mada_payment_method,tabby_payment_method,tamara_payment_method,razor_payment_method,razorpay_secretkey,razorpay_publickey,str_payment_method,stripe_secretkey,stripe_publickey,paystack_payment_method,paystack_secretkey,paystack_publickey,paypal_payment_method,paypal_secretkey,paypal_clientid,flutterwave_payment_method,flutterwave_secretkey,flutterwave_publickey,cinet_payment_method,cinet_clientid,cinet_apikey,cinet_secretkey,sadad_payment_method,sadad_clientid,sadad_secretkey,sadad_domain,airtelmoney_payment_method,airtelmoney_is_status,airtelmoney_clientid,airtelmoney_secretkey,phonepay_payment_method,phonepay_is_status,phonepay_appid,phonepay_merchentid,phonepay_saltid,phonepay_saltkey,midtrans_payment_method,midtrans_is_status,midtrans_clientid'
onMounted(() => {
  createRequest(GET_URL(data)).then((response) => {
    setFormData(response)
  })
})

//Form Submit
const formSubmit = handleSubmit((values) => {
  console.log(values)
  IS_SUBMITED.value = true
  const newValues = {}
  Object.keys(values).forEach((key) => {
    if (values[key] !== '') {
      newValues[key] = values[key] || 0
    }
  })
  storeRequest({
    url: STORE_URL,
    body: newValues
  }).then((res) => display_submit_message(res))
})

defineProps({
  label: { type: String, default: '' },
  modelValue: { type: String, default: '' },
  placeholder: { type: String, default: '' },
  errorMessage: { type: String, default: '' },
  errorMessages: { type: Array, default: () => [] }
})
</script>
