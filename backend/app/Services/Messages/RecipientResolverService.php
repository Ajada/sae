<?php 

namespace App\Services\Messages;

use App\Models\Contact;
use App\Models\ContactList;
use Illuminate\Support\Collection;

class RecipientResolverService
{
    public function resolve(array $data, int $companyId, string $channel = 'email'): Collection
    {
        $recipients = collect();

        if (! empty($data['contact_ids'])) {
            $contacts = Contact::query()
                ->where('company_id', $companyId)
                ->whereIn('id', $data['contact_ids'])
                ->where('is_active', true)
                ->get();

            $recipients = $recipients->merge(
                $contacts->map(fn (Contact $contact) => $this->mapContact($contact))
            );
        }

        if (! empty($data['contact_list_ids'])) {
            $lists = ContactList::query()
                ->where('company_id', $companyId)
                ->whereIn('id', $data['contact_list_ids'])
                ->where('is_active', true)
                ->with('contacts')
                ->get();

            foreach ($lists as $list) {
                $recipients = $recipients->merge(
                    $list->contacts
                        ->where('is_active', true)
                        ->map(fn (Contact $contact) => $this->mapContact($contact))
                );
            }
        }

        if (! empty($data['recipients'])) {
            $recipients = $recipients->merge(
                collect($data['recipients'])->map(fn (array $recipient) => [
                    'contact_id' => null,
                    'name' => $recipient['name'] ?? null,
                    'email' => $recipient['email'] ?? null,
                    'phone' => $recipient['phone'] ?? null,
                ])
            );
        }

        if ($channel === 'email') {
            return $recipients
                ->filter(fn ($recipient) => ! empty($recipient['email']))
                ->unique(fn ($recipient) => mb_strtolower($recipient['email']))
                ->values();
        }

        return $recipients->values();
    }

    public function getEmailWarnings(array $data, int $companyId): array
    {
        $withoutEmail = collect();

        if (! empty($data['contact_ids'])) {
            $withoutEmail = $withoutEmail->merge(
                Contact::query()
                    ->where('company_id', $companyId)
                    ->whereIn('id', $data['contact_ids'])
                    ->whereNull('email')
                    ->get(['id', 'uuid', 'name', 'phone'])
            );
        }

        if (! empty($data['contact_list_ids'])) {
            $lists = ContactList::query()
                ->where('company_id', $companyId)
                ->whereIn('id', $data['contact_list_ids'])
                ->with(['contacts' => function ($query) {
                    $query->whereNull('email')
                        ->select('contacts.id', 'contacts.uuid', 'contacts.name', 'contacts.phone');
                }])
                ->get();

            foreach ($lists as $list) {
                $withoutEmail = $withoutEmail->merge($list->contacts);
            }
        }

        return [
            'contacts_without_email_count' => $withoutEmail->unique('id')->count(),
            'contacts_without_email' => $withoutEmail->unique('id')->values(),
        ];
    }

    private function mapContact(Contact $contact): array
    {
        return [
            'contact_id' => $contact->id,
            'name' => $contact->name,
            'email' => $contact->email,
            'phone' => $contact->phone,
        ];
    }
}