export default [
  {
    path: 'contacts',
    name: 'contacts.index',
    component: () => import('./pages/ContactsPage.vue'),
  },
  {
    path: 'contact-lists',
    name: 'contact-lists.index',
    component: () => import('./pages/ContactListsPage.vue'),
  },
]