<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreContactListRequest;
use App\Http\Requests\UpdateContactListRequest;
use App\Models\ContactList;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;

class ContactListController extends Controller
{
    public function index(Request $request)
    {
        $companyId = app('company.id');

        $lists = ContactList::query()
            ->where('company_id', $companyId)
            ->withCount('contacts')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');

                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('is_active'), function ($query) use ($request) {
                $query->where('is_active', $request->boolean('is_active'));
            })
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return response()->json($lists);
    }

    public function store(StoreContactListRequest $request, ActivityLogger $logger)
    {
        $companyId = app('company.id');
        $validated = $request->validated();

        $list = ContactList::create([
            'company_id' => $companyId,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        if (! empty($validated['contact_ids'])) {
            $list->contacts()->sync($validated['contact_ids']);
        }

        $logger->log(
            action: 'contact_list.created',
            subject: $list,
            newValues: [
                ...$list->toArray(),
                'contact_ids' => $validated['contact_ids'] ?? [],
            ]
        );

        return response()->json([
            'message' => 'Lista cadastrada com sucesso.',
            'list' => $list->load('contacts:id,uuid,name,email,phone'),
        ], 201);
    }

    public function show(ContactList $contactList)
    {
        $this->ensureCompanyList($contactList);

        $contactList->load([
            'contacts:id,uuid,company_id,name,email,phone,company_name,role,is_active',
        ]);

        $contactsWithoutEmail = $contactList->contacts
            ->filter(fn ($contact) => empty($contact->email))
            ->values()
            ->map(fn ($contact) => [
                'id' => $contact->id,
                'uuid' => $contact->uuid,
                'name' => $contact->name,
                'phone' => $contact->phone,
            ]);

        return response()->json([
            'list' => $contactList,
            'warnings' => [
                'contacts_without_email_count' => $contactsWithoutEmail->count(),
                'contacts_without_email' => $contactsWithoutEmail,
            ],
        ]);
    }

    public function update(
        UpdateContactListRequest $request,
        ContactList $contactList,
        ActivityLogger $logger
    ) {
        $this->ensureCompanyList($contactList);

        $oldValues = [
            ...$contactList->toArray(),
            'contact_ids' => $contactList->contacts()->pluck('contacts.id')->toArray(),
        ];

        $validated = $request->validated();

        $contactList->update([
            'name' => $validated['name'] ?? $contactList->name,
            'description' => array_key_exists('description', $validated)
                ? $validated['description']
                : $contactList->description,
            'is_active' => array_key_exists('is_active', $validated)
                ? $validated['is_active']
                : $contactList->is_active,
            'updated_by' => auth()->id(),
        ]);

        if (array_key_exists('contact_ids', $validated)) {
            $contactList->contacts()->sync($validated['contact_ids'] ?? []);
        }

        $logger->log(
            action: 'contact_list.updated',
            subject: $contactList,
            oldValues: $oldValues,
            newValues: [
                ...$contactList->fresh()->toArray(),
                'contact_ids' => $contactList->contacts()->pluck('contacts.id')->toArray(),
            ]
        );

        return response()->json([
            'message' => 'Lista atualizada com sucesso.',
            'list' => $contactList->fresh()->load('contacts:id,uuid,name,email,phone'),
        ]);
    }

    public function destroy(ContactList $contactList, ActivityLogger $logger)
    {
        $this->ensureCompanyList($contactList);

        $oldValues = [
            ...$contactList->toArray(),
            'contact_ids' => $contactList->contacts()->pluck('contacts.id')->toArray(),
        ];

        $contactList->delete();

        $logger->log(
            action: 'contact_list.deleted',
            subject: $contactList,
            oldValues: $oldValues
        );

        return response()->json([
            'message' => 'Lista removida com sucesso.',
        ]);
    }

    private function ensureCompanyList(ContactList $list): void
    {
        abort_if(
            $list->company_id !== app('company.id'),
            404,
            'Lista não encontrada.'
        );
    }
}
