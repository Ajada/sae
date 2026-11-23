<script setup>
import { PhBellRinging, PhEnvelopeSimple, PhLightning, PhShieldCheck } from '@phosphor-icons/vue'

import BaseButton from '@/shared/components/buttons/BaseButton.vue'

const features = [
  {
    icon: PhEnvelopeSimple,
    title: 'Disparos centralizados',
    description: 'Organize envios de e-mail, avisos e integrações em uma única base.',
  },
  {
    icon: PhBellRinging,
    title: 'Avisos automáticos',
    description: 'Controle vencimentos, notificações e lembretes sem depender de controles manuais.',
  },
  {
    icon: PhLightning,
    title: 'Agendamentos e recorrências',
    description: 'Prepare envios futuros e comunicações recorrentes para listas e contatos.',
  },
  {
    icon: PhShieldCheck,
    title: 'Mais segurança',
    description: 'Fluxo com sessão segura, logs e base preparada para escalar.',
  },
]
</script>

<template>
  <section class="relative overflow-hidden">
    <div class="absolute inset-0 -z-10 bg-[radial-gradient(circle_at_top_left,rgb(var(--color-primary)/0.18),transparent_35%),radial-gradient(circle_at_bottom_right,rgb(var(--color-primary)/0.10),transparent_30%)]" />

    <div class="mx-auto grid min-h-[calc(100vh-4rem)] max-w-7xl items-center gap-12 px-4 py-16 sm:px-6 lg:grid-cols-2 lg:px-8">
      <div>
        <span class="inline-flex rounded-full border border-app-primary/20 bg-app-primary/10 px-4 py-1.5 text-sm font-semibold text-app-primary">
          Central de notificações, disparos e automações
        </span>

        <h1 class="mt-6 max-w-3xl text-4xl font-black tracking-tight text-app-text sm:text-5xl lg:text-6xl">
          Automatize comunicações sem perder controle.
        </h1>

        <p class="mt-6 max-w-2xl text-lg leading-8 text-app-muted">
          Uma base moderna para enviar e-mails, programar notificações, controlar vencimentos,
          disparar mensagens recorrentes e integrar os sistemas da empresa em um único fluxo.
        </p>

        <div class="mt-8 flex flex-col gap-3 sm:flex-row">
          <RouterLink :to="{ name: 'login' }">
            <BaseButton size="lg">
              Acessar plataforma
            </BaseButton>
          </RouterLink>

          <BaseButton
            variant="secondary"
            size="lg"
          >
            Conhecer recursos
          </BaseButton>
        </div>
      </div>

      <div class="relative">
        <div class="rounded-[2rem] border border-app-border bg-app-surface/80 p-4 shadow-2xl shadow-black/10 backdrop-blur-xl dark:shadow-black/30">
          <div class="rounded-[1.5rem] border border-app-border bg-app-surface-strong p-6">
            <div class="flex items-center justify-between border-b border-app-border pb-5">
              <div>
                <p class="text-sm text-app-muted">
                  Visão geral
                </p>
                <h2 class="text-xl font-bold text-app-text">
                  Operações automatizadas
                </h2>
              </div>

              <div class="rounded-full bg-app-success/10 px-3 py-1 text-xs font-bold text-app-success">
                Online
              </div>
            </div>

            <div class="mt-6 grid gap-4">
              <div class="rounded-2xl border border-app-border bg-app-bg p-4">
                <p class="text-sm text-app-muted">
                  Próximo disparo
                </p>
                <p class="mt-1 text-lg font-bold text-app-text">
                  Cobrança mensal — dia 10 às 08:00
                </p>
              </div>

              <div class="grid grid-cols-2 gap-4">
                <div class="rounded-2xl border border-app-border bg-app-bg p-4">
                  <p class="text-sm text-app-muted">
                    Mensagens
                  </p>
                  <p class="mt-2 text-3xl font-black text-app-text">
                    1.284
                  </p>
                </div>

                <div class="rounded-2xl border border-app-border bg-app-bg p-4">
                  <p class="text-sm text-app-muted">
                    Vencimentos
                  </p>
                  <p class="mt-2 text-3xl font-black text-app-primary">
                    37
                  </p>
                </div>
              </div>

              <div class="rounded-2xl border border-app-primary/20 bg-app-primary/10 p-4">
                <p class="text-sm font-semibold text-app-primary">
                  Novo aviso automático criado
                </p>
                <p class="mt-1 text-sm text-app-muted">
                  PGR Cliente X vence em 30 dias.
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="mx-auto grid max-w-7xl gap-4 px-4 pb-16 sm:px-6 md:grid-cols-2 lg:grid-cols-4 lg:px-8">
      <article
        v-for="feature in features"
        :key="feature.title"
        class="rounded-2xl border border-app-border bg-app-surface p-5"
      >
        <component
          :is="feature.icon"
          class="h-7 w-7 text-app-primary"
        />

        <h3 class="mt-4 font-bold text-app-text">
          {{ feature.title }}
        </h3>

        <p class="mt-2 text-sm leading-6 text-app-muted">
          {{ feature.description }}
        </p>
      </article>
    </div>
  </section>
</template>