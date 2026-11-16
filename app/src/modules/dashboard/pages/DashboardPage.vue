<script setup>
import {
  PhCalendarCheck,
  PhEnvelopeSimple,
  PhPaperPlaneTilt,
  PhWarningCircle,
} from '@phosphor-icons/vue'

import ContentCard from '@/shared/components/cards/ContentCard.vue'
import StatCard from '@/shared/components/cards/StatCard.vue'
import PageHeader from '@/shared/components/layout/PageHeader.vue'

const stats = [
  {
    title: 'Mensagens enviadas',
    value: '0',
    description: 'Envios realizados pelo motor central.',
    icon: PhEnvelopeSimple,
    accent: 'primary',
  },
  {
    title: 'Agendadas',
    value: '0',
    description: 'Mensagens aguardando processamento.',
    icon: PhPaperPlaneTilt,
    accent: 'success',
  },
  {
    title: 'Vencimentos próximos',
    value: '0',
    description: 'Itens que exigem atenção.',
    icon: PhCalendarCheck,
    accent: 'warning',
  },
  {
    title: 'Falhas',
    value: '0',
    description: 'Envios ou processos com erro.',
    icon: PhWarningCircle,
    accent: 'danger',
  },
]

const steps = [
  'Base autenticada com cookie HttpOnly.',
  'Layout interno preparado para módulos.',
  'Tema light/dark funcionando com tokens globais.',
  'Estrutura pronta para integrar os services do X Flow.',
]
</script>

<template>
  <section>
    <PageHeader
      eyebrow="Dashboard"
      title="Visão geral"
      description="Esta é a base interna da plataforma. Os próximos módulos serão conectados ao backend do X Flow usando a estrutura de services e stores."
    />

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <StatCard
        v-for="stat in stats"
        :key="stat.title"
        v-bind="stat"
      />
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-[1.2fr_0.8fr]">
      <ContentCard
        title="Atividade recente"
        description="Os eventos reais serão carregados via API nas próximas etapas."
      >
        <div class="space-y-4">
          <div
            v-for="index in 4"
            :key="index"
            class="flex items-start gap-4 rounded-2xl border border-app-border bg-app-bg p-4"
          >
            <div class="mt-1 h-2.5 w-2.5 rounded-full bg-app-primary" />

            <div>
              <p class="text-sm font-bold text-app-text">
                Evento de exemplo {{ index }}
              </p>

              <p class="mt-1 text-sm text-app-muted">
                Placeholder para logs, mensagens, vencimentos e automações.
              </p>
            </div>
          </div>
        </div>
      </ContentCard>

      <ContentCard
        title="Status da base"
        description="Checklist funcional da estrutura."
      >
        <ul class="space-y-3">
          <li
            v-for="step in steps"
            :key="step"
            class="flex items-start gap-3 text-sm text-app-muted"
          >
            <span class="mt-1 inline-flex h-2.5 w-2.5 shrink-0 rounded-full bg-app-success" />
            <span>{{ step }}</span>
          </li>
        </ul>

        <div class="mt-6 rounded-2xl border border-app-primary/20 bg-app-primary/10 p-4">
          <p class="text-sm font-bold text-app-primary">
            Próximo marco
          </p>

          <p class="mt-1 text-sm leading-6 text-app-muted">
            Criar os services base dos módulos e começar a integrar Contatos, Listas e Provedores.
          </p>
        </div>
      </ContentCard>
    </div>
  </section>
</template>