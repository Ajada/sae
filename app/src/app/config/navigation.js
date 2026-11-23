import {
  PhBellRinging,
  PhBuildingOffice,
  PhCalendarCheck,
  PhChartBar,
  PhEnvelopeSimple,
  PhGearSix,
  PhHouse,
  PhKey,
  PhUsers,
} from '@phosphor-icons/vue'

export const navigation = [
  {
    label: 'Principal',
    items: [
      {
        label: 'Dashboard',
        name: 'dashboard',
        icon: PhHouse,
      },
    ],
  },

  {
    label: 'Gestão',
    items: [
      {
        label: 'EMPRESAS',
        name: 'managed-companies',
        icon: PhBuildingOffice,
      },
      {
        label: 'Mensagens',
        name: 'messages.index',
        icon: PhEnvelopeSimple,
        disabled: true,
      },
      {
        label: 'Vencimentos',
        name: 'reminders.index',
        icon: PhCalendarCheck,
        disabled: true,
      },
      {
        label: 'Recorrências',
        name: 'recurring.index',
        icon: PhBellRinging,
        disabled: true,
      },
    ],
  },

  {
    label: 'Administração',
    items: [
      {
        label: 'Provedores',
        name: 'providers.index',
        icon: PhGearSix,
        disabled: true,
      },
      {
        label: 'API Clients',
        name: 'api-clients.index',
        icon: PhKey,
        disabled: true,
      },
      {
        label: 'Relatórios',
        name: 'reports.index',
        icon: PhChartBar,
        disabled: true,
      },
    ],
  },
]