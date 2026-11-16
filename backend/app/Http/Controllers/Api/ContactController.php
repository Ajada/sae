<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreContactRequest;
use App\Http\Requests\UpdateContactRequest;
use App\Models\Contact;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function index(Request $request)
    {
        $companyId = app('company.id');

        $contacts = Contact::query()
            ->where('company_id', $companyId)
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');

                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('company_name', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('is_active'), function ($query) use ($request) {
                $query->where('is_active', $request->boolean('is_active'));
            })
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return response()->json($contacts);
    }

    public function store(StoreContactRequest $request, ActivityLogger $logger)
    {
        $companyId = app('company.id');

        $contact = Contact::create([
            ...$request->validated(),
            'company_id' => $companyId,
            'is_active' => $request->boolean('is_active', true),
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        $logger->log(
            action: 'contact.created',
            subject: $contact,
            newValues: $contact->toArray()
        );

        return response()->json([
            'message' => 'Contato cadastrado com sucesso.',
            'contact' => $contact,
        ], 201);
    }

    public function show(Contact $contact)
    {
        $this->ensureCompanyContact($contact);

        return response()->json([
            'contact' => $contact->load('lists:id,uuid,name'),
        ]);
    }

    public function update(
        UpdateContactRequest $request,
        Contact $contact,
        ActivityLogger $logger
    ) {
        $this->ensureCompanyContact($contact);

        $oldValues = $contact->toArray();

        $contact->update([
            ...$request->validated(),
            'updated_by' => auth()->id(),
        ]);

        $logger->log(
            action: 'contact.updated',
            subject: $contact,
            oldValues: $oldValues,
            newValues: $contact->fresh()->toArray()
        );

        return response()->json([
            'message' => 'Contato atualizado com sucesso.',
            'contact' => $contact->fresh(),
        ]);
    }

    public function destroy(Contact $contact, ActivityLogger $logger)
    {
        $this->ensureCompanyContact($contact);

        $oldValues = $contact->toArray();

        $contact->delete();

        $logger->log(
            action: 'contact.deleted',
            subject: $contact,
            oldValues: $oldValues
        );

        return response()->json([
            'message' => 'Contato removido com sucesso.',
        ]);
    }

    private function ensureCompanyContact(Contact $contact): void
    {
        abort_if(
            $contact->company_id !== app('company.id'),
            404,
            'Contato não encontrado.'
        );
    }
}
