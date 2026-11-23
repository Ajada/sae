<script setup>
import { reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import BaseAlert from '@/shared/components/feedback/BaseAlert.vue'
import BaseButton from '@/shared/components/buttons/BaseButton.vue'
import BaseInput from '@/shared/components/forms/BaseInput.vue'
import BasePasswordInput from '@/shared/components/forms/BasePasswordInput.vue'

import { useAuthStore } from '../stores/auth.store'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()

const form = reactive({
  email: '',
  password: '',
})

const errors = reactive({
  email: '',
  password: '',
})

const generalError = ref('')

function clearErrors() {
  errors.email = ''
  errors.password = ''
  generalError.value = ''
}

function validateForm() {
  clearErrors()

  if (!form.email) {
    errors.email = 'Informe seu e-mail.'
  }

  if (!form.password) {
    errors.password = 'Informe sua senha.'
  }

  return !errors.email && !errors.password
}

async function handleSubmit() {
  if (!validateForm()) {
    return
  }

  try {
    await authStore.login({
      email: form.email,
      password: form.password,
    })

    const redirect = route.query.redirect || '/app/dashboard'

    router.push(String(redirect))
  } catch (error) {
    const responseErrors = error?.response?.data?.errors

    if (responseErrors?.email?.[0]) {
      errors.email = responseErrors.email[0]
      return
    }

    generalError.value = error?.response?.data?.message || 'Não foi possível realizar o login. Verifique seus dados e tente novamente.'
  }
}
</script>

<template>
  <form
    class="space-y-5"
    @submit.prevent="handleSubmit"
  >
    <BaseAlert
      v-if="generalError"
      type="danger"
    >
      {{ generalError }}
    </BaseAlert>

    <BaseInput
      v-model="form.email"
      label="E-mail"
      type="email"
      placeholder="seuemail@empresa.com"
      autocomplete="email"
      :error="errors.email"
      :disabled="authStore.isLoading"
    />

    <BasePasswordInput
      v-model="form.password"
      label="Senha"
      placeholder="Sua senha"
      autocomplete="current-password"
      :error="errors.password"
      :disabled="authStore.isLoading"
    />

    <div class="flex items-center justify-between">
      <label class="flex items-center gap-2 text-sm text-app-muted">
        <input
          type="checkbox"
          class="h-4 w-4 rounded border-app-border text-app-primary focus:ring-app-primary"
        >
        Manter conectado
      </label>

      <button
        type="button"
        class="text-sm font-semibold text-app-primary hover:underline"
      >
        Esqueci minha senha
      </button>
    </div>

    <BaseButton
      type="submit"
      full
      size="lg"
      :loading="authStore.isLoading"
    >
      Entrar na plataforma
    </BaseButton>
  </form>
</template>