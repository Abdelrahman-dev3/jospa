(function () {
  const discountFields = [
    {
      gateway: 'tap',
      toggleId: 'payment_method_tap',
      anchorId: 'payment_method_tap_mada',
      typeName: 'tap_payment_discount_type',
      amountName: 'tap_payment_discount_amount',
      title: 'Tap'
    },
    {
      gateway: 'urpay',
      toggleId: 'payment_method_urpay',
      anchorId: 'payment_method_urpay',
      typeName: 'urpay_payment_discount_type',
      amountName: 'urpay_payment_discount_amount',
      title: 'UrPay'
    },
    {
      gateway: 'tabby',
      toggleId: 'payment_method_tabby',
      anchorId: 'payment_method_tabby',
      typeName: 'tabby_payment_discount_type',
      amountName: 'tabby_payment_discount_amount',
      title: 'Tabby'
    },
    {
      gateway: 'tamara',
      toggleId: 'payment_method_tamara',
      anchorId: 'payment_method_tamara',
      typeName: 'tamara_payment_discount_type',
      amountName: 'tamara_payment_discount_amount',
      title: 'Tamara'
    }
  ];

  const fieldsQuery = discountFields
    .flatMap((field) => [field.typeName, field.amountName])
    .join(',');

  function isPaymentMethodPage() {
    return window.location.hash === '#/payment-method' || window.location.hash.startsWith('#/payment-method?');
  }

  function createHeaders() {
    const csrfToken = document.head.querySelector('[name~=csrf-token][content]')?.content || '';

    return {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      'X-CSRF-Token': csrfToken
    };
  }

  async function request(path, method = 'GET', body = null) {
    const response = await fetch(path, {
      method,
      headers: createHeaders(),
      body: body ? JSON.stringify(body) : null
    });

    return response.json();
  }

  function createDiscountRow(field) {
    const wrapper = document.createElement('div');
    wrapper.className = 'row ms-1 mb-4 gateway-discount-row';
    wrapper.dataset.gatewayDiscount = field.gateway;
    wrapper.innerHTML = `
      <div class="col-md-6">
        <label class="form-label" for="${field.typeName}">نوع خصم ${field.title}</label>
        <select class="form-control" id="${field.typeName}" name="${field.typeName}">
          <option value="fixed">مبلغ ثابت</option>
          <option value="percent">نسبة مئوية</option>
        </select>
      </div>
      <div class="col-md-6">
        <label class="form-label" for="${field.amountName}">قيمة خصم ${field.title}</label>
        <input class="form-control" id="${field.amountName}" name="${field.amountName}" type="number" min="0" step="0.01" value="0" />
      </div>
    `;

    return wrapper;
  }

  function updateRowVisibility(field, form) {
    const row = form.querySelector(`[data-gateway-discount="${field.gateway}"]`);
    const toggle = form.querySelector(`#${field.toggleId}`);

    if (!row || !toggle) {
      return;
    }

    row.style.display = toggle.checked ? '' : 'none';
  }

  function attachGatewayFields(form) {
    discountFields.forEach((field) => {
      if (form.querySelector(`[name="${field.typeName}"]`)) {
        return;
      }

      const anchorInput = form.querySelector(`#${field.anchorId}`);
      const anchorGroup = anchorInput ? anchorInput.closest('.form-group') : null;

      if (!anchorGroup) {
        return;
      }

      const row = createDiscountRow(field);
      anchorGroup.insertAdjacentElement('afterend', row);

      const toggle = form.querySelector(`#${field.toggleId}`);

      if (toggle) {
        toggle.addEventListener('change', () => updateRowVisibility(field, form));
      }

      updateRowVisibility(field, form);
    });
  }

  async function populateValues(form) {
    try {
      const response = await request(`settings-data?fields=${fieldsQuery}`, 'GET');

      discountFields.forEach((field) => {
        const typeInput = form.querySelector(`[name="${field.typeName}"]`);
        const amountInput = form.querySelector(`[name="${field.amountName}"]`);

        if (typeInput) {
          typeInput.value = response[field.typeName] || 'fixed';
        }

        if (amountInput) {
          amountInput.value = response[field.amountName] ?? 0;
        }
      });
    } catch (error) {
      console.error('Failed to load gateway discount settings', error);
    }
  }

  async function storeGatewayDiscounts(form) {
    const payload = {};

    discountFields.forEach((field) => {
      const typeInput = form.querySelector(`[name="${field.typeName}"]`);
      const amountInput = form.querySelector(`[name="${field.amountName}"]`);
      const toggle = form.querySelector(`#${field.toggleId}`);
      const isEnabled = Boolean(toggle && toggle.checked);

      payload[field.typeName] = isEnabled ? (typeInput?.value || 'fixed') : 'fixed';
      payload[field.amountName] = isEnabled ? (amountInput?.value || 0) : 0;
    });

    const response = await request('settings', 'POST', payload);

    if (response?.status === false) {
      window.errorSnackbar?.(response.message || 'تعذر حفظ خصومات بوابات الدفع');
    }
  }

  function enhanceForm(form) {
    if (!form || form.dataset.gatewayDiscountEnhanced === '1') {
      return;
    }

    if (form.querySelector('[name="tap_payment_discount_type"]')) {
      form.dataset.gatewayDiscountEnhanced = 'native';
      return;
    }

    form.dataset.gatewayDiscountEnhanced = '1';

    attachGatewayFields(form);
    populateValues(form);

    form.addEventListener('submit', () => {
      window.setTimeout(() => {
        storeGatewayDiscounts(form);
      }, 0);
    });
  }

  function boot() {
    if (!isPaymentMethodPage()) {
      return;
    }

    const form = document.querySelector('#setting-app form');

    if (form) {
      enhanceForm(form);
    }
  }

  const observer = new MutationObserver(() => boot());
  observer.observe(document.body, { childList: true, subtree: true });

  window.addEventListener('hashchange', boot);
  document.addEventListener('DOMContentLoaded', boot);
  window.setTimeout(boot, 300);
})();
